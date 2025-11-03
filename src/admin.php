<?php
require_once __DIR__ . '/utils/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$conn = db();
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
if (empty($user['is_admin'])) {
    echo "<h2>Access denied.</h2>";
    exit;
}

/* -------------------- ADD DOCTOR -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $email = trim($_POST['email']);
    $first = trim($_POST['first_name']);
    $last = trim($_POST['last_name']);
    $spec = trim($_POST['specialization']);
    $bio = trim($_POST['bio']);
    $password = password_hash('123456', PASSWORD_ARGON2ID);

    $stmt = $conn->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, is_doctor)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->bind_param('ssss', $email, $password, $first, $last);
    $stmt->execute();
    $newId = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO doctors (user_id, specialization, bio) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $newId, $spec, $bio);
    $stmt->execute();
    $stmt->close();
}

/* -------------------- EDIT DOCTOR -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_doctor'])) {
    $docId = (int) $_POST['doctor_id'];
    $first = trim($_POST['first_name']);
    $last = trim($_POST['last_name']);
    $spec = trim($_POST['specialization']);
    $bio = trim($_POST['bio']);

    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
    $stmt->bind_param('ssi', $first, $last, $docId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE doctors SET specialization = ?, bio = ? WHERE user_id = ?");
    $stmt->bind_param('ssi', $spec, $bio, $docId);
    $stmt->execute();
    $stmt->close();
}

/* -------------------- REMOVE DOCTOR -------------------- */
if (isset($_POST['remove_doctor'])) {
    $docId = (int) $_POST['doctor_id'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $docId);
    $stmt->execute();
}

