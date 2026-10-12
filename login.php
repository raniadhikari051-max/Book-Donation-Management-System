<?php
/**
 * login.php
 * Handles user authentication for all four roles.
 * Protected with CSRF token verification and brute-force rate-limiting.
 * Integrates shared UI shell (header, navbar, dark mode, language switcher, footer).
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, send them straight to their dashboard
if (isset($_SESSION['user_id'])) {
    redirect($_SESSION['role'] . '/dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF token
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid or expired session. Please refresh and try again.';
    }

    $email    = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '') {
        $errors[] = 'Please enter your email address.';
    }
    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        // Look up the user by email
        $stmt = $conn->prepare(
            'SELECT user_id, full_name, email, password, role, status, failed_attempts, locked_until 
             FROM users 
             WHERE email = ?'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            // 2. Check if account is currently locked
            $lockMinutes = is_account_locked($user);
            if ($lockMinutes !== false) {
                $errors[] = "Account temporarily locked due to too many failed attempts. Please try again in {$lockMinutes} minute(s).";
            } elseif ($user['status'] !== 'active') {
                $errors[] = 'Your account has been deactivated. Please contact the administrator.';
            } elseif (password_verify($password, $user['password'])) {
                // 3. Success: Reset rate-limiting counters
                reset_failed_logins($conn, $user['user_id']);

                // Set session variables
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['role']      = $user['role'];

                log_activity($conn, 'LOGIN', "User logged in ({$user['role']})", $user['user_id']);

                // Route them to their role-specific dashboard
                set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect($user['role'] . '/dashboard.php');
            } else {
                // 4. Bad password: increment failed attempts
                record_failed_login($conn, $user['user_id'], (int)$user['failed_attempts']);
                $attemptsLeft = max(0, 5 - ((int)$user['failed_attempts'] + 1));
                if ($attemptsLeft > 0) {
                    $errors[] = "Incorrect password. {$attemptsLeft} attempt(s) remaining before temporary lockout.";
                } else {
                    $errors[] = "Too many failed attempts. Account locked for 15 minutes.";
                }
            }
        } else {
            // Unknown email — generic error message for security
            $errors[] = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Login — Book Donation and Management System';
$activePage = 'login';
$inSubfolder = false;
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                <div class="text-center mb-4">
                    <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-bold text-uppercase small mb-2">Member Access</span>
                    <h2 class="fw-bold text-dark mb-1">Welcome Back</h2>
                    <p class="text-muted small">Log in to manage donations, requests, or volunteer tasks</p>
                </div>

                <?= render_flash() ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger rounded-3" role="alert">
                        <ul class="mb-0 small">
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold small text-dark">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control bg-light border-start-0" id="email" name="email"
                                   value="<?= h($email) ?>" placeholder="name@example.com" required autocomplete="email" autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between">
                            <label for="password" class="form-label fw-semibold small text-dark">Password</label>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control bg-light border-start-0" id="password" name="password"
                                   placeholder="••••••••" required autocomplete="current-password">
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-semibold shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </div>
                </form>

                <div class="text-center pt-3 border-top">
                    <span class="text-muted small">Don't have an account? </span>
                    <a href="register.php" class="fw-bold text-primary text-decoration-none small">Create One</a>
                </div>

                <!-- Verified Demo Credentials Card -->
                <div class="card border-0 bg-light rounded-3 p-3 mt-4">
                    <h6 class="fw-bold text-dark small mb-2"><i class="bi bi-info-circle text-primary me-1"></i>Demo Accounts (Password: <code>Pass@123</code>)</h6>
                    <ul class="list-unstyled mb-0 small text-muted font-monospace" style="font-size: 0.78rem;">
                        <li><strong>Admin:</strong> admin@bookdonate.local</li>
                        <li><strong>Donor:</strong> aarav.donor@example.com</li>
                        <li><strong>Student:</strong> rohan.recipient@example.com</li>
                        <li><strong>Volunteer:</strong> kunal.volunteer@example.com</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>