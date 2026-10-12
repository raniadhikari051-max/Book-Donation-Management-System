<?php
/**
 * book_details.php
 * Comprehensive Book Details & Access Options Page.
 * Displays full book specifications, condition, pricing (Original vs 50% Lifetime Price),
 * interactive borrowing duration calculator, and access buttons (Buy for Lifetime vs Borrow).
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$bookId = (int)($_GET['id'] ?? ($_GET['book_id'] ?? 0));
if ($bookId <= 0) {
    redirect('available_books.php');
}

// Fetch book details with category and donor name
$stmt = $conn->prepare("SELECT b.*, c.category_name, 
                        u.full_name AS donor_full_name, dn.organization_name
                        FROM books b
                        JOIN categories c ON b.category_id = c.category_id
                        LEFT JOIN donors dn ON b.donor_id = dn.donor_id
                        LEFT JOIN users u ON dn.user_id = u.user_id
                        WHERE b.book_id = ?");
$stmt->bind_param("i", $bookId);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
    set_flash('danger', 'Book not found.');
    redirect('available_books.php');
}

$pageTitle = $book['title'] . ' — Book Details';
$activePage = 'books';

$origPrice = (float)($book['original_price'] > 0 ? $book['original_price'] : 500.00);
$lifePrice = (float)($book['lifetime_price'] > 0 ? $book['lifetime_price'] : round($origPrice * 0.5, 2));
$youSave = $origPrice - $lifePrice;

$isAvailable = ($book['available_quantity'] > 0 && $book['status'] === 'Available');

// Availability badge logic
$availStatus = $book['status'];
if ($book['available_quantity'] <= 0 && $availStatus === 'Available') {
    $availStatus = 'Borrowed';
}
$statusBadgeClass = match($availStatus) {
    'Available' => 'badge-available',
    'Borrowed' => 'badge-borrowed',
    'Reserved' => 'badge-pending',
    'Sold', 'Unavailable', 'Damaged' => 'badge-overdue',
    'Under Verification' => 'badge-verification',
    default => 'bg-secondary text-white'
};

$donatedByDisplay = 'Anonymous Supporter';
if (!empty($book['organization_name'])) {
    $donatedByDisplay = $book['organization_name'];
} elseif (!empty($book['donor_full_name'])) {
    // Anonymize slightly for privacy (e.g. Ramesh K.)
    $parts = explode(' ', trim($book['donor_full_name']));
    $donatedByDisplay = $parts[0] . (isset($parts[1]) ? ' ' . substr($parts[1], 0, 1) . '.' : '');
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <?= render_flash() ?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-forest text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="available_books.php" class="text-forest text-decoration-none">Available Books</a></li>
            <li class="breadcrumb-item active text-truncate" style="max-width: 300px;" aria-current="page"><?= h($book['title']) ?></li>
        </ol>
    </nav>

    <div class="row g-5">
        <!-- Book Cover Column -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center position-relative overflow-hidden">
                <div class="position-absolute top-0 start-0 m-3 z-2">
                    <span class="badge <?= $statusBadgeClass ?> px-3 py-2 rounded-pill fs-7">
                        ● <?= h($availStatus) ?>
                    </span>
                </div>

                <div class="rounded-4 overflow-hidden shadow-sm d-flex align-items-center justify-content-center bg-light border p-2" style="min-height: 440px;">
                    <?php 
                    $bookImg = !empty($book['image']) ? basename($book['image']) : '';
                    if ($bookImg && file_exists(__DIR__ . '/uploads/book_images/' . $bookImg)): ?>
                        <img src="uploads/book_images/<?= h($bookImg) ?>" alt="<?= h($book['title']) ?>" 
                             class="img-fluid rounded-3 shadow-sm" style="max-height: 420px; width: auto; object-fit: contain;">
                    <?php else: ?>
                        <div class="text-muted text-center py-5">
                            <i class="bi bi-journal-bookmark display-1 text-secondary opacity-50 d-block mb-3"></i>
                            <span class="badge bg-light text-muted border">No Cover Image Uploaded</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top px-2">
                    <span class="small text-muted"><i class="bi bi-boxes me-1"></i>Stock Available: <strong><?= (int)$book['available_quantity'] ?> copy(ies)</strong></span>
                    <span class="small text-muted"><i class="bi bi-translate me-1"></i>Language: <strong><?= h($book['language'] ?? 'English') ?></strong></span>
                </div>
            </div>

            <!-- Delivery & Hub Info Box -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mt-4 bg-white">
                <h6 class="fw-bold text-forest mb-3"><i class="bi bi-truck me-2"></i>Handover & Delivery Options</h6>
                <ul class="list-unstyled small text-muted mb-0">
                    <li class="mb-2 d-flex align-items-center">
                        <i class="bi bi-geo-alt-fill text-terracotta me-2"></i>
                        <span><strong>Free Hub Pickup:</strong> Ready within 24 hours at Pune Central Hub</span>
                    </li>
                    <li class="mb-2 d-flex align-items-center">
                        <i class="bi bi-box-seam-fill text-forest me-2"></i>
                        <span><strong>Doorstep Dispatch:</strong> Dispatched within 1-2 business days with live tracking</span>
                    </li>
                    <li class="d-flex align-items-center">
                        <i class="bi bi-shield-check text-success me-2"></i>
                        <span><strong>Quality Verified:</strong> Thoroughly inspected physical copy</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Book Information & Access Options Column -->
        <div class="col-lg-7">
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge bg-forest-light text-forest px-3 py-1 rounded-pill">
                    <?= h($book['category_name']) ?>
                </span>
                <span class="badge badge-condition-<?= strtolower(str_replace(' ', '', h($book['book_condition']))) ?> px-3 py-1 rounded-pill">
                    Condition: <?= h($book['book_condition']) ?>
                </span>
            </div>

            <h1 class="display-6 fw-bold text-dark mb-2"><?= h($book['title']) ?></h1>
            <p class="lead text-muted mb-3">By <strong class="text-dark"><?= h($book['author'] ?: 'Unknown Author') ?></strong></p>

            <!-- Book Specs Grid -->
            <div class="row g-2 p-3 bg-light rounded-4 mb-4 small">
                <div class="col-sm-6">
                    <span class="text-muted">ISBN:</span> 
                    <strong><?= h($book['isbn'] ?: 'N/A') ?></strong>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted">Edition:</span> 
                    <strong><?= h($book['edition'] ?: 'Standard Edition') ?></strong>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted">Publisher:</span> 
                    <strong><?= h($book['publisher'] ?: 'Educational Press') ?></strong>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted">Donated By:</span> 
                    <strong class="text-forest"><i class="bi bi-heart-fill text-danger me-1"></i><?= h($donatedByDisplay) ?></strong>
                </div>
            </div>

            <!-- Description -->
            <div class="mb-4">
                <h6 class="fw-bold text-dark">About This Book</h6>
                <p class="text-secondary" style="line-height: 1.7;">
                    <?= nl2br(h($book['description'] ?: 'A classic academic and learning resource donated to support community reading and education. In clean and completely readable condition.')) ?>
                </p>
            </div>

            <!-- Access Options Card (Lifetime Purchase vs Borrow) -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4 border-start border-4 border-terracotta">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-key-fill text-terracotta me-2"></i>Choose How to Access This Book</h5>

                <?php if ($isAvailable): ?>
                    <div class="row g-3">
                        <!-- Option A: Buy for Lifetime (Requirement 3) -->
                        <div class="col-md-6">
                            <div class="border rounded-4 p-3 h-100 d-flex flex-column justify-content-between bg-light">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-terracotta text-white rounded-pill px-2 py-1 small">OPTION A</span>
                                        <span class="badge-save">SAVE 50%</span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">Buy for Lifetime</h6>
                                    <p class="small text-muted mb-2">Permanent ownership. Book remains permanently linked to your account.</p>

                                    <!-- Price Breakdown (Requirement 3) -->
                                    <div class="p-2 bg-white rounded-3 mb-3 border">
                                        <div class="d-flex justify-content-between small text-muted">
                                            <span>Original Price:</span>
                                            <span class="price-original"><?= format_currency($origPrice) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-1">
                                            <span class="fw-bold text-dark">Lifetime Price:</span>
                                            <span class="price-lifetime"><?= format_currency($lifePrice) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between small text-success mt-1 pt-1 border-top">
                                            <span>You Save:</span>
                                            <strong><?= format_currency($youSave) ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <a href="checkout.php?book_id=<?= (int)$book['book_id'] ?>&type=lifetime" 
                                   class="btn btn-terracotta rounded-pill w-100 py-2 fw-bold shadow-sm">
                                    <i class="bi bi-bag-check-fill me-1"></i>Buy for Lifetime – 50% Price
                                </a>
                            </div>
                        </div>

                        <!-- Option B: Borrow for Limited Period (Requirement 4) -->
                        <div class="col-md-6">
                            <div class="border rounded-4 p-3 h-100 d-flex flex-column justify-content-between bg-light">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-forest text-white rounded-pill px-2 py-1 small">OPTION B</span>
                                        <span class="badge bg-white text-forest border rounded-pill small">FLEXIBLE DURATION</span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">Borrow for Limited Period</h6>
                                    <p class="small text-muted mb-2">Read and return within your chosen duration. Automatic return reminders.</p>

                                    <div class="p-2 bg-white rounded-3 mb-3 border">
                                        <div class="small text-muted mb-1">Available Borrowing Periods:</div>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="badge bg-light text-dark border">7 Days</span>
                                            <span class="badge bg-light text-dark border">15 Days</span>
                                            <span class="badge bg-light text-dark border">30 Days</span>
                                            <span class="badge bg-light text-dark border">60 Days</span>
                                            <span class="badge bg-light text-dark border">90 Days</span>
                                            <span class="badge bg-light text-primary border">Custom</span>
                                        </div>
                                        <div class="small text-success mt-2">
                                            <i class="bi bi-check-circle me-1"></i>Free for students & community readers
                                        </div>
                                    </div>
                                </div>

                                <button type="button" class="btn btn-forest rounded-pill w-100 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#borrowDurationModal">
                                    <i class="bi bi-arrow-repeat me-1"></i>Borrow This Book
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary text-center py-4 rounded-4 mb-0">
                        <i class="bi bi-exclamation-circle fs-3 d-block mb-2"></i>
                        <h6 class="fw-bold mb-1">Currently Not Available in Stock</h6>
                        <p class="small text-muted mb-0">All physical copies of this book are currently <?= strtolower(h($availStatus)) ?>. Please check back soon or browse other available titles.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Borrow Duration Modal (Requirement 4) -->
<div class="modal fade" id="borrowDurationModal" tabindex="-1" aria-labelledby="borrowModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom p-4">
                <div>
                    <h5 class="modal-title fw-bold text-forest" id="borrowModalLabel">
                        <i class="bi bi-clock-history me-2"></i>Borrow Book: Select Duration
                    </h5>
                    <small class="text-muted"><?= h($book['title']) ?></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="GET" action="checkout.php">
                <input type="hidden" name="book_id" value="<?= (int)$book['book_id'] ?>">
                <input type="hidden" name="type" value="borrow">

                <div class="modal-body p-4">
                    <label class="form-label fw-bold small">How long do you want to keep this book?</label>

                    <!-- Predefined Durations -->
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <input type="radio" class="btn-check duration-radio" name="preset_duration" id="dur7" value="7">
                            <label class="btn btn-outline-forest w-100 rounded-3 py-2 small" for="dur7">7 Days</label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check duration-radio" name="preset_duration" id="dur15" value="15" checked>
                            <label class="btn btn-outline-forest w-100 rounded-3 py-2 small" for="dur15">15 Days</label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check duration-radio" name="preset_duration" id="dur30" value="30">
                            <label class="btn btn-outline-forest w-100 rounded-3 py-2 small" for="dur30">30 Days</label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check duration-radio" name="preset_duration" id="dur60" value="60">
                            <label class="btn btn-outline-forest w-100 rounded-3 py-2 small" for="dur60">60 Days</label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check duration-radio" name="preset_duration" id="dur90" value="90">
                            <label class="btn btn-outline-forest w-100 rounded-3 py-2 small" for="dur90">90 Days</label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check duration-radio" name="preset_duration" id="durCustom" value="custom">
                            <label class="btn btn-outline-primary w-100 rounded-3 py-2 small" for="durCustom">Custom</label>
                        </div>
                    </div>

                    <!-- Custom Duration Date Picker (Requirement 4) -->
                    <div id="customDurationSection" class="p-3 border rounded-3 bg-light mb-3" style="display: none;">
                        <h6 class="small fw-bold text-dark mb-2">Custom Date Range:</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small text-muted">Start Date</label>
                                <input type="date" name="start_date" id="borrowStartDate" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted">Return Date</label>
                                <input type="date" name="return_date" id="borrowReturnDate" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Live Calculated Summary Card -->
                    <div class="p-3 rounded-4 bg-forest-light text-forest border border-success">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small">Borrow Period:</span>
                            <strong id="displayPeriod">15 Days</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small">Start Date:</span>
                            <span id="displayStartDate"><?= date('d F Y') ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small">Return Date:</span>
                            <strong class="text-terracotta" id="displayReturnDate"><?= date('d F Y', strtotime('+15 days')) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-forest rounded-pill px-5 fw-bold shadow-sm">
                        Continue to Checkout <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dynamic Duration Calculation Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const radioButtons = document.querySelectorAll('.duration-radio');
    const customSection = document.getElementById('customDurationSection');
    const startDateInp = document.getElementById('borrowStartDate');
    const returnDateInp = document.getElementById('borrowReturnDate');
    const displayPeriod = document.getElementById('displayPeriod');
    const displayStartDate = document.getElementById('displayStartDate');
    const displayReturnDate = document.getElementById('displayReturnDate');

    function formatDate(date) {
        return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    function calculateDates() {
        const selectedRadio = document.querySelector('.duration-radio:checked');
        const now = new Date();
        const start = startDateInp.value ? new Date(startDateInp.value) : now;

        if (selectedRadio && selectedRadio.value !== 'custom') {
            customSection.style.display = 'none';
            const days = parseInt(selectedRadio.value, 10);
            const returnD = new Date(start);
            returnD.setDate(start.getDate() + days);

            // Format for inputs
            const yyyy = returnD.getFullYear();
            const mm = String(returnD.getMonth() + 1).padStart(2, '0');
            const dd = String(returnD.getDate()).padStart(2, '0');
            returnDateInp.value = `${yyyy}-${mm}-${dd}`;

            displayPeriod.innerText = `${days} Days`;
            displayStartDate.innerText = formatDate(start);
            displayReturnDate.innerText = formatDate(returnD);
        } else {
            customSection.style.display = 'block';
            const ret = new Date(returnDateInp.value);
            const diffTime = ret.getTime() - start.getTime();
            const diffDays = Math.max(1, Math.round(diffTime / (1000 * 3600 * 24)));

            displayPeriod.innerText = `${diffDays} Days`;
            displayStartDate.innerText = formatDate(start);
            displayReturnDate.innerText = formatDate(ret);
        }
    }

    radioButtons.forEach(rb => rb.addEventListener('change', calculateDates));
    startDateInp.addEventListener('change', calculateDates);
    returnDateInp.addEventListener('change', calculateDates);

    // Initial calculation
    calculateDates();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
