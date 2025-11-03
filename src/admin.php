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
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" 
          rel="stylesheet" />
</head>
<body>
<?php include __DIR__ . '/components/navbar.php'; ?>

<div class="admin-container">
    <h1>Admin Panel</h1>

    <!-- ============================= -->
    <!--        ADD DOCTOR FORM        -->
    <!-- ============================= -->
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

    <!-- ============================= -->
    <!--      EXISTING DOCTOR LIST     -->
    <!-- ============================= -->
    <section class="doctor-list">
        <h2>Existing Doctors</h2>

        <?php if (count($doctors) === 0): ?>
            <p>No doctors found.</p>
        <?php else: ?>
            <?php foreach ($doctors as $doc): ?>
                <div class="doctor-card">
                    <h3>
                        <?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?>
                    </h3>
                    <p><b>Email:</b> <?= htmlspecialchars($doc['email']) ?></p>

                    <form method="POST" class="form-inline">
                        <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">

                        <label>First Name</label>
                        <input type="text" name="first_name" 
                               value="<?= htmlspecialchars($doc['first_name'] ?? '') ?>" required>

                        <label>Last Name</label>
                        <input type="text" name="last_name" 
                               value="<?= htmlspecialchars($doc['last_name'] ?? '') ?>" required>

                        <label>Specialization</label>
                        <input type="text" name="specialization" 
                               value="<?= htmlspecialchars($doc['specialization'] ?? '') ?>" required>

                        <label>Bio</label>
                        <textarea name="bio"><?= htmlspecialchars($doc['bio'] ?? '') ?></textarea>

                        <div class="button-group">
                            <button type="submit" name="edit_doctor" class="btn-edit">Save Changes</button>
                            <button type="submit" name="remove_doctor" class="btn-danger">Remove</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

</body>
</html>
