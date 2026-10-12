<?php
/**
 * index.php
 * Public homepage for the Book Donation and Management System.
 * Warm Earthy Literacy & Eco-Community Theme.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Home — Give Books, Spread Knowledge';
$activePage = 'home';

// Fetch live database statistics
$statBooks = 0;
$statDonations = 0;
$statDistributed = 0;
$statVolunteers = 0;

if ($res = $conn->query("SELECT COALESCE(SUM(total_quantity), 0) AS c FROM books")) {
    $statBooks = $res->fetch_assoc()['c'] ?? 0;
}
if ($res = $conn->query("SELECT COUNT(*) AS c FROM donations WHERE status IN ('Received', 'Completed')")) {
    $statDonations = $res->fetch_assoc()['c'] ?? 0;
}
if ($res = $conn->query("SELECT COALESCE(SUM(quantity_distributed), 0) AS c FROM distributions")) {
    $statDistributed = $res->fetch_assoc()['c'] ?? 0;
}
if ($res = $conn->query("SELECT COUNT(*) AS c FROM volunteers")) {
    $statVolunteers = $res->fetch_assoc()['c'] ?? 0;
}

// Fetch 4 featured available books (including image column)
$featuredBooks = [];
$bookQuery = "SELECT b.book_id, b.title, b.author, b.available_quantity, b.book_condition, b.image, c.category_name 
              FROM books b 
              JOIN categories c ON b.category_id = c.category_id 
              WHERE b.available_quantity > 0 AND b.status = 'Available' 
              ORDER BY b.date_added DESC LIMIT 4";
if ($res = $conn->query($bookQuery)) {
    while ($row = $res->fetch_assoc()) {
        $featuredBooks[] = $row;
    }
}

// Fetch upcoming community events
$events = [];
$eventQuery = "SELECT event_id, title, description, event_type, event_date, location 
               FROM events 
               WHERE event_date >= CURDATE() 
               ORDER BY event_date ASC LIMIT 2";
if ($res = $conn->query($eventQuery)) {
    while ($row = $res->fetch_assoc()) {
        $events[] = $row;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section (Earthy Warm Style) -->
<section class="hero-section-warm">
    <div class="container position-relative">
        <div class="row align-items-center gy-5">
            <!-- Left Column: Copy & CTAs -->
            <div class="col-lg-6 pe-lg-4">
                <span class="badge badge-terracotta-pill mb-3">
                    <i class="bi bi-suit-heart-fill me-1 text-terracotta"></i> Community Engagement Project
                </span>
                <h1 class="display-3 fw-extrabold text-dark mb-3" style="line-height: 1.15;">
                    Give Your Books A<br><span class="text-terracotta">Second Life.</span>
                </h1>
                <p class="hero-tagline mb-4">
                    Connect directly with students, school libraries, and avid readers in need. Donate unused textbooks and storybooks, or request the books you need for your education.
                </p>
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'donor'): ?>
                        <a href="donor/donate_book.php" class="btn btn-terracotta btn-lg px-4 py-3 fw-bold rounded-pill shadow-sm">
                            <i class="bi bi-book-half me-2"></i>Donate Books
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-terracotta btn-lg px-4 py-3 fw-bold rounded-pill shadow-sm">
                            <i class="bi bi-book-half me-2"></i>Donate Books
                        </a>
                    <?php endif; ?>
                    <a href="available_books.php" class="btn btn-outline-dark-custom btn-lg px-4 py-3 fw-bold rounded-pill">
                        <i class="bi bi-search me-2"></i>Browse Books
                    </a>
                </div>
                <div class="pt-1">
                    <a href="transparency.php" class="badge badge-sage-pill px-3 py-2 text-decoration-none shadow-sm">
                        <i class="bi bi-shield-check me-1"></i> Public Impact
                    </a>
                </div>
            </div>

            <!-- Right Column: Stacked Book Illustration & Floating Card -->
            <div class="col-lg-6">
                <div class="row align-items-center g-3 justify-content-center">
                    <div class="col-sm-7 text-center">
                        <div class="hero-img-container">
                            <img src="assets/images/hero_books_stack.jpg" alt="Stack of educational books" class="img-fluid">
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <div class="hero-floating-card">
                            <h3 class="fw-bold text-dark mb-2" style="font-size: 1.35rem;">Empowering<br>Readers</h3>
                            <div style="width: 34px; height: 3px; background-color: var(--terracotta); border-radius: 9999px;" class="mb-3"></div>
                            <p class="small text-muted mb-0" style="line-height: 1.6;">
                                From local donors to schools and aspiring scholars across our community.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Mockup Feature Cards Grid -->
        <div class="row g-4 mt-4 pt-3">
            <div class="col-sm-6 col-lg-3">
                <a href="<?= isset($_SESSION['user_id']) && $_SESSION['role'] === 'donor' ? 'donor/donate_book.php' : 'login.php' ?>" class="feature-card-eco">
                    <div class="icon-circle-forest">
                        <i class="bi bi-book"></i>
                    </div>
                    <h5>Donate Books</h5>
                    <p>Give your pre-loved books a new home.</p>
                    <div><span class="card-arrow">&rarr;</span></div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="available_books.php" class="feature-card-eco">
                    <div class="icon-circle-terracotta">
                        <i class="bi bi-search"></i>
                    </div>
                    <h5>Request Books</h5>
                    <p>Find the books you need for your studies.</p>
                    <div><span class="card-arrow">&rarr;</span></div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="register.php" class="feature-card-eco">
                    <div class="icon-circle-forest">
                        <i class="bi bi-people"></i>
                    </div>
                    <h5>Support Community</h5>
                    <p>Help build a better and literate tomorrow.</p>
                    <div><span class="card-arrow">&rarr;</span></div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="transparency.php" class="feature-card-eco">
                    <div class="icon-circle-terracotta">
                        <i class="bi bi-flower1"></i>
                    </div>
                    <h5>Make an Impact</h5>
                    <p>Reduce waste. Promote education. Create change.</p>
                    <div><span class="card-arrow">&rarr;</span></div>
                </a>
            </div>
        </div>

        <!-- Section Title: Together for a Stronger Community -->
        <div class="text-center mt-5 pt-3">
            <div class="section-subtitle-dash">Together for a</div>
            <h2 class="display-6 fw-bold text-dark mt-1">Stronger Community</h2>
        </div>
    </div>
</section>

<!-- Live Counter Strip -->
<section class="py-5 bg-white border-top border-bottom">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon bg-forest-light text-forest mx-auto">
                        <i class="bi bi-bookshelf"></i>
                    </div>
                    <div class="fs-2 fw-bold text-dark"><?= number_format($statBooks) ?>+</div>
                    <div class="text-muted small text-uppercase fw-semibold">Books in Inventory</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon bg-terracotta-light text-terracotta mx-auto">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="fs-2 fw-bold text-dark"><?= number_format($statDonations) ?>+</div>
                    <div class="text-muted small text-uppercase fw-semibold">Donation Drives Completed</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon bg-forest-light text-forest mx-auto">
                        <i class="bi bi-mortarboard"></i>
                    </div>
                    <div class="fs-2 fw-bold text-dark"><?= number_format($statDistributed) ?>+</div>
                    <div class="text-muted small text-uppercase fw-semibold">Books Handed to Students</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon bg-terracotta-light text-terracotta mx-auto">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="fs-2 fw-bold text-dark"><?= number_format($statVolunteers) ?></div>
                    <div class="text-muted small text-uppercase fw-semibold">Active Volunteers</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Books Section with Cover Photos -->
<section class="py-5" style="background-color: var(--bg-base);">
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <span class="badge badge-sage-pill mb-2">Fresh Arrivals</span>
                <h2 class="display-6 fw-bold text-dark mb-0">Recently Added Books</h2>
            </div>
            <a href="available_books.php" class="btn btn-outline-dark-custom rounded-pill px-4">
                View Full Catalog (<?= number_format($statBooks) ?>) <i class="bi bi-arrow-right ms-1 text-terracotta"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php if (!empty($featuredBooks)): ?>
                <?php foreach ($featuredBooks as $b): ?>
                    <div class="col-sm-6 col-lg-3">
                        <div class="custom-card h-100 d-flex flex-column overflow-hidden">
                            <!-- Book Cover with Fallback -->
                            <?php 
                            $idxImgFile = !empty($b['image']) ? basename($b['image']) : '';
                            if ($idxImgFile && file_exists(__DIR__ . '/uploads/book_images/' . $idxImgFile)): ?>
                                <div class="book-cover-showcase">
                                    <img src="uploads/book_images/<?= h($idxImgFile) ?>" alt="<?= h($b['title']) ?>" 
                                         class="book-3d-cover">
                                </div>
                            <?php else: ?>
                                <div class="card-img-placeholder">
                                    <i class="bi bi-journal-text"></i>
                                </div>
                            <?php endif; ?>

                            <div class="p-4 d-flex flex-column flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-forest-light text-forest border-0"><?= h($b['category_name']) ?></span>
                                    <span class="badge badge-condition-<?= strtolower(h($b['book_condition'])) ?>">
                                        <?= h($b['book_condition']) ?>
                                    </span>
                                </div>
                                <h5 class="fw-bold text-dark text-truncate mb-1" title="<?= h($b['title']) ?>"><?= h($b['title']) ?></h5>
                                <p class="small text-muted mb-3">By <?= h($b['author'] ?: 'Unknown') ?></p>
                                <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                    <span class="small text-muted">Available: <strong class="text-dark"><?= (int)$b['available_quantity'] ?></strong></span>
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'recipient'): ?>
                                        <a href="recipient/request_book.php?book_id=<?= (int)$b['book_id'] ?>" class="btn btn-sm btn-terracotta rounded-pill px-3">
                                            Request
                                        </a>
                                    <?php else: ?>
                                        <a href="available_books.php" class="btn btn-sm btn-outline-dark-custom rounded-pill px-3">
                                            Browse
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-4 text-muted">
                    No books in catalog yet. Be the first to donate!
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Upcoming Events Strip -->
<?php if (!empty($events)): ?>
<section class="py-5 bg-white border-top">
    <div class="container py-3">
        <div class="text-center mb-4">
            <span class="badge badge-terracotta-pill mb-2">Community Outreach</span>
            <h2 class="display-6 fw-bold text-dark">Upcoming Donation Drives & Events</h2>
        </div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($events as $ev): ?>
                <div class="col-md-6 col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100" style="border: 1px solid var(--card-border) !important;">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-forest text-white rounded-pill px-3 py-1"><?= h($ev['event_type']) ?></span>
                            <small class="text-muted"><i class="bi bi-calendar-event me-1"></i><?= date('M d, Y', strtotime($ev['event_date'])) ?></small>
                        </div>
                        <h4 class="fw-bold text-dark mb-2"><?= h($ev['title']) ?></h4>
                        <p class="text-muted small mb-3"><?= h($ev['description']) ?></p>
                        <div class="mt-auto small text-muted">
                            <i class="bi bi-geo-alt-fill text-terracotta me-1"></i><?= h($ev['location']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Call to Action -->
<section class="py-5 bg-forest text-white text-center">
    <div class="container py-4">
        <h2 class="display-6 fw-bold mb-3 text-white">Ready to Make an Impact?</h2>
        <p class="lead max-w-700 mx-auto text-light opacity-90 mb-4">
            Join hundreds of citizens, donors, students, and volunteers making education accessible for everyone.
        </p>
        <div class="d-flex justify-content-center flex-wrap gap-3">
            <a href="register.php" class="btn btn-terracotta btn-lg px-5 py-3 fw-bold rounded-pill shadow">
                Join As Donor / Volunteer
            </a>
            <a href="how_it_works.php" class="btn btn-outline-light btn-lg px-4 py-3 rounded-pill">
                Learn How It Works
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>