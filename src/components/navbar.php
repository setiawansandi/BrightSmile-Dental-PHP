<?php
require_once __DIR__ . '/../utils/bootstrap.php'; // session + db + redirect

// Tiny escape helper
if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

$user = null;
$notifications = [];
$unread_count = 0;

if (!empty($_SESSION['user_id'])) {
    $conn = db();

    // --- Fetch User Info ---
    $stmt_user = $conn->prepare("
        SELECT CONCAT_WS(' ', first_name, last_name) AS full_name,
               COALESCE(avatar_url, '') AS avatar_url,
               is_admin
        FROM users
        WHERE id = ?
    ");
    $stmt_user->bind_param('i', $_SESSION['user_id']);
    $stmt_user->execute();
    $res_user = $stmt_user->get_result();
    $user = $res_user->fetch_assoc() ?: null;
    $stmt_user->close();

    // --- Fetch Notifications ---
    $current_user_id = $_SESSION['user_id'];
    $stmt_notif = $conn->prepare("
        SELECT 
            n.id,
            n.action_type,
            n.is_read,
            n.created_at,
            n.appointment_id,
            a.appt_date,
            a.appt_time,
            -- Get the name of the person who *caused* the notification
            CONCAT_WS(' ', actor.first_name, actor.last_name) AS actor_name
        FROM notifications AS n
        LEFT JOIN users AS actor ON n.actor_id = actor.id
        LEFT JOIN appointments AS a ON n.appointment_id = a.id
        WHERE n.recipient_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt_notif->bind_param('i', $current_user_id);
    $stmt_notif->execute();
    $res_notif = $stmt_notif->get_result();
    while ($row = $res_notif->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt_notif->close();

    // --- Count Unread Notifications ---
    $stmt_count = $conn->prepare("
        SELECT COUNT(id) AS unread_count 
        FROM notifications 
        WHERE recipient_id = ? AND is_read = 0
    ");
    $stmt_count->bind_param('i', $current_user_id);
    $stmt_count->execute();
    $res_count = $stmt_count->get_result();
    $unread_count = $res_count->fetch_assoc()['unread_count'] ?? 0;
    $stmt_count->close();

    $conn->close();
}

// Defaults if no avatar/name
$displayName = $user['full_name'] ?? 'Account';
$avatarUrl = !empty($user['avatar_url']) ? $user['avatar_url'] : 'assets/images/none.png';
$isAdmin = !empty($user['is_admin']);

function format_notification($notif)
{
    $actor = '<strong>' . e($notif['actor_name']) . '</strong>';
    $time = $notif['appt_date'] ? ' on ' . e(date('d M Y', strtotime($notif['appt_date']))) : '';

    switch ($notif['action_type']) {
        case 'booked':
            return "$actor booked an appointment with you$time.";
        case 'rescheduled':
            return "$actor rescheduled your appointment$time.";
        case 'canceled':
            return "$actor canceled an appointment$time.";
        case 'completed':
            return "Your appointment with $actor$time is completed.";
        default:
            return 'You have a new notification.';
    }
}

function format_relative_time($datetime_string)
{
    if (empty($datetime_string)) {
        return '';
    }

    try {
        $notif_time = new DateTime($datetime_string);
        $now = new DateTime(); // Current time
        $interval = $now->diff($notif_time);

        if ($interval->days > 6) {
            return $notif_time->format('d M, g:i a');
        } elseif ($interval->d > 0) {
            return $interval->d . ($interval->d == 1 ? ' day' : ' days') . ' ago';
        } elseif ($interval->h > 0) {
            return $interval->h . ($interval->h == 1 ? ' hour' : ' hours') . ' ago';
        } elseif ($interval->i > 0) {
            return $interval->i . ($interval->i == 1 ? ' minute' : ' minutes') . ' ago';
        } else {
            return 'Just now';
        }
    } catch (Exception $e) {
        return date('d M, g:i a', strtotime($datetime_string));
    }
}
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
            <a href="auth.php" class="btn-base btn-login">Login</a>
        <?php else: ?>
            <div class="user-actions">

                <div class="inbox-menu">
                    <a href="#" id="inbox-trigger" class="inbox-icon-link" aria-haspopup="menu" aria-expanded="false"
                        data-unread-count="<?= $unread_count ?>">
                        <svg width="25" height="26" viewBox="0 0 25 26" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M12.1958 0C7.01975 0 2.82372 4.19603 2.82372 9.37209V10.2236C2.82372 11.0663 2.57425 11.8903 2.10676 12.5915L0.717746 14.675C-0.894363 17.0932 0.336356 20.3801 3.14023 21.1448C4.05399 21.394 4.97547 21.6048 5.90229 21.7772L5.90459 21.7834C6.83453 24.2648 9.32002 26 12.1958 26C15.0715 26 17.557 24.2648 18.4869 21.7834L18.4892 21.7772C19.416 21.6048 20.3376 21.394 21.2514 21.1448C24.0553 20.3801 25.286 17.0932 23.6739 14.675L22.2849 12.5915C21.8173 11.8903 21.5679 11.0663 21.5679 10.2236V9.37209C21.5679 4.19603 17.3718 0 12.1958 0ZM16.2789 22.1145C13.5665 22.4386 10.8249 22.4385 8.11255 22.1144C8.9723 23.3498 10.4677 24.186 12.1958 24.186C13.9237 24.186 15.4192 23.3498 16.2789 22.1145ZM4.63768 9.37209C4.63768 5.19785 8.02157 1.81395 12.1958 1.81395C16.37 1.81395 19.7539 5.19785 19.7539 9.37209V10.2236C19.7539 11.4245 20.1094 12.5985 20.7755 13.5978L22.1645 15.6813C23.0899 17.0692 22.3835 18.9558 20.7741 19.3948C15.1578 20.9265 9.23382 20.9265 3.61751 19.3948C2.00813 18.9558 1.30173 17.0692 2.22705 15.6813L3.61606 13.5978C4.2822 12.5985 4.63768 11.4245 4.63768 10.2236V9.37209Z"
                                fill="currentColor" />
                        </svg>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-dot"></span>
                        <?php endif; ?>
                    </a>
                    <div class="inbox-dropdown" role="menu" aria-label="Notifications">
                        <div class="inbox-dropdown-header">
                            <h3>Notifications</h3>
                        </div>
                        <ul class="inbox-list">
                            <?php if (empty($notifications)): ?>
                                <li class="inbox-list-empty">
                                    <p>All caught up! No new activity.</p>
                                </li>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <li class="inbox-list-item <?php echo $notif['is_read'] ? '' : 'is-unread'; ?>"
                                        data-notification-id="<?= e($notif['id']) ?>">

                                        <?php if ($notif['action_type'] == 'completed'): ?>
                                            <img src="assets/icons/calendar-check.svg" alt="" class="item-icon">
                                        <?php elseif ($notif['action_type'] == 'canceled'): ?>
                                            <img src="assets/icons/calendar-x.svg" alt="" class="item-icon">
                                        <?php elseif ($notif['action_type'] == 'rescheduled'): ?>
                                            <img src="assets/icons/calendar-clock.svg" alt="" class="item-icon">
                                        <?php else:
                                            ?>
                                            <img src="assets/icons/calendar.svg" alt="" class="item-icon">
                                        <?php endif; ?>

                                        <p class="item-text">
                                            <?php echo format_notification($notif); ?>
                                            <span
                                                class="item-time"><?php echo e(format_relative_time($notif['created_at'])); ?></span>
                                        </p>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

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
                        <form action="utils/logout.php" method="post">
                            <button type="submit" class="logout-link" role="menuitem">Logout</button>
                        </form>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </div>
</div>

<script src="js/notifications.js"></script>