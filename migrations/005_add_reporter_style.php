<?php

class Migration_005 extends Migration {
    public function __construct() {
        parent::__construct(4, 5);
    }

    function migrate($pdo) {
        // add style col
        $pdo->exec("ALTER TABLE reporters ADD COLUMN IF NOT EXISTS style VARCHAR(500) NOT NULL DEFAULT '{}' AFTER color;");

        // Copy color to style field
        $limit = 1000;
        $offset = 0;

        while (true) {
            $stmt = $pdo->prepare("SELECT * FROM reporters LIMIT :limit OFFSET :offset");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $offset += $limit;

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (sizeof($rows) < 1) break;

            foreach ($rows as $row) {
                $id = $row['id'];
                $style = json_encode(array(
                    "color" => $row['color']
                ));

                $upd = $pdo->prepare("UPDATE reporters SET style=:style WHERE id=:id");
                $upd->bindParam(':style', $style, PDO::PARAM_STR);
                $upd->bindParam(':id', $id, PDO::PARAM_INT);
                $upd->execute();
            }
        }

        // todo: drop style column

        return array(
            'success' => true,
            'message' => ''
        );
    }
}

?>