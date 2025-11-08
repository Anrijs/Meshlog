<?php

function makePdo() {
    include '../config.php';
    $host = $config['db']['host'] ?? die("Invalid db config: host");
    $name = $config['db']['database'] ?? die("Invalid db config: database");
    $user = $config['db']['user'] ?? die("Invalid db config: user");
    $pass = $config['db']['password'] ?? die("Invalid db config: password");

    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

function migrate($pdo, $src, $dst, $idcol) {
    $limit = 1000;
    $offset = 0;

    $bucket = array();
    $bucketout = array();
    $bucketrm = array();
    // hash => (first_time, first_id, flush, [list])

    while (true) {
        $MAX_TIME = 180; // 3 min from first.

        $stmt = $pdo->prepare("SELECT * FROM $src LIMIT :limit OFFSET :offset");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $offset += $limit;

        echo "----- $offset\n";

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Display results
        foreach ($rows as $row) {
            $hash = $row['hash'];
            if (!$hash) continue;  // unrecoverable

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
                    $time, // first created_at
                    $hash, // first hash
                    intval($id),   // first adv id
                    false, // flush
                    array($obj) // list of reports
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

        // TODO: seems like only first one is migrated

        // iterate and flush uncahnged
        if (sizeof($bucketout) > 0) {
            try {
                // Build the SQL dynamically
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

                echo "Inserted " . $stmt->rowCount() . " rows successfully!\n";
            } catch (PDOException $e) {
                echo "Error: " . $e->getMessage();
            }
        }
        $bucketout = [];

        $removed = 0;
        if (sizeof($bucketrm) > 0) {
            // Build placeholders dynamically
            $placeholders = rtrim(str_repeat('?,', count($bucketrm)), ',');

            $sql = "DELETE FROM $src WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bucketrm);

            $removed = $stmt->rowCount();
            $offset -= $removed;
            echo "Deleted " . $stmt->rowCount() . " rows.\n";
        }
        $bucketrm = [];

        if (sizeof($rows) < 1) break;
    }
}

function deleteCols($pdo, $src, $cols) {
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

// TODO: maybe add some flag and run async? Then client js runs checks

// DB Operations will take a while
set_time_limit(0); // 0 = unlimited
ini_set('max_execution_time', 0);

// run
$pdo = makePdo();

// advertisements
migrate($pdo, "advertisements", "advertisement_reports", "advertisement_id");
deleteCols($pdo, "advertisements", array('reporter_id', 'path', 'snr', 'received_at'));

// channels
migrate($pdo, "channel_messages", "channel_message_reports", "channel_message_id");
deleteCols($pdo, "channel_messages", array('reporter_id', 'path', 'received_at'));

// DMs
migrate($pdo, "direct_messages", "direct_message_reports", "direct_message_id");
deleteCols($pdo, "direct_messages", array('reporter_id', 'path', 'received_at'));


?>