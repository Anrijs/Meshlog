<?php

class MeshLogTelemetry extends MeshLogEntity {
    protected static $table = "telemetry";

    public $contact_ref = null;  // MeshLogContact
    public $reporter_ref = null; // MeshLogReporter

    public $data = null;

    public $sent_at = null;
    public $received_at = null;
    public $created_at = null;

    public static function fromJson($data, $meshlog) {
        $m = new MeshLogTelemetry($meshlog);
        
        if (!isset($data['telemetry'])) return $m;
        if (!isset($data['time'])) return $m;

        $m->data = json_encode($data['telemetry']) ?? '[]';

        $m->received_at = Utils::time2str($data['time']['local']) ?? null;
        $m->sent_at = Utils::time2str($data['time']['sender']) ?? null;

        return $m;
    }

    public static function fromDb($data, $meshlog) {
        if (!$data) return null;

        $m = new MeshLogTelemetry($meshlog);
    
        $m->_id = $data['id'];
        $m->data = $data['data'];
    
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

        if ($this->sent_at == null) { echo 'Missing sent_at'; return false; }
        if ($this->received_at == null) { echo 'Missing received_at'; return false; }

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
            'data' => $this->data,
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
            "data" => array($this->data, PDO::PARAM_STR),
            "sent_at" => array($this->sent_at, PDO::PARAM_STR),
            "received_at" => array($this->received_at, PDO::PARAM_STR)
        );
    }
}

?>