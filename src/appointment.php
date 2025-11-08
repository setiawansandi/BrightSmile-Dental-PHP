<?php
require_once __DIR__ . '/utils/bootstrap.php';

// ===== helper to check role =====
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_id = $is_logged_in ? (int) $_SESSION['user_id'] : null;
$is_doctor = false;
$doctor_fullname = '';

if ($is_logged_in) {
    try {
        $conn = db();
        $stmt = $conn->prepare("SELECT is_doctor, CONCAT_WS(' ', first_name, last_name) AS full_name FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (isset($conn)) $conn->close();

        if ($res) {
            $is_doctor = ((int) $res['is_doctor'] === 1);
            $doctor_fullname = (string) ($res['full_name'] ?? '');
        }
    } catch (Exception $e) {
        $is_doctor = false;
    }
}

// ===== AVAILABILITY CHECK (API MODE) =====
if (isset($_GET['doctor']) && isset($_GET['date'])) {
    header('Content-Type: application/json');

    $doctor_id = (int) $_GET['doctor'];
    $date = $_GET['date'];
    $current_appt_id = isset($_GET['current_appt_id']) ? (int) $_GET['current_appt_id'] : 0;

    $booked_times = [];
    try {
        $conn = db();
        $sql = "SELECT id, appt_time, patient_user_id
                 FROM appointments
                 WHERE doctor_user_id = ?
                   AND appt_date = ?
                   AND status != 'cancelled'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $doctor_id, $date);
        $stmt->execute();
        $res = $stmt->get_result();

        $current_user_id = $is_logged_in ? $user_id : null;
        $isViewingOwnDoctorDay = ($is_doctor && $is_logged_in && (int) $user_id === (int) $doctor_id);

        while ($row = $res->fetch_assoc()) {
            $time = (new DateTime($row['appt_time']))->format('H:i');

            // PATIENT: "mine" if this user is the patient
            $isMineForPatient = ($current_user_id !== null && (int) $row['patient_user_id'] === (int) $current_user_id);

            // DOCTOR (reschedule): "mine" only for the appointment currently being edited
            $isMineForDoctor = ($isViewingOwnDoctorDay && $current_appt_id > 0 && (int) $row['id'] === $current_appt_id);

            $booked_times[] = [
                'time' => $time,
                // 'appointment_id' 	=> (int)$row['id'], 			// <-- appt id per row
                'patient_user_id' => (int) $row['patient_user_id'],
                'is_mine' => ($isMineForPatient || $isMineForDoctor),
                // 'is_doctors' 			=> $isViewingOwnDoctorDay 
            ];
        }
        $stmt->close();
        $conn->close();

        echo json_encode($booked_times);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database query failed']);
    }
    exit;
}

