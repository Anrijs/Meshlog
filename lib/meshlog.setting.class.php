<?php

class MeshLogSetting extends MeshLogEntity {
    const KEY_DB_VERSION = "DB_VERSION";
    const KEY_MAX_CONTACT_AGE = "MAX_CONTACT_AGE";

    protected static $table = "settings";

    public $name = null;
    public $value = null;

    public static function fromDb($data, $meshlog) {
        if (!$data) return null;

        $m = new MeshLogSetting($meshlog);
        $m->name = $data['name'];
        $m->value = $data['value'];

        return $m;
    }

    public static function saveSettings($meshlog, $settings) {
        $tableStr = static::$table;
        $stmt = $meshlog->pdo->prepare("
            INSERT INTO $tableStr (name, value)
            VALUES (:name, :value)
            ON DUPLICATE KEY UPDATE value = :value
        ");

        foreach ($settings as $key => $val) {
            $stmt->execute([
                ':name'  => $key,
                ':value' => $val
            ]);
        }
    }

    public function asArray($secret = false) {
        return array(
            'id' => $this->getId(),
            'name' => $this->name,
            'value' => $this->value
        );
    }

    protected function getParams() {
        return array(
            "name" => array($this->name, PDO::PARAM_STR),
            "value" => array($this->value, PDO::PARAM_STR)
        );
    }
}

?>