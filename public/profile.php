<?php
// public_html/public/profile.php

session_start();

// --- DEBUGGING: Activează afișarea erorilor PHP ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../php_errors.log');
// --- Sfârșit DEBUGGING ---

// Verificăm dacă utilizatorul este autentificat
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Funcție ajutătoare pentru a afișa timpul în format "acum X minute/ore/zile"
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'an',
        'm' => 'lună',
        'w' => 'săptămână',
        'd' => 'zi',
        'h' => 'oră',
        'i' => 'minut',
        's' => 'secundă',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 'e' : '');
            if ($k == 'y' && $diff->$k > 1) $v = $diff->$k . ' ani';
            if ($k == 'm' && $diff->$k > 1) $v = $diff->$k . ' luni';
            if ($k == 'w' && $diff->$k > 1) $v = $diff->$k . ' săptămâni';
            if ($k == 'd' && $diff->$k > 1) $v = $diff->$k . ' zile';
            if ($k == 'h' && $diff->$k > 1) $v = $diff->$k . ' ore';
            if ($k == 'i' && $diff->$k > 1) $v = $diff->$k . ' minute';
            if ($k == 's' && $diff->$k > 1) $v = $diff->$k . ' secunde';
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' în urmă' : 'chiar acum';
}

// Funcție pentru trunchierea textului (utilizată pentru previzualizări)
function truncate_text($text, $limit) {
    // Folosim mb_strlen și mb_substr pentru a lucra corect cu caracterele multi-byte (UTF-8)
    if (mb_strlen($text) > $limit) {
        // Caută ultimul spațiu înainte de limită pentru a nu tăia un cuvânt
        $breakpoint = mb_strpos($text, ' ', $limit);
        if ($breakpoint === false) { // Nu există spațiu după limită, tăiem pur și simplu
            return mb_substr($text, 0, $limit) . '...';
        }
        return mb_substr($text, 0, $breakpoint) . '...';
    }
    return $text;
}


// Includem fișierele necesare conform structurii existente a proiectului
// Adăugăm verificări file_exists pentru a depista probleme de cale
$databasePath = __DIR__ . '/../core/Database.php';
$userModelPath = __DIR__ . '/../app/models/User.php';
$postModelPath = __DIR__ . '/../app/models/Post.php';
$commentModelPath = __DIR__ . '/../app/models/Comment.php';
$voteModelPath = __DIR__ . '/../app/models/Vote.php';

if (!file_exists($databasePath)) {
    die('Eroare critică: Fișierul Database.php nu a fost găsit la calea: ' . htmlspecialchars($databasePath));
}
require_once $databasePath;

if (!file_exists($userModelPath)) {
    die('Eroare critică: Fișierul User.php nu a fost găsit la calea: ' . htmlspecialchars($userModelPath));
}
require_once $userModelPath;

if (!file_exists($postModelPath)) {
    die('Eroare critică: Fișierul Post.php nu a fost găsit la calea: ' . htmlspecialchars($postModelPath));
}
require_once $postModelPath;

if (!file_exists($commentModelPath)) {
    die('Eroare critică: Fișierul Comment.php nu a fost găsit la calea: ' . htmlspecialchars($commentModelPath));
}
require_once $commentModelPath;

if (!file_exists($voteModelPath)) {
    die('Eroare critică: Fișierul Vote.php nu a fost găsit la calea: ' . htmlspecialchars($voteModelPath));
}
require_once $voteModelPath;


// Obținem o conexiune la baza de date
$database = new Database();
$db = $database->getConnection();

// Verifică dacă conexiunea la baza de date este validă
if ($db === null) {
    die('Eroare: Conexiunea la baza de date a eșuat. Verifică configurația bazei de date (db.php) și credențialele.');
}

// Creăm instanțe ale modelelor
$user_model = new User($db);
$post_model = new Post($db);
$comment_model = new Comment($db);
$vote_model = new Vote($db);

