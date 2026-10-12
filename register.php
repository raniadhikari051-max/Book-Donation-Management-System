<?php
/**
 * register.php
 * Multi-role registration (donor / recipient / volunteer).
 * Protected with CSRF token verification and audit logging.
 * Integrates shared UI shell (header, navbar, dark mode, language switcher, footer).
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in? Send them to their dashboard instead.
if (isset($_SESSION['user_id'])) {
    redirect($_SESSION['role'] . '/dashboard.php');
}

$errors = [];

// Read ?role= from GET if present and valid; default to 'donor'
$defaultRole = 'donor';
if (isset($_GET['role']) && in_array($_GET['role'], ['donor', 'recipient', 'volunteer'], true)) {
    $defaultRole = $_GET['role'];
}

$old = [
    'full_name' => '',
    'email'     => '',
    'phone'     => '',
    'address'   => '',
    'role'      => $defaultRole,
    'extra'     => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid or expired session. Please refresh and try again.';
    }

    $full_name = clean_input($_POST['full_name'] ?? '');
    $email     = clean_input($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $phone     = clean_input($_POST['phone'] ?? '');
    $address   = clean_input($_POST['address'] ?? '');
    $role      = clean_input($_POST['role'] ?? '');
    $extra     = clean_input($_POST['extra'] ?? ''); // org name / institution / area covered

    $old = compact('full_name', 'email', 'phone', 'address', 'role', 'extra');

    // ---- Validation -----------------------------------------------------
    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }
    if (!is_valid_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!is_valid_password($password)) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and confirm password do not match.';
    }
    if (!in_array($role, ['donor', 'recipient', 'volunteer'], true)) {
        $errors[] = 'Please choose a valid account type.';
    }

    // Check for duplicate email
    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'An account with this email already exists.';
        }
        $stmt->close();
    }

    // ---- Insert -----------------------------------------------------------
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                'INSERT INTO users (full_name, email, password, phone, address, role) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('ssssss', $full_name, $email, $hashed, $phone, $address, $role);
            $stmt->execute();
            $newUserId = $stmt->insert_id;
            $stmt->close();

            // Create the matching role-profile row starting at points = 0 (points earned via verified actions only)
            if ($role === 'donor') {
                $stmt = $conn->prepare(
                    'INSERT INTO donors (user_id, organization_name, points) VALUES (?, ?, 0)'
                );
                $stmt->bind_param('is', $newUserId, $extra);
            } elseif ($role === 'recipient') {
                $stmt = $conn->prepare(
                    'INSERT INTO recipients (user_id, institution_name, points) VALUES (?, ?, 0)'
                );
                $stmt->bind_param('is', $newUserId, $extra);
            } else { // volunteer
                $stmt = $conn->prepare(
                    'INSERT INTO volunteers (user_id, area_covered) VALUES (?, ?)'
                );
                $stmt->bind_param('is', $newUserId, $extra);
            }
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            log_activity($conn, 'REGISTER', "New {$role} account registered: {$email}", $newUserId);

            set_flash('success', 'Registration successful! You can now log in.');
            redirect('login.php');
        } catch (\Exception $e) {
            $conn->rollback();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

$pageTitle = 'Create an Account — Book Donation & Management System';
$activePage = 'register';
$inSubfolder = false;
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-7 col-xl-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                <div class="text-center mb-4">
                    <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-bold text-uppercase small mb-2">Join Our Community</span>
                    <h2 class="fw-bold text-dark mb-1">Create an Account</h2>
                    <p class="text-muted small">Choose your role to donate books, request study materials, or volunteer</p>
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

                <form method="POST" action="register.php" novalidate>
                    <?= csrf_field() ?>

                    <!-- Account Type Selector -->
                    <div class="mb-3">
                        <label for="role" class="form-label fw-semibold small text-dark">I want to register as <span class="text-danger">*</span></label>
                        <select class="form-select rounded-3" id="role" name="role" required onchange="toggleExtraFields()">
                            <option value="donor" <?= $old['role'] === 'donor' ? 'selected' : '' ?>>Donor (Give books & support education)</option>
                            <option value="recipient" <?= $old['role'] === 'recipient' ? 'selected' : '' ?>>Recipient (Student / School / Scholar)</option>
                            <option value="volunteer" <?= $old['role'] === 'volunteer' ? 'selected' : '' ?>>Volunteer (Coordinate pickups & distributions)</option>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label fw-semibold small text-dark">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="full_name" name="full_name"
                                   placeholder="e.g. Ramesh Kulkarni"
                                   value="<?= h($old['full_name']) ?>" required autocomplete="name">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold small text-dark">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control rounded-3" id="email" name="email"
                                   placeholder="name@example.com"
                                   value="<?= h($old['email']) ?>" required autocomplete="email">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold small text-dark">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control rounded-3" id="password" name="password"
                                   placeholder="Min 6 characters" required autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label fw-semibold small text-dark">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control rounded-3" id="confirm_password" name="confirm_password"
                                   placeholder="Repeat password" required autocomplete="new-password">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold small text-dark">Phone Number</label>
                            <input type="tel" class="form-control rounded-3" id="phone" name="phone"
                                   placeholder="10-digit mobile"
                                   value="<?= h($old['phone']) ?>" autocomplete="tel">
                        </div>
                        <div class="col-md-6">
                            <label for="extra" class="form-label fw-semibold small text-dark" id="extraLabel">Organization (Optional)</label>
                            <input type="text" class="form-control rounded-3" id="extra" name="extra"
                                   placeholder="College / Company / Area"
                                   value="<?= h($old['extra']) ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="address" class="form-label fw-semibold small text-dark">Address / City</label>
                        <textarea class="form-control rounded-3" id="address" name="address" rows="2"
                                  placeholder="Your street address or neighborhood in Pune"><?= h($old['address']) ?></textarea>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-semibold shadow-sm">
                            <i class="bi bi-person-check me-2"></i>Create Account
                        </button>
                    </div>
                </form>

                <div class="text-center pt-3 border-top">
                    <span class="text-muted small">Already have an account? </span>
                    <a href="login.php" class="fw-bold text-primary text-decoration-none small">Log In Here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleExtraFields() {
    const role = document.getElementById('role').value;
    const label = document.getElementById('extraLabel');
    const input = document.getElementById('extra');

    if (role === 'donor') {
        label.textContent = 'Organization / Company (Optional)';
        input.placeholder = 'e.g. Pune University / Tech Corp';
    } else if (role === 'recipient') {
        label.textContent = 'College / School / Institution';
        input.placeholder = 'e.g. Pune Institute of Computer Technology';
    } else if (role === 'volunteer') {
        label.textContent = 'Preferred Service Area';
        input.placeholder = 'e.g. Kothrud, Deccan, Shivajinagar';
    }
}
// Trigger on initial page load to respect pre-selected role
document.addEventListener('DOMContentLoaded', toggleExtraFields);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>