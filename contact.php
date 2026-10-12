<?php
/**
 * contact.php
 * Public contact form inserting into contact_messages table.
 * Fully secured with CSRF protection.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Contact Us';
$activePage = 'contact';

$errors = [];
$old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid or expired session token. Please refresh and try again.';
    }

    $name    = clean_input($_POST['name'] ?? '');
    $email   = clean_input($_POST['email'] ?? '');
    $subject = clean_input($_POST['subject'] ?? '');
    $message = clean_input($_POST['message'] ?? '');

    $old = compact('name', 'email', 'subject', 'message');

    if ($name === '') {
        $errors[] = 'Please enter your name.';
    }
    if (!is_valid_email($email)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if ($message === '') {
        $errors[] = 'Please enter your message.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $name, $email, $subject, $message);
        if ($stmt->execute()) {
            set_flash('success', 'Thank you for contacting us! Your message has been received. Our team will get back to you shortly.');
            $old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
        } else {
            $errors[] = 'Failed to submit message. Please try again.';
        }
        $stmt->close();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-primary text-white py-5">
    <div class="container">
        <h1 class="display-5 fw-bold mb-2">Contact Us</h1>
        <p class="lead text-light mb-0">Have questions, bulk donation inquiries, or want to partner with us?</p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                <h3 class="fw-bold text-dark mb-2">Send Us A Message</h3>
                <p class="text-muted small mb-4">Fill out the form below and our project coordinators will respond within 24 hours.</p>

                <?= render_flash() ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger rounded-3">
                        <ul class="mb-0 small">
                            <?php foreach ($errors as $e): ?>
                                <li><?= h($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="contact.php" novalidate>
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold small">Your Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="name" name="name"
                                   placeholder="Full name" value="<?= h($old['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control rounded-3" id="email" name="email"
                                   placeholder="name@example.com" value="<?= h($old['email']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="subject" class="form-label fw-semibold small">Subject</label>
                        <input type="text" class="form-control rounded-3" id="subject" name="subject"
                               placeholder="e.g. Bulk Textbook Donation / College Partnership" value="<?= h($old['subject']) ?>">
                    </div>

                    <div class="mb-4">
                        <label for="message" class="form-label fw-semibold small">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control rounded-3" id="message" name="message" rows="5"
                                  placeholder="Write your message or inquiry here..." required><?= h($old['message']) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-semibold shadow-sm">
                        <i class="bi bi-send me-2"></i>Send Message
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
                <h4 class="fw-bold text-dark mb-4">Collection Center & Hub</h4>
                <div class="d-flex align-items-start mb-4">
                    <div class="stat-icon bg-primary-subtle text-primary me-3 flex-shrink-0">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Central Drop-off Hub</h6>
                        <p class="text-muted small mb-0">Department of Computer Applications, BBA(CA) Campus, Kothrud, Pune, Maharashtra 411038</p>
                    </div>
                </div>

                <div class="d-flex align-items-start mb-4">
                    <div class="stat-icon bg-success-subtle text-success me-3 flex-shrink-0">
                        <i class="bi bi-telephone"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Helpdesk & Volunteer Helpline</h6>
                        <p class="text-muted small mb-0">+91 (020) 2543-0000 / +91 98765 43210</p>
                    </div>
                </div>

                <div class="d-flex align-items-start">
                    <div class="stat-icon bg-warning-subtle text-warning me-3 flex-shrink-0">
                        <i class="bi bi-clock"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Operational Timings</h6>
                        <p class="text-muted small mb-0">Monday – Saturday: 9:00 AM – 5:30 PM<br>Sunday: Closed</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>