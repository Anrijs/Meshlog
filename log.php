<?php
require_once "config.php";
include "lib/meshlog.class.php";

$json_params = file_get_contents("php://input");
$json = json_decode($json_params, true);

$systime = floor(microtime(true) * 1000);
$json["time"]["server"] = $systime;

$meshlog = new MeshLog($config['db']);
$response = $meshlog->insert($json);

if (is_array($response) && array_key_exists("error", $response)) {
    echo $response["error"];
}

?>