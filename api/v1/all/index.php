<?php
require_once "../../../lib/meshlog.class.php";
require_once "../../../config.php";
include "../utils.php";

$meshlog = new MeshLog($config['db']);

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
    'advertisements' => TRUE
);

$reporters = $meshlog->getReporters($params);
$contacts = $meshlog->getContacts($paramsContacts);
$advertisements = $meshlog->getAdvertisements($params);
$channels = $meshlog->getChannels($params);
$direct_messages = $meshlog->getDirectMessages($params);
$channel_messages = $meshlog->getChannelMessages($params);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
    'reporters' => $reporters,
    'contacts' => $contacts,
    'advertisements' => $advertisements,
    'channels' => $channels,
    'direct_messages' => $direct_messages,
    'channel_messages' => $channel_messages
), JSON_PRETTY_PRINT);

?>