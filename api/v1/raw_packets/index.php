<?php
require_once "../../../lib/meshlog.class.php";
require_once "../../../config.php";
include "../utils.php";

$meshlog = new MeshLog($config['db']);
$err = $meshlog->getError();

if ($err) {
    $results = array('error' => $err);  
} else {
    $results = $meshlog->getRawPackets(array(
        'offset' => 0, 
        'count' => DEFAULT_COUNT,
        'after_ms' => getParam('after_ms', 0),
        'before_ms' => getParam('before_ms', 0)
    ), true);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($results);

?>