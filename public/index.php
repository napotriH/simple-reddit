<?php
// public_html/public/index.php

// DEBUG START POINT
error_log("DEBUG_INDEX: Script started.");

session_start();

// Asigurăm că sesiunea este pornită și user_id este disponibil
if (!isset($_SESSION['user_id'])) {
    error_log("DEBUG_INDEX: User not authenticated. Redirecting to login.php.");
    header("Location: login.php");
    exit();
}

error_log("DEBUG_INDEX: Session started. User ID: " . ($_SESSION['user_id'] ?? 'N/A') . ", Username: " . ($_SESSION['username'] ?? 'N/A'));

// --- DEBUGGING: Activează afișarea erorilor PHP ---
ini_set('display_errors', 1); // Afișează erorile direct pe pagină
ini_set('display_startup_errors', 1); // Afișează erorile care apar la pornirea PHP
error_reporting(E_ALL); // Raportează toate tipurile de erori
ini_set('log_errors', 1); // Loghează erorile într-un fișier
ini_set('error_log', __DIR__ . '/../../php_errors.log'); // Specifică calea fișierului de log
// --- Sfârșit DEBUGGING ---

error_log("DEBUG_INDEX: Error reporting configured.");

// Includem fișierele necesare conform structurii existente a proiectului
// Adăugăm verificări file_exists pentru a depista probleme de cale
$databasePath = __DIR__ . '/../core/Database.php';
$postModelPath = __DIR__ . '/../app/models/Post.php';
$voteModelPath = __DIR__ . '/../app/models/Vote.php';
$userModelPath = __DIR__ . '/../app/models/User.php';
$subscriptionModelPath = __DIR__ . '/../app/models/Subscription.php';

error_log("DEBUG_INDEX: Checking file paths...");

if (!file_exists($databasePath)) {
    error_log("CRITICAL ERROR_INDEX: Missing file: " . $databasePath);
    die('Eroare critică: Fișierul Database.php nu a fost găsit. Verifică calea: ' . htmlspecialchars($databasePath));
}
require_once $databasePath;
error_log("DEBUG_INDEX: Database.php included successfully.");

if (!file_exists($postModelPath)) {
    error_log("CRITICAL ERROR_INDEX: Missing file: " . $postModelPath);
    die('Eroare critică: Fișierul Post.php nu a fost găsit. Verifică calea: ' . htmlspecialchars($postModelPath));
}
require_once $postModelPath;
error_log("DEBUG_INDEX: Post.php included successfully.");

if (!file_exists($voteModelPath)) {
    error_log("CRITICAL ERROR_INDEX: Missing file: " . $voteModelPath);
    die('Eroare critică: Fișierul Vote.php nu a fost găsit. Verifică calea: ' . htmlspecialchars($voteModelPath));
}
require_once $voteModelPath;
error_log("DEBUG_INDEX: Vote.php included successfully.");

if (!file_exists($userModelPath)) {
    error_log("CRITICAL ERROR_INDEX: Missing file: " . $userModelPath);
    die('Eroare critică: Fișierul User.php nu a fost găsit. Verifică calea: ' . htmlspecialchars($userModelPath));
}
require_once $userModelPath;
error_log("DEBUG_INDEX: User.php included successfully.");

if (!file_exists($subscriptionModelPath)) {
    error_log("CRITICAL ERROR_INDEX: Missing file: " . $subscriptionModelPath);
    die('Eroare critică: Fișierul Subscription.php nu a fost găsit. Verifică calea: ' . htmlspecialchars($subscriptionModelPath));
}
require_once $subscriptionModelPath;
error_log("DEBUG_INDEX: Subscription.php included successfully.");

// Obținem o conexiune la baza de date
error_log("DEBUG_INDEX: Creating new Database object.");
$database = new Database();
error_log("DEBUG_INDEX: Attempting to get database connection.");
$db = $database->getConnection();

// Verifică dacă conexiunea la baza de date este validă
if ($db === null) {
    error_log("CRITICAL ERROR_INDEX: Database connection failed.");
    die('Eroare: Conexiunea la baza de date a eșuat. Verifică configurația bazei de date (db.php) și credențialele.');
}
error_log("DEBUG_INDEX: Database connection successful.");

// Creăm instanțe ale modelelor
error_log("DEBUG_INDEX: Instantiating models.");
$post_model = new Post($db);
$vote_model = new Vote($db);
$user_model = new User($db); // Pentru a afișa avatarul în post-meta
$subscription_model = new Subscription($db); // Pentru a filtra postările după abonamente
error_log("DEBUG_INDEX: Models instantiated.");

$current_user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // Default la 'all'

$posts = [];
$posts_stmt = null;

error_log("DEBUG_INDEX: Current filter: " . $filter . " for user ID: " . $current_user_id);

