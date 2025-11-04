<?php
require_once __DIR__ . '/utils/authguard.php';

/* ====== AUTH GUARD ====== */
if (empty($_SESSION['user_id'])) {
  redirect('auth.php?login_required=1');
}
$userId = (int) $_SESSION['user_id'];

/* ====== CSRF (simple) ====== */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

/* ====== DB CONNECTION ====== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = db();
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+08:00'");

/* ====== HELPERS (page-local) ====== */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function badgeClass($status) {
  $k = strtolower((string)$status);
  return match ($k) {
    'confirmed' => 'badge badge--confirmed',
    'completed' => 'badge badge--completed',
    'cancelled' => 'badge badge--cancelled',
    default     => 'badge',
  };
}

/* ====== HANDLE POST ACTIONS (cancel/complete) ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['appointmentId'], $_POST['csrf'])) {
  // CSRF
  if (!hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
    http_response_code(400);
    exit('Bad request (CSRF).');
  }

  $action        = strtolower(trim((string)$_POST['action']));
  $appointmentId = (int) $_POST['appointmentId'];
  if (!$appointmentId || !in_array($action, ['cancel','complete'], true)) {
    redirect('dashboard.php');
  }

  // Who am I?
  $stmt = $conn->prepare("SELECT is_doctor FROM users WHERE id = ?");
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();
  $isDoctor = $row && ((int)$row['is_doctor'] === 1);

  // Load appointment
  $stmt = $conn->prepare("SELECT id, patient_user_id, doctor_user_id, status FROM appointments WHERE id = ?");
  $stmt->bind_param('i', $appointmentId);
  $stmt->execute();
  $appt = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($appt) {
    $status = strtolower((string)$appt['status']);
    $nextStatus = null;

    if ($action === 'cancel') {
      // Patient can cancel their own confirmed appointment
      if (!$isDoctor && (int)$appt['patient_user_id'] === $userId && $status === 'confirmed') {
        $nextStatus = 'cancelled';
      }
    } elseif ($action === 'complete') {
      // Doctor can complete their own confirmed appointment
      if ($isDoctor && (int)$appt['doctor_user_id'] === $userId && $status === 'confirmed') {
        $nextStatus = 'completed';
      }
    }

    if ($nextStatus) {
      $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
      $stmt->bind_param('si', $nextStatus, $appointmentId);
      $stmt->execute();
      $stmt->close();
      redirect('dashboard.php?updated=1');
    } else {
      redirect('dashboard.php?error=forbidden');
    }
  } else {
    redirect('dashboard.php?error=not_found');
  }
}

/* ====== USER (PATIENT/DOCTOR) INFO ====== */
$sqlUser = "
  SELECT
      id,
      is_doctor,
      CONCAT_WS(' ', first_name, last_name) AS full_name,
      email,
      phone,
      DATE_FORMAT(dob, '%d/%m/%Y') AS dob_fmt,
      avatar_url
  FROM users
  WHERE id = ?
";
$stmt = $conn->prepare($sqlUser);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$me = $res->fetch_assoc() ?: [];
$stmt->close();

$isDoctor = !empty($me) && ((int)$me['is_doctor'] === 1);

/* ====== APPOINTMENT HISTORY (role-aware) ====== */
$appointments = [];

if ($isDoctor) {
  // Doctor view: show patients
  $sqlAppts = "
    SELECT
      a.id,
      DATE_FORMAT(a.appt_date, '%d/%m/%Y') AS date_fmt,
      DATE_FORMAT(a.appt_time, '%H:%i')    AS time_fmt,
      a.status,
      CONCAT_WS(' ', pu.first_name, pu.last_name) AS counterpart_name
    FROM appointments a
    JOIN users pu ON pu.id = a.patient_user_id
    WHERE a.doctor_user_id = ?
    ORDER BY a.appt_date DESC, a.appt_time DESC
  ";
} else {
  // Patient view: show doctors
  $sqlAppts = "
    SELECT
      a.id,
      DATE_FORMAT(a.appt_date, '%d/%m/%Y') AS date_fmt,
      DATE_FORMAT(a.appt_time, '%H:%i')    AS time_fmt,
      a.status,
      CONCAT_WS(' ', du.first_name, du.last_name) AS counterpart_name
    FROM appointments a
    JOIN users du ON du.id = a.doctor_user_id
    WHERE a.patient_user_id = ?
    ORDER BY a.appt_date DESC, a.appt_time DESC
  ";
}
$stmt = $conn->prepare($sqlAppts);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $appointments[] = $row;
}
$stmt->close();

