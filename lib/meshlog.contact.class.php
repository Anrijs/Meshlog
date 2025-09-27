<?php

class MeshLogContact extends MeshLogEntity {
    protected static $table = "contacts";

    public $public_key = null;
    public $enabled = null;
    public $name = null;
    public $created_at = null;

    public static function fromJson($data, $meshlog) {
        $m = new MeshLogContact($meshlog);
        
        if (!isset($data['contact'])) return $m;
        $m->public_key = $data['contact']['pubkey'] ?? null;
        $m->enabled = true; // default

        return $m;
    }

    public static function fromDb($data, $meshlog) {
        if (!$data) return null;

        $m = new MeshLogContact($meshlog);
        $m->_id = $data['id'];
        $m->public_key = $data['public_key'];
        $m->name = $data['name'];
        $m->enabled = $data['enabled'];
        $m->created_at = $data['created_at'];

        return $m;
    }

    public function isValid() {
        if ($this->public_key == null) { $this->error = "Missing public key"; return false; };
        return parent::isValid();
    }

    public function asArray($secret = false) {
        return array(
            'id' => $this->getId(),
            'public_key' => $this->public_key,
            'name' => $this->name,
            'created_at' => $this->created_at
        );
    }

    protected function getParams() {
        return array(
            "public_key" => array($this->public_key, PDO::PARAM_STR),
            "name" => array($this->name, PDO::PARAM_STR),
            "enabled" => array($this->enabled, PDO::PARAM_STR),
        );
    }
}

?>