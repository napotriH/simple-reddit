<?php
// public_html/app/models/Community.php

require_once __DIR__ . '/../../core/Database.php';

class Community {
    private $conn;
    private $table_name = "communities";

    // Proprietăți ale obiectului
    public $id;
    public $name;
    public $description;
    public $created_by_user_id; // Observație: s-a folosit created_by_user_id pentru a corespunde bazei de date
    public $created_at;

    // Constructor cu $db ca și conexiune la baza de date
    public function __construct($db) {
        $this->conn = $db;
    }

    // Metodă pentru a citi toate comunitățile
    public function readAll() {
        $query = "SELECT
                    id, name, description, created_by_user_id, created_at
                  FROM
                    " . $this->table_name . "
                  ORDER BY
                    created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    // Metodă pentru a crea o nouă comunitate
    public function create() {
        // Query de inserare
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                    name = :name,
                    description = :description,
                    created_by_user_id = :created_by_user_id";

        // Pregătirea interogării
        $stmt = $this->conn->prepare($query);

        // Curățarea datelor (evitarea injecțiilor SQL)
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->created_by_user_id = htmlspecialchars(strip_tags($this->created_by_user_id));

        // Legarea valorilor
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":created_by_user_id", $this->created_by_user_id, PDO::PARAM_INT);

        // Executarea interogării
        if ($stmt->execute()) {
            return true;
        }

        // Afișează eroare dacă inserarea eșuează
        error_log("Community model: Eroare la crearea comunității: " . implode(" ", $stmt->errorInfo()));
        return false;
    }

    // Metodă pentru a citi o singură comunitate după ID
    public function readOne() {
        $query = "SELECT
                    id, name, description, created_by_user_id, created_at
                  FROM
                    " . $this->table_name . "
                  WHERE
                    id = :id
                  LIMIT 0,1"; // Limită la un singur rezultat

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Setează proprietățile obiectului
        if ($row) {
            $this->name = $row['name'];
            $this->description = $row['description'];
            $this->created_by_user_id = $row['created_by_user_id'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }
    
    // Metodă pentru a verifica dacă o comunitate există după nume
    public function exists($community_name) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE name = :name LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $community_name);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
    }

    // Metodă pentru a obține ID-ul unei comunități după nume
    public function getIdByName($community_name) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE name = :name LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $community_name);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['id'] : null;
    }

    // Aici pot fi adăugate și metode pentru update și delete mai târziu, dacă este necesar
}