/* -------------------- FETCH DOCTORS -------------------- */
$doctors = $conn->query("
    SELECT 
        u.id, 
        u.email, 
        u.first_name, 
        u.last_name,
        d.specialization, 
        d.bio
    FROM users u
    JOIN doctors d ON d.user_id = u.id
    ORDER BY u.id DESC
")->fetch_all(MYSQLI_ASSOC);

/* -------------------- VIEW APPOINTMENTS -------------------- */
$limit = 10; // appointments per page
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// count total appointments
$totalRes = $conn->query("SELECT COUNT(*) AS total FROM appointments");
$total = $totalRes->fetch_assoc()['total'];
$totalPages = ceil($total / $limit);

// fetch paginated appointments with patient + doctor info
$stmt = $conn->prepare("
    SELECT 
        a.id,
        a.appt_date,
        a.appt_time,
        a.status,
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
        CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
        doc.specialization
    FROM appointments a
    JOIN users p ON p.id = a.patient_user_id
    JOIN doctors doc ON doc.user_id = a.doctor_user_id
    JOIN users d ON d.id = doc.user_id
    ORDER BY a.appt_date DESC, a.appt_time DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="css/root.css">
    <link rel="stylesheet" href="css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>

<body>
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <div class="admin-container">
        <h1>Admin Panel</h1>

        <!-- =============================== -->
        <!-- TABS NAVIGATION -->
        <!-- =============================== -->
        <div class="tab-container">
            <div class="tab-buttons">
                <button class="tab-button active" data-tab="add-doctor">Add Doctor</button>
                <button class="tab-button" data-tab="manage-doctors">Manage Doctors</button>
                <button class="tab-button" data-tab="appointments">Appointments</button>
            </div>

            <!-- ============================= -->
            <!-- TAB: ADD DOCTOR -->
            <!-- ============================= -->
            <div id="add-doctor" class="tab-content active">
                <section class="add-doctor">
                    <h2>Add Doctor</h2>
                    <form method="POST" class="form">
                        <input type="hidden" name="add_doctor" value="1">

                        <label>Email</label>
                        <input type="email" name="email" required>

                        <label>First Name</label>
                        <input type="text" name="first_name" required>

                        <label>Last Name</label>
                        <input type="text" name="last_name" required>

                        <label>Specialization</label>
                        <input type="text" name="specialization" required>

                        <label>Bio</label>
                        <textarea name="bio" required></textarea>

                        <button type="submit" class="btn-base">Add Doctor</button>
                    </form>
                </section>
            </div>

            <!-- ============================= -->
            <!-- TAB: MANAGE DOCTORS -->
            <!-- ============================= -->
            <div id="manage-doctors" class="tab-content">
                <section class="doctor-list">
                    <h2>Existing Doctors</h2>

                    <?php if (count($doctors) === 0): ?>
                        <p>No doctors found.</p>
                    <?php else: ?>
                        <?php foreach ($doctors as $doc): ?>
                            <div class="doctor-card">
                                <h3><?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?></h3>

                                <form method="POST" class="form-inline">
                                    <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">

                                    <label>Email</label>
                                    <input type="email" value="<?= htmlspecialchars($doc['email']) ?>" readonly>

                                    <label>First Name</label>
                                    <input type="text" name="first_name"
                                        value="<?= htmlspecialchars($doc['first_name'] ?? '') ?>" required>

                                    <label>Last Name</label>
                                    <input type="text" name="last_name" value="<?= htmlspecialchars($doc['last_name'] ?? '') ?>"
                                        required>

                                    <label>Specialization</label>
                                    <input type="text" name="specialization"
                                        value="<?= htmlspecialchars($doc['specialization'] ?? '') ?>" required>

                                    <label>Bio</label>
                                    <textarea name="bio"><?= htmlspecialchars($doc['bio'] ?? '') ?></textarea>

                                    <div class="button-group">
                                        <button type="submit" name="edit_doctor" class="btn-edit">Save Changes</button>
                                        <button type="submit" name="remove_doctor"
                                            class="btn-danger btn-delete-doctor">Remove</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            </div>

            <!-- ============================= -->
            <!-- TAB: APPOINTMENTS -->
            <!-- ============================= -->
            <div id="appointments" class="tab-content">
                <section class="appointments">
                    <h2>Appointments</h2>

                    <?php if (count($appointments) === 0): ?>
                        <p>No appointments found.</p>
                    <?php else: ?>
                        <table class="appt-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Specialization</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appt): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($appt['id']) ?></td>
                                        <td><?= htmlspecialchars($appt['appt_date']) ?></td>
                                        <td><?= htmlspecialchars($appt['appt_time']) ?></td>
                                        <td><?= htmlspecialchars($appt['patient_name']) ?></td>
                                        <td><?= htmlspecialchars($appt['doctor_name']) ?></td>
                                        <td><?= htmlspecialchars($appt['specialization']) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($appt['status'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>#appointments" class="btn-base">Prev</a>
                            <?php endif; ?>

                            <span>Page <?= $page ?> of <?= $totalPages ?></span>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>#appointments" class="btn-base">Next</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>

    <script>
        const buttons = document.querySelectorAll('.tab-button');
        const contents = document.querySelectorAll('.tab-content');

        function activateTab(tabId) {
            buttons.forEach(b => b.classList.toggle('active', b.dataset.tab === tabId));
            contents.forEach(c => c.classList.toggle('active', c.id === tabId));
        }

        // Read tab from URL hash (#appointments)
        const currentTab = window.location.hash ? window.location.hash.substring(1) : 'add-doctor';
        activateTab(currentTab);

        // Handle tab switching
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.dataset.tab;
                activateTab(tabId);
                history.replaceState(null, '', '#' + tabId);
            });
        });

        // ===============================
        // Confirm before deleting doctor
        // ===============================
        document.querySelectorAll('.btn-delete-doctor').forEach(button => {
            button.addEventListener('click', function (e) {
                const confirmed = confirm('Are you sure you want to delete this doctor? This action cannot be undone.');
                if (!confirmed) {
                    e.preventDefault(); // stop form submission
                }
            });
        });
    </script>



</body>

</html>