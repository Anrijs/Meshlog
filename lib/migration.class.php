<?php

class Migration {
    function __construct($from, $to) {
        $this->from = $from;
        $this->to = $to;
    }

    function isPending($current_version) {
        return $current_version < $this->to;
    }

    function isDestructive() {
        return false;
    }

    function getName() {
        return "$this->from => $this->to";
    }

    function run($pdo, $current_version) {
        if ($current_version > $this->to) {
            return array(
                'success' => true,
                'message' => "Already migrated"
            );
        }
        if ($current_version != $this->from) {
            return array(
                'success' => false,
                'message' => "Can't upgrade from $current_version to $this->to"
            );
        }

        $result = $this->migrate($pdo);

        if ($result['success']) {
            $this->bumpVersion($pdo);
        }

        return $result;
    }

    function bumpVersion($pdo) {
        // v0 did not have settings table
        if ($this->to < 1) return;

        $sql =  "INSERT INTO `settings` (`name`, `value`) ";
        $sql .= "VALUES ('DB_VERSION', :version) ";
        $sql .= "ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";

        $query = $pdo->prepare($sql);
        $query->bindParam(':version', $this->to, PDO::PARAM_STR);
        $query->execute();
    }

    // Migration code
    function migrate($pdo) {
        return array(
            'success' => false,
            'message' => "Not implemented"
        );
    }
}

?>