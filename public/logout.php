<?php
// public_html/public/logout.php

// Pornim sesiunea
session_start();

// Distrugem toate variabilele de sesiune
$_SESSION = array();

// Dacă se folosește un cookie de sesiune, îl ștergem.
// Notă: Aceasta va distruge sesiunea, nu doar datele sesiunii!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// În final, distrugem sesiunea.
session_destroy();

// Redirecționăm utilizatorul către pagina de login
header("Location: login.php");
exit();
?>
