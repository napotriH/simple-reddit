<?php
session_start();

// Verificăm dacă utilizatorul este autentificat
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Verificăm dacă formularul a fost trimis corect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    require_once __DIR__ . '/../core/Database.php';
    require_once __DIR__ . '/../app/models/Post.php';

    // Obținem ID-ul postării
    $post_id = $_POST['post_id'] ?? null;

    if (!$post_id || !is_numeric($post_id)) {
        die('Eroare: ID-ul postării este invalid.');
    }

    // Conectare la baza de date
    $database = new Database();
    $db = $database->getConnection();

    $post_model = new Post($db);
    $post_model->id = $post_id;

    // Verificăm dacă utilizatorul curent este autorul postării
    if ($post_model->isAuthor($_SESSION['user_id'])) {
        // Ștergem postarea
        if ($post_model->delete()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Postarea a fost ștearsă cu succes.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Eroare la ștergerea postării.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Nu aveți permisiunea de a șterge această postare.'];
    }

    header("Location: index.php");
    exit();
}
?>
