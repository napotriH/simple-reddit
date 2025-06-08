<?php
// public_html/public/communities.php

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
require_once __DIR__ . '/../app/models/Community.php';
require_once __DIR__ . '/../app/models/Subscription.php'; // Includem noul model Subscription

// Obținem o conexiune la baza de date
$database = new Database();
$db = $database->getConnection();

$community = new Community($db);
$subscription = new Subscription($db); // Instanțiem modelul Subscription

$message = null; // Inițializăm variabila pentru mesaje

// Procesăm trimiterea formularului pentru crearea unei comunități
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_community'])) {
    $community->name = trim($_POST['community_name'] ?? ''); // Curățăm spațiile albe
    $community->description = trim($_POST['community_description'] ?? ''); // Curățăm spațiile albe
    $community->created_by_user_id = $_SESSION['user_id'];

    // Validare
    if (empty($community->name)) {
        $message = ['type' => 'error', 'text' => 'Numele comunității este obligatoriu.'];
    } elseif ($community->exists($community->name)) {
        $message = ['type' => 'error', 'text' => 'O comunitate cu acest nume există deja.'];
    } else {
        if ($community->create()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Comunitatea a fost creată cu succes!'];
            header("Location: communities.php"); // Redirecționăm pentru a preveni re-trimiterea formularului
            exit();
        } else {
            $message = ['type' => 'error', 'text' => 'A apărut o eroare la crearea comunității.'];
            error_log("Eroare la crearea comunității: " . implode(" ", $db->errorInfo()));
        }
    }
}

// Preluăm toate comunitățile pentru afișare
$communities_stmt = $community->readAll();
$communities_list = [];
while ($row = $communities_stmt->fetch(PDO::FETCH_ASSOC)) {
    // Verificăm dacă utilizatorul curent este abonat la această comunitate
    $subscription->user_id = $_SESSION['user_id'];
    $subscription->community_id = $row['id'];
    $row['is_subscribed'] = $subscription->isSubscribed();
    $communities_list[] = $row;
}

// Preluăm mesajele de sesiune (după redirecționare)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunități - Reddit Local</title>
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
            <!-- Antetul paginii (acum specific paginii, nu global) -->
            <div class="page-header">
                <h1>Comunități Reddit Local</h1>
            </div>

            <?php
            // Afișăm mesajele de succes sau eroare
            if ($message) {
                $message_type = $message['type'];
                $message_text = $message['text'];
                echo "<div class='message {$message_type}'>{$message_text}</div>";
            }
            ?>

            <div class="community-form-section">
                <h3>Creează o comunitate nouă</h3>
                <form action="communities.php" method="POST" class="community-form">
                    <div class="form-group">
                        <label for="community_name">Nume comunitate (fără spații, ex: OrasulMeu):</label>
                        <input type="text" id="community_name" name="community_name" required placeholder="Ex: r/NumeComunitate">
                    </div>
                    <div class="form-group">
                        <label for="community_description">Descriere:</label>
                        <textarea id="community_description" name="community_description" placeholder="O scurtă descriere a comunității..."></textarea>
                    </div>
                    <button type="submit" name="create_community">Creează comunitate</button>
                </form>
            </div>

            <div class="community-list-section">
                <h3>Comunități existente</h3>
                <?php if (!empty($communities_list)): ?>
                    <div class="community-list">
                        <?php foreach ($communities_list as $comm): ?>
                            <div class="community-item" data-community-id="<?php echo htmlspecialchars($comm['id']); ?>">
                                <h4>
                                    <a href="community_detail.php?id=<?php echo htmlspecialchars($comm['id']); ?>">
                                        r/<?php echo htmlspecialchars($comm['name']); ?>
                                    </a>
                                </h4>
                                <p><?php echo nl2br(htmlspecialchars($comm['description'])); ?></p>
                                <span class="meta">Creată la: <?php echo htmlspecialchars($comm['created_at']); ?></span>
                                <button class="subscribe-button <?php echo $comm['is_subscribed'] ? 'subscribed' : ''; ?>"
                                        data-action="<?php echo $comm['is_subscribed'] ? 'unsubscribe' : 'subscribe'; ?>">
                                    <?php echo $comm['is_subscribed'] ? 'Dezabonează-te' : 'Abonează-te'; ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-communities">Nu există comunități încă. Fii primul care <a href="#create-community-form">creează una</a>!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const subscribeButtons = document.querySelectorAll('.subscribe-button');

            subscribeButtons.forEach(button => {
                button.addEventListener('click', async function() {
                    const communityItem = this.closest('.community-item');
                    const communityId = communityItem.dataset.communityId;
                    let action = this.dataset.action; // 'subscribe' sau 'unsubscribe'

                    try {
                        const response = await fetch('api/toggle_subscription.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
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
            });

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
