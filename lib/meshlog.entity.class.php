<?php

class MeshLogEntity {
    protected static $table = null;
    private $meshlog = null;
    protected $_id = null;
    protected $error = '';

    function __construct($meshlog) {
        $this->meshlog = $meshlog;
    }
    public static function getTable() {
        return static::$table;
    }

    public static function findBy($field, $value, $meshlog, $extra=array(), $binary=False) {
        if (empty($value) || empty($field) || !$meshlog) return false;

        $tableStr = static::$table;
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;

        if ($type == PDO::PARAM_STR && $binary) {
            $conditions = ["BINARY $field = :$field"];
        } else {
            $conditions = ["$field = :$field"];
        }
        $params = [":$field" => [$value, $type]];

        foreach ($extra as $key => $condition) {
            // $condition should be an array: ['operator' => '>', 'value' => 1000]
            if (!isset($condition['operator'], $condition['value'])) {
                continue;
            }

            // Only allow safe operators
            $allowedOperators = ['=', '!=', '<', '<=', '>', '>=', 'LIKE'];
            if (!in_array($condition['operator'], $allowedOperators)) {
                continue;
            }

            $paramName = ":extra_$key";
            $conditions[] = "$key {$condition['operator']} $paramName";

            $paramType = is_int($condition['value']) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $params[$paramName] = [$condition['value'], $paramType];
        }

        $sql = "SELECT * FROM $tableStr WHERE " . implode(' AND ', $conditions) . " ORDER BY id DESC";
        $query = $meshlog->pdo->prepare($sql);
        foreach ($params as $param => [$val, $ptype]) {
            $query->bindValue($param, $val, $ptype);
        }

        $query->execute();

        $result = $query->fetch(PDO::FETCH_ASSOC);
        $contact = static::fromDb($result, $meshlog);
        return $contact;
    }

    public static function findById($id, $meshlog) {
        $tableStr = static::$table;
        $query = $meshlog->pdo->prepare("SELECT * FROM $tableStr WHERE id = :id ORDER BY id DESC");
        $query->bindParam(':id', $id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetch(PDO::FETCH_ASSOC);
        $contact = static::fromDb($result, $meshlog);
        return $contact;
    }

    public function getId() {
        return $this->_id;
    }

    public function isNew() {
        return $this->_id == null;
    }

    public function isValid() {
        return !empty($this::$table);
    }

    public function save($meshlog) {
        if (!$this->isValid()) return false;

        $data = $this->getParams();
        $cols = array_keys($data);

        $tableStr = $this::$table;

        $sql = "";
        $meshlog->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->isNew()) {
            $colsStr = implode(',', $cols);
            $paramsStr = ':' . implode(',:', $cols);
            $sql = "INSERT INTO $tableStr ($colsStr) VALUES ($paramsStr)";
        } else {
            $params = array();
            foreach ($cols as $c) {
                $params[] = "$c = :$c";
            }
            $paramsStr = implode(', ', $params);
            $sql = " UPDATE $tableStr SET $paramsStr WHERE id = :id";
        }

        $query = $meshlog->pdo->prepare($sql);
        if (!$this->isNew()) {
            $query->bindValue(":id", $this->getId(), PDO::PARAM_INT);
        }
        foreach ($cols as $c) {
            $param = ":$c";
            $value = $data[$c][0];
            $type = $data[$c][1];
            $query->bindValue($param, $value, $type);
        }

        $result = false;
        try {
            $result = $query->execute();
            if ($this->isNew()) {
                $this->_id = $meshlog->pdo->lastInsertId();
            }
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log($e->getMessage());
            return false;
        }

        if ($this->isNew() && $result) {
            $this->_id = $this->pdo->lastInsertId(); 
        }

        return $result;
    }

    public function delete() {
        $tableStr = $this::$table;
        $id = $this->getId();
        $stmt = $this->meshlog->pdo->prepare("DELETE FROM $tableStr WHERE id = :id");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }

    public function asArray($secret=false) {
        return array();
    }

    protected function getParams() {
        return false;
    }

public static function getAll($meshlog, $params) {
        $offset = $params['offset'] ?? 0;
        $count = $params['count'] ?? DEFAULT_COUNT;
        $after_ms = $params['after_ms'] ?? 0;
        $before_ms = $params['before_ms'] ?? 0;
        $secret = $params['secret'] ?? false;

        $where = $params['where'] ?? array();
        $sqlJoin = $params['join'] ?? '';
        $sqlWhere = '';
        $sqlBind = array();

        if ($after_ms > 0) {
            $after_ms = floor($after_ms / 1000);
            $sqlWhere = 'WHERE t.created_at > FROM_UNIXTIME(:after_ms) ';
        }
        if ($before_ms > 0) {
            $before_ms = floor($before_ms / 1000);
            if (strlen($sqlWhere)) {
                $sqlWhere .= " AND t.created_at < FROM_UNIXTIME(:before_ms)";
            } else {
                $sqlWhere = " WHERE t.created_at < FROM_UNIXTIME(:before_ms)";
            }
        }

        if (sizeof($where) >= 1) {
            if (strlen($sqlWhere)) {
                $sqlWhere .= " AND " . $where[0];
            } else {
                $sqlWhere = 'WHERE ' . $where[0];
            }
        }
        if (sizeof($where) >= 2) $sqlBind = $where[1];

        //error_log("SELECT * FROM $tableStr $sqlWhere ORDER BY id DESC LIMIT :offset,:count");
        // SELECT * FROM  created_at < FROM_UNIXTIME(:before_ms)  AND authorized = 1 ORDER BY id DESC LIMIT :offset,:count

        if ($count > MAX_COUNT) $count = MAX_COUNT;
        $tableStr = static::$table;
    
        $query = $meshlog->pdo->prepare("SELECT t.* FROM $tableStr t $sqlJoin $sqlWhere  ORDER BY t.id DESC LIMIT :offset,:count");
        
        foreach ($sqlBind as $b) {
            if (sizeof($b) != 3) continue;
            $query->bindParam($b[0], $b[1],  $b[2]);
        }
        $query->bindParam(':offset', $offset,  PDO::PARAM_INT);
        $query->bindParam(':count', $count,  PDO::PARAM_INT);
        if ($after_ms > 0) $query->bindParam(':after_ms', $after_ms,  PDO::PARAM_INT);
        if ($before_ms > 0) $query->bindParam(':before_ms', $before_ms,  PDO::PARAM_INT);
        $query->execute();
    
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        
        $objects = array();
        foreach ($result as $r) {
            $objects[] = static::fromDb($r, $meshlog)->asArray($secret);
        }
        return array(
            "objects" => $objects
        );
    }

    public static function countAll($meshlog) {
        $tableStr = static::$table;
        $stmt = $meshlog->pdo->query("SELECT COUNT(*) AS total FROM $tableStr");
        $row = $stmt->fetch();
        return $row['total'];
    }

    public function getError() {
        return $this->error;
    }
}

?>