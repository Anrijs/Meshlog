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

    $params = array(
        'offset' => getParam('offset', 0),
        'count' => getParam('count', DEFAULT_COUNT),
        'after_ms' => getParam('after_ms', 0),
        'before_ms' => getParam('before_ms', 0),
    );

    $paramsContacts = array(
        'offset' => getParam('offset', 0),
        'count' => getParam('count', DEFAULT_COUNT),
        'after_ms' => getParam('after_ms', 0),
        'before_ms' => getParam('before_ms', 0),
        'advertisements' => TRUE,
        'telemetry' => TRUE,
        'max_age' => $maxage
    );

    $reporters = $meshlog->getReporters($params);
    $contacts = $meshlog->getContacts($paramsContacts);
    $advertisements = $meshlog->getAdvertisements($params, true);
    $channels = $meshlog->getChannels($params);
    $direct_messages = $meshlog->getDirectMessages($params, true);
    $channel_messages = $meshlog->getChannelMessages($params, true);

    $results = array(
        'reporters' => $reporters,
        'contacts' => $contacts,
        'advertisements' => $advertisements,
        'channels' => $channels,
        'direct_messages' => $direct_messages,
        'channel_messages' => $channel_messages
    );
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($results, JSON_PRETTY_PRINT);

?>