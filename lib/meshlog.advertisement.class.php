<?php

class MeshLogAdvertisement extends MeshLogEntity {
    protected static $table = "advertisements";

    public $contact_ref = null;  // MeshLogContact

    public $hash = null;
    public $name = null;
    public $lat = null;
    public $lon = null;
    public $type = null;
    public $flags = null;

    public $sent_at = null;
    public $created_at = null;

    public static function fromJson($data, $meshlog) {
        $m = new MeshLogAdvertisement($meshlog);
        
        if (!isset($data['contact'])) return $m;
        if (!isset($data['time'])) return $m;

        $m->hash = $data['hash'] ?? null;
        $m->name = $data['contact']['name'] ?? null;
        $m->lat = floatval($data['contact']['lat']) ?? 0.0;
        $m->lon = floatval($data['contact']['lon']) ?? 0.0;
        $m->type = $data['contact']['type'] ?? 0;
        $m->flags = $data['contact']['flags'] ?? 0;

        $m->lat /= 1000000.0;
        $m->lon /= 1000000.0;

        $m->sent_at = Utils::time2str($data['time']['sender']) ?? null;

        // $pubkey = $data['contact']['pubkey'] ?? null;

        // if ($pubkey && $meshlog)   $m->contact_ref = MeshLogContact::findBy("public_key", $pubkey, $meshlog);

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
        $m->type = $data['type'];
        $m->flags = $data['flags'];
    
        $m->sent_at = $data['sent_at'];
        $m->created_at = $data['created_at'];

        // load refs
        $m->contact_ref = MeshLogContact::findById($data['contact_id'], $meshlog);

        return $m;
    }

    function isValid() {
        $err = "";
        if ($this->contact_ref == null) return false;

        if ($this->name == null) { $err .= 'Missing name,'; }
        if ($this->hash == null) { $err .= 'Missing hash,'; }
        if ($this->type != 1) {
          if ($this->lat == null) { $err .= 'Missing lat,'; }
          if ($this->lon == null) { $err .= 'Missing lon,'; }
        }
        if ($this->sent_at == null) { $err .= 'Missing sent_at,'; }

        if ($err) {
            error_log("Failed to save adv: $err");
            $this->error = $err;
        }

        return true;
    }

    public function asArray($secret = false) {
        $rid = null;
        $cid = null;

        if ($this->contact_ref) $cid = $this->contact_ref->getId();

        return array(
            'id' => $this->getId(),
            'contact_id' => $cid,
            "hash" => $this->hash,
            "name" => $this->name,
            "lat" => floatval($this->lat),
            "lon" => floatval($this->lon),
            "type" => $this->type,
            "flags" => $this->flags,
            "sent_at" => $this->sent_at,
            "created_at" => $this->created_at
        );
    } 

    protected function getParams() {
        $cid = null;

        if ($this->contact_ref) $cid = $this->contact_ref->getId();

        return array(
            "contact_id" => array($cid, PDO::PARAM_INT),
            "hash" => array($this->hash, PDO::PARAM_STR),
            "name" => array($this->name, PDO::PARAM_STR),
            "lat" => array($this->lat, PDO::PARAM_STR),
            "lon" => array($this->lon, PDO::PARAM_STR),
            "type" => array($this->type, PDO::PARAM_INT),
            "flags" => array($this->flags, PDO::PARAM_INT),
            "sent_at" => array($this->sent_at, PDO::PARAM_STR),
        );
    }

    public static function getPublicFields($prefix='t') {
        return "$prefix.id,
                $prefix.contact_id,
                $prefix.hash,
                $prefix.name,
                $prefix.lat,
                $prefix.lon,
                $prefix.type,
                $prefix.flags,
                $prefix.sent_at,
                $prefix.created_at";
    }
}

?>