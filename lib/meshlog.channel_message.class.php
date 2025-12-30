<?php

class MeshLogChannelMessage extends MeshLogEntity {
    protected static $table = "channel_messages";

    public $contact_ref = null;  // MeshLogContact
    public $channel_ref = null;    // MeshLogChannel

    public $hash = null;
    public $name = null;
    public $message = null;

    public $sent_at = null;
    public $created_at = null;

    public static function fromJson($data, $meshlog) {
        $m = new MeshLogChannelMessage($meshlog);
        
        if (!isset($data['message'])) return $m;
        if (!isset($data['time'])) return $m;

        $text = $data['message']['text'] ?? null;
        $parts = explode(":", $text, 2);
        $name = $parts[0] ? $parts[0] : null;
        $msg  = $parts[1] ? substr($parts[1], 1) : ''; 

        $m->hash = $data['hash'] ?? null;
        $m->name = $name;
        $m->message = $msg;

        $m->sent_at = Utils::time2str($data['time']['sender']) ?? null;

        return $m;
    }

    public static function fromDb($data, $meshlog) {
        if (!$data) return null;

        $m = new MeshLogChannelMessage($meshlog);

        $m->_id = $data['id'];
        $m->hash = $data['hash'];
        $m->name = $data['name'];
        $m->message = $data['message'];
    
        $m->sent_at = $data['sent_at'];
        $m->created_at = $data['created_at'];

        $m->contact_ref = MeshLogContact::findById($data['contact_id'], $meshlog);
        $m->channel_ref = MeshLogChannel::findById($data['channel_id'], $meshlog);

        return $m;
    }

    function isValid() {
        // contact can be empty if it has not advertised yet.

        if ($this->name == null) { $this->error = 'Missing name'; return false; }
        if ($this->hash == null) { $this->error = 'Missing hash'; return false; }
        if ($this->message == null) { $this->error = 'Missing message'; return false; }
        if ($this->sent_at == null) { $this->error = 'Missing sent_at'; return false; }

        return true;
    }

    public function asArray($secret = false) {
        $cid = null;

        if ($this->contact_ref) $cid = $this->contact_ref->getId();

        return array(
            'id' => $this->getId(),
            'contact_id' => $cid,
            'channel_id' => $this->channel_ref->getId(),
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
            "channel_id" => array($this->channel_ref->getId(), PDO::PARAM_INT),
            "hash" => array($this->hash, PDO::PARAM_STR),
            "name" => array($this->name, PDO::PARAM_STR),
            "message" => array($this->message, PDO::PARAM_STR),
            "sent_at" => array($this->sent_at, PDO::PARAM_STR),
        );
    }

    public static function getPublicFields($prefix='t') {
        return "$prefix.id,
                $prefix.contact_id,
                $prefix.channel_id,
                $prefix.hash,
                $prefix.name,
                $prefix.message,
                $prefix.sent_at,
                $prefix.created_at";
    }
}

?>