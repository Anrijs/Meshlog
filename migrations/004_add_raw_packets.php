<?php

class Migration_004 extends Migration {
    public function __construct() {
        parent::__construct(3, 4);
    }

    function migrate($pdo) {
        $statements = array();
        
        $statements[] = <<<EOD
            -- 1. raw packets
            CREATE TABLE IF NOT EXISTS `raw_packets` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `reporter_id` int(11) NOT NULL COMMENT 'who received and reported',
            `header` tinyint NOT NULL,
            `path` varchar(192) NOT NULL,
            `payload` varbinary(256) NOT NULL,
            `snr` smallint(6) NOT NULL COMMENT 'last hop snr',
            `decoded` tinyint NOT NULL,
            `received_at` timestamp NOT NULL COMMENT 'reporter timestamp',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `reporter_id` (`reporter_id`),
            CONSTRAINT `raw_packet_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `reporters` (`id`)
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