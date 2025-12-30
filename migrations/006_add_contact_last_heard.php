<?php

class Migration_006 extends Migration {
    public function __construct() {
        parent::__construct(5, 6);
    }

    function migrate($pdo) {
        // add last_heard_at col
        $pdo->exec("ALTER TABLE contacts ADD COLUMN IF NOT EXISTS last_heard_at TIMESTAMP NOT NULL DEFAULT current_timestamp() AFTER enabled;");

        // Set last_heard_at to created_at
        $pdo->exec("UPDATE contacts SET last_heard_at = created_at;");

        // Set last_heard_at to latest adv or message created_at
        $pdo->exec("UPDATE contacts c
            LEFT JOIN (
                SELECT contact_id, MAX(created_at) AS last_heard_at
                FROM (
                    SELECT contact_id, created_at FROM advertisements
                    UNION ALL
                    SELECT contact_id, created_at FROM channel_messages
                    UNION ALL
                    SELECT contact_id, created_at FROM direct_messages
                ) t
                GROUP BY contact_id
            ) x ON x.contact_id = c.id
            SET c.last_heard_at = x.last_heard_at
            WHERE contact_id;
        ");

        return array(
            'success' => true,
            'message' => ''
        );
    }
}

?>