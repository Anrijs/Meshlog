<?php

require_once 'utils.php';
require_once 'meshlog.entity.class.php';
require_once 'meshlog.advertisement.class.php';
require_once 'meshlog.contact.class.php';
require_once 'meshlog.direct_message.class.php';
require_once 'meshlog.channel_message.class.php';
require_once 'meshlog.channel.class.php';
require_once 'meshlog.reporter.class.php';
require_once 'meshlog.setting.class.php';
require_once 'meshlog.telemetry.class.php';
require_once 'meshlog.user.class.php';
require_once 'meshlog.report.class.php';
require_once 'meshlog.raw_packet.class.php';

define("MAX_COUNT", 2500);
define("DEFAULT_COUNT", 500);

class MeshLog {
    private $error = '';
    private $version = 6;
    private $settings = array(
        MeshlogSetting::KEY_DB_VERSION => 0,
        MeshlogSetting::KEY_MAX_CONTACT_AGE => 1814400
    );

    function __construct($config) {
        $host = $config['host'] ?? die("Invalid db config");
        $name = $config['database'] ?? die("Invalid db config");
        $user = $config['user'] ?? die("Invalid db config");
        $pass = $config['password'] ?? die("Invalid db config");
        $this->pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->loadSettings();

        $this->error = $this->checkUpdates();
    }

    function __destruct() {
        $this->pdo = null;
    }

    function getError() {
        return $this->error;
    }

    function loadSettings() {
        $table = MeshLogSetting::getTable();
        $stmt = $this->pdo->prepare("SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = :table");
            $stmt->execute(['table' => $table]);

        if ($stmt->fetchColumn() > 0) {
            $settings = MeshLogSetting::getAll($this, array());
            foreach ($settings['objects'] as $s) {
                $k = $s['name'];
                $v = $s['value'];
                if ($k) {
                    $this->settings[$k] = $v;
                }
            }

            $users = MeshLogUser::countAll($this);
            if ($users > 0) return;
        }
        $this->error = 'Setup not complete. Go to <a href="setup.php">setup</a>';
    }

    function getDbVersion() {
        return $this->getConfig(MeshlogSetting::KEY_DB_VERSION, 0);
    }

    function updateAvailable() {
        return $this->version != $this->getDbVersion();
    }

    function checkUpdates() {
        if ($this->version != $this->getConfig(MeshlogSetting::KEY_DB_VERSION, 0)) {
            return "Database upgrade required! <a href=\"setup.php\">Login</a>";
        };
        return 0;
    }

    function saveSettings() {
        MeshLogSetting::saveSettings($this, $this->settings);
    }

    function getConfig($key, $default=null) {
        if (!isset($this->settings[$key])) return $default;
        return $this->settings[$key];
    }

    function setConfig($key, $value) {
        // TODO write DB
    }

    function authorize($data) {
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) return false;
        if (!isset($data['reporter'])) return false;

        $count = 1;
        $pubkey = $data['reporter'];
        $token = $_SERVER['HTTP_AUTHORIZATION'];
        $token = str_replace("Bearer ", "", $token, $count);

        $query = $this->pdo->prepare('SELECT * FROM reporters WHERE public_key = :pubkey AND auth = :auth AND authorized = 1');
        $query->bindParam(':pubkey',$pubkey, PDO::PARAM_STR);
        $query->bindParam(':auth',  $token,  PDO::PARAM_STR);
        $query->execute();

        $result = $query->fetch(PDO::FETCH_ASSOC);

        if (!$result) return false;

