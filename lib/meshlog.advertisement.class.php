<?php

class MeshLogAdvertisement extends MeshLogEntity {
    protected static $table = "advertisements";

    public $contact_ref = null;  // MeshLogContact
    public $reporter_ref = null; // MeshLogReporter

    public $hash = null;
    public $name = null;
    public $lat = null;
    public $lon = null;
    public $path = null;
    public $snr = null;
    public $type = null;
    public $flags = null;

    public $sent_at = null;
    public $received_at = null;
    public $created_at = null;

    public static function fromJson($data, $meshlog) {
        $m = new MeshLogAdvertisement($meshlog);
        
        if (!isset($data['contact'])) return $m;
        if (!isset($data['time'])) return $m;

        $m->hash = $data['hash'] ?? null;
        $m->name = $data['contact']['name'] ?? null;
        $m->lat = floatval($data['contact']['lat']) ?? 0.0;
        $m->lon = floatval($data['contact']['lon']) ?? 0.0;
        $m->path = $data['message']['path'] ?? null;
        $m->snr = $data['snr'] ?: null;
        $m->type = $data['contact']['type'] ?: 0;
        $m->flags = $data['contact']['flags'] ?: 0;

        $m->lat /= 1000000.0;
        $m->lon /= 1000000.0;

        $m->sent_at = Utils::time2str($data['time']['local']) ?? null;
        $m->received_at = Utils::time2str($data['time']['sender']) ?? null;

        // $pubkey = $data['contact']['pubkey'] ?? null;
        // $reporter = $data['reporter'] ?? null;

        // if ($pubkey && $meshlog)   $m->contact_ref = MeshLogContact::findBy("public_key", $pubkey, $meshlog);
        // if ($reporter && $meshlog) $m->reporter_ref = MeshLogReporter::findBy("public_key", $pubkey, $meshlog);

        return $m;
    }

    public static function fromDb($data, $meshlog) {
        if (!$data) return null;

        $m = new MeshLogAdvertisement($meshlog);

        $m->_id = $data['id'];
        $m->hash = $data['hash'];
        $m->name = $data['name'];
        $m->lat = $data['lat'];
        $m->lon = $data['lon'];
        $m->path = $data['path'];
        $m->snr = $data['snr'];
        $m->type = $data['type'];
        $m->flags = $data['flags'];
    
        $m->sent_at = $data['sent_at'];
        $m->received_at = $data['received_at'];
        $m->created_at = $data['created_at'];

        // load refs
        $m->contact_ref = MeshLogContact::findById($data['contact_id'], $meshlog);
        $m->reporter_ref = MeshLogReporter::findById($data['reporter_id'], $meshlog);

        return $m;
    }

    function isValid() {
        $err = "";
        if ($this->contact_ref == null) return false;
        if ($this->reporter_ref == null) return false;

        if ($this->name == null) { $err .= 'no name,'; }
        if ($this->hash == null) { $err .= 'no hash,'; }
        if ($this->type != 1) {
          if ($this->lat == null) { $err .= 'no lat,'; }
          if ($this->lon == null) { $err .= 'no lon,'; }
        }
        if ($this->snr == null) { $err .= 'no snr,'; }
        if ($this->sent_at == null) { $err .= 'no sent_at,'; }
        if ($this->received_at == null) { $err .= 'no received_at,'; }

        if ($err) {
            error_log("Failed to save adv: $err");
            echo $err;
        }

        return true;
    }

    public function asArray() {
        $rid = null;
        $cid = null;

        if ($this->reporter_ref) $rid = $this->reporter_ref->getId();
        if ($this->contact_ref) $cid = $this->contact_ref->getId();

        return array(
            'id' => $this->getId(),
            'contact_id' => $cid,
            'reporter_id' => $rid,
            "hash" => $this->hash,
            "name" => $this->name,
            "lat" => floatval($this->lat),
            "lon" => floatval($this->lon),
            "snr" => floatval($this->snr),
            "type" => $this->type,
            "flags" => $this->flags,
            "path" => $this->path,
            "sent_at" => $this->sent_at,
            "received_at" => $this->received_at,
            "created_at" => $this->created_at
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
            "lat" => array($this->lat, PDO::PARAM_STR),
            "lon" => array($this->lon, PDO::PARAM_STR),
            "path" => array($this->path, PDO::PARAM_STR),
            "type" => array($this->type, PDO::PARAM_INT),
            "flags" => array($this->flags, PDO::PARAM_INT),
            "snr" => array($this->snr, PDO::PARAM_INT),
            "sent_at" => array($this->sent_at, PDO::PARAM_STR),
            "received_at" => array($this->received_at, PDO::PARAM_STR),
        );
    }
}

?>