<?php
declare(strict_types=1);
require_once __DIR__ . '/utils/bootstrap.php';
require_once __DIR__ . '/components/navbar.php';

// Create DB connection
$conn = db();

// Fetch doctors (join users and doctors tables)
$sql = "
  SELECT 
    users.id AS user_id,
    users.first_name,
    users.last_name,
    users.avatar_url,
    doctors.specialization,
    doctors.bio
  FROM users
  INNER JOIN doctors ON users.id = doctors.user_id
  WHERE users.is_doctor = 1
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Doctors</title>
  <link rel="stylesheet" href="css/doctors.css" />
  <link rel="stylesheet" href="css/root.css" />
  <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>

<body>

  <!-- Hero Section -->
  <section class="hero">
    <h1>Meet Our Expert Team</h1>
    <p>Our highly skilled dental professionals are committed to providing<br>
      exceptional care with the latest techniques.</p>
  </section>

  <!-- Doctors Section -->
  <section class="team">
    <?php if ($result && $result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <?php
        $doctorId = (int) $row['user_id'];
        $name = 'Dr ' . htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
        $specialty = htmlspecialchars((string) ($row['specialization'] ?? ''));
        $bio = htmlspecialchars((string) ($row['bio'] ?? ''));
        $avatarUrl = htmlspecialchars((string) ($row['avatar_url'] ?? ''));

        // Fallback if no image set
        if (empty($avatarUrl) || !file_exists($avatarUrl)) {
          $avatarUrl = 'assets/images/default-doctor.png';
        }
        ?>
        <div class="card" data-name="<?= $name ?>" data-specialty="<?= $specialty ?>" data-description="<?= $bio ?>">
          <img src="<?= $avatarUrl ?>" alt="<?= $name ?>">
          <h3><?= $name ?></h3>
          <p><?= $specialty ?></p>
          <div class="actions">
            <button class="btn-base btn-info">Info</button>
            <a href="appointment.php?doctor=<?= $doctorId ?>" class="btn-base btn-book">Book</a>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p>No doctors found.</p>
    <?php endif; ?>
  </section>

  <!-- Modal -->
  <div id="doctorModal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Doctor Info</h2>
      <div class="modal-body">
        <div class="modal-left">
          <img id="modalImage" src="" alt="Doctor">
          <h3 id="modalName"></h3>
          <p id="modalSpecialty"></p>
        </div>
        <div class="modal-right">
          <p id="modalDescription"></p>
          <a id="modalBookLink" href="appointment.php" class="btn-base btn-book">Book</a>
        </div>
      </div>
    </div>
  </div>

  <?php require __DIR__ . '/components/footer.php'; ?>
  <script src="js/doctors.js"></script>
</body>

</html>