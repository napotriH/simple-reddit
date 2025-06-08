<?php
// public_html/app/models/Comment.php

class Comment {
    private $conn;
    private $table_name = "comments";

    public $id;
    public $post_id;
    public $user_id;
    public $parent_comment_id; // Poate fi NULL
    public $content;
    public $created_at;
    public $upvotes;
    public $downvotes;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      post_id = :post_id,
                      user_id = :user_id,
                      parent_comment_id = :parent_comment_id,
                      content = :content";

        $stmt = $this->conn->prepare($query);

        // Curățare date pentru securitate
        $this->post_id = htmlspecialchars(strip_tags($this->post_id));
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->content = htmlspecialchars(strip_tags($this->content));

        // Legare parametri
        $stmt->bindParam(':post_id', $this->post_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        $stmt->bindParam(':content', $this->content);

        // Gestionarea corectă a parent_comment_id
        // Dacă parent_comment_id este gol (null, '', 0), îl legăm ca NULL
        if (empty($this->parent_comment_id)) {
            $stmt->bindValue(':parent_comment_id', null, PDO::PARAM_NULL);
        } else {
            // Altfel, îl legăm ca un număr întreg
            $stmt->bindParam(':parent_comment_id', $this->parent_comment_id, PDO::PARAM_INT);
        }

        if ($stmt->execute()) {
            return true;
        }

        // Înregistrarea erorii în log
        error_log("Comment model: Eroare la creare comentariu: " . implode(" ", $stmt->errorInfo()));
        return false;
    }

    // Metodă pentru a citi comentariile unei postări
    public function readByPostId($post_id) {
        $query = "SELECT
                    c.id, c.content, c.created_at, c.upvotes, c.downvotes,
                    u.username as author_username, u.avatar_url as author_avatar_url,
                    c.parent_comment_id, c.user_id -- Adăugat c.user_id aici
                  FROM
                    " . $this->table_name . " c
                  JOIN
                    users u ON c.user_id = u.id
                  WHERE
                    c.post_id = :post_id
                  ORDER BY
                    c.created_at ASC"; // Ordine cronologică pentru comentarii

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // Metodă pentru a citi un singur comentariu după ID (nouă metodă pentru API)
    public function readOne() {
        $query = "SELECT
                    id, post_id, user_id, parent_comment_id, content, created_at, upvotes, downvotes
                  FROM
                    " . $this->table_name . "
                  WHERE
                    id = ?
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = $row['id'];
            $this->post_id = $row['post_id'];
            $this->user_id = $row['user_id'];
            $this->parent_comment_id = $row['parent_comment_id'];
            $this->content = $row['content'];
            $this->created_at = $row['created_at'];
            $this->upvotes = $row['upvotes'];
            $this->downvotes = $row['downvotes'];
            return true;
        }
        return false;
    }

    // Metodă pentru a citi comentariile unui utilizator
    public function readByUserId($user_id) {
        $query = "SELECT
                    c.id, c.content, c.created_at, c.upvotes, c.downvotes,
                    p.title as post_title, p.id as post_id,
                    comm.name as community_name, comm.id as community_id
                  FROM
                    " . $this->table_name . " c
                  JOIN
                    posts p ON c.post_id = p.id
                  JOIN
                    communities comm ON p.community_id = comm.id
                  WHERE
                    c.user_id = :user_id
                  ORDER BY
                    c.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // NOUĂ Metodă pentru a actualiza numărul de upvotes și downvotes (valori absolute)
    public function updateVotes($comment_id, $upvotes, $downvotes) {
        $query = "UPDATE " . $this->table_name . "
                  SET upvotes = :upvotes, downvotes = :downvotes
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':upvotes', $upvotes, PDO::PARAM_INT);
        $stmt->bindParam(':downvotes', $downvotes, PDO::PARAM_INT);
        $stmt->bindParam(':id', $comment_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return true;
        }
        error_log("Comment model: Eroare la actualizarea voturilor pentru comentariu: " . implode(" ", $stmt->errorInfo()));
        return false;
    }
}
