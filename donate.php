<?php
/**
 * donate.php
 * Public and Member Book Donation Portal.
 * Allows donating single or multiple books with image upload, condition rating,
 * logistics options (doorstep pickup vs hub drop-off), and generates unique Donation ID (DON-2026-XXXXX).
 * Also includes a live Donation ID status lookup.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Donate Books — Community Literacy Drive';
$activePage = 'donate';

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? null;

// Prefill data if logged in
$donorName = $_SESSION['full_name'] ?? '';
$donorEmail = '';
$donorPhone = '';
$donorAddress = '';

if ($isLoggedIn) {
    $uStmt = $conn->prepare("SELECT email, phone, address FROM users WHERE user_id = ?");
    $uStmt->bind_param("i", $userId);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    if ($uRow) {
        $donorEmail = $uRow['email'] ?? '';
        $donorPhone = $uRow['phone'] ?? '';
        $donorAddress = $uRow['address'] ?? '';
    }
}

// Fetch categories
$categories = [];
if ($catRes = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC")) {
    while ($c = $catRes->fetch_assoc()) {
        $categories[] = $c;
    }
}

$errors = [];
$successDonation = null;

// Handle Donation Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_donation'])) {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token mismatch. Please reload and try again.';
    } else {
        $donorNameInput = clean_input($_POST['donor_name'] ?? '');
        $donorEmailInput = clean_input($_POST['contact_email'] ?? '');
        $donorPhoneInput = clean_input($_POST['contact_phone'] ?? '');
        $pickupOption = clean_input($_POST['pickup_option'] ?? 'Drop-off');
        $address = clean_input($_POST['address'] ?? '');
        $preferredDate = clean_input($_POST['preferred_date'] ?? date('Y-m-d'));
        $remarks = clean_input($_POST['remarks'] ?? '');

        if (empty($donorNameInput)) {
            $errors[] = 'Donor name is required.';
        }
        if (!empty($donorEmailInput) && !is_valid_email($donorEmailInput)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($pickupOption === 'Pickup' && empty($address)) {
            $errors[] = 'Doorstep pickup address is required.';
        }

        // Parse line items
        $titles = $_POST['title'] ?? [];
        $authors = $_POST['author'] ?? [];
        $isbns = $_POST['isbn'] ?? [];
        $editions = $_POST['edition'] ?? [];
        $categoryIds = $_POST['category_id'] ?? [];
        $conditions = $_POST['condition'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $descriptions = $_POST['description'] ?? [];

        $booksData = [];
        $uploadDir = __DIR__ . '/uploads/book_images/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        for ($i = 0; $i < count($titles); $i++) {
            $t = clean_input($titles[$i] ?? '');
            if ($t !== '') {
                $savedImgName = null;
                // Handle file upload for this book row if uploaded
                if (isset($_FILES['book_image']['name'][$i]) && !empty($_FILES['book_image']['name'][$i])) {
                    $fileTmp = $_FILES['book_image']['tmp_name'][$i];
                    $fileSize = $_FILES['book_image']['size'][$i];
                    $fileErr = $_FILES['book_image']['error'][$i];
                    if ($fileErr === UPLOAD_ERR_OK && $fileSize <= 3 * 1024 * 1024) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $fileTmp);
                        finfo_close($finfo);
                        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
                            $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
                            $savedImgName = 'don_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            move_uploaded_file($fileTmp, $uploadDir . $savedImgName);
                        }
                    }
                }

                $cond = clean_input($conditions[$i] ?? 'Good');
                if (!in_array($cond, ['New', 'Like New', 'Good', 'Acceptable'])) {
                    $cond = 'Good';
                }

                $booksData[] = [
                    'title' => $t,
                    'author' => clean_input($authors[$i] ?? 'Unknown Author'),
                    'isbn' => clean_input($isbns[$i] ?? ''),
                    'edition' => clean_input($editions[$i] ?? ''),
                    'category_id' => max(1, (int)($categoryIds[$i] ?? 1)),
                    'condition' => $cond,
                    'quantity' => max(1, (int)($quantities[$i] ?? 1)),
                    'description' => clean_input($descriptions[$i] ?? ''),
                    'image' => $savedImgName
                ];
            }
        }

        if (empty($booksData)) {
            $errors[] = 'Please specify at least one book title to donate.';
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // Ensure a donor record exists if user is logged in
                $donorId = null;
                if ($userId) {
                    $dStmt = $conn->prepare("SELECT donor_id FROM donors WHERE user_id = ?");
                    $dStmt->bind_param("i", $userId);
                    $dStmt->execute();
                    $dRes = $dStmt->get_result()->fetch_assoc();
                    $dStmt->close();
                    if ($dRes) {
                        $donorId = (int)$dRes['donor_id'];
                    } else {
                        $insD = $conn->prepare("INSERT INTO donors (user_id, total_books_donated, points) VALUES (?, 0, 0)");
                        $insD->bind_param("i", $userId);
                        $insD->execute();
                        $donorId = $insD->insert_id;
                        $insD->close();
                    }
                } else {
                    $donorId = 1; // Fallback demo donor profile for guest donations
                }

                $donationCode = generate_donation_code();
                $todayDate = date('Y-m-d');
                $status = 'Submitted';
                $verificationStatus = 'Pending';

                $insH = $conn->prepare("INSERT INTO donations (donation_code, donor_id, donor_name, contact_email, contact_phone, pickup_option, address, preferred_date, donation_date, status, verification_status, remarks, qr_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insH->bind_param('sisssssssssss', $donationCode, $donorId, $donorNameInput, $donorEmailInput, $donorPhoneInput, $pickupOption, $address, $preferredDate, $todayDate, $status, $verificationStatus, $remarks, $donationCode);
                $insH->execute();
                $donationPkId = $insH->insert_id;
                $insH->close();

                // Insert line items
                $insItem = $conn->prepare("INSERT INTO donation_books (donation_id, title, author, isbn, edition, category_id, quantity, book_condition, image, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($booksData as $b) {
                    $insItem->bind_param('issssiisss', $donationPkId, $b['title'], $b['author'], $b['isbn'], $b['edition'], $b['category_id'], $b['quantity'], $b['condition'], $b['image'], $b['description']);
                    $insItem->execute();
                }
                $insItem->close();

                // Log and notify
                if ($userId) {
                    add_notification($conn, $userId, "Donation package {$donationCode} submitted with " . count($booksData) . " book title(s). Current status: Submitted.");
                    log_activity($conn, 'DONATION_SUBMIT', "User submitted donation {$donationCode} ({$donationPkId})", $userId);
                }

                $conn->commit();
                $successDonation = [
                    'donation_id' => $donationPkId,
                    'donation_code' => $donationCode,
                    'donor_name' => $donorNameInput,
                    'books_count' => count($booksData),
                    'total_copies' => array_sum(array_column($booksData, 'quantity')),
                    'status' => $status
                ];
            } catch (Exception $ex) {
                $conn->rollback();
                $errors[] = 'Database error submitting donation: ' . $ex->getMessage();
            }
        }
    }
}

// Handle Donation Tracking Lookup (Check Status)
$searchedDonation = null;
$searchError = '';
if (isset($_GET['lookup_code']) && !empty(trim($_GET['lookup_code']))) {
    $lookupCode = clean_input($_GET['lookup_code']);
    $lookupStmt = $conn->prepare("SELECT d.*, u.full_name AS registered_name,
                                  (SELECT COUNT(*) FROM donation_books db WHERE db.donation_id = d.donation_id) AS total_titles,
                                  (SELECT COALESCE(SUM(quantity), 0) FROM donation_books db WHERE db.donation_id = d.donation_id) AS total_copies
                                  FROM donations d
                                  LEFT JOIN donors dn ON d.donor_id = dn.donor_id
                                  LEFT JOIN users u ON dn.user_id = u.user_id
                                  WHERE d.donation_code = ? OR d.qr_code = ? OR d.donation_id = ?");
    $lookupInt = (int)$lookupCode;
    $lookupStmt->bind_param('ssi', $lookupCode, $lookupCode, $lookupInt);
    $lookupStmt->execute();
    $searchedDonation = $lookupStmt->get_result()->fetch_assoc();
    $lookupStmt->close();

    if ($searchedDonation) {
        $lItemsStmt = $conn->prepare("SELECT db.*, c.category_name FROM donation_books db LEFT JOIN categories c ON db.category_id = c.category_id WHERE db.donation_id = ?");
        $lItemsStmt->bind_param('i', $searchedDonation['donation_id']);
        $lItemsStmt->execute();
        $searchedDonation['items'] = $lItemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $lItemsStmt->close();
    } else {
        $searchError = "No donation record found matching '{$lookupCode}'. Please check your Donation ID.";
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Header -->
<div class="bg-forest text-white py-5 position-relative overflow-hidden">
    <div class="container position-relative z-2">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="badge bg-terracotta text-white rounded-pill px-3 py-2 mb-3 fw-bold">
                    <i class="bi bi-heart-fill me-1"></i> Give the Gift of Knowledge
                </span>
                <h1 class="display-5 fw-bold mb-3 text-white">Donate Books, Change Lives</h1>
                <p class="lead text-light mb-4" style="opacity: 0.95;">
                    Donate your textbooks, reference guides, and literature to empower underprivileged students. 
                    <strong>Donate 10 or more approved books to unlock a 50% discount coupon</strong> on purchased books!
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#donationFormSection" class="btn btn-terracotta rounded-pill px-4 py-2 shadow-sm fw-bold">
                        <i class="bi bi-plus-circle-fill me-2"></i>Donate Now
                    </a>
                    <a href="#statusLookupSection" class="btn btn-outline-light rounded-pill px-4 py-2">
                        <i class="bi bi-search me-2"></i>Check Donation Status
                    </a>
                </div>
            </div>
            <div class="col-lg-5 text-center mt-4 mt-lg-0">
                <div class="p-4 bg-white text-dark rounded-4 shadow-lg text-start">
                    <h5 class="fw-bold text-forest mb-3"><i class="bi bi-gift-fill text-terracotta me-2"></i>Donor Reward Program</h5>
                    <div class="d-flex align-items-center mb-3 p-3 rounded-3 bg-light">
                        <div class="fs-1 text-terracotta me-3"><i class="bi bi-ticket-perforated-fill"></i></div>
                        <div>
                            <div class="fw-bold">10+ Approved Books = 50% Coupon</div>
                            <small class="text-muted">Earn an exclusive 50% discount voucher linked to your account for future lifetime book purchases.</small>
                        </div>
                    </div>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Free doorstep pickup or hub drop-off</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Official Digital Certificate of Appreciation</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Real-time status tracking via unique Donation ID</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <?= render_flash() ?>

    <?php if ($successDonation): ?>
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-5 border-start border-5 border-success">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="display-3 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
                    <h2 class="fw-bold text-dark">Thank You for Your Generous Donation!</h2>
                    <p class="lead text-muted">Your donation package has been registered in our system.</p>
                </div>

                <div class="p-4 rounded-4 bg-light mb-4 text-center">
                    <div class="text-muted small text-uppercase fw-bold letter-spacing-1 mb-1">Your Unique Donation ID</div>
                    <div class="display-6 fw-bold text-terracotta mb-2 font-monospace"><?= h($successDonation['donation_code']) ?></div>
                    <p class="small text-muted mb-0">Please keep this ID for your records. You can use it anytime to track verification and pickup.</p>
                </div>

                <div class="row g-3 mb-4 text-center">
                    <div class="col-sm-4">
                        <div class="border rounded-3 p-3 bg-white">
                            <span class="text-muted small">Titles Donated</span>
                            <h4 class="fw-bold mb-0 text-forest"><?= (int)$successDonation['books_count'] ?></h4>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="border rounded-3 p-3 bg-white">
                            <span class="text-muted small">Total Copies</span>
                            <h4 class="fw-bold mb-0 text-forest"><?= (int)$successDonation['total_copies'] ?></h4>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="border rounded-3 p-3 bg-white">
                            <span class="text-muted small">Current Status</span>
                            <div><span class="badge badge-pending px-3 py-2 rounded-pill"><?= h($successDonation['status']) ?></span></div>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-white border rounded-4 mb-4">
                    <h6 class="fw-bold text-dark mb-3 text-center">Donation Lifecycle Progress</h6>
                    <div class="d-flex flex-wrap justify-content-between text-center position-relative">
                        <div class="p-2 flex-fill">
                            <div class="badge rounded-circle p-2 bg-success text-white mb-1"><i class="bi bi-check-lg"></i></div>
                            <div class="small fw-bold">Submitted</div>
                        </div>
                        <div class="p-2 flex-fill">
                            <div class="badge rounded-circle p-2 bg-warning text-dark mb-1"><i class="bi bi-clock"></i></div>
                            <div class="small text-muted">Under Verification</div>
                        </div>
                        <div class="p-2 flex-fill">
                            <div class="badge rounded-circle p-2 bg-light text-muted border mb-1"><i class="bi bi-check2-circle"></i></div>
                            <div class="small text-muted">Approved</div>
                        </div>
                        <div class="p-2 flex-fill">
                            <div class="badge rounded-circle p-2 bg-light text-muted border mb-1"><i class="bi bi-truck"></i></div>
                            <div class="small text-muted">Received</div>
                        </div>
                        <div class="p-2 flex-fill">
                            <div class="badge rounded-circle p-2 bg-light text-muted border mb-1"><i class="bi bi-box2-heart"></i></div>
                            <div class="small text-muted">Available to Students</div>
                        </div>
                    </div>
                </div>

                <div class="text-center d-flex flex-wrap justify-content-center gap-3">
                    <a href="?lookup_code=<?= urlencode($successDonation['donation_code']) ?>#statusLookupSection" class="btn btn-forest rounded-pill px-4">
                        <i class="bi bi-eye me-1"></i>Track This Donation
                    </a>
                    <?php if ($isLoggedIn): ?>
                        <a href="user_dashboard.php?tab=donations" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="bi bi-grid me-1"></i>Go to My Dashboard
                        </a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-terracotta rounded-pill px-4">
                            <i class="bi bi-person-plus me-1"></i>Create Account to Claim Rewards
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger rounded-4 shadow-sm mb-4">
            <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please correct the following:</h6>
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Main Donation Form -->
        <div class="col-lg-8" id="donationFormSection">
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold text-dark mb-1">Book Donation Form</h4>
                            <p class="text-muted small mb-0">Fill in the details for each book you would like to contribute.</p>
                        </div>
                        <span class="badge bg-forest-light text-forest px-3 py-2 rounded-pill">
                            <i class="bi bi-layers-half me-1"></i>Multi-Book Support
                        </span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form method="POST" action="donate.php" enctype="multipart/form-data" id="donationMainForm">
                        <?= csrf_field() ?>

                        <!-- Section 1: Donor Contact Information -->
                        <div class="mb-4 p-3 bg-light rounded-4">
                            <h6 class="fw-bold text-forest mb-3">
                                <i class="bi bi-person-badge-fill me-2"></i>1. Donor & Contact Information
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Donor Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="donor_name" class="form-control" required 
                                           placeholder="e.g. Ramesh Kulkarni" value="<?= h($donorName) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Email Address</label>
                                    <input type="email" name="contact_email" class="form-control" 
                                           placeholder="e.g. ramesh@example.com" value="<?= h($donorEmail) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Contact Phone Number</label>
                                    <input type="tel" name="contact_phone" class="form-control" 
                                           placeholder="e.g. 9876543210" value="<?= h($donorPhone) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Donation Date</label>
                                    <input type="date" name="preferred_date" class="form-control" 
                                           value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Book Details (Dynamic Rows) -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-forest mb-0">
                                    <i class="bi bi-journal-plus me-2"></i>2. Books to Donate
                                </h6>
                                <button type="button" class="btn btn-sm btn-outline-forest rounded-pill" id="btnAddBook">
                                    <i class="bi bi-plus-lg me-1"></i>Add Another Book
                                </button>
                            </div>

                            <div id="booksContainer">
                                <!-- Book Row #1 -->
                                <div class="book-row border rounded-4 p-3 mb-3 bg-white shadow-sm position-relative">
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                        <span class="fw-bold text-terracotta row-index-label"><i class="bi bi-book me-1"></i>Book #1</span>
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle btn-remove-book d-none" title="Remove book">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label class="form-label small fw-bold">Book Name / Title <span class="text-danger">*</span></label>
                                            <input type="text" name="title[]" class="form-control" required placeholder="e.g. Higher Engineering Mathematics">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Author</label>
                                            <input type="text" name="author[]" class="form-control" placeholder="e.g. B.S. Grewal">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">ISBN (Optional)</label>
                                            <input type="text" name="isbn[]" class="form-control" placeholder="e.g. 978-8174091955">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Category <span class="text-danger">*</span></label>
                                            <select name="category_id[]" class="form-select" required>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= (int)$cat['category_id'] ?>"><?= h($cat['category_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Edition</label>
                                            <input type="text" name="edition[]" class="form-control" placeholder="e.g. 44th Edition">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Condition <span class="text-danger">*</span></label>
                                            <select name="condition[]" class="form-select" required>
                                                <option value="New">New (Mint / Unused)</option>
                                                <option value="Like New">Like New (Barely used)</option>
                                                <option value="Good" selected>Good (Minor wear, completely intact)</option>
                                                <option value="Acceptable">Acceptable (Readable, marked)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Number of Copies <span class="text-danger">*</span></label>
                                            <input type="number" name="quantity[]" class="form-control" min="1" value="1" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Book Cover Image</label>
                                            <input type="file" name="book_image[]" class="form-control" accept="image/jpeg,image/png,image/webp">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-bold">Description / Notes</label>
                                            <textarea name="description[]" class="form-control" rows="2" placeholder="Brief note about the book content or condition..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Logistics & Handover Method -->
                        <div class="mb-4 p-3 bg-light rounded-4">
                            <h6 class="fw-bold text-forest mb-3">
                                <i class="bi bi-truck me-2"></i>3. Handover & Submission Option
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check p-3 border rounded-3 bg-white h-100">
                                        <input class="form-check-input" type="radio" name="pickup_option" id="optDropOff" value="Drop-off" checked>
                                        <label class="form-check-label ms-2" for="optDropOff">
                                            <div class="fw-bold text-dark"><i class="bi bi-building me-1 text-primary"></i>Drop-off at Community Hub</div>
                                            <small class="text-muted">Handover books at our main library center (Free & fastest verification)</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check p-3 border rounded-3 bg-white h-100">
                                        <input class="form-check-input" type="radio" name="pickup_option" id="optPickup" value="Pickup">
                                        <label class="form-check-label ms-2" for="optPickup">
                                            <div class="fw-bold text-dark"><i class="bi bi-house-door me-1 text-terracotta"></i>Doorstep Volunteer Collection</div>
                                            <small class="text-muted">A verified volunteer will schedule a doorstep collection from your address</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12" id="pickupAddressGroup" style="display: none;">
                                    <label class="form-label small fw-bold">Doorstep Pickup Address <span class="text-danger">*</span></label>
                                    <input type="text" name="address" id="pickupAddressInput" class="form-control" 
                                           placeholder="Enter complete residential / office address with pincode" value="<?= h($donorAddress) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Additional Remarks (Optional)</label>
                                    <input type="text" name="remarks" class="form-control" placeholder="Any instructions for our verification or collection team...">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">
                                <i class="bi bi-shield-check text-success me-1"></i>Every donated book is inspected by our team
                            </span>
                            <button type="submit" name="submit_donation" class="btn btn-terracotta rounded-pill px-5 py-2 fw-bold shadow-sm">
                                <i class="bi bi-send-fill me-2"></i>Submit Donation Package
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar: Track Existing Donation Status & Reward Banner -->
        <div class="col-lg-4">
            <!-- Donation Status Lookup (Requirement 1) -->
            <div class="card border-0 shadow-sm rounded-4 bg-white mb-4" id="statusLookupSection">
                <div class="card-header bg-white border-bottom p-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-search text-primary me-2"></i>Check Donation Status</h6>
                </div>
                <div class="card-body p-3">
                    <form method="GET" action="donate.php#statusLookupSection">
                        <div class="input-group mb-2">
                            <input type="text" name="lookup_code" class="form-control font-monospace" required
                                   placeholder="e.g. DON-2026-00125" value="<?= h($_GET['lookup_code'] ?? '') ?>">
                            <button class="btn btn-forest" type="submit">Lookup</button>
                        </div>
                        <small class="text-muted">Enter the unique Donation ID given at submission.</small>
                    </form>

                    <?php if ($searchError): ?>
                        <div class="alert alert-warning small rounded-3 mt-3 mb-0"><?= h($searchError) ?></div>
                    <?php endif; ?>

                    <?php if ($searchedDonation): ?>
                        <div class="border rounded-3 p-3 mt-3 bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="font-monospace fw-bold text-terracotta"><?= h($searchedDonation['donation_code']) ?></span>
                                <?php
                                $st = $searchedDonation['status'];
                                $badgeClass = ($st === 'Available' || $st === 'Received' || $st === 'Approved') ? 'badge-available' : (($st === 'Rejected') ? 'badge-overdue' : 'badge-pending');
                                ?>
                                <span class="badge <?= $badgeClass ?> px-2 py-1"><?= h($st) ?></span>
                            </div>
                            <div class="small mb-1"><strong>Donor:</strong> <?= h($searchedDonation['donor_name'] ?: ($searchedDonation['registered_name'] ?? 'Community Donor')) ?></div>
                            <div class="small mb-1"><strong>Date:</strong> <?= date('d M Y', strtotime($searchedDonation['created_at'])) ?></div>
                            <div class="small mb-2"><strong>Total Copies:</strong> <?= (int)$searchedDonation['total_copies'] ?></div>

                            <!-- Stepper for this donation -->
                            <div class="border-top pt-2">
                                <div class="small fw-bold text-dark mb-1">Status Lifecycle:</div>
                                <?php
                                $statuses = ['Submitted', 'Under Verification', 'Approved', 'Received', 'Available'];
                                $currentIdx = array_search($st, $statuses);
                                if ($currentIdx === false && $st === 'Pending') $currentIdx = 0;
                                ?>
                                <ul class="list-unstyled small mb-0">
                                    <?php foreach ($statuses as $idx => $sName): ?>
                                        <li class="d-flex align-items-center mb-1 <?= ($currentIdx !== false && $idx <= $currentIdx) ? 'text-success fw-bold' : 'text-muted' ?>">
                                            <i class="bi <?= ($currentIdx !== false && $idx <= $currentIdx) ? 'bi-check-circle-fill me-2' : 'bi-circle me-2' ?>"></i>
                                            <?= $sName ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reward Eligibility Info Card -->
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                <div class="text-center mb-3">
                    <div class="d-inline-flex p-3 bg-terracotta-light text-terracotta rounded-circle mb-2">
                        <i class="bi bi-award-fill fs-2"></i>
                    </div>
                    <h5 class="fw-bold mb-1">Donation Reward Rules</h5>
                    <p class="small text-muted mb-0">How our 50% reward coupons work</p>
                </div>
                <div class="p-3 rounded-3 bg-light mb-3">
                    <div class="fw-bold text-forest mb-1"><i class="bi bi-check2-all text-success me-1"></i>Rule 1: Approved Only</div>
                    <small class="text-muted d-block">Only books that are verified and approved count towards the 10-book reward milestone.</small>
                </div>
                <div class="p-3 rounded-3 bg-light mb-3">
                    <div class="fw-bold text-forest mb-1"><i class="bi bi-percent text-terracotta me-1"></i>Rule 2: 50% Purchase Discount</div>
                    <small class="text-muted d-block">The coupon code unlocks 50% off on all available lifetime book purchases.</small>
                </div>
                <div class="p-3 rounded-3 bg-light">
                    <div class="fw-bold text-forest mb-1"><i class="bi bi-shield-lock text-primary me-1"></i>Rule 3: Linked to Account</div>
                    <small class="text-muted d-block">Log in or register so your donations automatically accumulate in your personal reward wallet.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic Row Adder JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Pickup Option Toggle
    const optPickup = document.getElementById('optPickup');
    const optDropOff = document.getElementById('optDropOff');
    const pickupAddressGroup = document.getElementById('pickupAddressGroup');
    const pickupAddressInput = document.getElementById('pickupAddressInput');

    function togglePickup() {
        if (optPickup.checked) {
            pickupAddressGroup.style.display = 'block';
            pickupAddressInput.required = true;
        } else {
            pickupAddressGroup.style.display = 'none';
            pickupAddressInput.required = false;
        }
    }

    if (optPickup && optDropOff) {
        optPickup.addEventListener('change', togglePickup);
        optDropOff.addEventListener('change', togglePickup);
    }

    // Multi-Book Row Adder
    const booksContainer = document.getElementById('booksContainer');
    const btnAddBook = document.getElementById('btnAddBook');
    let bookCount = 1;

    if (btnAddBook && booksContainer) {
        btnAddBook.addEventListener('click', () => {
            bookCount++;
            const firstRow = booksContainer.querySelector('.book-row');
            const newRow = firstRow.cloneNode(true);

            newRow.querySelectorAll('input').forEach(inp => {
                if (inp.type === 'file') inp.value = '';
                else if (inp.type === 'number') inp.value = '1';
                else inp.value = '';
            });
            newRow.querySelectorAll('textarea').forEach(t => t.value = '');
            newRow.querySelector('.row-index-label').innerHTML = `<i class="bi bi-book me-1"></i>Book #${bookCount}`;
            
            const removeBtn = newRow.querySelector('.btn-remove-book');
            removeBtn.classList.remove('d-none');
            removeBtn.addEventListener('click', () => {
                newRow.remove();
                updateRowNumbers();
            });

            booksContainer.appendChild(newRow);
            updateRowNumbers();
        });
    }

    function updateRowNumbers() {
        const rows = booksContainer.querySelectorAll('.book-row');
        bookCount = rows.length;
        rows.forEach((r, idx) => {
            r.querySelector('.row-index-label').innerHTML = `<i class="bi bi-book me-1"></i>Book #${idx + 1}`;
            const btn = r.querySelector('.btn-remove-book');
            if (rows.length > 1) {
                btn.classList.remove('d-none');
            } else {
                btn.classList.add('d-none');
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