$target_user_id = isset($_GET['id']) ? $_GET['id'] : $_SESSION['user_id'];

// Validăm că ID-ul utilizatorului este un număr întreg
if (!is_numeric($target_user_id)) {
    die('Eroare: ID-ul utilizatorului este invalid.');
}

$user_model->id = $target_user_id;

// Încercăm să citim detaliile utilizatorului țintă
if (!$user_model->readOne()) {
    die('Eroare: Utilizatorul nu a fost găsit.');
}

$message = ''; // Inițializăm variabila pentru mesaje
$message_type = ''; // Inițializăm tipul mesajului

// Procesează solicitarea de actualizare a profilului (dacă este utilizatorul curent)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $target_user_id == $_SESSION['user_id']) {
    $new_email = trim($_POST['email'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $profile_updated = false;

    // Validează și actualizează email-ul
    if (!empty($new_email) && $new_email !== $user_model->email) {
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Adresa de email nu este validă.';
            $message_type = 'error';
        } else {
            // Verifică dacă noul email există deja la alt utilizator
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $stmt->bindParam(':email', $new_email);
            $stmt->bindParam(':id', $target_user_id);
            $stmt->execute();
            if ($stmt->fetch()) {
                $message = 'Această adresă de email este deja utilizată de un alt cont.';
                $message_type = 'error';
            } else {
                $user_model->email = $new_email;
                if ($user_model->updateEmail()) {
                    $_SESSION['email'] = $new_email; // Actualizează sesiunea
                    $message .= 'Email actualizat cu succes!';
                    $message_type = 'success';
                    $profile_updated = true;
                } else {
                    $message .= ' Eroare la actualizarea email-ului.';
                    $message_type = 'error';
                }
            }
        }
    }


    // Procesează actualizarea parolei, dacă este cazul
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $message .= ($message ? '<br>' : '') . 'Parola trebuie să aibă minim 6 caractere.';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message .= ($message ? '<br>' : '') . 'Parolele nu se potrivesc.';
            $message_type = 'error';
        } else {
            $user_model->password = password_hash($new_password, PASSWORD_DEFAULT);
            if ($user_model->updatePassword()) {
                $message .= ($message ? '<br>' : '') . 'Parola a fost actualizată!';
                if ($message_type !== 'error') {
                    $message_type = 'success';
                }
                $profile_updated = true;
            } else {
                $message .= ($message ? '<br>' : '') . ' Eroare la actualizarea parolei.';
                $message_type = 'error';
            }
        }
    }

    // Procesează încărcarea avatarului
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/avatars/'; // Calea relativă la public_html/ (web root)
        $absolute_upload_dir = __DIR__ . '/../' . $upload_dir; // Calea absolută pentru mkdir și unlink

        if (!is_dir($absolute_upload_dir)) {
            mkdir($absolute_upload_dir, 0777, true); // Creează directorul dacă nu există
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml']; // Adăugat SVG
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
            $message .= ($message ? '<br>' : '') . 'Doar imagini JPG, PNG, GIF sau SVG sunt permise pentru avatar.';
            $message_type = 'error';
        } elseif ($_FILES['avatar']['size'] > $max_size) {
            $message .= ($message ? '<br>' : '') . 'Avatarul este prea mare (max 2MB).';
            $message_type = 'error';
        } else {
            $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $new_file_name = uniqid('avatar_') . '.' . $file_extension;
            // Calea relativă la web root, pe care o vom stoca în baza de date
            $destination_path_for_db = $upload_dir . $new_file_name;
            // Calea absolută pentru a muta fișierul pe server
            $destination_path_absolute = $absolute_upload_dir . $new_file_name;


            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination_path_absolute)) {
                // Șterge vechiul avatar dacă există și nu este cel implicit
                if ($user_model->avatar_url && file_exists(__DIR__ . '/../' . $user_model->avatar_url) && $user_model->avatar_url !== 'uploads/avatars/default_avatar.svg') { // Calea implicită corectată
                    unlink(__DIR__ . '/../' . $user_model->avatar_url);
                }

                // Actualizează calea avatarului în baza de date
                $user_model->avatar_url = $destination_path_for_db;
                if ($user_model->updateAvatarUrl()) {
                    $message .= ($message ? '<br>' : '') . 'Avatarul a fost actualizat!';
                    if ($message_type !== 'error') {
                        $message_type = 'success';
                    }
                    $profile_updated = true;
                } else {
                    $message .= ($message ? '<br>' : '') . ' Eroare la actualizarea avatarului în baza de date.';
                    $message_type = 'error';
                }
            } else {
                $message .= ($message ? '<br>' : '') . ' Eroare la încărcarea fișierului avatar.';
                $message_type = 'error';
            }
        }
    }

    // Re-citește informațiile utilizatorului după actualizare pentru a afișa cele mai recente date
    $user_model->readOne();

    // Setează mesajul în sesiune pentru a-l afișa după redirect
    $_SESSION['message'] = ['type' => $message_type, 'text' => $message];
    header("Location: profile.php?id=" . $target_user_id);
    exit();
}

