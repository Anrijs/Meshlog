<?php

class MeshLogChannel extends MeshLogEntity {
    protected static $table = "channels";

    public $hash = null;
    public $name = null;
    public $enabled = null;

    public static function fromJson($data, $meshlog) {
        $m = new MeshLogChannel($meshlog);

        $m->hash = $data['channel']['hash'] ?? '11';
        $m->name = $data['channel']['name'] ?? 'unknown';
        $m->enabled = true; // default

        return $m;
    }

    public static function fromDb($data, $meshlog) {
        if (!$data) return null;

        $m = new MeshLogReporter($meshlog);

        $m->_id = $data['id'];
        $m->hash = $data['hash'];
        $m->name = $data['name'];
        $m->enabled = $data['enabled'];

        return $m;
    }

    function isValid() {
        if ($this->hash == null) return false;
        if ($this->name == null) return false;

        return true;
    }

    public function asArray() {
        return array(
            'id' => $this->getId(),
            'hash' => $this->hasj,
            'name' => $this->name,
            'created_at' => $this->created_at
        );
    }

    protected function getParams() {
        return array(
            "hash" => array($this->hash, PDO::PARAM_STR),
            "name" => array($this->name, PDO::PARAM_STR),
            "enabled" => array($this->enabled, PDO::PARAM_INT),
        );
    }
}

?>