<?php
/**
 * transparency.php
 * Public Community Transparency & Impact Portal (BBA CA 5th Sem CEP).
 * Preserves student beneficiary privacy by anonymizing recipient names in testimonials.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Live Impact Aggregates
$donRes = $conn->query("SELECT COALESCE(SUM(quantity), 0) AS total_donated FROM donation_books");
$totalDonated = $donRes ? (int)$donRes->fetch_assoc()['total_donated'] : 0;

$distRes = $conn->query("SELECT COALESCE(SUM(quantity_distributed), 0) AS total_dist FROM distributions");
$totalDistributed = $distRes ? (int)$distRes->fetch_assoc()['total_dist'] : 0;

$studRes = $conn->query("SELECT COUNT(DISTINCT recipient_id) AS total_students FROM book_requests WHERE status IN ('Distributed', 'Completed')");
$totalStudents = $studRes ? (int)$studRes->fetch_assoc()['total_students'] : 0;

$volRes = $conn->query("SELECT COUNT(*) AS total_vols FROM volunteers");
$totalVolunteers = $volRes ? (int)$volRes->fetch_assoc()['total_vols'] : 0;

// Top Donors Leaderboard (Full names publicly credited for benefactors)
$topDonorsSql = "SELECT u.full_name, d.total_books_donated, d.points, d.organization_name
                 FROM donors d
                 JOIN users u ON d.user_id = u.user_id
                 ORDER BY d.total_books_donated DESC, d.points DESC LIMIT 10";
$topDonorsRes = $conn->query($topDonorsSql);
$topDonors = $topDonorsRes ? $topDonorsRes->fetch_all(MYSQLI_ASSOC) : [];

// Verified Student Testimonials from Feedback (Beneficiary Privacy: Anonymized display)
$fbSql = "SELECT f.*, u.full_name, r.institution_name 
          FROM feedback f
          JOIN recipients r ON f.recipient_id = r.recipient_id
          JOIN users u ON r.user_id = u.user_id
          WHERE f.rating >= 4
          ORDER BY f.feedback_id DESC LIMIT 6";
$fbRes = $conn->query($fbSql);
$testimonials = $fbRes ? $fbRes->fetch_all(MYSQLI_ASSOC) : [];

$inSubfolder = false;
$pageTitle = 'Community Transparency & Impact — Pune Book Drive';
$activePage = 'transparency';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="py-5 bg-gradient-primary text-white" style="background: linear-gradient(135deg, #D96B43 0%, #2F5D44 100%);">
    <div class="container py-4 text-center">
        <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold text-uppercase mb-3">
            BBA(CA) 5th Semester &bull; Community Engagement Project
        </span>
        <h1 class="display-4 fw-bold mb-3">Community Transparency Portal</h1>
        <p class="lead max-w-700 mx-auto text-light opacity-90">
            Real-time public accountability of all books collected, verified, and distributed to underprivileged students and scholars across Pune.
        </p>
    </div>
</section>

<!-- KPI Metrics Grid -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <div class="fs-1 fw-bold text-primary mb-1"><?= number_format($totalDonated) ?>+</div>
                    <div class="text-uppercase small fw-bold text-muted">Books Donated</div>
                    <small class="text-secondary mt-2 d-block">Contributed by local citizens</small>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <div class="fs-1 fw-bold text-success mb-1"><?= number_format($totalDistributed) ?>+</div>
                    <div class="text-uppercase small fw-bold text-muted">Books Distributed</div>
                    <small class="text-secondary mt-2 d-block">Delivered into students' hands</small>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <div class="fs-1 fw-bold text-warning mb-1"><?= number_format($totalStudents) ?></div>
                    <div class="text-uppercase small fw-bold text-muted">Students Supported</div>
                    <small class="text-secondary mt-2 d-block">From colleges & schools</small>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <div class="fs-1 fw-bold text-info mb-1"><?= number_format($totalVolunteers) ?></div>
                    <div class="text-uppercase small fw-bold text-muted">Student Volunteers</div>
                    <small class="text-secondary mt-2 d-block">Conducting pickups & delivery</small>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main Body: Top Donors & Testimonials -->
<section class="py-5">
    <div class="container">
        <div class="row g-5">
            <!-- Left: Top Benefactors Leaderboard (Full names publicly recognized) -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center">
                            <div class="p-2 bg-warning-subtle text-warning rounded-circle me-3">
                                <i class="bi bi-trophy-fill fs-4"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-0">Top Education Benefactors</h4>
                                <small class="text-muted">Honoring citizens who shared the most books</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($topDonors)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($topDonors as $idx => $dn): ?>
                                    <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <span class="badge rounded-circle me-3 <?= $idx === 0 ? 'bg-warning text-dark' : ($idx === 1 ? 'bg-secondary text-white' : ($idx === 2 ? 'bg-danger text-white' : 'bg-light text-dark border')) ?>" style="width: 32px; height: 32px; line-height: 22px;">
                                                <?= $idx + 1 ?>
                                            </span>
                                            <div>
                                                <div class="fw-bold text-dark"><?= h($dn['full_name']) ?></div>
                                                <small class="text-muted"><?= h($dn['organization_name'] ?: 'Independent Donor') ?></small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-success-subtle text-success fs-6 rounded-pill px-3">
                                                <?= (int)$dn['total_books_donated'] ?> Books
                                            </span>
                                            <small class="d-block text-muted"><?= (int)$dn['points'] ?> pts</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted py-4 text-center">No donor records available yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Student Impact & Verified Testimonials (Privacy-protected) -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center">
                            <div class="p-2 bg-success-subtle text-success rounded-circle me-3">
                                <i class="bi bi-chat-quote-fill fs-4"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-0">Verified Student Impact</h4>
                                <small class="text-muted">Authentic feedback from beneficiary scholars</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($testimonials)): ?>
                            <div class="row g-3">
                                <?php foreach ($testimonials as $t): ?>
                                    <?php
                                    // Anonymize student recipient name for beneficiary privacy
                                    $inst = !empty($t['institution_name']) ? h($t['institution_name']) : 'Pune College';
                                    $nameParts = explode(' ', trim($t['full_name']));
                                    $firstName = $nameParts[0] ?? 'Student';
                                    $lastInitial = (count($nameParts) > 1) ? ' ' . mb_substr(end($nameParts), 0, 1) . '.' : '';
                                    $anonymizedLabel = $firstName . $lastInitial;
                                    ?>
                                    <div class="col-12">
                                        <div class="p-3 bg-light rounded-4 border">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="text-warning">
                                                    <?php for ($s = 1; $s <= (int)$t['rating']; $s++): ?>
                                                        <i class="bi bi-star-fill"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <small class="text-muted"><?= date('M d, Y', strtotime($t['created_at'])) ?></small>
                                            </div>
                                            <p class="mb-2 text-dark fst-italic">&ldquo;<?= h($t['comments']) ?>&rdquo;</p>
                                            <div class="d-flex justify-content-between small align-items-center">
                                                <span class="fw-bold text-primary">
                                                    <i class="bi bi-mortarboard me-1"></i>A student from <?= $inst ?>
                                                </span>
                                                <small class="text-muted"><?= h($anonymizedLabel) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted py-4 text-center">No student reviews published yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Banner -->
<section class="py-5 bg-white border-top">
    <div class="container text-center py-4">
        <h3 class="fw-bold mb-3">Join our Community Literacy Movement</h3>
        <p class="text-muted mb-4 max-w-600 mx-auto">Have textbooks or study notes sitting idle? Hand them over and transform a college student's future today.</p>
        <div class="d-flex justify-content-center gap-3">
            <a href="register.php?role=donor" class="btn btn-primary rounded-pill px-4 btn-lg">Donate Books</a>
            <a href="register.php?role=recipient" class="btn btn-outline-primary rounded-pill px-4 btn-lg">Request Books</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>