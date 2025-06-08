<?php
// public_html/public/create_post.php

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
require_once __DIR__ . '/../app/models/Community.php'; // Includem modelul Community

// Obținem o conexiune la baza de date
$database = new Database();
$db = $database->getConnection();

$post = new Post($db);
$community = new Community($db); // Instanțiem obiectul Community

$message = null; // Inițializăm variabila pentru mesaje

// Preluăm toate comunitățile pentru selector
$communities_stmt = $community->readAll();
$communities_list = [];
while ($row = $communities_stmt->fetch(PDO::FETCH_ASSOC)) {
    $communities_list[] = $row;
}

// Directorul unde vor fi încărcate imaginile de copertă
$upload_dir = '../uploads/posts/';
// Creăm directorul dacă nu există
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Procesăm trimiterea formularului
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_post'])) {
    // --- DEBUGGING: Afișează toate datele POST și FILES primite ---
    error_log("POST Data on create_post.php: " . print_r($_POST, true));
    error_log("FILES Data on create_post.php: " . print_r($_FILES, true));
    // --- Sfârșit DEBUGGING ---

    $post->title = trim($_POST['post_title'] ?? '');
    $post->community_id = $_POST['community_id'] ?? null;
    $post->type = $_POST['post_type'] ?? '';
    $post->user_id = $_SESSION['user_id'];

    $post->content = null;
    $post->url = null;
    $post->cover_image_url = null; // Inițializăm

    if ($post->type === 'text') {
        $post->content = trim($_POST['post_content'] ?? '');
    } elseif ($post->type === 'link') {
        $post->url = trim($_POST['post_url'] ?? '');
    } elseif ($post->type === 'image') {
        // Preluăm conținutul text pentru postările de tip imagine
        $post->content = trim($_POST['image_post_content'] ?? ''); // Noul câmp de text

        // Logica pentru încărcarea imaginii
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['cover_image']['name'];
            $file_tmp_name = $_FILES['cover_image']['tmp_name'];
            $file_size = $_FILES['cover_image']['size'];
            $file_type = $_FILES['cover_image']['type'];

            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_ext, $allowed_extensions)) {
                // Generăm un nume unic pentru fișier
                $new_file_name = uniqid('cover_') . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp_name, $destination)) {
                    $post->cover_image_url = $destination; // Salvăm calea relativă
                } else {
                    $message = ['type' => 'error', 'text' => 'Eroare la mutarea fișierului încărcat.'];
                }
            } else {
                $message = ['type' => 'error', 'text' => 'Doar fișierele JPG, JPEG, PNG și GIF sunt permise.'];
            }
        } else if ($post->type === 'image' && $_FILES['cover_image']['error'] === UPLOAD_ERR_NO_FILE) {
             $message = ['type' => 'error', 'text' => 'Te rog să încarci o imagine pentru postarea de tip imagine.'];
        } else {
            // Alte erori de încărcare (ex: dimensiune prea mare)
            $message = ['type' => 'error', 'text' => 'Eroare la încărcarea imaginii: ' . $_FILES['cover_image']['error']];
        }
    }


    // Validări
    if (empty($post->title)) {
        $message = ['type' => 'error', 'text' => 'Titlul postării este obligatoriu.'];
    } elseif (empty($post->community_id) || !is_numeric($post->community_id)) {
        $message = ['type' => 'error', 'text' => 'Trebuie să selectezi o comunitate.'];
    } elseif ($post->type === 'text' && empty($post->content)) {
        $message = ['type' => 'error', 'text' => 'Conținutul postării text nu poate fi gol.'];
    } elseif ($post->type === 'link' && (empty($post->url) || !filter_var($post->url, FILTER_VALIDATE_URL))) {
        $message = ['type' => 'error', 'text' => 'URL-ul este obligatoriu și trebuie să fie valid pentru postările de tip link.'];
    } elseif ($post->type === 'image' && empty($post->cover_image_url)) {
        // Mesajul de eroare specific pentru imagine va fi setat deja mai sus
    }
    else {
        if ($post->create()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Postarea a fost creată cu succes!'];
            header("Location: index.php");
            exit();
        } else {
            $message = ['type' => 'error', 'text' => 'A apărut o eroare la crearea postării.'];
            error_log("Eroare la crearea postării: " . implode(" ", $db->errorInfo()));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creează o postare - Reddit Local</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
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
                <h1>Creează o postare nouă</h1>
            </div>

            <?php
            if ($message) {
                $message_type = $message['type'];
                $message_text = $message['text'];
                echo "<div class='message {$message_type}'>{$message_text}</div>";
            }
            ?>

            <!-- Adăugăm enctype pentru a permite încărcarea fișierelor -->
            <form action="create_post.php" method="POST" class="post-form" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="post_title">Titlu postare:</label>
                    <input type="text" id="post_title" name="post_title" required placeholder="Un titlu scurt și clar">
                </div>

                <div class="form-group">
                    <label for="community_id">Alege o comunitate:</label>
                    <select id="community_id" name="community_id" required>
                        <option value="">-- Selectează comunitatea --</option>
                        <?php foreach ($communities_list as $comm): ?>
                            <option value="<?php echo htmlspecialchars($comm['id']); ?>">r/<?php echo htmlspecialchars($comm['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="post_type">Tipul postării:</label>
                    <select id="post_type" name="post_type" required>
                        <option value="text">Text</option>
                        <option value="link">Link</option>
                        <option value="image">Imagine</option> <!-- Noul tip de postare -->
                    </select>
                </div>

                <div id="text_content_group" class="form-group">
                    <label for="post_content">Conținut text:</label>
                    <textarea id="post_content" name="post_content" placeholder="Scrie ceva..."></textarea>
                </div>

                <div id="link_url_group" class="form-group" style="display: none;">
                    <label for="post_url">URL:</label>
                    <input type="url" id="post_url" name="post_url" placeholder="Ex: https://example.com/articol">
                </div>

                <!-- Noul câmp pentru încărcarea imaginii de copertă -->
                <div id="image_upload_group" class="form-group" style="display: none;">
                    <label for="cover_image">Imagine de copertă:</label>
                    <input type="file" id="cover_image" name="cover_image" accept="image/jpeg, image/png, image/gif">
                    <p class="form-help-text">Fișiere permise: JPG, PNG, GIF. Max 2MB.</p>
                </div>

                <!-- Noul câmp pentru textul asociat imaginii -->
                <div id="image_text_content_group" class="form-group" style="display: none;">
                    <label for="image_post_content">Text (opțional) pentru imagine:</label>
                    <textarea id="image_post_content" name="image_post_content" placeholder="Adaugă o descriere sau un text pentru imaginea ta..."></textarea>
                    <p class="form-help-text">Acest text va apărea sub imagine în detaliile postării.</p>
                </div>

                <button type="submit" name="create_post">Postează</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const postTypeSelect = document.getElementById('post_type');
            const textContentGroup = document.getElementById('text_content_group');
            const linkUrlGroup = document.getElementById('link_url_group');
            const imageUploadGroup = document.getElementById('image_upload_group');
            const imageTextContentGroup = document.getElementById('image_text_content_group'); // Noul element HTML

            function togglePostTypeFields() {
                // Ascunde toate grupurile și elimină 'required'
                textContentGroup.style.display = 'none';
                linkUrlGroup.style.display = 'none';
                imageUploadGroup.style.display = 'none';
                imageTextContentGroup.style.display = 'none';

                textContentGroup.querySelector('textarea').removeAttribute('required');
                linkUrlGroup.querySelector('input').removeAttribute('required');
                imageUploadGroup.querySelector('input').removeAttribute('required');
                // imageTextContentGroup.querySelector('textarea').removeAttribute('required'); // Nu este required implicit

                // Afișează grupul corect și adaugă 'required'
                if (postTypeSelect.value === 'text') {
                    textContentGroup.style.display = 'block';
                    textContentGroup.querySelector('textarea').setAttribute('required', 'required');
                } else if (postTypeSelect.value === 'link') {
                    linkUrlGroup.style.display = 'block';
                    linkUrlGroup.querySelector('input').setAttribute('required', 'required');
                } else if (postTypeSelect.value === 'image') {
                    imageUploadGroup.style.display = 'block';
                    imageUploadGroup.querySelector('input').setAttribute('required', 'required');
                    imageTextContentGroup.style.display = 'block'; // Afișează câmpul text opțional pentru imagini
                }
            }

            // Apelează la încărcarea paginii și la schimbarea selectului
            togglePostTypeFields();
            postTypeSelect.addEventListener('change', togglePostTypeFields);

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
        });
    </script>
</body>
</html>
