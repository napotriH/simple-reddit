<?php
// public_html/public/api/vote_comment.php

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
require_once __DIR__ . '/../../app/models/Comment.php';
require_once __DIR__ . '/../../app/models/Vote.php';

$data = json_decode(file_get_contents("php://input"), true);

$comment_id = $data['comment_id'] ?? null;
$vote_action = $data['vote_action'] ?? null; // 'upvote' sau 'downvote'

// Validare date
if (empty($comment_id) || !is_numeric($comment_id) || !in_array($vote_action, ['upvote', 'downvote'])) {
    error_log("Vote Comment API: Date invalide. comment_id: " . var_export($comment_id, true) . ", vote_action: " . var_export($vote_action, true));
    echo json_encode(['success' => false, 'message' => 'Date invalide.']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Verifică dacă conexiunea la baza de date este validă
if ($db === null) {
    error_log("Vote Comment API: Eroare de conexiune la baza de date.");
    echo json_encode(['success' => false, 'message' => 'Eroare la conectarea la baza de date.']);
    exit();
}

$comment_model = new Comment($db);
$vote_model = new Vote($db);

$current_user_id = $_SESSION['user_id'];
$vote_type = ($vote_action === 'upvote') ? 1 : -1;

$response = ['success' => false, 'message' => 'Eroare necunoscută.'];

try {
    // Obține comentariul pentru a-i actualiza voturile
    $comment_model->id = $comment_id;
    if (!$comment_model->readOne()) {
        echo json_encode(['success' => false, 'message' => 'Comentariul nu a fost găsit.']);
        exit();
    }

    $current_upvotes = $comment_model->upvotes;
    $current_downvotes = $comment_model->downvotes;

    // Verifică votul anterior al utilizatorului pentru acest comentariu
    $existing_vote = $vote_model->hasVoted($current_user_id, null, $comment_id);

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
        if ($vote_model->addVote($current_user_id, null, $comment_id, $vote_type)) { // Aici se apelează addVote
            if ($vote_type === 1) { $current_upvotes++; } else { $current_downvotes++; }
            $response['success'] = true;
            $response['message'] = 'Vot înregistrat.';
        } else {
            $response['message'] = 'Eroare la înregistrarea votului.';
        }
    }

    // Actualizează voturile comentariului în baza de date DOAR DACĂ operațiunea de vot în sine a fost reușită
    if ($response['success']) {
        if ($comment_model->updateVotes($comment_id, $current_upvotes, $current_downvotes)) {
            // Re-citește comentariul pentru a obține scorul actualizat și a-l returna
            $comment_model->readOne();
            $response['new_score'] = $comment_model->upvotes - $comment_model->downvotes;
            $response['user_vote_status'] = $vote_model->hasVoted($current_user_id, null, $comment_id)['vote_type'] ?? 0;
        } else {
            // Dacă actualizarea voturilor în comentariu eșuează, setează succesul la false
            $response['success'] = false;
            $response['message'] = 'Eroare la actualizarea scorului comentariului.';
        }
    }

} catch (Exception $e) {
    $response['message'] = 'Eroare la procesarea votului: ' . $e->getMessage();
    error_log('Vote Comment API error: ' . $e->getMessage());
}

echo json_encode($response);
