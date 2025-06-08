<?php
// public_html/public/login.php

session_start();

// --- DEBUGGING: Activează afișarea erorilor PHP ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../php_errors.log');
// --- Sfârșit DEBUGGING ---

// Verificăm dacă utilizatorul este deja autentificat
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Includem fișierele necesare
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/models/User.php';

$message = null; // Variabilă pentru mesaje de eroare/succes

// Procesăm trimiterea formularului
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();

    $user = new User($db);

    $user->username = $_POST['username'] ?? '';
    $user->password = $_POST['password'] ?? '';

    if ($user->login()) {
        // Autentificare reușită, setăm variabilele de sesiune
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Autentificare reușită!'];
        header("Location: index.php");
        exit();
    } else {
        $message = ['type' => 'error', 'text' => 'Nume de utilizator sau parolă incorectă.'];
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autentificare - Reddit Local</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Bara de navigare globală (Navbar) -->
    <nav class="navbar">
        <a href="index.php" class="logo">Reddit Local</a>
        <div class="nav-links">
            <a href="login.php">Autentificare</a>
            <a href="register.php">Înregistrare</a>
        </div>
    </nav>

    <!-- Wrapper pentru conținutul principal -->
    <div class="main-content-wrapper">
        <div class="container">
            <!-- Antetul paginii (acum specific paginii, nu global) -->
            <div class="page-header">
                <h1>Autentificare</h1>
            </div>

            <?php
            // Afișăm mesajele de succes sau eroare, dacă există
            if ($message) {
                $message_type = $message['type'];
                $message_text = $message['text'];
                echo "<div class='message {$message_type}'>{$message_text}</div>";
            }
            ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="username">Nume de utilizator:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Parolă:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Autentificare</button>
            </form>
            <p class="link-text">Nu ai un cont? <a href="register.php">Înregistrează-te aici</a>.</p>
        </div>
    </div>
</body>
</html>
