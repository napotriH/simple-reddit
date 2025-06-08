<?php
// public_html/public/community_detail.php

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

// Includem fișierele necesare
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/models/Post.php';
require_once __DIR__ . '/../app/models/Community.php';
require_once __DIR__ . '/../app/models/Vote.php';
require_once __DIR__ . '/../app/models/Subscription.php'; // Includem modelul Subscription

// Obținem o conexiune la baza de date
$database = new Database();
$db = $database->getConnection();

// Verifică dacă conexiunea la baza de date este validă
if ($db === null) {
    die('Eroare: Conexiunea la baza de date a eșuat.');
}

// Creăm obiecte pentru Post, Community, Vote și Subscription
$post_model = new Post($db); // Am schimbat numele variabilei pentru claritate
$community_model = new Community($db); // Am schimbat numele variabilei pentru claritate
$vote_model = new Vote($db); // Am schimbat numele variabilei pentru claritate
$subscription_model = new Subscription($db);

$message = null; // Inițializăm variabila pentru mesaje

// Preluăm ID-ul comunității din URL
$community_id = isset($_GET['id']) ? intval($_GET['id']) : null; // Folosim intval și null

// Validăm că ID-ul comunității este un număr întreg pozitiv
if (empty($community_id) || $community_id <= 0) {
    die('Eroare: ID-ul comunității este invalid sau lipsește.');
}

$community_model->id = $community_id;

// Încercăm să citim detaliile comunității
if (!$community_model->readOne()) {
    // Mesajul de eroare specific pe care îl primești
    die('Eroare: Comunitatea cu ID-ul ' . htmlspecialchars($community_id) . ' nu a fost găsită.');
}

$current_user_id = $_SESSION['user_id'];
$subscription_model->user_id = $current_user_id;
$subscription_model->community_id = $community_id;
$is_subscribed = $subscription_model->isSubscribed(); // Verifică dacă utilizatorul curent este abonat la această comunitate

// Obținem postările pentru această comunitate
// Metoda readByCommunityId din Post.php ar trebui să includă acum username și community_name
$posts_stmt = $post_model->readByCommunityId($community_id);
$posts = [];

// Calea de bază pentru a ajunge la directorul root al proiectului de la public/
$base_path_from_public_to_root = '../';

while ($row = $posts_stmt->fetch(PDO::FETCH_ASSOC)) {
    // Pentru fiecare postare, verificăm starea votului utilizatorului curent
    $user_vote_status = $vote_model->hasVoted($_SESSION['user_id'], $row['id'], null); // Asigură null pentru comment_id
    $row['user_vote_status'] = $user_vote_status ? $user_vote_status['vote_type'] : 0; // 0 = nevotat, 1 = upvote, -1 = downvote
    
    // Calea către avatarul autorului postării (din Post model)
    // Presupunem că $row['author_avatar_url'] este relativ la public_html/
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
}

// Preluăm mesajele de sesiune (după redirecționare)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
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

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>r/<?php echo htmlspecialchars($community_model->name); ?> - Reddit Local</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
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

    <div class="main-content-wrapper">
        <div class="container">
            <div class="community-header-details">
                <h1>r/<?php echo htmlspecialchars($community_model->name); ?></h1>
                <p class="community-description"><?php echo nl2br(htmlspecialchars($community_model->description)); ?></p>
                
                <div class="community-actions-bar">
                    <p class="community-meta">Creată acum <?php echo time_elapsed_string($community_model->created_at); ?></p>
                    <?php
                        $subscriber_count = $subscription_model->getSubscriberCount($community_id);
                    ?>
                    <p class="community-meta"><?php echo $subscriber_count; ?> abonat<?php echo ($subscriber_count !== 1) ? 'i' : ''; ?></p>

                    <button class="subscribe-button <?php echo $is_subscribed ? 'subscribed' : ''; ?>"
                            data-action="<?php echo $is_subscribed ? 'unsubscribe' : 'subscribe'; ?>"
                            data-community-id="<?php echo htmlspecialchars($community_model->id); ?>">
                        <?php echo $is_subscribed ? 'Dezabonează-te' : 'Abonează-te'; ?>
                    </button>
                </div>
            </div>
            
            <?php
            // Afișăm mesajele de succes sau eroare, dacă există
            if ($message) {
                $message_type = $message['type'];
                $message_text = $message['text'];
                echo "<div class='message {$message_type}'>{$message_text}</div>";
            }
            ?>

            <div class="post-list">
                <?php if (count($posts) > 0): ?>
                    <?php foreach ($posts as $p): ?>
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
                                    Postat în <a href="community_detail.php?id=<?php echo htmlspecialchars($p['community_id'] ?? ''); ?>">r/<?php echo htmlspecialchars($p['community_name'] ?? 'Comunitate necunoscută'); ?></a>
                                    de <a href="profile.php?id=<?php echo htmlspecialchars($p['user_id'] ?? ''); ?>"><img src="<?php echo htmlspecialchars($p['author_avatar_src'] ?? '../uploads/avatars/default_avatar.svg'); ?>" alt="Avatar" class="post-meta-avatar">u/<?php echo htmlspecialchars($p['author_username'] ?? 'Utilizator necunoscut'); ?></a>
                                    acum <?php echo time_elapsed_string($p['created_at']); ?>
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
                                        <?php echo nl2br(htmlspecialchars($p['content'])); ?>
                                        <?php if ($p['truncated']): ?>
                                            <a href="post_detail.php?id=<?php echo htmlspecialchars($p['id']); ?>" class="read-more">Citește mai mult...</a>
                                        <?php endif; ?>
                                    </p>
                                <?php elseif ($p['type'] === 'link' && $p['url']): ?>
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
                    <p class="no-posts">Nu există postări încă în această comunitate. Fii primul care <a href="create_post.php">creează una</a>!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // JavaScript pentru voturile pe postare (similar cu index.php)
            const voteSections = document.querySelectorAll('.post-card .vote-section');

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

            // JavaScript pentru abonare/dezabonare (similar cu communities.php)
            const subscribeButton = document.querySelector('.community-actions-bar .subscribe-button');
            if (subscribeButton) {
                subscribeButton.addEventListener('click', async function() {
                    const communityId = this.dataset.communityId;
                    let action = this.dataset.action; // 'subscribe' sau 'unsubscribe'

                    try {
                        const response = await fetch('api/toggle_subscription.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                community_id: communityId,
                                action: action
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Actualizează butonul și starea
                            if (action === 'subscribe') {
                                this.classList.add('subscribed');
                                this.textContent = 'Dezabonează-te';
                                this.dataset.action = 'unsubscribe';
                            } else {
                                this.classList.remove('subscribed');
                                this.textContent = 'Abonează-te';
                                this.dataset.action = 'subscribe';
                            }
                            console.log(data.message);
                            // Poți afișa un mesaj de succes temporar utilizatorului
                        } else {
                            console.error('Eroare la abonare/dezabonare:', data.message);
                            alert('Eroare: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Eroare rețea:', error);
                        alert('A apărut o eroare de rețea. Te rugăm să încerci din nou.');
                    }
                });
            }

            // JavaScript pentru meniul hamburger (adăugat aici)
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
        });
    </script>
</body>
</html>
