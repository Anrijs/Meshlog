<?php

class MeshLogDirectMessage extends MeshLogEntity {
    protected static $table = "direct_messages";

    public $contact_ref = null;  // MeshLogContact
    public $reporter_ref = null; // MeshLogReporter

    public $hash = null;
    public $name = null;
    public $message = null;
    public $path = null;

    public $sent_at = null;
    public $received_at = null;
    public $created_at = null;

    public static function fromJson($data, $meshlog) {
        $m = new MeshLogDirectMessage($meshlog);
        
        if (!isset($data['message'])) return $m;
        if (!isset($data['contact'])) return $m;
        if (!isset($data['time'])) return $m;

        $m->hash = $data['hash'] ?? null;
        $m->message = $data['message']['text'] ?? '';
        $m->path = $data['message']['path'] ?? null;
        $m->name = $data['contact']['name'];

        $m->sent_at = Utils::time2str($data['time']['local']) ?? null;
        $m->received_at = Utils::time2str($data['time']['sender']) ?? null;

        return $m;
    }

    public static function fromDb($data, $meshlog) {
        if (!$data) return null;

        $m = new MeshLogDirectMessage($meshlog);
    
        $m->_id = $data['id'];
        $m->message = $data['message'];
        $m->name = $data['name'];
        $m->path = $data['path'];
        $m->hash = $data['hash'];
    
        $m->sent_at = $data['sent_at'];
        $m->received_at = $data['received_at'];
        $m->created_at = $data['created_at'];

        $m->contact_ref = MeshLogContact::findById($data['contact_id'], $meshlog);
        $m->reporter_ref = MeshLogReporter::findById($data['reporter_id'], $meshlog);

        return $m;
    }

    function isValid() {
        if ($this->contact_ref == null) return false;
        if ($this->reporter_ref == null) return false;

        if ($this->hash == null) { echo 'no hash'; return false; }
        if ($this->message == null) { echo 'no snr'; return false; }
        if ($this->sent_at == null) { echo 'no sent_at'; return false; }
        if ($this->received_at == null) { echo 'no received_at'; return false; }

        return true;
    }

    public function asArray($secret = false) {
        $rid = null;
        $cid = null;

        if ($this->reporter_ref) $rid = $this->reporter_ref->getId();
        if ($this->contact_ref) $cid = $this->contact_ref->getId();

        return array(
            'id' => $this->getId(),
            'contact_id' => $cid,
            'reporter_id' => $rid,
            'hash' => $this->hash,
            'name' => $this->name,
            'message' => $this->message,
            'path' => $this->path,
            'sent_at' => $this->sent_at,
            'received_at' => $this->received_at,
            'created_at' => $this->created_at
        );
    }

    protected function getParams() {
        $rid = null;
        $cid = null;

        if ($this->reporter_ref) $rid = $this->reporter_ref->getId();
        if ($this->contact_ref) $cid = $this->contact_ref->getId();

        return array(
            "contact_id" => array($cid, PDO::PARAM_INT),
            "reporter_id" => array($rid, PDO::PARAM_INT),
            "hash" => array($this->hash, PDO::PARAM_STR),
            "name" => array($this->name, PDO::PARAM_STR),
            "message" => array($this->message, PDO::PARAM_STR),
            "path" => array($this->path, PDO::PARAM_STR),
            "sent_at" => array($this->sent_at, PDO::PARAM_STR),
            "received_at" => array($this->received_at, PDO::PARAM_STR)
        );
    }
}

?>