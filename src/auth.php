<?php
require_once __DIR__.'/utils/bootstrap.php';

function redirect_with_login_attempt(string $email, string $code): void
{
    $_SESSION['login_attempt_email'] = $email;
    redirect("auth.php?login_error={$code}");
}

function persist_form_data(array $data): void
{
    $_SESSION['form_data'] = $data;
}

function clear_form_data(): void
{
    unset($_SESSION['form_data']);
}

/* -------------------- Validation -------------------- */
function validate_signup_input(array $in, mysqli $conn): array
{
    $errors = [];

    $first_name = $in['first_name'] ?? '';
    $last_name = $in['last_name'] ?? '';
    $dob = $in['dob'] ?? '';
    $phone = $in['phone'] ?? '';
    $email = $in['email'] ?? '';
    $password = $in['password'] ?? '';
    $confirm = $in['confirm_password'] ?? '';

    if (!preg_match('/^[\p{L}\s]+$/u', $first_name) || !preg_match('/^[\p{L}\s]+$/u', $last_name)) {
        $errors[] = 'nameinvalid';
    }
    if ($password !== $confirm) {
        $errors[] = 'passwordmismatch';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'emailinvalid';
    }
    // E.164 phone (e.g. +15551234567)
    if (empty($phone) || !preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
        $errors[] = 'phoneinvalid';
    }
    if (strlen($password) < 8) {
        $errors[] = 'passwordshort';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'passwordnoupper';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'passwordnolower';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'passwordnonumber';
    }
    if (!preg_match('/[^\p{L}\p{N}]/u', $password)) {
        $errors[] = 'passwordnosymbol';
    }

    // email taken?
    $check = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $check->bind_param('s', $email);
    $check->execute();
    $res = $check->get_result();
    if ($res->num_rows > 0) {
        $errors[] = 'emailtaken';
    }
    $check->close();

    return $errors;
}

// Reset session safely, keep user_id
function keep_only_user_id(int $id): void
{
    session_regenerate_id(true);   // new session id for safety
    $_SESSION = ['user_id' => $id]; // whitelist: only user_id survives
}


/* -------------------- Actions -------------------- */
function signup(mysqli $conn, array $in): void
{
    $errors = validate_signup_input($in, $conn);

    if (!empty($errors)) {
        persist_form_data([
            'first_name' => $in['first_name'] ?? '',
            'last_name' => $in['last_name'] ?? '',
            'email' => $in['email'] ?? '',
            'dob' => $in['dob'] ?? '',
            'phone' => $in['phone'] ?? '',
        ]);
        redirect('auth.php?signup_errors=' . implode(',', $errors));
    }

    $email = $in['email'];
    $password = $in['password'];
    $first_name = $in['first_name'];
    $last_name = $in['last_name'];
    $dob = $in['dob'];
    $phone = $in['phone'];

    // Hash (Argon2id)
    $hashed = password_hash($password, PASSWORD_ARGON2ID);

    $stmt = $conn->prepare('
        INSERT INTO users (email, password_hash, first_name, last_name, dob, phone)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->bind_param('ssssss', $email, $hashed, $first_name, $last_name, $dob, $phone);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        die('Database Error during registration: ' . $err);
    }

    $new_user_id = $conn->insert_id;
    $stmt->close();

    // Log the user in (fresh session id)
    session_regenerate_id(true);
    $_SESSION['user_id'] = $new_user_id;
    clear_form_data();

    // Update last_login
    $update = $conn->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
    $update->bind_param('i', $new_user_id);
    $update->execute();
    $update->close();

    redirect('index.php');
}

function login(mysqli $conn, array $in): void
{
    $email = $in['email'] ?? '';
    $password = $in['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_with_login_attempt($email, 'emailinvalid');
    }

    $stmt = $conn->prepare('SELECT id, email, password_hash FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows !== 1) {
        $stmt->close();
        redirect_with_login_attempt($email, 'nouser');
    }

    $user = $res->fetch_assoc();
    $stmt->close();

    if (!password_verify($password, $user['password_hash'])) {
        redirect_with_login_attempt($email, 'wrongpassword');
    }

    // Success
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    unset($_SESSION['login_attempt_email']);

    // Optional rehash if algorithm params changed
    if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
        $newHash = password_hash($password, PASSWORD_ARGON2ID);
        $rehash = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $rehash->bind_param('si', $newHash, $user['id']);
        $rehash->execute();
        $rehash->close();
    }

    // Update last_login
    $update = $conn->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
    $update->bind_param('i', $user['id']);
    $update->execute();
    $update->close();

    redirect('index.php');
}

