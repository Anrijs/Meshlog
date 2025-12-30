<?php

class MeshLogReport extends MeshLogEntity {
    protected static $table = 'unknown_report_';
    protected static $refname = '_unknown_id';

    public $object_id = null; // Unlike other types, these are plain ids
    public $reporter_id = null;

    public $path = null;
    public $snr = null;

    public $received_at = null;
    public $created_at = null;

    public static function fromJson($data, $meshlog) {
        $m = new static($meshlog);

        $m->path = $data['message']['path'] ?? null;
        $m->snr = $data['snr'] ?? null;
        $m->received_at = Utils::time2str($data['time']['local']) ?? null;

        return $m;
    }

    public static function fromDb($data, $meshlog) {
        if (!$data) return null;

        $m = new static($meshlog);

        $m->_id = $data['id'];
        $m->path = $data['path'];
        $m->snr = $data['snr'];

        $m->received_at = $data['received_at'];
        $m->created_at = $data['created_at'];

        $m->object_id = $data[static::$refname];
        $m->reporter_id = $data['reporter_id'];

        return $m;
    }

    public static function getAllReports($meshlog, $ref_id, $params = array()) {
        $ref_id = intval($ref_id);
        $refname = static::$refname;
        $params['where'] = array("$refname = $ref_id");
        return static::getAll($meshlog, $params);
    }

    function isValid() {
        $err = "";
        if ($this->object_id === null) { $err .= 'Missing Ref,'; }
        if ($this->reporter_id === null) { $err .= 'Missing Reporter,'; }
        if ($this->path === null) { $err .= 'Missing Path,'; }
        if ($this->received_at === null) { $err .= 'Missing received_at,'; }

        if ($err) {
            error_log("Failed to save report: $err");
            $this->error = $err;
        }

        return true;
    }

    public function asArray($secret = false) {
        return array(
            'id' => $this->getId(),
            static::$refname => $this->object_id,
            'reporter_id' => $this->reporter_id,
            "snr" => floatval($this->snr),
            "path" => $this->path,
            "received_at" => $this->received_at,
            "created_at" => $this->created_at
        );
    } 

    protected function getParams() {
        return array(
            static::$refname => array($this->object_id, PDO::PARAM_INT),
            "reporter_id" => array($this->reporter_id, PDO::PARAM_INT),
            "path" => array($this->path, PDO::PARAM_STR),
            "snr" => array($this->snr, PDO::PARAM_INT),
            "received_at" => array($this->received_at, PDO::PARAM_STR),
        );
    }
}

class MeshLogAdvertisementReport extends MeshLogReport {
    protected static $table = 'advertisement_reports';
    protected static $refname = 'advertisement_id';

    public static function getTable() {
        return static::$table;
    }

    public static function getRefName() {
        return static::$refname;
    }
}

class MeshLogChannelMessageReport extends MeshLogReport {
    protected static $table = 'channel_message_reports';
    protected static $refname = 'channel_message_id';

    public static function getTable() {
        return static::$table;
    }

    public static function getRefName() {
        return static::$refname;
    }
}

class MeshLogDirectMessageReport extends MeshLogReport {
    protected static $table = 'direct_message_reports';
    protected static $refname = 'direct_message_id';

    public static function getTable() {
        return static::$table;
    }

    public static function getRefName() {
        return static::$refname;
    }
}

?>