// ===== FORM SUBMISSION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_logged_in) {
        try {
            $conn = db();

            if ($is_doctor) {
                $patient_user_id = (int) ($_POST['patient_user_id'] ?? 0);
                if ($patient_user_id <= 0) {
                    header('Location: appointment.php?error=' . urlencode('patient_required'));
                    exit;
                }
                $doctor_user_id = $user_id;
            } else {
                $patient_user_id = $user_id;
                $doctor_user_id = (int) ($_POST['doctor_id'] ?? 0);
            }

            $appt_date = $_POST['appt_date'];
            $appt_time = $_POST['appt_time'];
            $is_update = !empty($_POST['update_id']);

            $appointment_id = 0;

            if ($is_update) {
                $appointment_id = (int) $_POST['update_id'];
                
                // --- UPDATE APPOINTMENT ---
                if ($is_doctor) {
                    $sql = "UPDATE appointments 
                            SET doctor_user_id = ?, appt_date = ?, appt_time = ?
                            WHERE id = ? AND doctor_user_id = ? AND patient_user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('issiii', $doctor_user_id, $appt_date, $appt_time, $appointment_id, $user_id, $patient_user_id);
                } else {
                    $sql = "UPDATE appointments 
                            SET doctor_user_id = ?, appt_date = ?, appt_time = ?
                            WHERE id = ? AND patient_user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('issii', $doctor_user_id, $appt_date, $appt_time, $appointment_id, $patient_user_id);
                }
                $stmt->execute();
                $stmt->close();

                // --- CREATE DOUBLE NOTIFICATION FOR RESCHEDULE ---
                $actor_id = $user_id; 
                
                $current_patient_id = $patient_user_id; 
                $current_doctor_id = $doctor_user_id;   
                
                // Log notification for the TARGET RECIPIENT
                $target_recipient_id = ($actor_id === $current_patient_id) ? $current_doctor_id : $current_patient_id;
                $action_type_recipient = 'rescheduled'; 
                
                if (isset($target_recipient_id) && $target_recipient_id > 0) {
                    $stmt_target_notify = $conn->prepare(
                        "INSERT INTO notifications (recipient_id, actor_id, appointment_id, action_type) 
                         VALUES (?, ?, ?, ?)"
                    );
                    $stmt_target_notify->bind_param("iiis", $target_recipient_id, $actor_id, $appointment_id, $action_type_recipient);
                    $stmt_target_notify->execute();
                    $stmt_target_notify->close();
                }
                
                // Log CONFIRMATION notification for the ACTOR
                $other_party_id = ($actor_id === $current_patient_id) ? $current_doctor_id : $current_patient_id; 
                $action_type_actor = 'rescheduled_actor'; 
                
                if ($actor_id > 0 && $appointment_id > 0) {
                    $stmt_actor_notify = $conn->prepare(
                        "INSERT INTO notifications (recipient_id, actor_id, appointment_id, action_type) 
                         VALUES (?, ?, ?, ?)"
                    );
                    $stmt_actor_notify->bind_param("iiis", $actor_id, $other_party_id, $appointment_id, $action_type_actor);
                    $stmt_actor_notify->execute();
                    $stmt_actor_notify->close();
                }
                
            } else {
                if ($is_doctor) {
                    header('Location: appointment.php?error=' . urlencode('doctor_cannot_create_here'));
                    exit;
                }
                $sql = "INSERT INTO appointments (patient_user_id, doctor_user_id, appt_date, appt_time, status) 
                         VALUES (?, ?, ?, ?, 'confirmed')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('iiss', $patient_user_id, $doctor_user_id, $appt_date, $appt_time);
                $stmt->execute();
                $appointment_id = $conn->insert_id;
                $stmt->close();

                // --- CREATE DOUBLE NOTIFICATION FOR BOOKING ---
                $current_patient_id = $patient_user_id;
                $current_doctor_id = $doctor_user_id;

                if ($appointment_id > 0) {
                    // Log notification for the DOCTOR (Recipient of the request)
                    $stmt_doctor_notify = $conn->prepare(
                        "INSERT INTO notifications (recipient_id, actor_id, appointment_id, action_type) 
                         VALUES (?, ?, ?, 'booked')"
                    );
                    $stmt_doctor_notify->bind_param("iii", $current_doctor_id, $current_patient_id, $appointment_id);
                    $stmt_doctor_notify->execute();
                    $stmt_doctor_notify->close();

                    // Log CONFIRMATION for the PATIENT (Actor of the request)
                    $stmt_patient_notify = $conn->prepare(
                        "INSERT INTO notifications (recipient_id, actor_id, appointment_id, action_type) 
                         VALUES (?, ?, ?, 'booked_actor')"
                    );
                    $stmt_patient_notify->bind_param("iii", $current_patient_id, $current_doctor_id, $appointment_id);
                    $stmt_patient_notify->execute();
                    $stmt_patient_notify->close();
                }
            }

            // --- Email notification ---
            try {
                $stmt_user = $conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
                $stmt_user->bind_param('i', $patient_user_id);
                $stmt_user->execute();
                $patient_data = $stmt_user->get_result()->fetch_assoc();
                $stmt_user->close();

                $actual_doctor_id = $is_doctor ? $user_id : $doctor_user_id;
                $stmt_doc = $conn->prepare("SELECT CONCAT_WS(' ', first_name, last_name) AS full_name FROM users WHERE id = ?");
                $stmt_doc->bind_param('i', $actual_doctor_id);
                $stmt_doc->execute();
                $doctor_data = $stmt_doc->get_result()->fetch_assoc();
                $stmt_doc->close();

                if ($patient_data && $doctor_data) {
                    $patient_name = $patient_data['first_name'];
                    $doctor_name = $doctor_data['full_name'];
                    $subject_action = $is_update ? 'Rescheduled' : 'Confirmed';
                    $message_action = $is_update ? 'rescheduled' : 'confirmed';
                    $pretty_date = (new DateTime($appt_date))->format('l, j F Y');
                    $pretty_time = (new DateTime($appt_time))->format('H:i A');

                    $from_email = 'f31ee@localhost';
                    $to = 'f32ee@localhost';
                    $subject = "Your BrightSmile Appointment is $subject_action";
                    $message = "
                        Hello $patient_name,

                        This is to notify you that your appointment with Dr. $doctor_name has been $message_action.

                        New Details:
                        Date: $pretty_date
                        Time: $pretty_time

                        - The BrightSmile Team
                    ";
                    $headers = 'From: ' . $from_email . "\r\n" .
                        'Reply-To: ' . $from_email . "\r\n" .
                        'X-Mailer: PHP/' . phpversion();

                    mail($to, $subject, $message, $headers, '-f' . $from_email);
                }
            } catch (Exception $e) { /* silent fail */
            }

            $conn->close();
            // Redirect to dashboard to see the result
            header('Location: dashboard.php?success=' . ($is_update ? 'rescheduled' : 'booked'));
            exit;
        } catch (Exception $e) {
            header('Location: appointment.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header('Location: appointment.php?error=notloggedin');
        exit;
    }
}