/* -------------------- Controller -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = db();

    if (isset($_POST['signup_submit'])) {
        signup($conn, $_POST);
    }

    if (isset($_POST['login_submit'])) {
        login($conn, $_POST);
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Authentication</title>
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="css/root.css">
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/css/intlTelInput.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js"></script>
</head>

<body>
    <header class="navbar-container">
        <div class="general navbar">
            <a href="index.php" class="logo" aria-label="BrightSmile home">
                <img src="assets/icons/logo.svg" alt="Logo">
                <span>BrightSmile</span>
            </a>
            <div></div>
        </div>
    </header>

    <main class="form-wrapper">
        <div class="form-container">
            <div class="toggle-wrapper">
                <div class="toggle-container">
                    <button id="login-toggle" class="toggle-btn active">Login</button>
                    <button id="signup-toggle" class="toggle-btn">Sign up</button>
                </div>
            </div>

            <?php
            if (isset($_GET['signup']) && $_GET['signup'] == 'success') {
                echo '<div class="form-success-message"><p>Account created successfully! Please log in.</p></div>';
            }
            ?>

            <?php
            $login_errors = [
                'email' => '',
                'password' => ''
            ];
            if (isset($_GET['login_error'])) {
                $error = $_GET['login_error'];
                if ($error == 'nouser') {
                    $login_errors['email'] = 'This email does not exist.';
                } elseif ($error == 'emailinvalid') {
                    $login_errors['email'] = 'Invalid email format entered.';
                } elseif ($error == 'wrongpassword') {
                    $login_errors['password'] = 'Incorrect password. Please try again.';
                }
            }

            $login_email_attempt = $_SESSION['login_attempt_email'] ?? '';
            unset($_SESSION['login_attempt_email']);
            ?>

            <form id="login-form" method="POST" action="auth.php">
                <div class="input-group <?php echo !empty($login_errors['email']) ? 'has-error' : '' ?>">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" required
                        value="<?php echo htmlspecialchars($login_email_attempt); ?>">
                    <span class="error-message" id="login-email-error">
                        <?php echo $login_errors['email']; ?>
                    </span>
                </div>
                <div class="input-group <?php echo !empty($login_errors['password']) ? 'has-error' : '' ?>">
                    <label for="login-password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="login-password" name="password" required>
                        <button type="button" class="toggle-password">
                            <img src="assets/icons/eye-open.svg" alt="Show password" class="eye-icon">
                            <img src="assets/icons/eye-close.svg" alt="Hide password" class="eye-slash-icon hidden">
                        </button>
                    </div>
                    <span class="error-message" id="login-password-error">
                        <?php echo $login_errors['password']; ?>
                    </span>
                </div>
                <button type="submit" class="btn-base submit-btn" name="login_submit">Login</button>
            </form>

            <?php
            $form_data = $_SESSION['form_data'] ?? [];
            unset($_SESSION['form_data']);

            $signup_errors = [
                'name' => '',
                'email' => '',
                'phone' => '',
                'password' => '',
                'confirm' => ''
            ];
            if (isset($_GET['signup_errors'])) {
                $signup_error_codes = explode(',', $_GET['signup_errors']);

                if (in_array('nameinvalid', $signup_error_codes)) {
                    $signup_errors['name'] = 'Must consist of letters only';
                }

                if (in_array('emailtaken', $signup_error_codes)) {
                    $signup_errors['email'] = 'This email address is already exist.';
                } elseif (in_array('emailinvalid', $signup_error_codes)) {
                    $signup_errors['email'] = 'Invalid email format entered.';
                }

                if (in_array('phoneinvalid', $signup_error_codes)) {
                    $signup_errors['phone'] = 'Please enter a valid phone number.';
                }

                $password_error_messages = [];

                if (in_array('passwordshort', $signup_error_codes)) {
                    $password_error_messages[] = '• Must be at least 8 characters long.';
                }

                if (in_array('passwordnoupper', $signup_error_codes)) {
                    $password_error_messages[] = '• Must include at least one uppercase letter.';
                }

                if (in_array('passwordnolower', $signup_error_codes)) {
                    $password_error_messages[] = '• Must include at least one lowercase letter.';
                }

                if (in_array('passwordnonumber', $signup_error_codes)) {
                    $password_error_messages[] = '• Must include at least one number.';
                }

                if (in_array('passwordnosymbol', $signup_error_codes)) {
                    $password_error_messages[] = '• Must include at least one symbol (e.g., !@#$).';
                }

                if (!empty($password_error_messages)) {
                    $signup_errors['password'] = implode('<br>', $password_error_messages);
                }

                if (in_array('passwordmismatch', $signup_error_codes)) {
                    $signup_errors['confirm'] = 'Passwords do not match. Please try again.';
                }
            }
            ?>

            <form id="signup-form" class="hidden" method="POST" action="auth.php">

                <div class="name-group">
                    <div class="input-group <?php echo !empty($signup_errors['name']) ? 'has-error' : '' ?>">
                        <label for="first-name">First Name</label>
                        <input type="text" id="first-name" name="first_name" required
                            value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>">
                        <span class="error-message" id="signup-name-error">
                            <?php echo $signup_errors['name']; ?>
                        </span>
                    </div>
                    <div class="input-group <?php echo !empty($signup_errors['name']) ? 'has-error' : '' ?>">
                        <label for="last-name">Last Name</label>
                        <input type="text" id="last-name" name="last_name" required
                            value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="input-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" required
                        value="<?php echo htmlspecialchars($form_data['dob'] ?? ''); ?>"
                        max="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="input-group <?php echo !empty($signup_errors['phone']) ? 'has-error' : '' ?>">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" required
                        value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>">
                    <span class="error-message" id="signup-phone-error">
                        <?php echo $signup_errors['phone']; ?>
                    </span>
                </div>

                <div class="input-group <?php echo !empty($signup_errors['email']) ? 'has-error' : '' ?>">
                    <label for="signup-email">Email</label>
                    <input type="email" id="signup-email" name="email" required
                        value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                    <span class="error-message" id="signup-email-error">
                        <?php echo $signup_errors['email']; ?>
                    </span>
                </div>
                <div class="input-group <?php echo !empty($signup_errors['password']) ? 'has-error' : '' ?>">
                    <label for="signup-password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="signup-password" name="password" required>
                        <button type="button" class="toggle-password">
                            <img src="assets/icons/eye-open.svg" alt="Show password" class="eye-icon">
                            <img src="assets/icons/eye-close.svg" alt="Hide password" class="eye-slash-icon hidden">
                        </button>
                    </div>

                    <div class="password-requirements" id="signup-req-list">
                        <ul>
                            <li id="req-length">Must be at least 8 characters long.</li>
                            <li id="req-lower">Must include at least one lowercase letter.</li>
                            <li id="req-upper">Must include at least one capital letter.</li>
                            <li id="req-number">Must include at least one number.</li>
                            <li id="req-symbol">Must include at least one symbol (e.g., !@#$).</li>
                        </ul>
                    </div>
                </div>
                <div class="input-group <?php echo !empty($signup_errors['confirm']) ? 'has-error' : '' ?>">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="toggle-password">
                            <img src="assets/icons/eye-open.svg" alt="Show password" class="eye-icon">
                            <img src="assets/icons/eye-close.svg" alt="Hide password" class="eye-slash-icon hidden">
                        </button>
                    </div>
                    <span class="error-message" id="signup-confirm-error">
                        <?php echo $signup_errors['confirm']; ?>
                    </span>
                </div>
                <button type="submit" class="btn-base submit-btn" name="signup_submit">Create Account</button>
            </form>
        </div>
    </main>

    <script src="js/auth.js" defer></script>

</body>

</html>