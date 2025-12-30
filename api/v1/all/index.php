<?php
$start = microtime(true);
require_once "../../../lib/meshlog.class.php";
require_once "../../../config.php";
include "../utils.php";

$meshlog = new MeshLog($config['db']);
$err = $meshlog->getError();

if ($err) {
    $results = array('error' => $err);
} else {
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
    );

    $reporters = $meshlog->getReporters($params);
    $contacts = $meshlog->getContactsQuick($paramsContacts);
    $advertisements = $meshlog->getAdvertisementsQuick($params);
    $channels = $meshlog->getChannels($params);
    $direct_messages = $meshlog->getDirectMessagesQuick($params);
    $channel_messages = $meshlog->getChannelMessagesQuick($params);

    $results = array(
        'reporters' => $reporters,
        'contacts' => $contacts,
        'advertisements' => $advertisements,
        'channels' => $channels,
        'direct_messages' => $direct_messages,
        'channel_messages' => $channel_messages
    );
}

$results['time'] = microtime(true) - $start;

header('Content-Type: application/json; charset=utf-8');
echo json_encode($results);

?>