// --- PAGE LOAD (GET MODE) ---
$all_doctors = [];
$upcoming_appointments = [];
$reschedule_mode = false;
$appointment_to_reschedule = null;
$doctor_total_appointments = 0;

$appointment_id_param = isset($_GET['appointmentId']) ? (int) $_GET['appointmentId'] : 0;
$doctor_has_resched = false;
$doctor_resched_patient_id = 0;

if ($is_logged_in) {
    try {
        $conn = db();

        // count doctor's confirmed appt
        if ($is_doctor) {
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM appointments WHERE doctor_user_id = ? AND `status` = 'confirmed'");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $doctor_total_appointments = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();
        }

        // reschedule logic
        if ($appointment_id_param > 0) {
            if ($is_doctor) {
                $sql = "
                    SELECT *
                    FROM appointments
                    WHERE id = ?
                      AND doctor_user_id = ?
                      AND status = 'confirmed'
                      AND appt_date >= CURDATE()
                    LIMIT 1
                ";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ii', $appointment_id_param, $user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $appointment_to_reschedule = $res->fetch_assoc() ?: null;
                $stmt->close();

                if (!empty($appointment_to_reschedule)) {
                    $doctor_has_resched = true;
                    $doctor_resched_patient_id = (int) $appointment_to_reschedule['patient_user_id'];
                }
            } else {
                $sql = "SELECT * FROM appointments WHERE id = ? AND patient_user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ii', $appointment_id_param, $user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $appointment_to_reschedule = $res->fetch_assoc();
                $stmt->close();

                if ($appointment_to_reschedule) {
                    $reschedule_mode = true;
                }
            }
        }

        // Patient's upcoming appointments
        if (!$is_doctor && !$reschedule_mode) {
            $sql = "
                SELECT 
                    a.id AS appointment_id, a.appt_date, a.appt_time,
                    u.id AS doctor_id, u.first_name, u.last_name, u.avatar_url
                FROM appointments a
                JOIN doctors d ON a.doctor_user_id = d.user_id
                JOIN users u ON d.user_id = u.id
                WHERE a.patient_user_id = ?
                  AND a.status = 'confirmed'
                  AND a.appt_date >= CURDATE()
                ORDER BY a.appt_date, a.appt_time
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $upcoming_appointments = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        // Fetch all doctors
        if ((!$is_doctor && ($reschedule_mode || count($upcoming_appointments) == 0)) || $is_doctor) {
            $sql_doctors = "
                SELECT u.id, u.first_name, u.last_name, u.avatar_url, d.specialization
                FROM users u
                JOIN doctors d ON u.id = d.user_id
                WHERE u.is_doctor = 1
                ORDER BY u.first_name, u.last_name
            ";
            $res = $conn->query($sql_doctors);
            $all_doctors = $res->fetch_all(MYSQLI_ASSOC);
        }

        $conn->close();
    } catch (Exception $e) {
        if (isset($conn))
            $conn->close();
        die("Database error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Book an Appointment - BrightSmile</title>
    <link rel="stylesheet" href="css/appointment.css" />
    <link rel="stylesheet" href="css/root.css" />
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>

<body>
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <?php if (!$is_logged_in): ?>
        <section class="content-wrapper">
            <div class="login-container">
                <h2>Login Required</h2>
                <p>Please login to book or check an appointment.</p>
                <div class="gap"></div>
                <a href="auth.php" class="btn-base btn-login btn-login-appointment">Login</a>
            </div>
        </section>

    <?php elseif ($is_doctor): ?>
        <main class="appointment-container">
            <h1 class="main-title">Book an <span>Appointment</span></h1>

            <?php if ($doctor_total_appointments > 0): ?>
                <section class="appointments-banner">
                    <div class="banner-text">
                        <h3>Your appointments</h3>
                        <p>You have <?= (int) $doctor_total_appointments ?>
                            appointment<?= $doctor_total_appointments > 1 ? 's' : '' ?> scheduled</p>
                    </div>
                    <a href="dashboard.php" class="btn-base btn-view-dash">View in Dashboard</a>
                </section>
            <?php endif; ?>

            <?php if ($appointment_id_param <= 0): ?>
                <section class="doctor-no-appointments-banner">
                    <div class="banner-text">
                        <h3>Open an appointment to reschedule</h3>
                        <p>Use the reschedule button from your dashboard (it will send you here).</p>
                    </div>
                </section>

            <?php elseif (!$doctor_has_resched): ?>
                <section class="doctor-no-appointments-banner">
                    <div class="banner-text">
                        <h3>No appointment to reschedule</h3>
                        <p>This appointment is not found, not yours, cancelled, or in the past.</p>
                    </div>
                </section>

            <?php else:
                $preselected_doctor = null;
                foreach ($all_doctors as $doc) {
                    if ((int) $doc['id'] === (int) $user_id)
                        $preselected_doctor = $doc;
                }
                $preselected_date = $appointment_to_reschedule['appt_date'];
                $preselected_time = (new DateTime($appointment_to_reschedule['appt_time']))->format('H:i');
            ?>
                <form action="appointment.php" method="POST">
                    <input type="hidden" name="patient_user_id" value="<?= (int) $doctor_resched_patient_id ?>">
                    <input type="hidden" name="doctor_id" value="<?= (int) $user_id ?>" required>
                    <input type="hidden" name="update_id" value="<?= (int) $appointment_to_reschedule['id'] ?>">

                    <div class="booking-wrapper">
                        <aside class="booking-card doctor-selector has-selection">
                            <div class="card-header">
                                <h2>Doctor</h2>
                            </div>
                            <div class="dropdown-mock">
                                <div class="dropdown-content-wrapper">
                                    <input type="hidden" id="selected_doctor_id" name="doctor_id"
                                        value="<?= $is_doctor ? (int) $user_id : ($preselected_doctor['id'] ?? '') ?>" required>
                                    <div class="doctor-item">
                                        <img src="<?= htmlspecialchars($preselected_doctor['avatar_url'] ?? 'assets/images/default-doctor.png') ?>"
                                            alt="Dr <?= htmlspecialchars($doctor_fullname) ?>">
                                        <div class="doctor-info">
                                            <span class="doctor-name">Dr <?= htmlspecialchars($doctor_fullname) ?></span>
                                            <span
                                                class="doctor-specialty"><?= htmlspecialchars($preselected_doctor['specialization']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </aside>

                        <section class="booking-card schedule-selector">
                            <div class="card-header">
                                <h2>Select Date</h2>
                                <a class="logo"><img src="assets/icons/logo.svg" alt="Logo" /></a>
                            </div>

                            <div class="date-input-wrapper">
                                <input id="appt_date_input" type="date" name="appt_date"
                                    value="<?= htmlspecialchars($preselected_date) ?>" required>
                            </div>

                            <div class="timeslot-selector">
                                <h3>Select Timeslot</h3>
                                <!-- doctor reschedule block -->
                                <input type="hidden" id="selected_timeslot" name="appt_time"
                                    value="<?= htmlspecialchars($preselected_time ?? '09:00') ?>" required>
                                <div class="timeslot-grid">
                                    <?php $slots = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00']; ?>
                                    <?php foreach ($slots as $slot): ?>
                                        <button type="button"
                                            class="timeslot-btn <?= $slot === $preselected_time ? 'selected' : '' ?>"><?= $slot ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-controls">
                                <a href="dashboard.php" class="cancel-btn">Cancel</a>
                                <button type="submit" class="btn-base btn-book">Update Appointment</button>
                            </div>
                        </section>
                    </div>
                </form>
            <?php endif; ?>
        </main>

    <?php elseif (count($upcoming_appointments) > 0 && !$reschedule_mode): ?>
        <main class="appointment-container">
            <h1 class="main-title">Book an <span>Appointment</span></h1>
            <section class="appointments-banner">
                <div class="banner-text">
                    <h3>Your upcoming appointments</h3>
                    <p>You have <?= count($upcoming_appointments) ?>
                        appointment<?= count($upcoming_appointments) > 1 ? 's' : '' ?> scheduled</p>
                </div>
                <?php foreach ($upcoming_appointments as $appt): ?>
                    <section class="appointment-card">
                        <img src="<?= htmlspecialchars($appt['avatar_url'] ?? 'assets/images/default-doctor.png') ?>"
                            alt="Dr <?= htmlspecialchars($appt['first_name']) ?>" class="doctor-pic">
                        <div class="appointment-details">
                            <?php $d = new DateTime($appt['appt_date'] . ' ' . $appt['appt_time']); ?>
                            <span class="appointment-time"><?= $d->format('l, j F Y \a\t H:i') ?></span>
                            <span class="doctor-name">Dr
                                <?= htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']) ?></span>
                        </div>
                        <a href="appointment.php?appointmentId=<?= (int) $appt['appointment_id'] ?>"
                            class="btn-base btn-reschedule">Reschedule</a>
                    </section>
                <?php endforeach; ?>
            </section>
        </main>

    <?php else:
        $is_rescheduling = ($reschedule_mode && $appointment_to_reschedule);
        $preselected_doctor_id = $is_rescheduling ? (int) $appointment_to_reschedule['doctor_user_id'] : (isset($_GET['doctor']) ? (int) $_GET['doctor'] : null);
        $preselected_date = $is_rescheduling ? $appointment_to_reschedule['appt_date'] : '';
        $preselected_time = $is_rescheduling ? (new DateTime($appointment_to_reschedule['appt_time']))->format('H:i') : '09:00';
        $preselected_doctor = null;
        if (isset($preselected_doctor_id)) {
            foreach ($all_doctors as $doc)
                if ($doc['id'] == $preselected_doctor_id)
                    $preselected_doctor = $doc;
        }
    ?>
        <main class="appointment-container">
            <h1 class="main-title"><?= $is_rescheduling ? 'Reschedule' : 'Book an' ?> <span>Appointment</span></h1>

            <?php if (!$is_rescheduling): ?>
                <section class="no-appointments-banner">
                    <div class="banner-text">
                        <h3>You don't have any upcoming appointments</h3>
                        <p>Book your visit now with our dental experts.</p>
                        <a class="logo"><img src="assets/icons/logo.svg" alt="Logo" /></a>
                    </div>
                </section>
            <?php endif; ?>

            <form action="appointment.php" method="POST">
                <?php if (!empty($appointment_to_reschedule)): ?>
                    <input type="hidden" name="update_id" value="<?= (int) $appointment_to_reschedule['id'] ?>">
                <?php endif; ?>

                <?php if ($is_doctor && isset($doctor_resched_patient_id)): ?>
                    <input type="hidden" name="patient_user_id" value="<?= (int) $doctor_resched_patient_id ?>">
                <?php endif; ?>

                <div class="booking-wrapper">

                    <!-- DOCTOR SELECTOR (shared for doctor + patient) -->
                    <aside
                        class="booking-card doctor-selector <?= $is_doctor ? 'has-selection locked' : ($preselected_doctor ? 'has-selection' : '') ?>">
                        <div class="card-header">
                            <h2>Select Doctor</h2>
                            <?php if (!$is_doctor): ?>
                                <a href="doctors.php" class="btn-base btn-view-all">View All</a>
                            <?php endif; ?>
                        </div>

                        <div class="dropdown-mock <?= $is_doctor ? 'disabled' : '' ?>">
                            <div class="dropdown-content-wrapper">
                                <?php
                                $doctorDisplay = null;
                                if ($is_doctor) {
                                    // doctor logged in → use self
                                    foreach ($all_doctors as $doc) {
                                        if ((int) $doc['id'] === (int) $user_id)
                                            $doctorDisplay = $doc;
                                    }
                                } elseif (!empty($preselected_doctor)) {
                                    $doctorDisplay = $preselected_doctor;
                                }
                                ?>
                                <?php if ($doctorDisplay): ?>
                                    <div class="doctor-item">
                                        <img src="<?= htmlspecialchars($doctorDisplay['avatar_url'] ?? 'assets/images/default-doctor.png') ?>"
                                            alt="Dr <?= htmlspecialchars($doctorDisplay['first_name']) ?>">
                                        <div class="doctor-info">
                                            <span class="doctor-name">Dr
                                                <?= htmlspecialchars($doctorDisplay['first_name'] . ' ' . $doctorDisplay['last_name']) ?></span>
                                            <span
                                                class="doctor-specialty"><?= htmlspecialchars($doctorDisplay['specialization']) ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span>Choose your preferred doctor</span>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-icon">
                                <img src="assets/icons/dropdown-arrow-white.svg" class="dropdown-arrow-icon" alt="">
                            </div>
                        </div>

                        <input type="hidden" id="selected_doctor_id" name="doctor_id"
                            value="<?= $is_doctor ? (int) $user_id : ($preselected_doctor['id'] ?? '') ?>" required>
                        
                        <!-- Only visible to patients -->
                        <?php if (!$is_doctor): ?>
                            <div class="doctor-list">
                                <?php foreach ($all_doctors as $doctor): ?>
                                    <div class="doctor-item" data-doctor-id="<?= $doctor['id'] ?>">
                                        <img src="<?= htmlspecialchars($doctor['avatar_url'] ?? 'assets/images/default-doctor.png') ?>"
                                            alt="Dr <?= htmlspecialchars($doctor['first_name']) ?>">
                                        <div class="doctor-info">
                                            <span class="doctor-name">Dr
                                                <?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?></span>
                                            <span class="doctor-specialty"><?= htmlspecialchars($doctor['specialization']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </aside>

                    <!-- DATE + TIMESLOT SECTION -->
                    <section class="booking-card schedule-selector">
                        <div class="card-header">
                            <h2>Select Date</h2>
                            <a class="logo"><img src="assets/icons/logo.svg" alt="Logo" /></a>
                        </div>

                        <div class="date-input-wrapper">
                            <input type="date" id="appt_date_input" name="appt_date"
                                value="<?= htmlspecialchars($preselected_date ?? '') ?>" required>
                        </div>

                        <div class="timeslot-selector">
                            <h3>Select Timeslot</h3>
                            <input type="hidden" id="selected_timeslot" name="appt_time"
                                value="<?= htmlspecialchars($preselected_time ?? '09:00') ?>" required>
                            <div class="timeslot-grid">
                                <?php $slots = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00']; ?>
                                <?php foreach ($slots as $slot): ?>
                                    <button type="button"
                                        class="timeslot-btn <?= ($slot === ($preselected_time ?? '')) ? 'selected' : '' ?>">
                                        <?= $slot ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-controls">
                            <a href="<?= $is_doctor ? 'dashboard.php' : 'appointment.php' ?>" class="cancel-btn">Cancel</a>
                            <button type="submit" class="btn-base btn-book">
                                <?= !empty($appointment_to_reschedule) ? 'Update Appointment' : 'Book Appointment' ?>
                            </button>
                        </div>
                    </section>
                </div>
            </form>

        </main>
    <?php endif; ?>

    <?php include __DIR__ . '/components/footer.php'; ?>
    <script src="js/appointment.js"></script>
</body>

</html>