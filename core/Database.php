<?php
// public_html/core/Database.php

// Include fișierul de configurare a bazei de date
require_once __DIR__ . '/../config/db.php';

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    /**
     * Obține o conexiune la baza de date folosind PDO.
     * @return PDO|null Obiectul de conexiune PDO în caz de succes, sau null în caz de eșec.
     */
    public function getConnection() {
        $this->conn = null; // Resetează conexiunea la fiecare apel

        try {
            // Creează o nouă instanță PDO
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            // Setează setul de caractere la UTF-8 pentru a asigura suportul pentru caractere speciale
            $this->conn->exec("set names utf8mb4");
            // Setează modul de eroare PDO la excepții pentru o gestionare mai bună a erorilor
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // Prinde excepțiile PDO și loghează un mesaj de eroare
            // Nu afișa direct eroarea utilizatorului și nu opri scriptul brusc aici,
            // ci lasă funcția să returneze null, iar scriptul apelant va gestiona.
            error_log("Eroare de conexiune la baza de date: " . $exception->getMessage());
            return null; // Returnează null în caz de eșec al conexiunii
        }

        return $this->conn;
    }
}
