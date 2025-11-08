<?php
require_once "../../../lib/meshlog.class.php";
require_once "../../../config.php";
include "../utils.php";

$meshlog = new MeshLog($config['db']);
$err = $meshlog->getError();

if ($err) {
    $results = array('error' => $err);  
} else {
    $results = $meshlog->getDirectMessages(array(
        'offset' => 0, 
        'count' => DEFAULT_COUNT,
        'advertisements' => TRUE,
        'after_ms' => getParam('after_ms', 0),
        'before_ms' => getParam('before_ms', 0),
        'max_age' => $maxage,
    ), true);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($results, JSON_PRETTY_PRINT);

?>