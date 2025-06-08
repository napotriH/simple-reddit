<?php
// public_html/public/post_detail.php

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
    // Redirecționăm către pagina de login dacă nu este autentificat
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
$postModelPath = __DIR__ . '/../app/models/Post.php';
$commentModelPath = __DIR__ . '/../app/models/Comment.php';
$voteModelPath = __DIR__ . '/../app/models/Vote.php';
$userModelPath = __DIR__ . '/../app/models/User.php';

if (!file_exists($databasePath)) {
    die('Eroare critică: Fișierul Database.php nu a fost găsit la calea: ' . htmlspecialchars($databasePath));
}
require_once $databasePath;

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

if (!file_exists($userModelPath)) {
    die('Eroare critică: Fișierul User.php nu a fost găsit la calea: ' . htmlspecialchars($userModelPath));
}
require_once $userModelPath;


// Obținem o conexiune la baza de date
$database = new Database();
$db = $database->getConnection();

// Verifică dacă conexiunea la baza de date este validă
if ($db === null) {
    die('Eroare: Conexiunea la baza de date a eșuat. Verifică configurația bazei de date (db.php) și credențialele.');
}

// Creăm instanțe ale modelelor
$post_model = new Post($db);
$comment_model = new Comment($db);
$vote_model = new Vote($db);
$user_model = new User($db); // Instanța modelului User

$message = ''; // Variabilă pentru mesaje de eroare/succes
$message_type = ''; // Tipul mesajului

// Verificăm dacă ID-ul postării este furnizat în URL
$post_id = isset($_GET['id']) ? $_GET['id'] : die('Eroare: ID-ul postării nu este specificat.');

// Validăm ID-ul postării
if (!is_numeric($post_id)) {
    die('Eroare: ID-ul postării este invalid.');
}

// Setăm ID-ul postării în model și încercăm să citim postarea
$post_model->id = $post_id;
if (!$post_model->readOne()) {
    die('Eroare: Postarea nu a fost găsită.');
}

// --- DEBUGGING: Afișează conținutul postării după ce a fost citită din DB ---
error_log("DEBUG post_detail.php: Post ID: " . $post_model->id . ", Type: " . $post_model->type . ", Content: '" . $post_model->content . "', Image URL: '" . $post_model->cover_image_url . "'");
// --- Sfârșit DEBUGGING ---


// Procesăm trimiterea comentariului
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $comment_content = trim($_POST['comment_content'] ?? '');
    $parent_comment_id = !empty($_POST['parent_comment_id']) ? intval($_POST['parent_comment_id']) : null;

    if (empty($comment_content)) {
        $message = 'Comentariul nu poate fi gol.';
        $message_type = 'error';
    } else {
        $comment_model->post_id = $post_id;
        $comment_model->user_id = $_SESSION['user_id'];
        $comment_model->content = $comment_content;
        $comment_model->parent_comment_id = $parent_comment_id; // Poate fi NULL

        if ($comment_model->create()) {
            $message = 'Comentariu adăugat cu succes!';
            $message_type = 'success';
            // Redirecționăm pentru a preveni re-trimiterea formularului la refresh
            header("Location: post_detail.php?id=" . $post_id);
            exit();
        } else {
            $message = 'Eroare la adăugarea comentariului.';
            $message_type = 'error';
        }
    }
}

// Preluăm comentariile pentru postarea curentă
$comments_stmt = $comment_model->readByPostId($post_id);
$comments_raw = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Construim o structură ierarhică pentru comentarii și aplicăm trunchierea
$comments_by_id = [];
foreach ($comments_raw as $comment) {
    // Obținem statusul votului utilizatorului pentru fiecare comentariu
    $user_vote_status_comment = $vote_model->hasVoted($_SESSION['user_id'], null, $comment['id']);
    $comment['user_vote_status'] = $user_vote_status_comment ? $user_vote_status_comment['vote_type'] : 0;

    // Obținem avatarul autorului comentariului
    $comment_author = $user_model; // Folosim aceeași instanță de model user
    $comment_author->id = $comment['user_id'];
    $comment_author->readOne(); // Citiți informațiile despre autorul comentariului
    $comment['author_username'] = $comment_author->username;
    $comment['author_avatar_url'] = $comment_author->avatar_url;

    // Trunchierea conținutului comentariilor
    if (!empty($comment['content'])) {
        $comment['full_content'] = $comment['content']; // Păstrează conținutul complet
        $comment['content_truncated'] = truncate_text($comment['content'], 150); // Trunchiază la 150 de caractere pentru comentarii
        $comment['truncated'] = (mb_strlen($comment['full_content']) > mb_strlen($comment['content_truncated'])); // Verifică dacă a fost trunchiat
    } else {
        $comment['truncated'] = false;
    }

    $comments_by_id[$comment['id']] = $comment;
    $comments_by_id[$comment['id']]['children'] = [];
}

