<?php

class MeshLogRawPacket extends MeshLogEntity {
    protected static $table = "raw_packets";

    public $reporter_id = null;

    public $header = null;
    public $path = null;
    public $payload = null;
    public $snr = null;
    public $decded = null;

    public $received_at = null;
    public $created_at = null;

    public static function fromJson($data, $meshlog) {
        $m = new MeshLogRawPacket($meshlog);
        
        if (!isset($data['time'])) return $m;
        if (!isset($data['packet'])) return $m;

        $m->header = $data['packet']['header'] ?? 0;
        $m->path = $data['packet']['path'] ?? '';
        $m->payload = hex2bin($data['packet']['payload'] ?? '');
        $m->snr = $data['packet']['snr'];
        $m->decoded = $data['packet']['decoded'];

        $m->received_at = Utils::time2str($data['time']['local']) ?? null;

        return $m;
    }

    public static function fromDb($data, $meshlog) {
        if (!$data) return null;

        $m = new MeshLogRawPacket($meshlog);
    
        $m->_id = $data['id'];
        $m->header = $data['header'];
        $m->path = $data['path'];
        $m->payload = $data['payload'];
        $m->snr = $data['snr'];
        $m->decoded = $data['decoded'];
    
        $m->received_at = $data['received_at'];
        $m->created_at = $data['created_at'];

        $m->reporter_id = $data['reporter_id'];

        return $m;
    }

    function isValid() {
        if ($this->reporter_id == null) return false;

        if ($this->payload == null) { echo 'Missing mepayloadssage'; return false; }
        if ($this->received_at == null) { echo 'Missing received_at'; return false; }

        return true;
    }

    public function asArray($secret = false) {
        return array(
            'id' => $this->getId(),
            'reporter_id' => $this->reporter_id,
            'header' => $this->header,
            'path' => $this->path,
            'payload' => $this->payload,
            'snr' => $this->snr,
            'decoded' => $this->decoded,
            'received_at' => $this->received_at,
            'created_at' => $this->created_at
        );
    }

    protected function getParams() {
        return array(
            "reporter_id" => array($this->reporter_id, PDO::PARAM_INT),
            "header" => array($this->header, PDO::PARAM_INT),
            "path" => array($this->path, PDO::PARAM_STR),
            "payload" => array($this->payload, PDO::PARAM_STR),
            "path" => array($this->path, PDO::PARAM_STR),
            "snr" => array($this->snr, PDO::PARAM_INT),
            "decoded" => array($this->decoded, PDO::PARAM_INT),
            "received_at" => array($this->received_at, PDO::PARAM_STR),
            "created_at" => array($this->created_at, PDO::PARAM_STR)
        );
    }
}

?>