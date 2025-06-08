<?php
// public_html/app/models/Post.php

class Post {
    private $conn;
    private $table_name = "posts";

    public $id;
    public $title;
    public $content;
    public $url;
    public $cover_image_url; // Noua proprietate pentru URL-ul imaginii de copertă
    public $user_id;
    public $community_id;
    public $type;
    public $created_at;
    public $upvotes;
    public $downvotes;

    // Proprietăți noi pentru a stoca informații legate de join-uri
    public $community_name;
    public $author_username; // Noua proprietate pentru numele de utilizator al autorului
    public $author_avatar_url; // Noua proprietate pentru avatarul autorului


    public function __construct($db) {
        $this->conn = $db;
    }

    // Metodă pentru a citi toate postările, cu detalii despre utilizator și comunitate
    public function readAll() {
        $query = "SELECT
                    p.id, p.title, p.content, p.url, p.cover_image_url, p.type, p.created_at, p.upvotes, p.downvotes,
                    p.user_id, p.community_id,
                    u.username as author_username, u.avatar_url as author_avatar_url,
                    c.name as community_name
                  FROM
                    " . $this->table_name . " p
                  LEFT JOIN
                    users u ON p.user_id = u.id
                  LEFT JOIN
                    communities c ON p.community_id = c.id
                  ORDER BY
                    p.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    // Metodă pentru a crea o postare nouă
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      title = :title,
                      content = :content,
                      url = :url,
                      cover_image_url = :cover_image_url,
                      user_id = :user_id,
                      community_id = :community_id,
                      type = :type";

        $stmt = $this->conn->prepare($query);

        // Curățare date
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->content = ($this->content === null) ? null : htmlspecialchars(strip_tags($this->content));
        $this->url = ($this->url === null) ? null : htmlspecialchars(strip_tags($this->url));
        $this->cover_image_url = ($this->cover_image_url === null) ? null : htmlspecialchars(strip_tags($this->cover_image_url));
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->community_id = htmlspecialchars(strip_tags($this->community_id));
        // NU mai aplicăm htmlspecialchars(strip_tags()) pentru $this->type, este o valoare internă
        // $this->type = htmlspecialchars(strip_tags($this->type));

        // --- DEBUGGING: Log the value of $this->type before binding ---
        error_log("Post model (create): Binding type value: '" . $this->type . "' for post '" . $this->title . "'");
        // --- End DEBUGGING ---

        // Legare parametri
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':content', $this->content);
        $stmt->bindParam(':url', $this->url);
        $stmt->bindParam(':cover_image_url', $this->cover_image_url);
        $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        $stmt->bindParam(':community_id', $this->community_id, PDO::PARAM_INT);
        $stmt->bindParam(':type', $this->type);

        if ($stmt->execute()) {
            return true;
        }
        error_log("Post model: Eroare la creare postare: " . implode(" ", $stmt->errorInfo()));
        return false;
    }

    // Metodă pentru a citi o singură postare
    public function readOne() {
        $query = "SELECT
                    p.id, p.title, p.content, p.url, p.cover_image_url, p.type, p.created_at, p.upvotes, p.downvotes,
                    p.user_id, p.community_id,
                    u.username as author_username, u.avatar_url as author_avatar_url,
                    c.name as community_name
                  FROM
                    " . $this->table_name . " p
                  LEFT JOIN
                    users u ON p.user_id = u.id
                  LEFT JOIN
                    communities c ON p.community_id = c.id
                  WHERE
                    p.id = :id
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // --- DEBUGGING: Log the fetched row from database ---
        error_log("Post model (readOne): Fetched row for ID " . $this->id . ": " . print_r($row, true));
        // --- End DEBUGGING ---

        if ($row) {
            $this->title = $row['title'];
            $this->content = $row['content'];
            $this->url = $row['url'];
            $this->cover_image_url = $row['cover_image_url'];
            $this->user_id = $row['user_id'];
            $this->community_id = $row['community_id'];
            $this->type = $row['type'];
            $this->created_at = $row['created_at'];
            $this->upvotes = $row['upvotes'];
            $this->downvotes = $row['downvotes'];
            $this->author_username = $row['author_username'];
            $this->author_avatar_url = $row['author_avatar_url'];
            $this->community_name = $row['community_name'];
            return true;
        }
        return false;
    }

    // Metodă pentru a actualiza voturile unei postări (doar upvotes și downvotes)
    public function updateVotes($post_id, $upvotes, $downvotes) {
        $query = "UPDATE " . $this->table_name . "
                  SET upvotes = :upvotes, downvotes = :downvotes
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':upvotes', $upvotes, PDO::PARAM_INT);
        $stmt->bindParam(':downvotes', $downvotes, PDO::PARAM_INT);
        $stmt->bindParam(':id', $post_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return true;
        }
        error_log("Post model: Eroare la actualizarea voturilor: " . implode(" ", $stmt->errorInfo()));
        return false;
    }

    // Metodă pentru a citi postările unui anumit utilizator
    public function readByUserId($user_id) {
        $query = "SELECT
                    p.id, p.title, p.content, p.url, p.cover_image_url, p.type, p.created_at, p.upvotes, p.downvotes,
                    p.user_id, p.community_id,
                    u.username as author_username, u.avatar_url as author_avatar_url,
                    c.name as community_name
                  FROM
                    " . $this->table_name . " p
                  LEFT JOIN
                    users u ON p.user_id = u.id
                  LEFT JOIN
                    communities c ON p.community_id = c.id
                  WHERE
                    p.user_id = :user_id
                  ORDER BY
                    p.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // Metodă pentru a citi postările dintr-o anumită comunitate
    public function readByCommunityId($community_id) {
        $query = "SELECT
                    p.id, p.title, p.content, p.url, p.cover_image_url, p.type, p.created_at, p.upvotes, p.downvotes,
                    p.user_id, p.community_id,
                    u.username as author_username, u.avatar_url as author_avatar_url,
                    c.name as community_name
                  FROM
                    " . $this->table_name . " p
                  LEFT JOIN
                    users u ON p.user_id = u.id
                  LEFT JOIN
                    communities c ON p.community_id = c.id
                  WHERE
                    p.community_id = :community_id
                  ORDER BY
                    p.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':community_id', $community_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // Metodă pentru a citi postările din comunitățile la care utilizatorul este abonat
    public function readBySubscribedCommunities($user_id) {
        $query = "SELECT
                    p.id, p.title, p.content, p.url, p.cover_image_url, p.type, p.created_at, p.upvotes, p.downvotes,
                    p.user_id, p.community_id,
                    u.username as author_username, u.avatar_url as author_avatar_url,
                    c.name as community_name
                  FROM
                    " . $this->table_name . " p
                  JOIN
                    user_community_subscriptions ucs ON p.community_id = ucs.community_id
                  LEFT JOIN
                    users u ON p.user_id = u.id
                  LEFT JOIN
                    communities c ON p.community_id = c.id
                  WHERE
                    ucs.user_id = :user_id
                  ORDER BY
                    p.created_at DESC";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("Post model readBySubscribedCommunities: Eroare la pregătirea interogării: " . implode(" ", $this->conn->errorInfo()));
            return false;
        }
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            error_log("Post model readBySubscribedCommunities: Eroare la execuția interogării: " . implode(" ", $stmt->errorInfo()));
            return false;
        }
        return $stmt;
    }
}
