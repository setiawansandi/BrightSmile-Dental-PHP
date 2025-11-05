<?php
// src/components/navbar.php
require_once __DIR__ . '/../utils/bootstrap.php'; // session + db + redirect

// Tiny escape helper
if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

$user = null;
if (!empty($_SESSION['user_id'])) {
    // Fetch minimal display info for the logged-in user
    $conn = db();
    $stmt = $conn->prepare("
      SELECT CONCAT_WS(' ', first_name, last_name) AS full_name,
             COALESCE(avatar_url, '') AS avatar_url,
             is_admin
      FROM users
      WHERE id = ?
  ");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc() ?: null;
    $stmt->close();
    $conn->close();
}

// Defaults if no avatar/name
$displayName = $user['full_name'] ?? 'Account';
$avatarUrl = !empty($user['avatar_url']) ? $user['avatar_url'] : 'assets/images/none.png';
$isAdmin = !empty($user['is_admin']);
?>

<div class="navbar-container">
    <div class="general navbar">
        <a href="index.php" class="logo" aria-label="BrightSmile home">
            <img src="assets/icons/logo.svg" alt="Logo" />
            <span>BrightSmile</span>
        </a>

        <nav>
            <a href="index.php">Home</a>
            <a href="appointment.php">Appointment</a>
            <a href="doctors.php">Doctors</a>
            <a href="services.php">Services</a>
            <a href="about.php">About</a>
        </nav>

        <?php if (empty($_SESSION['user_id'])): ?>
            <!-- Logged OUT -->
            <a href="auth.php" class="btn-base btn-login">Login</a>
        <?php else: ?>
            <!-- Logged IN -->
            <div class="user-menu">
                <a href="#" class="user-profile-trigger" aria-haspopup="menu" aria-expanded="false">
                    <img src="<?= e($avatarUrl) ?>" alt="" class="profile-pic">
                    <span class="user-name"><?= e($displayName) ?></span>
                    <img src="assets/icons/dropdown-arrow-black.svg" class="dropdown-arrow-icon" alt="">
                </a>

                <div class="user-dropdown" role="menu" aria-label="User menu">
                    <a href="dashboard.php" role="menuitem">Dashboard</a>

                    <?php if ($isAdmin): ?>
                        <a href="admin.php" role="menuitem">Admin Panel</a>
                    <?php endif; ?>

                    <!-- Logout via POST -->
                    <form action="utils/logout.php" method="post">
                        <button type="submit" class="logout-link" role="menuitem">Logout</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>