// Re-preia informațiile actuale ale utilizatorului pentru afișare (după un posibil redirect sau la prima încărcare)
$current_username = htmlspecialchars($user_model->username);
$current_email = htmlspecialchars($user_model->email);
$member_since = date('d.m.Y', strtotime($user_model->created_at));

// Calea de bază pentru a ajunge la directorul root al proiectului de la public/
$base_path_from_public_to_root = '../';

// Determină calea corectă a avatarului pentru afișare în HTML
if ($user_model->avatar_url) {
    // Dacă utilizatorul are un avatar personalizat, construiește calea relativă
    // Calea din BD (user_model->avatar_url) este relativă la public_html/
    $avatar_src = htmlspecialchars($base_path_from_public_to_root . $user_model->avatar_url);
} else {
    // Altfel, folosește avatarul implicit SVG (de asemenea relativ la public_html/)
    $avatar_src = htmlspecialchars($base_path_from_public_to_root . 'uploads/avatars/default_avatar.svg');
}

// Preluăm postările create de utilizatorul țintă
$posts_stmt = $post_model->readByUserId($target_user_id);
$user_posts = [];
while ($row = $posts_stmt->fetch(PDO::FETCH_ASSOC)) {
    $user_vote_status = $vote_model->hasVoted($_SESSION['user_id'], $row['id'], null);
    $row['user_vote_status'] = $user_vote_status ? $user_vote_status['vote_type'] : 0;
    
    // Preluăm calea imaginii de copertă
    $row['cover_image_src'] = $row['cover_image_url'] ? htmlspecialchars($base_path_from_public_to_root . $row['cover_image_url']) : '';

    // Trunchierea conținutului pentru postările de tip text
    if ($row['type'] === 'text' && !empty($row['content'])) {
        $row['full_content'] = $row['content']; // Păstrează conținutul complet
        $row['content_truncated'] = truncate_text($row['content'], 300); // Trunchiază la 300 de caractere
        $row['truncated'] = (mb_strlen($row['full_content']) > mb_strlen($row['content_truncated'])); // Verifică dacă a fost trunchiat
    } else {
        $row['truncated'] = false;
    }

    $user_posts[] = $row;
}

// Preluăm comentariile create de utilizatorul țintă
$comments_stmt = $comment_model->readByUserId($target_user_id);
$user_comments = [];
while ($row = $comments_stmt->fetch(PDO::FETCH_ASSOC)) {
    $user_vote_status = $vote_model->hasVoted($_SESSION['user_id'], null, $row['id']);
    $row['user_vote_status'] = $user_vote_status ? $user_vote_status['vote_type'] : 0;
    
    // Trunchierea conținutului comentariilor
    if (!empty($row['content'])) {
        $row['full_content'] = $row['content']; // Păstrează conținutul complet
        $row['content_truncated'] = truncate_text($row['content'], 150); // Trunchiază la 150 de caractere pentru comentarii
        $row['truncated'] = (mb_strlen($row['full_content']) > mb_strlen($row['content_truncated'])); // Verifică dacă a fost trunchiat
    } else {
        $row['truncated'] = false;
    }

    $user_comments[] = $row;
}

