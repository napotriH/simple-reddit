<?php
// public_html/app/models/User.php

class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $username;
    public $email;
    public $password; // Aceasta va fi parola hashuită din BD sau parola simplă introdusă de user
    public $created_at;
    public $avatar_url;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Creare utilizator nou
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      username = :username,
                      email = :email,
                      password_hash = :password_hash"; // Corectat la password_hash

        $stmt = $this->conn->prepare($query);

        // Curățare date
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        // Parola hashuită este deja preparată în controller/scriptul de înregistrare

        // Legare parametri
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password_hash', $this->password); // Aici $this->password este deja hashuită

        if ($stmt->execute()) {
            return true;
        }

        error_log("User model: Eroare la creare utilizator: " . implode(" ", $stmt->errorInfo()));
        return false;
    }

    // Citeste un singur utilizator după ID
    public function readOne() {
        $query = "SELECT
                    id, username, email, created_at, avatar_url
                  FROM
                    " . $this->table_name . "
                  WHERE
                    id = ?
                  LIMIT
                    0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->created_at = $row['created_at'];
            $this->avatar_url = $row['avatar_url'];
            return true;
        }
        return false;
    }

    // Citeste un singur utilizator după username
    public function readByUsername() {
        $query = "SELECT
                    id, username, email, password_hash, created_at, avatar_url
                  FROM
                    " . $this->table_name . "
                  WHERE
                    username = ?
                  LIMIT
                    0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->username);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->password = $row['password_hash']; // Aici $this->password va stoca hash-ul
            $this->created_at = $row['created_at'];
            $this->avatar_url = $row['avatar_url'];
            return true;
        }
        return false;
    }

    // Citeste un singur utilizator după email
    public function readByEmail() {
        $query = "SELECT
                    id, username, email, password_hash, created_at, avatar_url
                  FROM
                    " . $this->table_name . "
                  WHERE
                    email = ?
                  LIMIT
                    0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->email);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->password = $row['password_hash']; // Aici $this->password va stoca hash-ul
            $this->created_at = $row['created_at'];
            $this->avatar_url = $row['avatar_url'];
            return true;
        }
        return false;
    }

    // Verifică dacă utilizatorul există după email
    public function emailExists() {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $this->email = htmlspecialchars(strip_tags($this->email));
        $stmt->bindParam(1, $this->email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Verifică dacă utilizatorul există după username
    public function usernameExists() {
        $query = "SELECT id FROM " . $this->table_name . " WHERE username = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $this->username = htmlspecialchars(strip_tags($this->username));
        $stmt->bindParam(1, $this->username);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Metoda de login
    public function login() {
        // Caută utilizatorul după username
        $query = "SELECT
                    id, username, email, password_hash, avatar_url
                  FROM
                    " . $this->table_name . "
                  WHERE
                    username = :username
                  LIMIT
                    0,1";

        $stmt = $this->conn->prepare($query);

        // Curăță username-ul
        $this->username = htmlspecialchars(strip_tags($this->username));
        $stmt->bindParam(':username', $this->username);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && password_verify($this->password, $row['password_hash'])) {
            // Autentificare reușită, setează proprietățile obiectului
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->avatar_url = $row['avatar_url'];
            return true;
        }

        return false; // Autentificare eșuată
    }

    // Actualizează doar email-ul
    public function updateEmail() {
        $query = "UPDATE " . $this->table_name . "
                  SET email = :email
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $this->email = htmlspecialchars(strip_tags($this->email));
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':id', $this->id);
        if ($stmt->execute()) {
            return true;
        }
        error_log("User model: Eroare la actualizarea email-ului: " . implode(" ", $stmt->errorInfo()));
        return false;
    }

    // Actualizează doar parola
    public function updatePassword() {
        $query = "UPDATE " . $this->table_name . "
                  SET password_hash = :password_hash
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $this->password = htmlspecialchars(strip_tags($this->password)); // Parola deja hashuită
        $stmt->bindParam(':password_hash', $this->password);
        $stmt->bindParam(':id', $this->id);
        if ($stmt->execute()) {
            return true;
        }
        error_log("User model: Eroare la actualizarea parolei: " . implode(" ", $stmt->errorInfo()));
        return false;
    }

    // Actualizează doar avatar_url
    public function updateAvatarUrl() {
        $query = "UPDATE " . $this->table_name . "
                  SET avatar_url = :avatar_url
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $this->avatar_url = htmlspecialchars(strip_tags($this->avatar_url));
        $stmt->bindParam(':avatar_url', $this->avatar_url);
        $stmt->bindParam(':id', $this->id);
        if ($stmt->execute()) {
            return true;
        }
        error_log("User model: Eroare la actualizarea avatar_url: " . implode(" ", $stmt->errorInfo()));
        return false;
    }
}