        return MeshLogReporter::fromDb($result, $this);
    }

    function insert($data) {
        $reporter = $this->authorize($data);
        if (!$reporter) return false;

        if (!isset($data['type'])) return $this->repError('invalid type');

        $type = $data['type'];

        switch ($type) {
            case 'ADV':
                return $this->insertAdvertisement($data, $reporter);
                break;
            case 'MSG':
                return $this->insertDirectMessage($data, $reporter);
                break;
            case 'PUB':
                return $this->insertGroupMessage($data, $reporter);
                break;
            case 'SYS':
                return $this->insertSelfReport($data, $reporter);
                break;
            case 'TEL':
                return $this->insertTelemetry($data, $reporter);
                break;
            case 'RAW':
                return $this->insertRawPacket($data, $reporter);
                break;
        }

        error_log("Unknowwn type: $type");
    }

    private function insertAdvertisement($data, $reporter) {
        if (!$reporter) return $this->repError('no reporter');

        $pubkey = $data['contact']['pubkey'] ?? null;
        if (!$pubkey) return $this->repError('no key');

        $encname = $data['contact']['name'];
        $data['contact']['name'] = $encname;

        $contact = MeshLogContact::findBy("public_key", $pubkey, $this);

        if ($contact) {
            $contact->name = $data['contact']['name'];
        } else {
            $contact = MeshLogContact::fromJson($data, $this);
            $contact->name = $data['contact']['name'];
        }
        if (!$contact->save($this)) return $this->repError('failed to save contact');

        // Find adv by id, not older than X
        $adv = MeshLogAdvertisement::fromJson($data, $this);
        $adv->contact_ref = $contact;

        // 2 min grouping
        // Can't use sent_at. Device after reboot might send advert
        // with bad date, making hash duplicate with older messages
        $minage = date("Y-m-d H:i:s", time() - 120);
        $existing = MeshLogAdvertisement::findBy("hash", $adv->hash, $this, array('created_at' => array('operator' => '>', 'value' => $minage)));

        if ($existing) {
            $adv = $existing;
            $saved = true;
        } else {
            $saved = $adv->save($this);
            $contact->updateHeardAt($this);
        }

        if ($saved) {
            // add report
            $rep = MeshLogAdvertisementReport::fromJson($data, $this);
            $rep->object_id = $adv->getId();
            $rep->reporter_id = $reporter->getId();
            return $rep->save($this);
        }
        return $saved;
    }

    private function insertDirectMessage($data, $reporter) {
        if (!$reporter) return $this->repError('no reporter');

        $pubkey = $data['contact']['pubkey'] ?? null;
        if (!$pubkey) return $this->repError('no key');

        $contact = MeshLogContact::findBy("public_key", $pubkey, $this);
        if (!$contact) {
            $contact = MeshLogContact::fromJson($data, $this);
            if (!$contact->save($this)) return $this->repError('failed to save contact');
        }

        $dm = MeshLogDirectMessage::fromJson($data, $this);
        $dm->contact_ref = $contact;

        // 2 min grouping
        // Can't use sent_at. Device after reboot might send advert
        // with bad date, making hash duplicate with older messages
        $minage = date("Y-m-d H:i:s", time() - 120);
        $existing = MeshLogDirectMessage::findBy("hash", $dm->hash, $this, array('created_at' => array('operator' => '>', 'value' => $minage)));

        if ($existing) {
            $dm = $existing;
            $saved = true;
        } else {
            $saved = $dm->save($this);
            $contact->updateHeardAt($this);
        }

        if ($saved) {
            // add report
            $rep = MeshLogDirectMessageReport::fromJson($data, $this);
            $rep->object_id = $dm->getId();
            $rep->reporter_id = $reporter->getId();
            return $rep->save($this);
        }
        return $saved;
    }

    private function insertGroupMessage($data, $reporter) {
        if (!$reporter) return $this->repError('no reporter');

        $hash = $data['channel']['hash'] ?? '11';
        $text = $data['message']['text'] ?? null;
        
        if (!$text) return $this->repError('no message');
        $name = explode(':', $text, 2)[0];

        $channel = MeshLogChannel::findBy("hash", $hash, $this);

        if (!$channel) {
            $channel = MeshLogChannel::fromJson($data, $this);
            if (!$channel->save($this)) return $this->repError('failed to save channel');
        }

        $advertisement = MeshLogAdvertisement::findBy("name", $name, $this, array(), True);
        $contact = null;
        if ($advertisement) $contact = MeshLogContact::findById($advertisement->contact_ref->getId(), $this);

        $grpmsg = MeshLogChannelMessage::fromJson($data, $this);
        $grpmsg->contact_ref = $contact;
        $grpmsg->channel_ref = $channel;

        // 2 min grouping
        // Can't use sent_at. Device after reboot might send advert
        // with bad date, making hash duplicate with older messagesq
        $minage = date("Y-m-d H:i:s", time() - 120);
        $existing = MeshLogChannelMessage::findBy("hash", $grpmsg->hash, $this, array('created_at' => array('operator' => '>', 'value' => $minage)));

        if ($existing) {
            $grpmsg = $existing;
            $saved = true;
        } else {
            $saved = $grpmsg->save($this);
            if ($contact) $contact->updateHeardAt($this);
        }

        if ($saved) {
            // add report
            $rep = MeshLogChannelMessageReport::fromJson($data, $this);
            $rep->object_id = $grpmsg->getId();
            $rep->reporter_id = $reporter->getId();
            return $rep->save($this);
        }
        return $saved;
    }

    private function insertTelemetry($data, $reporter) {
        if (!$reporter) return $this->repError('no reporter');

        $pubkey = $data['contact']['pubkey'] ?? null;
        if (!$pubkey) return $this->repError('no key');

        $contact = MeshLogContact::findBy("public_key", $pubkey, $this);

        if (!$contact) {
            return $this->repError('contact doesnt exist');
        }

        $tel = MeshLogTelemetry::fromJson($data, $this);
        $tel->reporter_ref = $reporter;
        $tel->contact_ref = $contact;

        $cname = str_replace(
            " ",
            "\\ ",
            $contact->name
        );

        $cname = str_replace("\"", "", $cname);

        $res = $tel->save($this);
        if ($res) {
            $influxHost = "http://influx.99.anrijs.lv:8086";
            $database   = "SandboxZ";

            $errors = "";

            $data = json_decode($tel->data, true);
            foreach ($data as $chan) {
                if ($chan['type'] != "0") {
                    $ch = $chan['channel'];
                    $ty = $chan['type'];
                    $na = $chan['name'];
                    $va = $chan['value'];

                    $cdata = "mc_$na,contact=$pubkey,type=$ty,ch=$ch,name=$cname value=$va";

                    $url = "$influxHost/write?db=" . urlencode($database);

                    // Initialize cURL
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $cdata);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $response = curl_exec($ch);
                    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpcode >= 400) {
                        $errors .= "Error $httpcode: $response for request $cdata\n";
                    }
                }
            }

            if (!empty($errors)) {
                return $this->repError($errors);
            }
        } else {
            return $this->repError('failed to write db');
        }

        return $res;
    }

    private function insertRawPacket($data, $reporter) {
        if (!$reporter) return $this->repError('no reporter');

        $pkt = MeshLogRawPacket::fromJson($data, $this);
        $pkt->reporter_id = $reporter->getId();
        return $pkt->save($this);
    }

    // TODO
    private function insertSelfReport($data, $reporter) {
        if (!$reporter) return;
        $lat = $data['contact']['lat'] ?? null;
        $lon = $data['contact']['lon'] ?? null;
        $reporter->updateLocation($this, $lat, $lon);
    }

    private function repError($msg) {
        return array('error' => $msg);
    }

    // getters
    public function getReporters($params) {
        $params['where'] = array(
            'authorized = 1'
        );
        return MeshLogReporter::getAll($this, $params);
    }

    public function getContacts($params, $adv=FALSE) {
        $params['where'] = array(
            'enabled = 1'
        );

        $results = MeshLogContact::getAll($this, $params);
        $out = [];
        $maxage = isset($params['max_age']) ? $params['max_age'] : 0;

        if ($params['advertisements'] || $maxage) {
            foreach ($results['objects'] as $k => $c) {
                $id = $c['id'];

                if ($params['telemetry']) {
                    $tel = MeshLogTelemetry::findBy("contact_id", $id, $this, array('created_at' => array('operator' => '>', 'value' => $maxage)));
                    if ($tel) {
                        $c['telemetry'] = json_decode($tel->data);
                    }
                }

                $ad = MeshLogAdvertisement::findBy("contact_id", $id, $this, array('created_at' => array('operator' => '>', 'value' => $maxage)));
                if ($ad) {
                    $c['advertisement'] = $ad->asArray();
                    $out[] = $c;
                }
            }
        }

        return array("objects" => $out);
    }

    public function addReports($results, $klass) {
        foreach ($results['objects'] as $key => $val) {
            $id = $val['id'];

            $outrep = array();
            $reports = $klass::getAllReports($this, $id);
            foreach ($reports['objects'] as $rkey => $rval) {
                $outrep[] = $rval;
            }

            $results['objects'][$key]['reports'] = $outrep;
        }
        return $results;
    }

    public function getAdvertisements($params, $reports = false) {
        $params['where'] = array();
        $results = MeshLogAdvertisement::getAll($this, $params);

        if ($reports) {
            $results = $this->addReports($results, 'MeshLogAdvertisementReport');
        }

        return $results;
    }

    public function getChannels($params) {
        $params['where'] = array('enabled = 1');
        return MeshLogChannel::getAll($this, $params);
    }

    private function getQuickSql($tklass, $rklass, $extra1='') {
        $tfields = $tklass::getPublicFields();
        $ttable = $tklass::getTable();
        $rtable = $rklass::getTable();
        $rrefname = $rklass::getRefName();

        $sql = "
            SELECT
                $tfields,
                JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id', r.id,
                            'reporter_id', r.reporter_id,
                            'snr', r.snr,
                            'path', r.path,
                            'received_at', r.received_at,
                            'created_at', r.created_at
                        )
                ) AS reports
            FROM (
                SELECT t.* FROM $ttable t
                $extra1
                ORDER BY t.id DESC
                LIMIT :offset,:limit
            ) t
            LEFT JOIN $rtable r ON r.$rrefname = t.id
            GROUP BY t.id
            ORDER BY t.id DESC
        ";

        return $sql;
    }

    private function getTimeFiltersSql($params) {
        $after_ms = $params['after_ms'] ?? 0;
        $before_ms = $params['before_ms'] ?? 0;

        $binds = [];
        $sqlWhere = "";
        if ($after_ms > 0) {
            $after_ms = floor($after_ms / 1000);
            $sqlWhere = "t.created_at > FROM_UNIXTIME(:after_ms) ";
            $binds[] = array(":after_ms", $after_ms, PDO::PARAM_INT);
        }
        if ($before_ms > 0) {
            $before_ms = floor($before_ms / 1000);
            if (strlen($sqlWhere)) {
                $sqlWhere .= " AND t.created_at < FROM_UNIXTIME(:before_ms)";
            } else {
                $sqlWhere = "t.created_at < FROM_UNIXTIME(:before_ms)";
            }
            $binds[] = array(":before_ms", $before_ms, PDO::PARAM_INT);
        }

        return array($sqlWhere, $binds);
    }

    public function getReportedQuick($params, $tklass, $rklass, $extra, $binds) {
        $offset = (int) ($params['offset'] ?? 0);
        $limit = (int) ($params['count'] ?? DEFAULT_COUNT);
        $where = $this->getTimeFiltersSql($params);
        if (!empty($where[0])) {
            $extra .= " WHERE " . $where[0];
            foreach ($where[1] as $w) {
                $binds[] = $w;
            }
        }

        if ($limit > MAX_COUNT) $limit = MAX_COUNT;

        $sql = $this->getQuickSql(
            $tklass,
            $rklass,
            $extra
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        foreach ($binds as $b) {
            $stmt->bindValue($b[0], $b[1], $b[2]);
        }

        $stmt->execute();

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['reports'] = json_decode($row['reports'], true);
            $results[] = $row;
        }

        return array("objects" => $results);
    }

    public function getChannelMessagesQuick($params) {
        $channel_id = $params['channel_id'] ?? null;
        $extra = "JOIN channels c ON c.id = t.channel_id AND c.enabled = 1 ";
        $binds = array();

        if ($channel_id !== null) {
            $binds[] = array(':channel_id', (int) $channel_id, PDO::PARAM_INT);
        }

        return $this->getReportedQuick(
            $params,
            'MeshLogChannelMessage',
            'MeshLogChannelMessageReport',
            $extra,
            $binds
        );
    }

    public function getDirectMessagesQuick($params) {
        return $this->getReportedQuick(
            $params,
            'MeshLogDirectMessage',
            'MeshLogDirectMessageReport',
            "",
            array()
        );
    }

    public function getAdvertisementsQuick($params) {
        return $this->getReportedQuick(
            $params,
            'MeshLogAdvertisement',
            'MeshLogAdvertisementReport',
            "",
            array()
        );
    }

    public function getContactsQuick($params) {
        $maxage = $this->getConfig(MeshlogSetting::KEY_MAX_CONTACT_AGE);
        $offset = (int) ($params['offset'] ?? 0);
        $limit = (int) ($params['count'] ?? DEFAULT_COUNT);
        $extra = "WHERE last_heard_at >= NOW() - INTERVAL $maxage SECOND ";
        $binds = array();
        $where = $this->getTimeFiltersSql($params);
        if (!empty($where[0])) {
            $extra .= " AND " . $where[0];
            foreach ($where[1] as $w) {
                $binds[] = $w;
            }
        }

        $sql = "
            SELECT
                t.id,
                t.public_key,
                t.name,
                t.created_at,

                -- Latest advertisement
                (
                    SELECT JSON_OBJECT(
                        'id', a.id,
                        'hash', a.hash,
                        'name', a.name,
                        'lat', a.lat,
                        'lon', a.lon,
                        'type', a.type,
                        'flags', a.flags,
                        'sent_at', a.sent_at,
                        'created_at', a.created_at
                    )
                    FROM advertisements a
                    WHERE a.contact_id = t.id
                    ORDER BY a.created_at DESC
                    LIMIT 1
                ) AS advertisement,

                -- Latest telemetry
                (
                    SELECT l.data
                    FROM telemetry l
                    WHERE l.contact_id = t.id
                    ORDER BY l.created_at DESC
                    LIMIT 1
                ) AS telemetry

            FROM contacts t
            $extra
            ORDER BY t.id DESC
            LIMIT :offset,:limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        foreach ($binds as $b) {
            $stmt->bindValue($b[0], $b[1], $b[2]);
        }

        $stmt->execute();

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['telemetry'] = json_decode($row['telemetry'], true);
            $row['advertisement'] = json_decode($row['advertisement'], true);
            $results[] = $row;
        }

        return array("objects" => $results);

    }

    public function getChannelMessages($params, $reports = false) {
        $params['where'] = array();
        if (isset($params['id'])) {
            $ch = MeshLogChannel::findById(intval($id), $this);
            if (!$ch->enabled) return array();
            $params['where'] = array('channel_id = ' . intval($id));
        } else {
            $params['join'] = 'JOIN channels  ON t.channel_id = channels.id';
            $params['where'] = array('channels.enabled = 1');
        }

        $results = MeshLogChannelMessage::getAll($this, $params);

        if ($reports) {
            $results = $this->addReports($results, 'MeshLogChannelMessageReport');
        }

        return $results;
    }

    public function getDirectMessages($params, $reports = false) {
        $params['where'] = array();
        if (isset($params['id'])) {
            $params['where'] = array('contact_id = ' . intval($id));
        }

        $results = MeshLogDirectMessage::getAll($this, $params);

        if ($reports) {
            $results = $this->addReports($results, 'MeshLogDirectMessageReport');
        }

        return $results;
    }

    public function getRawPackets($params) {
        $params['where'] = array();
        $results = MeshLogRawPacket::getAll($this, $params);
        return $results;
    }
};

?>