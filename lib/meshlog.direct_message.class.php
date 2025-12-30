<?php

class MeshLogDirectMessage extends MeshLogEntity {
    protected static $table = "direct_messages";

    public $contact_ref = null;  // MeshLogContact

    public $hash = null;
    public $name = null;
    public $message = null;

    public $sent_at = null;
    public $created_at = null;

    public static function fromJson($data, $meshlog) {
        $m = new MeshLogDirectMessage($meshlog);
        
        if (!isset($data['message'])) return $m;
        if (!isset($data['contact'])) return $m;
        if (!isset($data['time'])) return $m;

        $m->hash = $data['hash'] ?? null;
        $m->message = $data['message']['text'] ?? '';
        $m->name = $data['contact']['name'];

        $m->sent_at = Utils::time2str($data['time']['sender']) ?? null;

        return $m;
    }

    public static function fromDb($data, $meshlog) {
        if (!$data) return null;

        $m = new MeshLogDirectMessage($meshlog);
    
        $m->_id = $data['id'];
        $m->message = $data['message'];
        $m->name = $data['name'];
        $m->hash = $data['hash'];
    
        $m->sent_at = $data['sent_at'];
        $m->created_at = $data['created_at'];

        $m->contact_ref = MeshLogContact::findById($data['contact_id'], $meshlog);

        return $m;
    }

    function isValid() {
        if ($this->contact_ref == null) return false;

        if ($this->hash == null) { echo 'Missing hash'; return false; }
        if ($this->message == null) { echo 'Missing message'; return false; }
        if ($this->sent_at == null) { echo 'Missing sent_at'; return false; }

        return true;
    }

    public function asArray($secret = false) {
        $cid = null;

        if ($this->contact_ref) $cid = $this->contact_ref->getId();

        return array(
            'id' => $this->getId(),
            'contact_id' => $cid,
            'hash' => $this->hash,
            'name' => $this->name,
            'message' => $this->message,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at
        );
    }

    protected function getParams() {
        $cid = null;

        if ($this->contact_ref) $cid = $this->contact_ref->getId();

        return array(
            "contact_id" => array($cid, PDO::PARAM_INT),
            "hash" => array($this->hash, PDO::PARAM_STR),
            "name" => array($this->name, PDO::PARAM_STR),
            "message" => array($this->message, PDO::PARAM_STR),
            "sent_at" => array($this->sent_at, PDO::PARAM_STR),
        );
    }

    public static function getPublicFields($prefix='t') {
        return "
            $prefix.id,
            $prefix.contact_id,
            $prefix.hash,
            $prefix.name,
            $prefix.message,
            $prefix.sent_at,
            $prefix.created_at";
    }
}

?>