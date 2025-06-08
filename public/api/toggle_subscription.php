<?php
// public_html/public/api/toggle_subscription.php

// --- DEBUGGING: Dezactivează afișarea erorilor PHP pentru API-uri și loghează-le în schimb ---
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../php_errors.log'); // Calea corectă către fișierul de log
// --- Sfârșit DEBUGGING ---

session_start();

header('Content-Type: application/json');

// Verifică dacă utilizatorul este autentificat
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilizator neautentificat.']);
    exit();
}

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/models/Subscription.php';

$data = json_decode(file_get_contents("php://input"), true);

$community_id = $data['community_id'] ?? null;
$action = $data['action'] ?? null; // 'subscribe' sau 'unsubscribe'

// Validare date
if (empty($community_id) || !is_numeric($community_id) || !in_array($action, ['subscribe', 'unsubscribe'])) {
    error_log("Toggle Subscription API: Date invalide. community_id: " . var_export($community_id, true) . ", action: " . var_export($action, true));
    echo json_encode(['success' => false, 'message' => 'Date invalide.']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$subscription = new Subscription($db);
$subscription->user_id = $_SESSION['user_id'];
$subscription->community_id = $community_id;

$response = ['success' => false, 'message' => ''];

try {
    if ($action === 'subscribe') {
        if ($subscription->isSubscribed()) {
            $response['success'] = true;
            $response['message'] = 'Ești deja abonat la această comunitate.';
        } elseif ($subscription->subscribe()) {
            $response['success'] = true;
            $response['message'] = 'Abonare reușită!';
        } else {
            $response['message'] = 'Eroare la abonare.';
        }
    } elseif ($action === 'unsubscribe') {
        if (!$subscription->isSubscribed()) {
            $response['success'] = true;
            $response['message'] = 'Nu ești abonat la această comunitate.';
        } elseif ($subscription->unsubscribe()) {
            $response['success'] = true;
            $response['message'] = 'Dezabonare reușită!';
        } else {
            $response['message'] = 'Eroare la dezabonare.';
        }
    }
} catch (Exception $e) {
    $response['message'] = 'Eroare la procesarea abonamentului: ' . $e->getMessage();
    error_log('Toggle Subscription API error: ' . $e->getMessage());
}

echo json_encode($response);
