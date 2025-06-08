<?php
// public_html/app/models/Vote.php

class Vote {
    private $conn;
    private $table_name = "votes";

    public $id;
    public $user_id;
    public $post_id;     // Poate fi NULL
    public $comment_id;  // Poate fi NULL
    public $vote_type;   // 1 pentru upvote, -1 pentru downvote
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Adaugă un vot nou
    public function addVote($user_id, $post_id = null, $comment_id = null, $vote_type) {
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      user_id = :user_id,
                      post_id = :post_id,
                      comment_id = :comment_id,
                      vote_type = :vote_type";

        $stmt = $this->conn->prepare($query);

        // Curățare date
        $user_id = htmlspecialchars(strip_tags($user_id));
        $vote_type = htmlspecialchars(strip_tags($vote_type));

        // Legare parametri
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':vote_type', $vote_type, PDO::PARAM_INT);

        if ($post_id !== null) {
            $post_id = htmlspecialchars(strip_tags($post_id));
            $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':post_id', null, PDO::PARAM_NULL);
        }

        if ($comment_id !== null) {
            $comment_id = htmlspecialchars(strip_tags($comment_id));
            $stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':comment_id', null, PDO::PARAM_NULL);
        }

        if ($stmt->execute()) {
            return true;
        }

        error_log("Vote model: Eroare la adăugare vot: " . implode(" ", $stmt->errorInfo()));
        return false;
    }

    // Actualizează un vot existent (schimbă tipul votului)
    public function updateVote($vote_id, $new_vote_type) {
        $query = "UPDATE " . $this->table_name . "
                  SET vote_type = :vote_type
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Curățare date
        $new_vote_type = htmlspecialchars(strip_tags($new_vote_type));
        $vote_id = htmlspecialchars(strip_tags($vote_id));

        // Legare parametri
        $stmt->bindParam(':vote_type', $new_vote_type, PDO::PARAM_INT);
        $stmt->bindParam(':id', $vote_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }

        error_log("Vote model: Eroare la actualizare vot: " . implode(" ", $stmt->errorInfo()));
        return false;
    }

    // Șterge un vot
    public function deleteVote($vote_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Curățare date
        $vote_id = htmlspecialchars(strip_tags($vote_id));

        // Legare parametru
        $stmt->bindParam(':id', $vote_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }

        error_log("Vote model: Eroare la ștergere vot: " . implode(" ", $stmt->errorInfo()));
        return false;
    }

    // Verifică dacă un utilizator a votat deja o postare sau un comentariu
    public function hasVoted($user_id, $post_id = null, $comment_id = null) {
        $query = "SELECT id, vote_type
                  FROM " . $this->table_name . "
                  WHERE user_id = :user_id";

        if ($post_id !== null) {
            $query .= " AND post_id = :post_id";
        } elseif ($comment_id !== null) {
            $query .= " AND comment_id = :comment_id";
        } else {
            return false; // Nu se poate verifica fără post_id sau comment_id
        }

        $query .= " LIMIT 0,1";

        $stmt = $this->conn->prepare($query);

        // Curățare date
        $user_id = htmlspecialchars(strip_tags($user_id));

        // Legare parametri
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        if ($post_id !== null) {
            $post_id = htmlspecialchars(strip_tags($post_id));
            $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        } elseif ($comment_id !== null) {
            $comment_id = htmlspecialchars(strip_tags($comment_id));
            $stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row : false; // Returnează ID-ul votului și tipul dacă există, altfel false
    }
}