if ($filter === 'subscribed' && $current_user_id) {
    // Preluăm postările din comunitățile la care utilizatorul este abonat
    error_log("DEBUG_INDEX: Calling readBySubscribedCommunities for user ID: " . $current_user_id);
    $posts_stmt = $post_model->readBySubscribedCommunities($current_user_id);
    $page_title = "Postările Mele (Comunități Abonate)";

    if ($posts_stmt === false) {
        error_log("DEBUG_INDEX: readBySubscribedCommunities returned false, check Post.php for errors.");
    } else if ($posts_stmt === null) {
        error_log("DEBUG_INDEX: readBySubscribedCommunities returned null, PDOStatement was not created.");
    } else {
        error_log("DEBUG_INDEX: readBySubscribedCommunities returned a valid PDOStatement object. Attempting to fetch rows...");
        // Verificăm dacă există rânduri înainte de a încerca să le extragem
        if ($posts_stmt->rowCount() == 0) {
            error_log("DEBUG_INDEX: PDOStatement for subscribed communities returned 0 rows.");
        } else {
            error_log("DEBUG_INDEX: PDOStatement for subscribed communities returned " . $posts_stmt->rowCount() . " rows.");
        }
    }

} else {
    // Preluăm toate postările (default)
    error_log("DEBUG_INDEX: Calling readAll for all posts.");
    $posts_stmt = $post_model->readAll();
    $page_title = "Postări Recente";
}

// Calea de bază pentru a ajunge la directorul root al proiectului de la public/
$base_path_from_public_to_root = '../';

error_log("DEBUG_INDEX: Processing posts statement.");
if ($posts_stmt) { // Asigură-te că $posts_stmt nu este false sau null
    $fetched_post_count = 0;
    while ($row = $posts_stmt->fetch(PDO::FETCH_ASSOC)) {
        // Obținem statusul votului utilizatorului pentru fiecare postare
        $user_vote_status = $vote_model->hasVoted($current_user_id, $row['id'], null);
        $row['user_vote_status'] = $user_vote_status ? $user_vote_status['vote_type'] : 0;

        // Obținem calea către avatarul autorului postării
        // Calea din BD (author_avatar_url) este relativă la public_html/
        // Avem nevoie de "../" pentru a ajunge de la public/ la directorul root
        $author_avatar_src = $row['author_avatar_url'] ? htmlspecialchars($base_path_from_public_to_root . $row['author_avatar_url']) : htmlspecialchars($base_path_from_public_to_root . 'uploads/avatars/default_avatar.svg');
        $row['author_avatar_src'] = $author_avatar_src;

        // Preluăm calea imaginii de copertă
        $row['cover_image_src'] = $row['cover_image_url'] ? htmlspecialchars($base_path_from_public_to_root . $row['cover_image_url']) : '';


        // Trunchierea conținutului pentru postările de tip text
        if ($row['type'] === 'text' && !empty($row['content'])) {
            $row['full_content'] = $row['content']; // Păstrează conținutul complet
            $row['content'] = truncate_text($row['content'], 300); // Trunchiază la 300 de caractere
            $row['truncated'] = (mb_strlen($row['full_content']) > mb_strlen($row['content'])); // Verifică dacă a fost trunchiat
        } else {
            $row['truncated'] = false;
        }

        $posts[] = $row;
        $fetched_post_count++;
        // error_log("DEBUG_INDEX: Added post to array: " . json_encode($row['title'])); // Uncomment for verbose logging of each post
    }
    error_log("DEBUG_INDEX: Total posts fetched for display in \$posts array: " . count($posts) . ". (Fetched " . $fetched_post_count . " from PDOStatement)"); // Debugging total posts
} else {
    error_log("DEBUG_INDEX: \$posts_stmt is null or false, no posts were processed for display.");
}


// Preluăm mesajele de sesiune (după redirecționare)
$message = null;
$message_type = null;
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message']['text'];
    $message_type = $_SESSION['message']['type'];
    unset($_SESSION['message']);
}
error_log("DEBUG_INDEX: Message from session: " . ($message ?? 'No message'));

// Funcție ajutătoare pentru a afișa timpul în format "acum X minute/ore/zile"
// Mutată funcția în interiorul blocului PHP principal pentru a asigura încărcarea
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

