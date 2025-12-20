<?php

class MeshLogReporter extends MeshLogEntity {
    protected static $table = "reporters";

    public $name = null;
    public $authorized = null;
    public $public_key = null;
    public $lat = null;
    public $lon = null;
    public $style = null;
    public $auth = null;

    public static function fromDb($data, $meshlog) {
        if (!$data) return null;

        $m = new MeshLogReporter($meshlog);

        $m->_id = $data['id'];
        $m->name = $data['name'];
        $m->authorized = $data['authorized'];
        $m->public_key = $data['public_key'];
        $m->lat = $data['lat'];
        $m->lon = $data['lon'];
        $m->style = $data['style'];
        $m->auth = $data['auth'];

        return $m;
    }

    public function asArray($secret = false) {
        $data = array(
            'id' => $this->getId(),
            'name' => $this->name,
            'public_key' => $this->public_key,
            'lat' => $this->lat,
            'lon' => $this->lon,
            'style' => $this->style,
        );

        if ($secret) {
            $data['auth'] = $this->auth;
            $data['authorized'] = $this->authorized;
        }

        return $data;
    }

    public function updateLocation($meshlog, $lat, $lon) {
        if (!$lat || !$lon) return;
        if ($lat == $this->lat && $lon == $this->lon) return;

        $tableStr = static::$table;
        $query = $meshlog->pdo->prepare("UPDATE $tableStr SET lat = :lat, lon = :lon WHERE id = :id");

        $query->bindParam(':lat', $lat,  PDO::PARAM_STR);
        $query->bindParam(':lon', $lon,  PDO::PARAM_STR);
        $query->bindParam(':id', $this->_id,  PDO::PARAM_INT);
        $query->execute();
    }

    public function isValid() {
        if ($this->public_key == null) { $this->error = "Missing Public Key"; return false; };
        if ($this->name == null) { $this->error = "Missing Name"; return false; };

        return parent::isValid();
    }

    protected function getParams() {
        return array(
            "name" => array($this->name, PDO::PARAM_STR),
            "public_key" => array($this->public_key, PDO::PARAM_STR),
            "authorized" => array($this->authorized, PDO::PARAM_STR),
            "lat" => array($this->lat, PDO::PARAM_STR),
            "lon" => array($this->lon, PDO::PARAM_STR),
            "style" => array($this->style, PDO::PARAM_STR),
            "color" => array("", PDO::PARAM_STR),
            "auth" => array($this->auth, PDO::PARAM_STR),
        );
    }
    
}

?>