// Preluăm mesajele de sesiune (după redirecționare)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message']['text'];
    $message_type = $_SESSION['message']['type'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilul lui u/<?php echo htmlspecialchars($user_model->username); ?> - Reddit Local</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Bara de navigare globală (Navbar) -->
    <nav class="navbar">
        <a href="index.php" class="logo">Reddit Local</a>
        <button class="menu-toggle" aria-label="Toggle navigation menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="nav-menu">
            <div class="nav-links">
                <span>Salut, <a href="profile.php" class="profile-link"><?php echo htmlspecialchars($_SESSION['username']); ?></a>!</span>
                <a href="communities.php">Comunități</a>
                <a href="create_post.php" class="create-post-button-nav">Creează o postare</a>
                <a href="logout.php">Deconectare</a>
            </div>
        </div>
    </nav>

    <!-- Wrapper pentru conținutul principal -->
    <div class="main-content-wrapper">
        <div class="container profile-container">
            <div class="profile-header">
                <h1>Profilul lui u/<?php echo htmlspecialchars($user_model->username); ?></h1>
                <div class="profile-info">
                    <img src="<?php echo $avatar_src; ?>" alt="Avatar utilizator" class="profile-avatar-display" onerror="this.onerror=null; this.src='../uploads/avatars/default_avatar.svg'; alert('Eroare la încărcarea avatarului. Se folosește avatarul implicit.');">
                    <h2>@<?php echo htmlspecialchars($user_model->username); ?></h2>
                    <p>Membru din: <?php echo date("d.m.Y", strtotime($user_model->created_at)); ?></p>
                    <?php if ($target_user_id == $_SESSION['user_id']): // Afișăm email-ul doar dacă este profilul propriu ?>
                        <p>Email: <?php echo htmlspecialchars($user_model->email); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($target_user_id == $_SESSION['user_id']): // Afișăm butonul de editare doar pentru utilizatorul curent ?>
                    <button id="editProfileBtn" class="edit-profile-btn">Editează Profilul</button>
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($target_user_id == $_SESSION['user_id']): // Formularul de editare doar pentru utilizatorul curent ?>
                <div id="profileEditForm" class="profile-edit-form hidden">
                    <h3>Editează informațiile profilului</h3>
                    <form action="profile.php?id=<?php echo htmlspecialchars($target_user_id); ?>" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="username_display">Nume utilizator:</label>
                            <input type="text" id="username_display" value="<?php echo htmlspecialchars($user_model->username); ?>" disabled>
                            <p class="form-help-text">Numele de utilizator nu poate fi schimbat momentan.</p>
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_model->email); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Parolă nouă (lasă gol dacă nu vrei să o schimbi):</label>
                            <input type="password" id="password" name="password">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmă parola nouă:</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>
                        <div class="form-group">
                            <label for="avatar">Avatar:</label>
                            <input type="file" id="avatar" name="avatar" accept="image/jpeg, image/png, image/gif, image/svg+xml">
                            <p class="form-help-text">Max 2MB, formate: JPG, PNG, GIF, SVG.</p>
                        </div>
                        <button type="submit" name="update_profile">Salvează Modificările</button>
                        <button type="button" id="cancelEditBtn" class="cancel-btn">Anulează</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="profile-tabs">
                <button class="tab-button active" data-tab="posts">Postările lui <?php echo htmlspecialchars($user_model->username); ?></button>
                <button class="tab-button" data-tab="comments">Comentariile lui <?php echo htmlspecialchars($user_model->username); ?></button>
            </div>

            <div id="posts" class="tab-content active">
                <h2>Postările lui <?php echo htmlspecialchars($user_model->username); ?></h2>
                <div class="post-list">
                    <?php if (!empty($user_posts)): ?>
                        <?php foreach ($user_posts as $p): ?>
                            <div class="post-card" data-post-id="<?php echo htmlspecialchars($p['id']); ?>">
                                <div class="vote-section">
                                    <span class="arrow up <?php echo ($p['user_vote_status'] == 1) ? 'voted' : ''; ?>" data-vote-type="upvote">&#9650;</span>
                                    <span class="score"><?php echo $p['upvotes'] - $p['downvotes']; ?></span>
                                    <span class="arrow down <?php echo ($p['user_vote_status'] == -1) ? 'voted' : ''; ?>" data-vote-type="downvote">&#9660;</span>
                                </div>
                                <div class="post-content">
                                    <h3 class="post-title">
                                        <a href="post_detail.php?id=<?php echo htmlspecialchars($p['id']); ?>">
                                            <?php echo htmlspecialchars($p['title']); ?>
                                        </a>
                                    </h3>
                                    <p class="post-meta">
                                        în <a href="community_detail.php?id=<?php echo htmlspecialchars($p['community_id'] ?? ''); ?>">r/<?php echo htmlspecialchars($p['community_name'] ?? 'Comunitate necunoscută'); ?></a> acum <?php echo time_elapsed_string($p['created_at']); ?>
                                    </p>
                                    <?php if (!empty($p['cover_image_src'])): ?>
                                        <div class="post-thumbnail">
                                            <a href="post_detail.php?id=<?php echo htmlspecialchars($p['id']); ?>">
                                                <img src="<?php echo $p['cover_image_src']; ?>" alt="Imagine copertă" onerror="this.onerror=null;this.src='https://placehold.co/150x150/e0e0e0/555555?text=Fără+Imagine';" />
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($p['type'] === 'text'): ?>
                                        <p class="post-body">
                                            <?php echo nl2br(htmlspecialchars($p['content_truncated'])); ?>
                                            <?php if ($p['truncated']): ?>
                                                <a href="post_detail.php?id=<?php echo htmlspecialchars($p['id']); ?>" class="read-more">Citește mai mult...</a>
                                            <?php endif; ?>
                                        </p>
                                    <?php elseif ($p['type'] === 'link'): ?>
                                        <p class="post-body">
                                            <a href="<?php echo htmlspecialchars($p['url']); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo htmlspecialchars($p['url']); ?> <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-posts">Acest utilizator nu a postat încă nimic.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="comments" class="tab-content">
                <h2>Comentariile lui <?php echo htmlspecialchars($user_model->username); ?></h2>
                <div class="comment-list">
                    <?php if (!empty($user_comments)): ?>
                        <?php foreach ($user_comments as $c): ?>
                            <div class="comment-item">
                                <div class="comment-meta">
                                    <span class="score"><?php echo $c['upvotes'] - $c['downvotes']; ?></span>
                                    punct(e) la
                                    <a href="post_detail.php?id=<?php echo htmlspecialchars($c['post_id']); ?>#comment-<?php echo htmlspecialchars($c['id']); ?>">
                                        comentariul tău
                                    </a> în
                                    r/<?php echo htmlspecialchars($c['community_name']); ?> acum <?php echo time_elapsed_string($c['created_at']); ?>:
                                </div>
                                <div class="comment-content">
                                    <p class="comment-display-content" data-full-content="<?php echo htmlspecialchars($c['full_content']); ?>">
                                        <span class="comment-text-content"><?php echo nl2br(htmlspecialchars($c['content_truncated'])); ?></span>
                                        <?php if ($c['truncated']): ?>
                                            <a href="#" class="read-more-comment">Citește mai mult...</a>
                                            <a href="#" class="read-less-comment" style="display: none;">Citește mai puțin</a>
                                        <?php endif; ?>
                                    </p>
                                    <div class="vote-section comment-vote-section" data-comment-id="<?php echo htmlspecialchars($c['id']); ?>">
                                        <span class="arrow up <?php echo ($c['user_vote_status'] == 1) ? 'voted' : ''; ?>" data-vote-type="upvote">&#9650;</span>
                                        <span class="arrow down <?php echo ($c['user_vote_status'] == -1) ? 'voted' : ''; ?>" data-vote-type="downvote">&#9660;</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-comments">Acest utilizator nu a comentat încă nimic.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Helper function for nl2br in JavaScript
            function nl2br(str) {
                return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
            }

            // Helper function for truncate_text in JavaScript (simplified for client-side use)
            function truncateTextJS(text, limit) {
                if (text.length > limit) {
                    let breakpoint = text.lastIndexOf(' ', limit);
                    if (breakpoint === -1 || breakpoint < (limit / 2)) { // Avoid cutting too early if no space found near limit
                         return text.substring(0, limit) + '...';
                    }
                    return text.substring(0, breakpoint) + '...';
                }
                return text;
            }

            // Logica pentru schimbarea tab-urilor
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Elimină clasa 'active' de la toate butoanele și conținuturile
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Adaugă clasa 'active' la butonul și conținutul corespunzător
                    button.classList.add('active');
                    document.getElementById(button.dataset.tab).classList.add('active');
                });
            });

            // JavaScript pentru voturile pe postare (similar cu index.php și community_detail.php)
            const postVoteSections = document.querySelectorAll('#posts .post-card .vote-section');

            postVoteSections.forEach(section => {
                const upArrow = section.querySelector('.arrow.up');
                const downArrow = section.querySelector('.arrow.down');
                const scoreSpan = section.querySelector('.score');
                const postId = section.closest('.post-card').dataset.postId;

                upArrow.addEventListener('click', () => handlePostVote(postId, 'upvote', upArrow, downArrow, scoreSpan));
                downArrow.addEventListener('click', () => handlePostVote(postId, 'downvote', upArrow, downArrow, scoreSpan));
            });

            async function handlePostVote(postId, voteAction, upArrow, downArrow, scoreSpan) {
                try {
                    const response = await fetch('api/vote_post.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            post_id: postId,
                            vote_action: voteAction
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        scoreSpan.textContent = data.new_score;

                        upArrow.classList.remove('voted');
                        downArrow.classList.remove('voted');

                        if (data.user_vote_status === 1) {
                            upArrow.classList.add('voted');
                        } else if (data.user_vote_status === -1) {
                            downArrow.classList.add('voted');
                        }
                    } else {
                        console.error('Eroare la votare postare:', data.message);
                        alert('Eroare la votare postare: ' + data.message);
                    }
                } catch (error) {
                    console.error('Eroare rețea la votare postare:', error);
                    alert('A apărut o eroare de rețea. Te rugăm să încerci din nou.');
                }
            }


            // JavaScript pentru voturile pe comentarii (similar cu post_detail.php)
            const commentVoteSections = document.querySelectorAll('#comments .comment-vote-section');

            commentVoteSections.forEach(section => {
                const upArrow = section.querySelector('.arrow.up');
                const downArrow = section.querySelector('.arrow.down');
                // Scor pentru comentariu este în afara secțiunii de vot, în .comment-meta
                const commentItem = section.closest('.comment-item');
                const scoreSpan = commentItem.querySelector('.comment-meta .score');
                const commentId = section.dataset.commentId;

                upArrow.addEventListener('click', () => handleCommentVote(commentId, 'upvote', upArrow, downArrow, scoreSpan));
                downArrow.addEventListener('click', () => handleCommentVote(commentId, 'downvote', upArrow, downArrow, scoreSpan));
            });

            async function handleCommentVote(commentId, voteAction, upArrow, downArrow, scoreSpan) {
                try {
                    const response = await fetch('api/vote_comment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            comment_id: commentId,
                            vote_action: voteAction
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        scoreSpan.textContent = data.new_score;

                        upArrow.classList.remove('voted');
                        downArrow.classList.remove('voted');

                        if (data.user_vote_status === 1) {
                            upArrow.classList.add('voted');
                        } else if (data.user_vote_status === -1) {
                            downArrow.classList.add('voted');
                        }
                    } else {
                        console.error('Eroare la votare comentariu:', data.message);
                        alert('Eroare la votare comentariu: ' + data.message);
                    }
                } catch (error) {
                    console.error('Eroare rețea la votare comentariu:', error);
                    alert('A apărut o eroare de rețea. Te rugăm să încerci din nou.');
                }
            }

            // JavaScript pentru meniul hamburger
            const menuToggle = document.querySelector('.menu-toggle');
            const navMenu = document.querySelector('.nav-menu');

            if (menuToggle && navMenu) {
                menuToggle.addEventListener('click', () => {
                    navMenu.classList.toggle('active');
                    menuToggle.classList.toggle('active');
                });

                document.addEventListener('click', (event) => {
                    if (!navMenu.contains(event.target) && !menuToggle.contains(event.target) && navMenu.classList.contains('active')) {
                        navMenu.classList.remove('active');
                        menuToggle.classList.remove('active');
                    }
                });

                navMenu.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', () => {
                        navMenu.classList.remove('active');
                        menuToggle.classList.remove('active');
                    });
                });
            }

            // Logica pentru afisarea/ascunderea formularului de editare a profilului
            const editProfileBtn = document.getElementById('editProfileBtn');
            const profileEditForm = document.getElementById('profileEditForm');
            const cancelEditBtn = document.getElementById('cancelEditBtn');

            if (editProfileBtn && profileEditForm && cancelEditBtn) {
                editProfileBtn.addEventListener('click', function() {
                    profileEditForm.classList.remove('hidden');
                    editProfileBtn.style.display = 'none'; // Ascunde butonul de editare
                });

                cancelEditBtn.addEventListener('click', function() {
                    profileEditForm.classList.add('hidden');
                    editProfileBtn.style.display = 'block'; // Afișează butonul de editare
                });
            }

            // Logica pentru extinderea/restrângerea comentariilor în profil
            document.querySelectorAll('.comment-display-content').forEach(commentContentElement => {
                const commentTextSpan = commentContentElement.querySelector('.comment-text-content');
                const readMoreButton = commentContentElement.querySelector('.read-more-comment');
                const readLessButton = commentContentElement.querySelector('.read-less-comment');
                const fullContent = commentContentElement.dataset.fullContent;
                const truncatedContent = truncateTextJS(fullContent, 150);

                // Ensure initial state is truncated if needed
                if (commentContentElement.dataset.truncated === 'true' && commentTextSpan) { // Check if PHP marked it as truncated
                    commentTextSpan.innerHTML = nl2br(truncatedContent);
                    if (readMoreButton) readMoreButton.style.display = 'inline';
                    if (readLessButton) readLessButton.style.display = 'none';
                }

                if (readMoreButton) {
                    readMoreButton.addEventListener('click', function(event) {
                        event.preventDefault();
                        if (commentTextSpan) {
                            commentTextSpan.innerHTML = nl2br(fullContent);
                        }
                        this.style.display = 'none';
                        if (readLessButton) {
                            readLessButton.style.display = 'inline';
                        }
                    });
                }

                if (readLessButton) {
                    readLessButton.addEventListener('click', function(event) {
                        event.preventDefault();
                        if (commentTextSpan) {
                            commentTextSpan.innerHTML = nl2br(truncatedContent);
                        }
                        this.style.display = 'none';
                        if (readMoreButton) {
                            readMoreButton.style.display = 'inline';
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