$firstColHeader = $isDoctor ? 'Patient' : 'Doctor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Dashboard • BrightSmile</title>

  <link rel="stylesheet" href="css/root.css" />
  <link rel="stylesheet" href="css/dashboard.css" />
  <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet" />

</head>
<body>
  <?php require_once __DIR__ . '/components/navbar.php'; ?>
  <!-- DASHBOARD SECTION -->
  <section class="general dashboard">
    <h1 class="dash-title">My <span class="highlight">Dashboard</span></h1>

    <?php if (isset($_GET['updated'])): ?>
      <div class="flash">Appointment updated.</div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'forbidden'): ?>
      <div class="flash error">You’re not allowed to perform that action.</div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'not_found'): ?>
      <div class="flash error">Appointment not found.</div>
    <?php endif; ?>

    <!-- User Information -->
    <div class="section-card patient-card">
      <p class="section-title"><?= $isDoctor ? 'Doctor Information' : 'Patient Information' ?></p>
      <div class="patient-grid">
        <div class="avatar">
          <?php if (!empty($me['avatar_url'])): ?>
            <img src="<?= e($me['avatar_url']) ?>" alt="Profile photo">
          <?php else: ?>
            <img src="assets/images/none.png" alt="" class="avatar-placeholder">
          <?php endif; ?>
        </div>

        <ul class="patient-fields">
          <li><span class="label">Name:</span> <span><?= e($me['full_name'] ?? '—') ?></span></li>
          <li><span class="label">Email:</span> <span><?= e($me['email'] ?? '—') ?></span></li>
          <li><span class="label">Phone:</span> <span><?= e($me['phone'] ?? '—') ?></span></li>
          <li><span class="label">Date of Birth:</span> <span><?= e($me['dob_fmt'] ?? '—') ?></span></li>
        </ul>

        <img src="assets/icons/logo.svg" alt="Logo" />
      </div>
    </div>

    <!-- Appointment History -->
    <div class="section-card history-card">
      <p class="section-title">Appointment History</p>

      <div class="table-wrap">
        <table class="appt-table">
          <colgroup>
            <col style="width:160px">
            <col style="width:120px">
            <col style="width:90px">
            <col style="width:120px">
            <col>
          </colgroup>
          <thead>
            <tr>
              <th><?= e($firstColHeader) ?></th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
              <th class="actions-col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$appointments): ?>
              <tr>
                <td colspan="5" class="empty-row">No appointments found.</td>
              </tr>
            <?php else: foreach ($appointments as $a): ?>
              <tr>
                <td><?= e($a['counterpart_name'] ?? '—') ?></td>
                <td><?= e($a['date_fmt']) ?></td>
                <td><?= e($a['time_fmt']) ?></td>
                <td><span class="<?= badgeClass($a['status']) ?>"><?= e(ucfirst($a['status'])) ?></span></td>
                <td class="actions">
                  <?php $isConfirmed = strtolower($a['status']) === 'confirmed'; ?>
                  <?php if ($isConfirmed): ?>
                    <!-- Reschedule for both -->
                    <a class="btn-base btn-sm" href="appointment.php?appointmentId=<?= (int)$a['id'] ?>">Reschedule</a>

                    <?php if ($isDoctor): ?>
                      <!-- Doctor: mark as Completed -->
                      <form action="" method="post">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="appointmentId" value="<?= (int)$a['id'] ?>">
                        <input type="hidden" name="action" value="complete">
                        <button type="submit" class="btn-base btn-sm btn-complete">Complete</button>
                      </form>
                    <?php else: ?>
                      <!-- Patient: Cancel -->
                      <form action="" method="post" onsubmit="return confirm('Cancel this appointment?');">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="appointmentId" value="<?= (int)$a['id'] ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn-base btn-sm btn-danger">Cancel</button>
                      </form>
                    <?php endif; ?>

                  <?php else: ?>
                    <span class="muted">--</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <?php require __DIR__ . '/components/footer.php'; ?>
</body>
</html>
