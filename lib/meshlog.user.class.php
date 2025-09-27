<?php

class MeshLogUser extends MeshLogEntity {
    protected static $table = "users";

    public $name = null;
    public $permissions = null;

    public static function register($meshlog, $user, $password, $permissions) {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        $tableStr = static::$table;
        $query = $meshlog->pdo->prepare("INSERT INTO $tableStr (name, password_hash, permissions) VALUES (:name, :pass, :perm)");
        $query->bindParam(':name', $user, PDO::PARAM_STR);
        $query->bindParam(':pass', $password_hashed, PDO::PARAM_STR);
        $query->bindParam(':perm', $permissions, PDO::PARAM_INT);

        try {
            $result = $query->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
        return true;
    }

    public static function login($meshlog, $user, $password) {
        $tableStr = static::$table;
        $query = $meshlog->pdo->prepare("SELECT * FROM $tableStr WHERE name=:name");
        $query->bindParam(':name', $user, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if (!$result) return false;
        if (!password_verify($password, $result['password_hash'])) return false;
        
        $user = new MeshLogUser($meshlog);
        $user->_id = $result['id'];
        $user->name = $result['name'];
        $user->permissions = $result['permissions'];

        return $user;
    }
}

?>