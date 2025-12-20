<?php
    require_once __DIR__ . '/../loggedin.php';

    $errors = array();
    $results = array('status' => 'unknown');

    if (isset($_POST['add']) || isset($_POST['edit'])) {
        $reporter = new MeshLogReporter($meshlog);
        if (isset($_POST['edit'])) {
            $id = $_POST['id'] ?? $errors[] = 'Missing id';
            $reporter = MeshLogReporter::findById($id, $meshlog);
        }

        $reporter->name = $_POST['name'] ?? $errors[] = 'Missing name';
        $reporter->public_key = $_POST['public_key'] ?? $errors[] = 'Missing public key';
        $reporter->lat = $_POST['lat'] ?? 0;
        $reporter->lon = $_POST['lon'] ?? 0;
        $reporter->auth = $_POST['auth'] ?? $errors[] = 'Missing ayth key';
        $reporter->authorized = $_POST['authorized'] ?? true;
        $reporter->style = $_POST['style'] ?? $errors[] = 'Missing style';

        if (!sizeof($errors)) {
            // save
            if ($reporter->save($meshlog)) {
                $results = array(
                    'status' => 'OK',
                    'reported' => $reporter->asArray()
                );
            } else {
                $errors[] = 'Failed to save: ' . $reporter->getError();
            }
        }
    } else if (isset($_POST['delete'])) {
        $id = $_POST['id'] ?? $errors[] = 'Missing id';
        $reporter = MeshLogReporter::findById($id, $meshlog);
        if ($reporter && $reporter->delete()) {
            $results = array('status' => 'OK');
        } else{
            $errors[] = 'Failed to delete';
        }
    } else {
        $results = MeshLogReporter::getAll($meshlog, array('secret' => true, 'order' => 'ASC'));
    }

    if (sizeof($errors)) {
        $results = array(
            'status' => 'error',
            'error' => implode("\n", $errors)
        );
    }

    echo json_encode($results, JSON_PRETTY_PRINT);
