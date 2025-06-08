<?php
// public_html/app/models/Subscription.php

class Subscription {
    private $conn;
    // Corectăm numele tabelului conform schemei MySQL
    private $table_name = "user_community_subscriptions";

    public $id; // Această proprietate este mai puțin relevantă pentru acest tabel fără coloană 'id'
    public $user_id;
    public $community_id;
    public $subscribed_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Abonează un utilizator la o comunitate
    public function subscribe() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      user_id = :user_id,
                      community_id = :community_id";

        $stmt = $this->conn->prepare($query);

        // Curățare date și asigurarea tipului INT
        $this->user_id = intval(htmlspecialchars(strip_tags($this->user_id)));
        $this->community_id = intval(htmlspecialchars(strip_tags($this->community_id)));

        // Legare parametri
        $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        $stmt->bindParam(':community_id', $this->community_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }

        error_log("Subscription model: Eroare la abonare: " . implode(" ", $stmt->errorInfo()));
        return false;
    }

    // Dezabonează un utilizator de la o comunitate
    public function unsubscribe() {
        $query = "DELETE FROM " . $this->table_name . " WHERE user_id = :user_id AND community_id = :community_id";

        $stmt = $this->conn->prepare($query);

        // Curățare date și asigurarea tipului INT
        $this->user_id = intval(htmlspecialchars(strip_tags($this->user_id)));
        $this->community_id = intval(htmlspecialchars(strip_tags($this->community_id)));

        // Legare parametri
        $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        $stmt->bindParam(':community_id', $this->community_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }

        error_log("Subscription model: Eroare dezabonare: " . implode(" ", $stmt->errorInfo()));
        return false;
    }

    // Verifică dacă un utilizator este abonat la o anumită comunitate
    public function isSubscribed() {
        // Corectăm coloana selectată, deoarece 'user_community_subscriptions' nu are o coloană 'id'
        $query = "SELECT user_id
                  FROM " . $this->table_name . "
                  WHERE user_id = :user_id AND community_id = :community_id
                  LIMIT 1"; // Limităm la 1 pentru eficiență

        $stmt = $this->conn->prepare($query);

        // Legare parametri (aici user_id și community_id ar trebui să fie deja setate ca intval() din contextul apelant)
        // Eliminăm htmlspecialchars/strip_tags/intval aici, deoarece valorile $this->user_id și $this->community_id
        // ar trebui să fie deja sanitizate și convertite la int în momentul în care sunt setate pe obiectul Subscription.
        $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        $stmt->bindParam(':community_id', $this->community_id, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            error_log("Subscription model isSubscribed: Eroare la execuția interogării: " . implode(" ", $stmt->errorInfo()));
            return false;
        }

        $num = $stmt->rowCount();

        return $num > 0;
    }

    // Obține numărul de abonați pentru o comunitate
    public function getSubscriberCount($community_id) {
        $query = "SELECT COUNT(*) as total_subscribers
                  FROM " . $this->table_name . "
                  WHERE community_id = :community_id";

        $stmt = $this->conn->prepare($query);
        $community_id_int = intval($community_id); // Conversie explicită
        $stmt->bindParam(':community_id', $community_id_int, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total_subscribers'];
    }

    // Obține comunitățile la care este abonat un utilizator
    public function getSubscribedCommunitiesByUserId($user_id) {
        $query = "SELECT c.id, c.name, c.description, c.created_at
                  FROM " . $this->table_name . " cs
                  JOIN communities c ON cs.community_id = c.id
                  WHERE cs.user_id = :user_id
                  ORDER BY c.name ASC";

        $stmt = $this->conn->prepare($query);
        $user_id_int = intval($user_id); // Conversie explicită
        $stmt->bindParam(':user_id', $user_id_int, PDO::PARAM_INT);
        
        if (!$stmt->execute()) {
             error_log("Subscription model getSubscribedCommunitiesByUserId: Eroare la execuția interogării: " . implode(" ", $stmt->errorInfo()));
             return false; // Returnează false în caz de eroare
        }

        return $stmt; // Returnează obiectul PDOStatement
    }
}
