<?php
// public_html/public/register.php

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

    $user->username = trim($_POST['username'] ?? ''); // Curățăm spațiile albe
    $user->email = trim($_POST['email'] ?? '');     // Curățăm spațiile albe
    $user->password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validare
    if (empty($user->username) || empty($user->email) || empty($user->password) || empty($confirm_password)) {
        $message = ['type' => 'error', 'text' => 'Toate câmpurile sunt obligatorii.'];
    } elseif (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
        $message = ['type' => 'error', 'text' => 'Adresa de email este invalidă.'];
    } elseif ($user->password !== $confirm_password) {
        $message = ['type' => 'error', 'text' => 'Parolele nu se potrivesc.'];
    } elseif (strlen($user->password) < 6) {
        $message = ['type' => 'error', 'text' => 'Parola trebuie să aibă minim 6 caractere.'];
    } elseif ($user->usernameExists()) {
        $message = ['type' => 'error', 'text' => 'Acest nume de utilizator există deja.'];
    } elseif ($user->emailExists()) {
        $message = ['type' => 'error', 'text' => 'Această adresă de email este deja înregistrată.'];
    } else {
        // Încercăm să înregistrăm utilizatorul
        if ($user->register()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Înregistrare reușită! Acum te poți autentifica.'];
            header("Location: login.php");
            exit();
        } else {
            $message = ['type' => 'error', 'text' => 'A apărut o eroare la înregistrare. Te rugăm să încerci din nou.'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Înregistrare - Reddit Local</title>
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
                <h1>Înregistrare</h1>
            </div>

            <?php
            // Afișăm mesajele de succes sau eroare, dacă există
            if ($message) {
                $message_type = $message['type'];
                $message_text = $message['text'];
                echo "<div class='message {$message_type}'>{$message_text}</div>";
            }
            ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="username">Nume de utilizator:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Parolă:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmă parola:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit">Înregistrare</button>
            </form>
            <p class="link-text">Ai deja un cont? <a href="login.php">Autentifică-te aici</a>.</p>
        </div>
    </div>
</body>
</html>
