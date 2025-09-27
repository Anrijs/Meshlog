<?php
    session_start();
    header('Content-Type: application/json; charset=utf-8');

    if (isset($_SESSION['user'])) {
        $user = $_SESSION['user'];
    } else {
        header("HTTP/1.1 401 Unauthorized");
        echo json_encode(array('error' => 'unauthoried'), JSON_PRETTY_PRINT);
        exit;
    }

    require_once __DIR__ . '/../../lib/meshlog.class.php';
    require_once __DIR__ . '/../../config.php';

    $meshlog = new MeshLog($config['db']);