$root_comments = [];
foreach ($comments_by_id as $comment_id => &$comment) {
    if (is_null($comment['parent_comment_id'])) {
        $root_comments[] = &$comment;
    } else {
        if (isset($comments_by_id[$comment['parent_comment_id']])) {
            $comments_by_id[$comment['parent_comment_id']]['children'][] = &$comment;
        }
    }
}
unset($comment); // Elimină referința la ultimul element

// Preluăm mesajele de sesiune (după redirecționare)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message']['text'];
    $message_type = $_SESSION['message']['type'];
    unset($_SESSION['message']);
}

// Calea de bază pentru a ajunge la directorul root al proiectului de la public/
$base_path_from_public_to_root = '../';

// Obținem avatarul autorului postării (folosind noua proprietate din Post model)
$post_author_avatar_src = $post_model->author_avatar_url ? htmlspecialchars($base_path_from_public_to_root . $post_model->author_avatar_url) : htmlspecialchars($base_path_from_public_to_root . 'uploads/avatars/default_avatar.svg');

// Calea imaginii de copertă
$cover_image_src = $post_model->cover_image_url ? htmlspecialchars($base_path_from_public_to_root . $post_model->cover_image_url) : '';

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post_model->title); ?> - Reddit Local</title>
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
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="post-detail-card" data-post-id="<?php echo htmlspecialchars($post_model->id); ?>">
                <div class="vote-section">
                    <span class="arrow up <?php echo ($vote_model->hasVoted($_SESSION['user_id'], $post_model->id, null)['vote_type'] ?? 0) == 1 ? 'voted' : ''; ?>" data-vote-type="upvote">&#9650;</span>
                    <span class="score"><?php echo $post_model->upvotes - $post_model->downvotes; ?></span>
                    <span class="arrow down <?php echo ($vote_model->hasVoted($_SESSION['user_id'], $post_model->id, null)['vote_type'] ?? 0) == -1 ? 'voted' : ''; ?>" data-vote-type="downvote">&#9660;</span>
                </div>
                <div class="post-content">
                    <h1 class="post-title"><?php echo htmlspecialchars($post_model->title); ?></h1>
                    <p class="post-meta">
                        Postat în
                        <a href="community_detail.php?id=<?php echo htmlspecialchars($post_model->community_id); ?>">r/<?php echo htmlspecialchars($post_model->community_name); ?></a>
                        de <a href="profile.php?id=<?php echo htmlspecialchars($post_model->user_id); ?>"><img src="<?php echo $post_author_avatar_src; ?>" alt="Avatar" class="post-meta-avatar">u/<?php echo htmlspecialchars($post_model->author_username); ?></a>
                        acum <?php echo time_elapsed_string($post_model->created_at); ?>
                    </p>

                    <?php if (!empty($cover_image_src)): ?>
                        <div class="post-cover-image">
                            <img src="<?php echo $cover_image_src; ?>" alt="Imagine copertă" class="post-cover-image-actual">
                        </div>
                    <?php endif; ?>

                    <?php
                    // Display content based on post type
                    if ($post_model->type === 'text' && !empty($post_model->content)) {
                        echo '<p class="post-body">' . nl2br(htmlspecialchars($post_model->content)) . '</p>';
                    } elseif ($post_model->type === 'link' && !empty($post_model->url)) {
                        echo '<p class="post-body">Link: <a href="' . htmlspecialchars($post_model->url) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($post_model->url) . ' <i class="fas fa-external-link-alt"></i></a></p>';
                    } elseif ($post_model->type === 'image' && !empty($post_model->content)) {
                        // Afișează textul pentru postările de tip imagine sub imagine
                        echo '<p class="post-body post-image-text">' . nl2br(htmlspecialchars($post_model->content)) . '</p>';
                    }
                    ?>
                </div>
            </div>

            <div class="comments-section">
                <h3>Comentarii</h3>
                <form action="post_detail.php?id=<?php echo htmlspecialchars($post_id); ?>" method="POST" class="comment-form">
                    <textarea name="comment_content" placeholder="Adaugă un comentariu..." rows="3" required></textarea>
                    <input type="hidden" name="parent_comment_id" value=""> <!-- Va fi populat de JS pentru răspunsuri -->
                    <button type="submit" name="submit_comment">Comentează</button>
                </form>

                <div class="comments-list">
                    <?php
                    // Funcție recursivă pentru a afișa comentariile și răspunsurile lor
                    function display_comments($comments_array, $vote_model, $current_user_id, $level = 0, $base_path_from_public_to_root) {
                        foreach ($comments_array as $comment) {
                            $comment_author_avatar_src = $comment['author_avatar_url'] ? htmlspecialchars($base_path_from_public_to_root . $comment['author_avatar_url']) : htmlspecialchars($base_path_from_public_to_root . 'uploads/avatars/default_avatar.svg');
                            ?>
                            <div class="comment-card level-<?php echo $level; ?>" data-comment-id="<?php echo htmlspecialchars($comment['id']); ?>">
                                <div class="vote-section-comment">
                                    <span class="arrow up-comment <?php echo ($comment['user_vote_status'] == 1) ? 'voted' : ''; ?>" data-vote-type="upvote">&#9650;</span>
                                    <span class="score-comment"><?php echo $comment['upvotes'] - $comment['downvotes']; ?></span>
                                    <span class="arrow down-comment <?php echo ($comment['user_vote_status'] == -1) ? 'voted' : ''; ?>" data-vote-type="downvote">&#9660;</span>
                                </div>
                                <div class="comment-content">
                                    <p class="comment-meta">
                                        <a href="profile.php?id=<?php echo htmlspecialchars($comment['user_id']); ?>"><img src="<?php echo $comment_author_avatar_src; ?>" alt="Avatar" class="comment-meta-avatar">u/<?php echo htmlspecialchars($comment['author_username']); ?></a>
                                        acum <?php echo time_elapsed_string($comment['created_at']); ?>
                                    </p>
                                    <p class="comment-display-content" data-full-content="<?php echo htmlspecialchars($comment['full_content']); ?>">
                                        <span class="comment-text-content"><?php echo nl2br(htmlspecialchars($comment['content_truncated'])); ?></span>
                                        <?php if ($comment['truncated']): ?>
                                            <a href="#" class="read-more-comment">Citește mai mult...</a>
                                            <a href="#" class="read-less-comment" style="display: none;">Citește mai puțin</a>
                                        <?php endif; ?>
                                    </p>
                                    <button class="reply-button" data-comment-id="<?php echo htmlspecialchars($comment['id']); ?>" data-comment-author="<?php echo htmlspecialchars($comment['author_username']); ?>">Răspunde</button>
                                </div>
                            </div>
                            <?php
                            // Afișează comentariile copil
                            if (!empty($comment['children'])) {
                                display_comments($comment['children'], $vote_model, $current_user_id, $level + 1, $base_path_from_public_to_root);
                            }
                        }
                    }

                    if (!empty($root_comments)) {
                        display_comments($root_comments, $vote_model, $_SESSION['user_id'], 0, $base_path_from_public_to_root);
                    } else {
                        echo '<p class="no-comments">Nu există comentarii încă. Fii primul care comentează!</p>';
                    }
                    ?>
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

            // JavaScript pentru voturile pe postare
            const postVoteSection = document.querySelector('.post-detail-card .vote-section');
            if (postVoteSection) {
                const upArrow = postVoteSection.querySelector('.arrow.up');
                const downArrow = postVoteSection.querySelector('.arrow.down');
                const scoreSpan = postVoteSection.querySelector('.score');
                const postId = postVoteSection.closest('.post-detail-card').dataset.postId;

                upArrow.addEventListener('click', () => handlePostVote(postId, 'upvote', upArrow, downArrow, scoreSpan));
                downArrow.addEventListener('click', () => handlePostVote(postId, 'downvote', upArrow, downArrow, scoreSpan));
            }

            async function handlePostVote(postId, voteAction, upArrow, downArrow, scoreSpan) {
                try {
                    const response = await fetch('api/vote_post.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json', // Folosim application/json pentru trimiterea datelor JSON
                        },
                        body: JSON.stringify({ // Trimitem datele ca JSON
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


            // JavaScript pentru voturile pe comentarii
            const commentVoteSections = document.querySelectorAll('.comment-card .vote-section-comment');

            commentVoteSections.forEach(section => {
                const upArrow = section.querySelector('.arrow.up-comment');
                const downArrow = section.querySelector('.arrow.down-comment');
                const scoreSpan = section.querySelector('.score-comment');
                const commentId = section.closest('.comment-card').dataset.commentId;

                upArrow.addEventListener('click', () => handleCommentVote(commentId, 'upvote', upArrow, downArrow, scoreSpan));
                downArrow.addEventListener('click', () => handleCommentVote(commentId, 'downvote', upArrow, downArrow, scoreSpan));
            });

            async function handleCommentVote(commentId, voteAction, upArrow, downArrow, scoreSpan) {
                try {
                    const response = await fetch('api/vote_comment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json', // Folosim application/json pentru trimiterea datelor JSON
                        },
                        body: JSON.stringify({ // Trimitem datele ca JSON
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

            // Logica pentru răspunsuri la comentarii
            const replyButtons = document.querySelectorAll('.reply-button');
            const commentForm = document.querySelector('.comment-form');
            const commentFormTextarea = commentForm.querySelector('textarea[name="comment_content"]');
            const parentCommentIdInput = commentForm.querySelector('input[name="parent_comment_id"]');

            replyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const commentId = this.dataset.commentId;
                    const commentAuthor = this.dataset.commentAuthor;
                    
                    parentCommentIdInput.value = commentId;
                    commentFormTextarea.value = `@${commentAuthor} `; // Pre-populează cu mențiunea autorului
                    commentFormTextarea.focus(); // Mută focusul pe textarea
                });
            });

            // Resetează câmpul parent_comment_id când utilizatorul scrie un comentariu nou (nu un răspuns)
            commentFormTextarea.addEventListener('input', function() {
                if (!this.value.startsWith('@')) { // Dacă textul nu începe cu @, presupunem că nu este un răspuns
                    parentCommentIdInput.value = '';
                }
            });
            // Adaugăm și un listener pentru resetare când formularul este trimis, pentru siguranță
            commentForm.addEventListener('submit', function() {
                parentCommentIdInput.value = '';
            });

            // Logica pentru extinderea/restrângerea comentariilor in-place
            document.querySelectorAll('.comment-display-content').forEach(commentContentElement => {
                const commentTextSpan = commentContentElement.querySelector('.comment-text-content');
                const readMoreButton = commentContentElement.querySelector('.read-more-comment');
                const readLessButton = commentContentElement.querySelector('.read-less-comment');
                const fullContent = commentContentElement.dataset.fullContent;
                const truncatedContent = truncateTextJS(fullContent, 150);

                if (readMoreButton) {
                    readMoreButton.addEventListener('click', function(event) {
                        event.preventDefault();
                        commentTextSpan.innerHTML = nl2br(fullContent);
                        this.style.display = 'none';
                        if (readLessButton) {
                            readLessButton.style.display = 'inline';
                        }
                    });
                }

                if (readLessButton) {
                    readLessButton.addEventListener('click', function(event) {
                        event.preventDefault();
                        commentTextSpan.innerHTML = nl2br(truncatedContent);
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
