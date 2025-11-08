<?php

class Migration_003 extends Migration {
    public function __construct() {
        parent::__construct(2, 3);
    }

    function isDestructive() {
        return "Advertisements, Channel Messages and Direct Messages table duplicate records will be split into object/report (1 to many structure) tables and duplicate rows and cols will be deleted!";
    }

    function migrate($pdo) {
        $statements = array();
        
        $statements[] = <<<EOD
            -- 1. advertisement_reports
            CREATE TABLE IF NOT EXISTS `advertisement_reports` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `advertisement_id` int(11) NOT NULL,
            `reporter_id` int(11) NOT NULL,
            `path` varchar(192) NOT NULL,
            `snr` smallint(6) NOT NULL COMMENT 'last hop snr',
            `received_at` timestamp NOT NULL COMMENT 'reporter timestamp',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `advertisement_id` (`advertisement_id`),
            KEY `reporter_id` (`reporter_id`),
            CONSTRAINT `advertisement_reports_ibfk_1` FOREIGN KEY (`advertisement_id`) REFERENCES `advertisements` (`id`),
            CONSTRAINT `advertisement_reports_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `reporters` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        $statements[] = <<<EOD
            -- 2. channel_message_reports
            CREATE TABLE IF NOT EXISTS `channel_message_reports` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `channel_message_id` int(11) NOT NULL,
            `reporter_id` int(11) NOT NULL,
            `path` varchar(192) NOT NULL,
            `snr` smallint(6) NOT NULL COMMENT 'last hop snr',
            `received_at` timestamp NOT NULL COMMENT 'reporter timestamp',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `channel_message_id` (`channel_message_id`),
            KEY `reporter_id` (`reporter_id`),
            CONSTRAINT `channel_message_reports_ibfk_1` FOREIGN KEY (`channel_message_id`) REFERENCES `channel_messages` (`id`),
            CONSTRAINT `channel_message_reports_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `reporters` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        $statements[] = <<<EOD
            -- 3. direct_message_reports
            CREATE TABLE IF NOT EXISTS `direct_message_reports` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `direct_message_id` int(11) NOT NULL,
            `reporter_id` int(11) NOT NULL,
            `path` varchar(192) NOT NULL,
            `snr` smallint(6) NOT NULL COMMENT 'last hop snr',
            `received_at` timestamp NOT NULL COMMENT 'reporter timestamp',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `direct_message_id` (`direct_message_id`),
            KEY `reporter_id` (`reporter_id`),
            CONSTRAINT `direct_message_reports_ibfk_1` FOREIGN KEY (`direct_message_id`) REFERENCES `direct_messages` (`id`),
            CONSTRAINT `direct_message_reports_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `reporters` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        EOD;

        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }


        // Migrate data

        // This can take a while...
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        // advertisements
        $this->migrateTable($pdo, MeshLogAdvertisement::getTable(), "advertisement_reports", "advertisement_id");
        $this->deleteTableCols($pdo, MeshLogAdvertisement::getTable(), array('reporter_id', 'path', 'snr', 'received_at'));

        // channels
        $this->migrateTable($pdo, MeshLogChannelMessage::getTable(), "channel_message_reports", "channel_message_id");
        $this->deleteTableCols($pdo, MeshLogChannelMessage::getTable(), array('reporter_id', 'path', 'received_at'));

        // DMs
        $this->migrateTable($pdo, MeshLogDirectMessage::getTable(), "direct_message_reports", "direct_message_id");
        $this->deleteTableCols($pdo, MeshLogDirectMessage::getTable(), array('reporter_id', 'path', 'received_at'));

        return array(
            'success' => true,
            'message' => ''
        );
    }

    private function migrateTable($pdo, $src, $dst, $idcol) {
        $limit = 1000;
        $offset = 0;

        $bucket = array();
        $bucketout = array();
        $bucketrm = array();

        while (true) {
            $MAX_TIME = 180; // 3 min from first.

            $stmt = $pdo->prepare("SELECT * FROM $src LIMIT :limit OFFSET :offset");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $offset += $limit;

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 4. Display results
            foreach ($rows as $row) {
                $hash = $row['hash'];
                if (!$hash) continue;  // unrecoverable, very early dev records

                $time = strtotime($row['created_at']);
                $id = $row['id'];
                $obj = array(
                    $row['reporter_id'],
                    $row['path'],
                    $row['snr'] ?? 0,
                    $row['received_at'],
                    $row['created_at'],
                );

                $create = true;
                if (array_key_exists($hash, $bucket)) {
                    $tdelta = $time - $bucket[$hash][0];
                    if ($tdelta < $MAX_TIME) {
                        $create = false;
                        $bucket[$hash][3] = false; // celar flush flag
                        $bucket[$hash][4][] = $obj;
                        $bucketrm[] = intval($row['id']);
                    } else {
                        $bucketout[] = $bucket[$hash];
                        unset($bucket[$hash]);
                        $create = true;
                    }
                }
                
                if ($create) {
                    $bucket[$hash] = array(
                        $time,          // first created_at
                        $hash,          // first hash
                        intval($id),    // first adv id
                        false,          // flush
                        array($obj)     // list of reports
                    );
                }
            }

            // send uncahnged to out queue
            foreach ($bucket as $hash => $obj) {
                if ($obj[3]) {
                    $bucketout[] = $obj;
                    unset($bucket[$hash]);
                } else {
                    $bucket[$hash][3] = true; // set flush flag
                }
            }

            // iterate and flush uncahnged
            if (sizeof($bucketout) > 0) {
                try {
                    $placeholders = [];
                    $values = [];

                    $obj = array(
                        $row['reporter_id'],
                        $row['path'],
                        $row['snr'] ?? 0,
                        $row['received_at'],
                        $row['created_at'],
                    );

                    $values = [];

                    foreach ($bucketout as $obj) {
                        $rel_id = $obj[2];
                        foreach ($obj[4] as $out) {
                            $placeholders[] = "(?, ?, ?, ?, ?, ?)";
                            $outrow = array(
                                $rel_id,
                                $out[0],
                                $out[1],
                                $out[2],
                                $out[3],
                                $out[4]
                            );
                            $values = array_merge($values, $outrow);
                        }
                    }

                    $sql = "INSERT INTO $dst ($idcol, reporter_id, path, snr, received_at, created_at) VALUES ";
                    $sql .= implode(", ", $placeholders);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);

                    //echo "Inserted " . $stmt->rowCount() . " rows successfully!\n";
                } catch (PDOException $e) {
                    //echo "Error: " . $e->getMessage();
                }
            }
            $bucketout = [];

            $removed = 0;
            if (sizeof($bucketrm) > 0) {
                $placeholders = rtrim(str_repeat('?,', count($bucketrm)), ',');

                $sql = "DELETE FROM $src WHERE id IN ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bucketrm);

                $removed = $stmt->rowCount();
                $offset -= $removed;
                //echo "Deleted " . $stmt->rowCount() . " rows.\n";
            }
            $bucketrm = [];

            if (sizeof($rows) < 1) break;
        }
    }

    private function deleteTableCols($pdo, $src, $cols) {
        $sql  = "SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME ";
        $sql .= "FROM information_schema.KEY_COLUMN_USAGE ";
        $sql .= "WHERE TABLE_NAME = '$src' AND COLUMN_NAME = 'reporter_id' AND TABLE_SCHEMA = DATABASE()";

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $name = $row['CONSTRAINT_NAME'];
            $sql = "ALTER TABLE $src DROP FOREIGN KEY $name";
            $pdo->exec($sql);
        }

        $sql = "ALTER TABLE $src DROP INDEX reporter_id";
        $pdo->exec($sql);

        if (sizeof($cols) > 0) {
            $drops = array();
            foreach ($cols as $c) {
                $drops[] = " DROP COLUMN $c";
            }

            $sql = "ALTER TABLE $src " . implode(",", $drops);
            $pdo->exec($sql);
        }
    }
}

?>