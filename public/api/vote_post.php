<?php
// public_html/public/api/vote_post.php

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

// Includem fișierele necesare
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/models/Post.php';
require_once __DIR__ . '/../../app/models/Vote.php';

$data = json_decode(file_get_contents("php://input"), true);

$post_id = $data['post_id'] ?? null;
$vote_action = $data['vote_action'] ?? null; // 'upvote' sau 'downvote'

// Validare date
if (empty($post_id) || !is_numeric($post_id) || !in_array($vote_action, ['upvote', 'downvote'])) {
    error_log("Vote Post API: Date invalide. post_id: " . var_export($post_id, true) . ", vote_action: " . var_export($vote_action, true));
    echo json_encode(['success' => false, 'message' => 'Date invalide.']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Verifică dacă conexiunea la baza de date este validă
if ($db === null) {
    error_log("Vote Post API: Eroare de conexiune la baza de date.");
    echo json_encode(['success' => false, 'message' => 'Eroare la conectarea la baza de date.']);
    exit();
}

$post_model = new Post($db);
$vote_model = new Vote($db);

$current_user_id = $_SESSION['user_id'];
$vote_type = ($vote_action === 'upvote') ? 1 : -1;

$response = ['success' => false, 'message' => 'Eroare necunoscută.'];

try {
    // Obține postarea pentru a-i actualiza voturile
    $post_model->id = $post_id;
    if (!$post_model->readOne()) {
        echo json_encode(['success' => false, 'message' => 'Postarea nu a fost găsită.']);
        exit();
    }

    $current_upvotes = $post_model->upvotes;
    $current_downvotes = $post_model->downvotes;

    // Verifică votul anterior al utilizatorului
    $existing_vote = $vote_model->hasVoted($current_user_id, $post_id, null);

    if ($existing_vote) {
        if ($existing_vote['vote_type'] == $vote_type) {
            // Utilizatorul încearcă să voteze din nou cu același tip, anulează votul
            if ($vote_model->deleteVote($existing_vote['id'])) {
                if ($vote_type === 1) { $current_upvotes--; } else { $current_downvotes--; }
                $response['success'] = true;
                $response['message'] = 'Vot anulat.';
            } else {
                $response['message'] = 'Eroare la anularea votului.';
            }
        } else {
            // Utilizatorul schimbă tipul votului
            if ($vote_model->updateVote($existing_vote['id'], $vote_type)) { // Aici se apelează updateVote
                if ($vote_type === 1) { // Schimbă de la downvote la upvote
                    $current_upvotes++;
                    $current_downvotes--;
                } else { // Schimbă de la upvote la downvote
                    $current_upvotes--;
                    $current_downvotes++;
                }
                $response['success'] = true;
                $response['message'] = 'Vot schimbat.';
            } else {
                $response['message'] = 'Eroare la schimbarea votului.';
            }
        }
    } else {
        // Utilizatorul votează pentru prima dată
        if ($vote_model->addVote($current_user_id, $post_id, null, $vote_type)) { // Aici se apelează addVote
            if ($vote_type === 1) { $current_upvotes++; } else { $current_downvotes++; }
            $response['success'] = true;
            $response['message'] = 'Vot înregistrat.';
        } else {
            $response['message'] = 'Eroare la înregistrarea votului.';
        }
    }

    // Actualizează voturile postării în baza de date DOAR DACĂ operațiunea de vot în sine a fost reușită
    if ($response['success']) {
        if ($post_model->updateVotes($post_id, $current_upvotes, $current_downvotes)) {
            // Re-citește postarea pentru a obține scorul actualizat și a-l returna
            $post_model->readOne();
            $response['new_score'] = $post_model->upvotes - $post_model->downvotes;
            $response['user_vote_status'] = $vote_model->hasVoted($current_user_id, $post_id, null)['vote_type'] ?? 0;
        } else {
            // Dacă actualizarea voturilor în postare eșuează, setează succesul la false
            $response['success'] = false;
            $response['message'] = 'Eroare la actualizarea scorului postării.';
        }
    }

} catch (Exception $e) {
    $response['message'] = 'Eroare la procesarea votului: ' . $e->getMessage();
    error_log('Vote Post API error: ' . $e->getMessage());
}

echo json_encode($response);
