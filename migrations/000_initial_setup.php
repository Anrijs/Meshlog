<?php

class Migration_000 extends Migration {
    public function __construct() {
        parent::__construct(-1, 0);
    }

    function migrate($pdo) {
        $statements = array();
        
        $statements[] = <<<EOD
            -- 1. contacts (referenced by advertisements, direct_messages, channel_messages)
            CREATE TABLE IF NOT EXISTS `contacts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `public_key` varchar(64) NOT NULL,
            `name` varchar(128) DEFAULT NULL,
            `enabled` tinyint(4) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `contact_pub_key` (`public_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        $statements[] = <<<EOD
            -- 2. reporters (referenced by advertisements, direct_messages, channel_messages)
            CREATE TABLE IF NOT EXISTS `reporters` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(200) NOT NULL,
            `public_key` varchar(200) NOT NULL,
            `lat` decimal(9,6) NOT NULL,
            `lon` decimal(9,6) NOT NULL,
            `auth` varchar(200) NOT NULL,
            `authorized` tinyint(4) NOT NULL,
            `color` varchar(16) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        $statements[] = <<<EOD
            -- 3. channels (referenced by channel_messages)
            CREATE TABLE IF NOT EXISTS `channels` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `hash` varchar(16) NOT NULL,
            `name` varchar(32) NOT NULL,
            `enabled` tinyint(4) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `channel_hash` (`hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        $statements[] = <<<EOD
            -- 4. advertisements (depends on contacts, reporters)
            CREATE TABLE IF NOT EXISTS `advertisements` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `contact_id` int(11) NOT NULL COMMENT 'who sent advertisement',
            `reporter_id` int(11) NOT NULL COMMENT 'who received and reported',
            `hash` varchar(16) NOT NULL,
            `name` varchar(255) DEFAULT NULL,
            `lat` decimal(9,6) NOT NULL,
            `lon` decimal(9,6) NOT NULL,
            `path` varchar(192) NOT NULL,
            `type` tinyint(4) NOT NULL,
            `flags` smallint(4) NOT NULL,
            `snr` smallint(6) NOT NULL COMMENT 'last hop snr',
            `sent_at` timestamp NOT NULL COMMENT 'sender timestamp',
            `received_at` timestamp NOT NULL COMMENT 'reporter timestamp',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `contact_id` (`contact_id`),
            KEY `reporter_id` (`reporter_id`),
            CONSTRAINT `advertisements_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`),
            CONSTRAINT `advertisements_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `reporters` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        $statements[] = <<<EOD
            -- 5. direct_messages (depends on contacts, reporters)
            CREATE TABLE IF NOT EXISTS `direct_messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `contact_id` int(11) NOT NULL COMMENT 'who sent message',
            `reporter_id` int(11) NOT NULL COMMENT 'who received and reported',
            `hash` varchar(16) NOT NULL,
            `name` varchar(123) NOT NULL,
            `message` varchar(320) NOT NULL,
            `path` varchar(192) NOT NULL,
            `sent_at` timestamp NOT NULL COMMENT 'sender timestamp',
            `received_at` timestamp NOT NULL COMMENT 'reporter timestamp',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `contact_id` (`contact_id`),
            KEY `reporter_id` (`reporter_id`),
            CONSTRAINT `direct_messages_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`),
            CONSTRAINT `direct_messages_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `reporters` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        $statements[] = <<<EOD
            -- 6. channel_messages (depends on contacts, reporters, channels)
            CREATE TABLE IF NOT EXISTS `channel_messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `contact_id` int(11) DEFAULT NULL COMMENT 'who sent message (presumed)',
            `reporter_id` int(11) NOT NULL COMMENT 'who received and reported',
            `hash` varchar(16) NOT NULL,
            `channel_id` int(11) NOT NULL COMMENT 'channel id',
            `name` varchar(128) NOT NULL,
            `message` varchar(320) NOT NULL,
            `path` varchar(192) NOT NULL,
            `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `received_at` timestamp NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `contact_id` (`contact_id`),
            KEY `reporter_id` (`reporter_id`),
            KEY `channel_id` (`channel_id`),
            CONSTRAINT `channel_messages_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`),
            CONSTRAINT `channel_messages_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `reporters` (`id`),
            CONSTRAINT `channel_messages_ibfk_3` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`)
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