error_log("DEBUG_INDEX: Starting HTML output.");
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Reddit Local</title>
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
        <div class="container">
            <div class="page-header">
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
                <div class="filter-options">
                    <a href="index.php?filter=all" class="filter-button <?php echo ($filter === 'all' ? 'active' : ''); ?>">Toate postările</a>
                    <a href="index.php?filter=subscribed" class="filter-button <?php echo ($filter === 'subscribed' ? 'active' : ''); ?>">Abonamentele mele</a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="post-list">
                <?php if (!empty($posts)): ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="post-card" data-post-id="<?php echo htmlspecialchars($post['id']); ?>">
                            <div class="vote-section">
                                <span class="arrow up <?php echo ($post['user_vote_status'] == 1) ? 'voted' : ''; ?>" data-vote-type="upvote">&#9650;</span>
                                <span class="score"><?php echo $post['upvotes'] - $post['downvotes']; ?></span>
                                <span class="arrow down <?php echo ($post['user_vote_status'] == -1) ? 'voted' : ''; ?>" data-vote-type="downvote">&#9660;</span>
                            </div>
                            <div class="post-content">
                                <h3 class="post-title">
                                    <a href="post_detail.php?id=<?php echo htmlspecialchars($post['id']); ?>">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                </h3>
                                <p class="post-meta">
                                    Postat în <a href="community_detail.php?id=<?php echo htmlspecialchars($post['community_id'] ?? ''); ?>">r/<?php echo htmlspecialchars($post['community_name'] ?? 'Comunitate necunoscută'); ?></a>
                                    de <a href="profile.php?id=<?php echo htmlspecialchars($post['user_id'] ?? ''); ?>"><img src="<?php echo htmlspecialchars($post['author_avatar_src']); ?>" alt="Avatar" class="post-meta-avatar">u/<?php echo htmlspecialchars($post['author_username'] ?? 'Utilizator necunoscut'); ?></a>
                                    acum <?php echo time_elapsed_string($post['created_at']); ?>
                                </p>
                                <?php if (!empty($post['cover_image_src'])): ?>
                                    <div class="post-thumbnail">
                                        <a href="post_detail.php?id=<?php echo htmlspecialchars($post['id']); ?>">
                                            <img src="<?php echo $post['cover_image_src']; ?>" alt="Imagine copertă" onerror="this.onerror=null;this.src='https://placehold.co/150x150/e0e0e0/555555?text=Fără+Imagine';" />
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($post['type'] === 'text'): ?>
                                    <p class="post-body">
                                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                        <?php if ($post['truncated']): ?>
                                            <a href="post_detail.php?id=<?php echo htmlspecialchars($post['id']); ?>" class="read-more">Citește mai mult...</a>
                                        <?php endif; ?>
                                    </p>
                                <?php elseif ($post['type'] === 'link' && $post['url']): ?>
                                    <p class="post-body">
                                        <a href="<?php echo htmlspecialchars($post['url']); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo htmlspecialchars($post['url']); ?> <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-posts">
                        <?php if ($filter === 'subscribed'): ?>
                            Nu există postări în comunitățile la care ești abonat. <a href="communities.php">Explorează comunități</a> sau <a href="create_post.php">creează o postare</a>!
                        <?php else: ?>
                            Nu există postări încă. Fii primul care <a href="create_post.php">creează una</a>!
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const voteSections = document.querySelectorAll('.vote-section');

            voteSections.forEach(section => {
                const upArrow = section.querySelector('.arrow.up');
                const downArrow = section.querySelector('.arrow.down');
                const scoreSpan = section.querySelector('.score');
                const postId = section.closest('.post-card').dataset.postId;

                upArrow.addEventListener('click', () => handleVote(postId, 'upvote', upArrow, downArrow, scoreSpan));
                downArrow.addEventListener('click', () => handleVote(postId, 'downvote', upArrow, downArrow, scoreSpan));
            });

            async function handleVote(postId, voteAction, upArrow, downArrow, scoreSpan) {
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
                        console.error('Eroare la votare:', data.message);
                        alert('Eroare la votare: ' + data.message);
                    }
                } catch (error) {
                    console.error('Eroare rețea la votare:', error);
                    alert('A apărut o eroare de rețea. Te rugăm să încerci din nou.');
                }
            }

            // JavaScript pentru meniul hamburger
            const menuToggle = document.querySelector('.menu-toggle');
            const navMenu = document.querySelector('.nav-menu');

            if (menuToggle && navMenu) {
                menuToggle.addEventListener('click', () => {
                    navMenu.classList.toggle('active');
                    menuToggle.classList.toggle('active'); // Adaugă clasă pentru animație hamburger
                });

                // Închide meniul la click în afara lui, pe elementele din meniu (navigare)
                document.addEventListener('click', (event) => {
                    if (!navMenu.contains(event.target) && !menuToggle.contains(event.target) && navMenu.classList.contains('active')) {
                        navMenu.classList.remove('active');
                        menuToggle.classList.remove('active');
                    }
                });

                // Închide meniul când un link din meniu este apăsat
                navMenu.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', () => {
                        navMenu.classList.remove('active');
                        menuToggle.classList.remove('active');
                    });
                });
            }
        });
    </script>
</body>
</html>
<?php error_log("DEBUG_INDEX: HTML output finished. Script concluding."); ?>
