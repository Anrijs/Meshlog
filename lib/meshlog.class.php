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
require_once 'meshlog.user.class.php';

define("MAX_COUNT", 5000);
define("DEFAULT_COUNT", 500);

class MeshLog {
    private $error = '';
    private $version = 1;
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
            return "Database upgrade required! <a href=\"login.php\">Login</a>";
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
            case 'RAW':
                return;
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

        $adv = MeshLogAdvertisement::fromJson($data, $this);
        $adv->reporter_ref = $reporter;
        $adv->contact_ref = $contact;

        return $adv->save($this);
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
        $dm->reporter_ref = $reporter;
        $dm->contact_ref = $contact;

        return $dm->save($this);
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

        $advertisement = MeshLogAdvertisement::findBy("name", $name, $this);
        $contact = null;
        if ($advertisement) $contact = MeshLogContact::findById($advertisement->contact_ref->getId(), $this);

        $grpmsg = MeshLogChannelMessage::fromJson($data, $this);
        $grpmsg->reporter_ref = $reporter;
        $grpmsg->contact_ref = $contact;
        $grpmsg->channel_ref = $channel;

        return $grpmsg->save($this);
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
                $ad = MeshLogAdvertisement::findBy("contact_id", $id, $this, array('created_at' => array('operator' => '>', 'value' => $maxage)));
                if ($ad) {
                    $c['advertisement'] = $ad->asArray();
                    $out[] = $c;
                }
            }

        }

        return array("objects" => $out);
    }

    public function getAdvertisements($params) {
        $params['where'] = array();
        return MeshLogAdvertisement::getAll($this, $params);
    }

    public function getChannels($params) {
        $params['where'] = array('enabled = 1');
        return MeshLogChannel::getAll($this, $params);
    }

    public function getChannelMessages($params) {
        $params['where'] = array();
        if (isset($params['id'])) {
            $ch = MeshLogChannel::findById(intval($id), $this);
            if (!$ch->enabled) return array();
            $params['where'] = array('channel_id = ' . intval($id));
        } else {
            $params['join'] = 'JOIN channels  ON t.channel_id = channels.id';
            $params['where'] = array('channels.enabled = 1');
        }

        return MeshLogChannelMessage::getAll($this, $params);
    }

    public function getDirectMessages($params) {
        $params['where'] = array();
        if (isset($params['id'])) {
            $params['where'] = array('contact_id = ' . intval($id));
        }

        return MeshLogDirectMessage::getAll($this, $params);
    }
};

?>