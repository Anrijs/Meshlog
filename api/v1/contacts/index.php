<?php
require_once "../../../lib/meshlog.class.php";
require_once "../../../config.php";
include "../utils.php";

$meshlog = new MeshLog($config['db']);
$err = $meshlog->getError();

if ($err) {
    $results = array('error' => $err);  
} else {
    $maxage = date("Y-m-d H:i:s", time() - $meshlog->getConfig(MeshlogSetting::KEY_MAX_CONTACT_AGE));
    $results = $meshlog->getContacts(array(
        'offset' => 0, 
        'count' => DEFAULT_COUNT,
        'advertisements' => TRUE,
        'telemetry' => TRUE,
        'after_ms' => getParam('after_ms', 0),
        'before_ms' => getParam('before_ms', 0),
    ));
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($results);

?>