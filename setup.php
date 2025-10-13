<?php
    include 'lib/meshlog.class.php';

    session_start();

    $user = null;
    if (isset($_SESSION['user'])) {
        $user = $_SESSION['user'];
    }

    function testConfig($servername, $dbname, $username, $password) {
        $pdo = null;
        try {
            $pdo = new PDO(
                "mysql:host=$servername;charset=utf8mb4",
                $username,
                $password,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $pdo = new PDO(
                "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        } catch (PDOException $e) {
            $pdo = null;
            return array("error" => "Connection failed: " . $e->getMessage(), "pdo" => null);
        }
        return array("error" => "", "pdo" => $pdo);
    }

    // TODO: do admin only setup
    $hasConfig = false;
    $hasAdmin = false;
    $hasMigrated = true;
    $pdo = null;
    $meshlog = null;
    $migrations = array();

    if (file_exists("config.php")) {
        require_once "config.php";

        if (isset($config['db']['host']) &&
            isset($config['db']['database']) &&
            isset($config['db']['user']) &&
            isset($config['db']['password'])
        ) {
            $result = testConfig(
                $config['db']['host'],
                $config['db']['database'],
                $config['db']['user'],
                $config['db']['password']);

            $pdo = $result['pdo'];
            if (!$pdo) {
                $errors[] = $result['error'] ?? "Unknown error (1)";
                exit;
            }
            
            $hasConfig = true;

            $current = -1;
            $table = MeshLogSetting::getTable();
            $stmt = $pdo->prepare("SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = :table");
            $stmt->execute(['table' => $table]);

            if ($stmt->fetchColumn() > 0) {
                // Table exists
                $meshlog = new MeshLog($config['db']);
                $current = $meshlog->getDbVersion();
            }


            // Check migrations.
            foreach (scandir("migrations") as $m) {
                if (str_ends_with($m, ".sql")) {
                    $parts = explode("_", $m);
                    $num = intval($parts[0]);
                    if ($num > $current) {
                        $migrations[$num] = $m;
                        $hasMigrated = false;
                    }
                }
            }
        }
    }

    if ($pdo && $meshlog) {
        $hasAdmin = MeshLogUser::countAll($meshlog) > 0;
    }

    // TOOD cehck admin
    if ($hasConfig && $hasAdmin && $hasMigrated) {
        echo "Already set up"; // TODO: reditect. // TODO: migrations
        exit;
    }

    if ($hasConfig && $hasAdmin && !$user) {
        header("Location: admin?setup");
        exit;
    }

    $def_admin_name = "admin";
    $def_db_host = "10.99.1.5";
    $def_db_name = "meshcore_dev";
    $def_db_user = "meshcore";

    $setup = isset($_POST['setup']);
    $errors = array();

    if ($setup) {
        header('Content-Type: application/json; charset=utf-8');

        // Error check + tets
        if (!$hasConfig) {
            $db_host = $_POST['db_host'] ?? $errors[] = "Mising database host"; 
            $db_name = $_POST['db_name'] ?? $errors[] = "Mising database name"; 
            $db_username = $_POST['db_username'] ?? $errors[] = "Mising database suername"; 
            $db_password = $_POST['db_password'] ?? $errors[] = "Mising database password"; 

            $result = testConfig($db_host, $db_name, $db_username, $db_password, true);
            $pdo = $result['pdo'];
            if (!$pdo) $errors[] = $result['error'] ?? "Unknown error (2)";
        } else if (!$hasMigrated) {
           // pass
        } else if (!$hasAdmin) {
            $admin_username = $_POST['admin_username'] ?? $errors[] = "Mising admin name"; 
            $admin_password = $_POST['admin_password'] ?? $errors[] = "Mising admin name"; 
            $admin_password_confirm = $_POST['admin_password_confirm'];

            if (strlen($admin_password) < 8) {
                $errors[] = "Admin password must be at least 8 characters long";
            }
            if ($admin_password != $admin_password_confirm) {
                $errors[] = "Admin user passwords does not match";
            }
        }

        if (sizeof($errors)) {
            echo json_encode(array('status' => "error", 'errors' => $errors), JSON_PRETTY_PRINT);
            exit;
        }

        // Create files and data
        if ($pdo && !$hasConfig) {
            // Create config file
            $src = array(
                "%DB_HOST%",
                "%DB_NAME%",
                "%DB_USER%",
                "%DB_PASSWORD%",
            );
            $dst = array(
                $db_host,
                $db_name,
                $db_username,
                $db_password,
            );

            $cfg = file_get_contents("config.example.php");
            $cfg = str_replace($src, $dst, $cfg);

            if (is_writable("config.php")) {
                file_put_contents("config.php", $cfg, LOCK_EX);
                clearstatcache("config.php");
                opcache_reset();
            } else {
                echo json_encode(array('status' => "config_write_error", 'raw' => $cfg), JSON_PRETTY_PRINT);
                exit;
            }
        } else if ($pdo && !$hasMigrated) {
            // Run migrations
            foreach ($migrations as $k => $v) {
                $sql = file_get_contents("migrations/" . $v);
                $statements = array_filter(array_map('trim', explode(";", $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        $pdo->exec($stmt);
                    }
                }
            }
        } else if ($pdo && !$hasAdmin) {
            $registered = MeshLoguser::register($meshlog, $admin_username, $admin_password, 0xFFFF);
            if (!$registered)  {
                echo json_encode(array('status' => "config_write_error", 'raw' => "cant"), JSON_PRETTY_PRINT);
                exit;
            }
        }

        echo json_encode(array('status' => "OK"), JSON_PRETTY_PRINT);
        exit;
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meshlog Setup</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div id="login">
        <section>
            <h1 class="form-title">Setup</h1>
            <div>
<?php if (!$hasConfig): ?>
                <!-- Database Config -->
                <h2 class="form-title">Database config</h2>
                <div class="form-group">
                    <label for="db_host">Host</label>
                    <input id="db_host" name="db_host" type="text" value="<?=$def_db_host?>">
                </div>
                <div class="form-group">
                    <label for="db_name">Database</label>
                    <input id="db_name" name="db_name" type="text" value="<?=$def_db_name?>">
                </div>
                <div class="form-group">
                    <label for="db_username">Username</label>
                    <input id="db_username" name="db_username" type="text" value="<?=$def_db_user?>">
                </div>
                <div class="form-group">
                    <label for="db_password">Password</label>
                    <input id="db_password" name="db_password" type="password">
                </div>
<?php elseif (!$hasMigrated): ?>
                Migrations pending:
                <?php print_r($migrations); ?>
<?php elseif (!$hasAdmin): ?>
                <!-- User -->
                <h2 class="form-title">Admin user</h2>
                <div class="form-group">
                    <label for="admin_username">Username</label>
                    <input id="admin_username" name="admin_username" type="text" value="<?=$def_admin_name?>">
                </div>
                <div class="form-group">
                    <label for="admin_password">Password</label>
                    <input id="admin_password" name="admin_password" type="password">
                </div>
                <div class="form-group">
                    <label for="admin_password_confirm">Confirm Password</label>
                    <input id="admin_password_confirm" name="admin_password_confirm" type="password">
                </div>
<?php endif ?>
                <div id="setup-error" class="form-group"></div>

                <div class="form-group right">
                    <input type="button" value="Setup" onclick="setup()">
                </div>
            </div>
        </section>
    </div>
    <script>
    function setup() {
        const data = {
<?php if (!$hasConfig): ?>
            db_host: document.getElementById("db_host").value,
            db_name: document.getElementById("db_name").value,
            db_username: document.getElementById("db_username").value,
            db_password: document.getElementById("db_password").value,
<?php elseif (!$hasMigrated): ?>
<?php elseif (!$hasAdmin): ?>
            admin_username: document.getElementById("admin_username").value,
            admin_password: document.getElementById("admin_password").value,
            admin_password_confirm: document.getElementById("admin_password_confirm").value,
<?php endif ?>
            setup: 1
        };

        let err = document.getElementById("setup-error");

            fetch(window.location.href, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams(data) // encodes as POST form data
            })
            .then(response => response.json()) // convert response to JSON
            .then(result => {
                err.innerHTML = '';
                if (result.status === "OK") {
                    setTimeout(() => {
                        window.location.reload(true);
                    }, 100);
                } else if (result.status === "config_write_error") {
                    let p = document.createElement("p");
                    p.innerHTML = "Unable to write <span class=\"code\">config.php</span> file. Create it manually:";
                    let pre = document.createElement("pre");
                    pre.classList.add("code");
                    pre.innerText = result.raw;

                    err.append(p);
                    err.append(pre);
                } else {
                    let errors = result.errors;
                    let ul = document.createElement("ul");
                    for (let i = 0; i < errors.length; i++) {
                        let li = document.createElement("li");
                        li.innerText = errors[i];
                        li.classList.add("error");
                        ul.append(li);
                    }
                    err.append(ul);
                }
            })
            .catch(error => {
                err.innerText = "Failed to connect.";
                console.error(error);
            });
        }
    </script>
</body>
</html>