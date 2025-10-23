<?php

class Migration_002 extends Migration {
    public function __construct() {
        parent::__construct(1, 2);
    }

    function migrate($pdo) {
        $statements = array();
        
        $statements[] = <<<EOD
            -- 1. telemetry
            CREATE TABLE IF NOT EXISTS `telemetry` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `contact_id` int(11) NOT NULL COMMENT 'who sent telemetry',
            `reporter_id` int(11) NOT NULL COMMENT 'who received and reported',
            `data` text NOT NULL,
            `sent_at` timestamp NOT NULL COMMENT 'sender timestamp',
            `received_at` timestamp NOT NULL COMMENT 'reporter timestamp',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `contact_id` (`contact_id`),
            KEY `reporter_id` (`reporter_id`),
            CONSTRAINT `telemetry_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`),
            CONSTRAINT `telemetry_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `reporters` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }

        return array(
            'success' => true,
            'message' => ''
        );
    }
}

?>