<?php

class Migration_001 extends Migration {
    public function __construct() {
        parent::__construct(0, 1);
    }

    function migrate($pdo) {
        $statements = array();
        
        $statements[] = <<<EOD
            -- 1. settings
            CREATE TABLE IF NOT EXISTS `settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `value` text NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name_unique` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        EOD;

        $statements[] = <<<EOD
            -- 2. users
            CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `password_hash` varchar(255) NOT NULL,
            `permissions` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `name_unique` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        $statements[] = <<<EOD
            -- 3. data
            INSERT IGNORE INTO `settings` (`name`, `value`) VALUES 
            ('MAX_CONTACT_AGE', '1814400');

            INSERT INTO `settings` (`name`, `value`)
            VALUES ('DB_VERSION', '1')
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
        EOD;

        $statements[] = <<<EOD
            -- 4. Allow cascade delete / recreate FK
            ALTER TABLE `advertisements` 
            DROP FOREIGN KEY `advertisements_ibfk_1`,
            DROP FOREIGN KEY `advertisements_ibfk_2`;

            ALTER TABLE `direct_messages` 
            DROP FOREIGN KEY `direct_messages_ibfk_1`,
            DROP FOREIGN KEY `direct_messages_ibfk_2`;

            ALTER TABLE `channel_messages` 
            DROP FOREIGN KEY `channel_messages_ibfk_1`,
            DROP FOREIGN KEY `channel_messages_ibfk_2`,
            DROP FOREIGN KEY `channel_messages_ibfk_3`;

            ALTER TABLE `advertisements`
            ADD CONSTRAINT `advertisements_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
            ADD CONSTRAINT `advertisements_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `reporters` (`id`) ON DELETE CASCADE;

            ALTER TABLE `direct_messages`
            ADD CONSTRAINT `direct_messages_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
            ADD CONSTRAINT `direct_messages_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `reporters` (`id`) ON DELETE CASCADE;

            ALTER TABLE `channel_messages`
            ADD CONSTRAINT `channel_messages_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
            ADD CONSTRAINT `channel_messages_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `reporters` (`id`) ON DELETE CASCADE,
            ADD CONSTRAINT `channel_messages_ibfk_3` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`) ON DELETE CASCADE;
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