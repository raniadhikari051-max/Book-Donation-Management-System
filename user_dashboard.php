<?php
/**
 * user_dashboard.php
 * Comprehensive User Dashboard for All Registered Users.
 * Sections:
 *   1. My Books (Lifetime Books, Currently Borrowed, Returned Books, Overdue Books)
 *   2. My Donations (Total, Approved, Pending, Rejected, History)
 *   3. My Rewards (Donation Count, Eligibility, Available 50% Coupons, Used, Expired)
 *   4. My Orders (Order ID, Book, Type, Amount, Payment, Tracking ID)
 *   5. In-App Notifications
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

require_login();
$userId = $_SESSION['user_id'];
$activeTab = clean_input($_GET['tab'] ?? 'books');

// Fetch user info
$uStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$uStmt->bind_param("i", $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

// 1. Fetch My Books: Lifetime Books
$lifetimeBooks = [];
$ltSql = "SELECT lb.*, b.title, b.author, b.isbn, b.image, b.book_condition, c.category_name, o.order_code, o.tracking_id
          FROM lifetime_books lb
          JOIN books b ON lb.book_id = b.book_id
          JOIN categories c ON b.category_id = c.category_id
          JOIN orders o ON lb.order_id = o.order_id
          WHERE lb.user_id = ?
          ORDER BY lb.lifetime_id DESC";
$ltStmt = $conn->prepare($ltSql);
$ltStmt->bind_param("i", $userId);
$ltStmt->execute();
$lifetimeBooks = $ltStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ltStmt->close();

// 2. Fetch My Books: Borrowed Books (Active, Returned, Overdue)
$currentlyBorrowed = [];
$returnedBooks = [];
$overdueBooks = [];

$bwSql = "SELECT brw.*, b.title, b.author, b.isbn, b.image, b.book_condition, c.category_name
          FROM borrowings brw
          JOIN books b ON brw.book_id = b.book_id
          JOIN categories c ON b.category_id = c.category_id
          WHERE brw.user_id = ?
          ORDER BY brw.borrowing_id DESC";
$bwStmt = $conn->prepare($bwSql);
$bwStmt->bind_param("i", $userId);
$bwStmt->execute();
$allBorrowings = $bwStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$bwStmt->close();

foreach ($allBorrowings as $brw) {
    $info = get_borrow_status_info($brw);
    $brw['status_info'] = $info;

    if ($info['state'] === 'returned') {
        $returnedBooks[] = $brw;
    } elseif ($info['state'] === 'overdue') {
        $overdueBooks[] = $brw;
        $currentlyBorrowed[] = $brw; // Also part of active borrowings
    } else {
        $currentlyBorrowed[] = $brw;
    }
}

// 3. Fetch My Donations
$donorId = null;
$dCheck = $conn->prepare("SELECT donor_id FROM donors WHERE user_id = ?");
$dCheck->bind_param("i", $userId);
$dCheck->execute();
$dRow = $dCheck->get_result()->fetch_assoc();
$dCheck->close();
if ($dRow) {
    $donorId = (int)$dRow['donor_id'];
}

$donationStats = [
    'total_packages' => 0,
    'total_copies' => 0,
    'approved_copies' => 0,
    'pending_copies' => 0,
    'rejected_copies' => 0
];
$donationHistory = [];

if ($donorId) {
    // Packages
    $dHistSql = "SELECT d.*, 
                 (SELECT COUNT(*) FROM donation_books db WHERE db.donation_id = d.donation_id) AS total_titles,
                 (SELECT COALESCE(SUM(quantity), 0) FROM donation_books db WHERE db.donation_id = d.donation_id) AS total_quantity
                 FROM donations d 
                 WHERE d.donor_id = ? 
                 ORDER BY d.donation_id DESC";
    $dhStmt = $conn->prepare($dHistSql);
    $dhStmt->bind_param("i", $donorId);
    $dhStmt->execute();
    $donationHistory = $dhStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $dhStmt->close();

    $donationStats['total_packages'] = count($donationHistory);
    foreach ($donationHistory as $dh) {
        $copies = (int)$dh['total_quantity'];
        $donationStats['total_copies'] += $copies;
        $st = $dh['status'];
        if ($st === 'Approved' || $st === 'Received' || $st === 'Available' || $st === 'Completed') {
            $donationStats['approved_copies'] += $copies;
        } elseif ($st === 'Rejected') {
            $donationStats['rejected_copies'] += $copies;
        } else {
            $donationStats['pending_copies'] += $copies;
        }
    }
}

// 4. Rewards & Coupons (Requirement 2 & 13)
$rewardInfo = check_and_award_donor_rewards($conn, $userId);

$activeCoupons = [];
$usedCoupons = [];
$expiredCoupons = [];

foreach ($rewardInfo['coupons'] as $cp) {
    if ($cp['status'] === 'Active') {
        if ($cp['expiry_date'] < date('Y-m-d')) {
            $expiredCoupons[] = $cp;
        } else {
            $activeCoupons[] = $cp;
        }
    } elseif ($cp['status'] === 'Used') {
        $usedCoupons[] = $cp;
    } else {
        $expiredCoupons[] = $cp;
    }
}

// 5. Fetch My Orders (Requirement 8)
$ordersSql = "SELECT o.*, b.title, b.author, b.image, b.book_condition, c.category_name,
              brw.return_date, brw.duration_days
              FROM orders o
              JOIN books b ON o.book_id = b.book_id
              JOIN categories c ON b.category_id = c.category_id
              LEFT JOIN borrowings brw ON o.borrowing_id = brw.borrowing_id
              WHERE o.user_id = ?
              ORDER BY o.order_id DESC";
$ordStmt = $conn->prepare($ordersSql);
$ordStmt->bind_param("i", $userId);
$ordStmt->execute();
$myOrders = $ordStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ordStmt->close();

// 6. Notifications
$nSql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY notification_id DESC LIMIT 20";
$nStmt = $conn->prepare($nSql);
$nStmt->bind_param("i", $userId);
$nStmt->execute();
$notifications = $nStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$nStmt->close();

$pageTitle = 'My Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-forest text-white py-4 mb-4">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <span class="badge bg-terracotta text-white rounded-pill px-3 py-1 mb-2">Member Portal</span>
                <h2 class="fw-bold mb-1 text-white">Welcome, <?= h($user['full_name']) ?></h2>
                <p class="small text-light mb-0"><i class="bi bi-envelope me-1"></i><?= h($user['email']) ?> | Role: <strong class="text-uppercase"><?= h($user['role']) ?></strong></p>
            </div>
            <div class="d-flex gap-2">
                <a href="donate.php" class="btn btn-terracotta rounded-pill px-3 py-2 fw-bold shadow-sm">
                    <i class="bi bi-heart-fill me-1"></i>Donate Books
                </a>
                <a href="available_books.php" class="btn btn-outline-light rounded-pill px-3 py-2">
                    <i class="bi bi-book me-1"></i>Browse Books
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container py-2 mb-5">
    <?= render_flash() ?>

    <!-- Overdue Alert Banner if any overdue books -->
    <?php if (!empty($overdueBooks)): ?>
        <div class="alert alert-danger rounded-4 shadow-sm p-4 mb-4 border-0">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-exclamation-triangle-fill display-5 text-danger"></i>
                <div class="flex-grow-1">
                    <h5 class="fw-bold text-danger mb-1">🔴 Overdue Books Alert</h5>
                    <p class="mb-0 text-dark">
                        You have <strong><?= count($overdueBooks) ?> overdue book(s)</strong> that have passed their scheduled return date. 
                        Please return them to keep borrowing privileges active for all students.
                    </p>
                </div>
                <a href="#tab-books" onclick="switchTab('books')" class="btn btn-danger rounded-pill px-4 text-nowrap">View Books</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs (Requirement 8) -->
    <ul class="nav nav-pills custom-tabs mb-4 p-2 bg-white rounded-4 shadow-sm border" id="dashboardTabs" role="tablist">
        <li class="nav-item flex-fill text-center">
            <button class="nav-link w-100 rounded-pill py-2 <?= $activeTab === 'books' ? 'active' : '' ?>" 
                    id="books-tab" data-bs-toggle="pill" data-bs-target="#tab-books" type="button" role="tab">
                <i class="bi bi-journal-bookmark me-1"></i> My Books (<?= count($lifetimeBooks) + count($currentlyBorrowed) ?>)
            </button>
        </li>
        <li class="nav-item flex-fill text-center">
            <button class="nav-link w-100 rounded-pill py-2 <?= $activeTab === 'donations' ? 'active' : '' ?>" 
                    id="donations-tab" data-bs-toggle="pill" data-bs-target="#tab-donations" type="button" role="tab">
                <i class="bi bi-box2-heart me-1"></i> My Donations (<?= $donationStats['total_copies'] ?>)
            </button>
        </li>
        <li class="nav-item flex-fill text-center">
            <button class="nav-link w-100 rounded-pill py-2 <?= $activeTab === 'rewards' ? 'active' : '' ?>" 
                    id="rewards-tab" data-bs-toggle="pill" data-bs-target="#tab-rewards" type="button" role="tab">
                <i class="bi bi-gift-fill me-1"></i> My Rewards (<?= count($activeCoupons) ?>)
            </button>
        </li>
        <li class="nav-item flex-fill text-center">
            <button class="nav-link w-100 rounded-pill py-2 <?= $activeTab === 'orders' ? 'active' : '' ?>" 
                    id="orders-tab" data-bs-toggle="pill" data-bs-target="#tab-orders" type="button" role="tab">
                <i class="bi bi-bag-check me-1"></i> My Orders (<?= count($myOrders) ?>)
            </button>
        </li>
        <li class="nav-item flex-fill text-center">
            <button class="nav-link w-100 rounded-pill py-2 <?= $activeTab === 'notifications' ? 'active' : '' ?>" 
                    id="notifs-tab" data-bs-toggle="pill" data-bs-target="#tab-notifs" type="button" role="tab">
                <i class="bi bi-bell me-1"></i> Notifications (<?= count($notifications) ?>)
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="dashboardTabsContent">
        
        <!-- ========================================== -->
        <!-- TAB 1: MY BOOKS (Requirement 8)             -->
        <!-- ========================================== -->
        <div class="tab-pane fade <?= $activeTab === 'books' ? 'show active' : '' ?>" id="tab-books" role="tabpanel">
            
            <!-- Currently Borrowed Books Section -->
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-arrow-repeat text-forest me-2"></i>Currently Borrowed (<?= count($currentlyBorrowed) ?>)
                    </h5>
                    <span class="badge bg-forest-light text-forest rounded-pill px-3 py-1">Limited Period</span>
                </div>

                <?php if (!empty($currentlyBorrowed)): ?>
                    <div class="row g-3">
                        <?php foreach ($currentlyBorrowed as $bItem): ?>
                            <?php $info = $bItem['status_info']; ?>
                            <div class="col-md-6">
                                <div class="border rounded-4 p-3 h-100 bg-light position-relative">
                                    <div class="d-flex gap-3">
                                        <div class="rounded-3 bg-white p-2 text-center" style="width: 75px; height: 95px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                            <?php 
                                            $bookImg = !empty($bItem['image']) ? basename($bItem['image']) : '';
                                            if ($bookImg && file_exists(__DIR__ . '/uploads/book_images/' . $bookImg)): ?>
                                                <img src="uploads/book_images/<?= h($bookImg) ?>" alt="<?= h($bItem['title']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                            <?php else: ?>
                                                <i class="bi bi-journal-bookmark fs-2 text-secondary"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="badge bg-white text-forest border mb-1"><?= h($bItem['category_name']) ?></span>
                                            <h6 class="fw-bold text-dark mb-1 text-truncate" style="max-width: 250px;"><?= h($bItem['title']) ?></h6>
                                            <small class="text-muted d-block">By <?= h($bItem['author'] ?: 'Unknown') ?></small>
                                            
                                            <!-- Return Reminder Countdown (Requirement 7) -->
                                            <div class="mt-2">
                                                <span class="badge <?= $info['badge_class'] ?> rounded-pill px-3 py-1">
                                                    <?= h($info['label']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center small text-muted">
                                        <span>Due: <strong class="text-dark"><?= date('d M Y', strtotime($bItem['return_date'])) ?></strong></span>
                                        <?php if ($bItem['tracking_id']): ?>
                                            <a href="track_book.php?tracking_id=<?= urlencode($bItem['tracking_id']) ?>" class="btn btn-sm btn-outline-forest rounded-pill px-3">
                                                <i class="bi bi-geo-alt me-1"></i>Track Book
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0 small">You do not have any borrowed books currently. Browse available titles to borrow for free!</p>
                <?php endif; ?>
            </div>

            <!-- Lifetime Owned Books Section (Requirement 3 & 8) -->
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-award-fill text-terracotta me-2"></i>Lifetime Books (<?= count($lifetimeBooks) ?>)
                    </h5>
                    <span class="badge badge-lifetime rounded-pill px-3 py-1">🟣 Permanently Owned</span>
                </div>

                <?php if (!empty($lifetimeBooks)): ?>
                    <div class="row g-3">
                        <?php foreach ($lifetimeBooks as $lItem): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="border rounded-4 p-3 h-100 bg-light d-flex flex-column justify-content-between">
                                    <div class="d-flex gap-3 mb-2">
                                        <div class="rounded-3 bg-white p-2 text-center" style="width: 70px; height: 90px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                            <?php 
                                            $bookImg = !empty($lItem['image']) ? basename($lItem['image']) : '';
                                            if ($bookImg && file_exists(__DIR__ . '/uploads/book_images/' . $bookImg)): ?>
                                                <img src="uploads/book_images/<?= h($bookImg) ?>" alt="<?= h($lItem['title']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                            <?php else: ?>
                                                <i class="bi bi-journal-bookmark fs-2 text-secondary"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="badge bg-white text-forest border mb-1"><?= h($lItem['category_name']) ?></span>
                                            <h6 class="fw-bold text-dark mb-1 text-truncate" style="max-width: 180px;"><?= h($lItem['title']) ?></h6>
                                            <small class="text-muted d-block">By <?= h($lItem['author'] ?: 'Unknown') ?></small>
                                            <small class="text-success fw-bold">Paid: <?= format_currency($lItem['purchase_price']) ?></small>
                                        </div>
                                    </div>
                                    <div class="pt-2 border-top d-flex justify-content-between align-items-center small text-muted">
                                        <span>Bought: <?= date('d M Y', strtotime($lItem['purchased_at'])) ?></span>
                                        <a href="track_book.php?tracking_id=<?= urlencode($lItem['tracking_id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2">
                                            Track
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0 small">No lifetime purchased books yet. Choose "Buy for Lifetime" on any available book to get 50% discount and permanent ownership!</p>
                <?php endif; ?>
            </div>

            <!-- Returned Books History -->
            <?php if (!empty($returnedBooks)): ?>
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Returned Books History</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted text-uppercase">
                                <tr>
                                    <th>Book Title</th>
                                    <th>Borrowed Period</th>
                                    <th>Returned Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($returnedBooks as $rb): ?>
                                    <tr>
                                        <td><strong><?= h($rb['title']) ?></strong> (<?= h($rb['author']) ?>)</td>
                                        <td class="small"><?= date('d M Y', strtotime($rb['start_date'])) ?> to <?= date('d M Y', strtotime($rb['return_date'])) ?></td>
                                        <td class="small"><?= $rb['returned_date'] ? date('d M Y', strtotime($rb['returned_date'])) : 'Completed' ?></td>
                                        <td><span class="badge bg-success">Returned</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: MY DONATIONS (Requirement 1 & 8)     -->
        <!-- ========================================== -->
        <div class="tab-pane fade <?= $activeTab === 'donations' ? 'show active' : '' ?>" id="tab-donations" role="tabpanel">
            <!-- Donation Counters Grid -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                        <span class="text-muted small text-uppercase fw-bold">Total Books Donated</span>
                        <h3 class="fw-bold text-forest mb-0 mt-1"><?= $donationStats['total_copies'] ?></h3>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                        <span class="text-muted small text-uppercase fw-bold">Approved Books</span>
                        <h3 class="fw-bold text-success mb-0 mt-1"><?= $donationStats['approved_copies'] ?></h3>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                        <span class="text-muted small text-uppercase fw-bold">Pending Verification</span>
                        <h3 class="fw-bold text-warning mb-0 mt-1"><?= $donationStats['pending_copies'] ?></h3>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
                        <span class="text-muted small text-uppercase fw-bold">Rejected Books</span>
                        <h3 class="fw-bold text-danger mb-0 mt-1"><?= $donationStats['rejected_copies'] ?></h3>
                    </div>
                </div>
            </div>

            <!-- Donation Packages History -->
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0">Donation History</h5>
                    <a href="donate.php" class="btn btn-sm btn-terracotta rounded-pill px-3 fw-bold">
                        <i class="bi bi-plus-lg me-1"></i>Donate More Books
                    </a>
                </div>

                <?php if (!empty($donationHistory)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted text-uppercase">
                                <tr>
                                    <th>Donation ID</th>
                                    <th>Date</th>
                                    <th>Copies</th>
                                    <th>Handover</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donationHistory as $dh): ?>
                                    <?php
                                    $st = $dh['status'];
                                    $dCode = $dh['donation_code'] ?: ($dh['qr_code'] ?: 'DON-2026-' . str_pad($dh['donation_id'], 5, '0', STR_PAD_LEFT));
                                    $badge = match($st) {
                                        'Submitted' => 'badge-pending',
                                        'Under Verification', 'Pending' => 'badge-verification',
                                        'Approved' => 'badge-available',
                                        'Pickup Scheduled' => 'badge-borrowed',
                                        'Received', 'Available', 'Completed' => 'bg-success text-white',
                                        'Rejected' => 'badge-overdue',
                                        default => 'bg-secondary text-white'
                                    };
                                    ?>
                                    <tr>
                                        <td><span class="font-monospace fw-bold text-terracotta"><?= h($dCode) ?></span></td>
                                        <td class="small text-muted"><?= date('d M Y', strtotime($dh['created_at'])) ?></td>
                                        <td><strong class="text-forest"><?= (int)$dh['total_quantity'] ?></strong></td>
                                        <td class="small"><?= h($dh['pickup_option']) ?></td>
                                        <td><span class="badge <?= $badge ?> rounded-pill px-3 py-1"><?= h($st) ?></span></td>
                                        <td>
                                            <a href="donate.php?lookup_code=<?= urlencode($dCode) ?>#statusLookupSection" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                Track Status
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <p>No donation packages registered under your account yet.</p>
                        <a href="donate.php" class="btn btn-terracotta rounded-pill px-4">Donate Books Now</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3: MY REWARDS & COUPONS (Req 2 & 8)    -->
        <!-- ========================================== -->
        <div class="tab-pane fade <?= $activeTab === 'rewards' ? 'show active' : '' ?>" id="tab-rewards" role="tabpanel">
            <!-- Reward Eligibility Banner (Requirement 2 & 13) -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white border-start border-4 border-warning">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <span class="badge bg-warning text-dark px-3 py-1 rounded-pill mb-2 fw-bold">
                            <i class="bi bi-gift-fill me-1"></i>10+ Books Reward Milestone
                        </span>
                        <h4 class="fw-bold mb-1">
                            Books Donated: <span class="text-forest"><?= $rewardInfo['approved_count'] ?></span>
                        </h4>
                        <p class="mb-0 text-muted">
                            Reward Status: 
                            <?php if ($rewardInfo['is_eligible']): ?>
                                <strong class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Eligible (Earned 50% Off Coupon!)</strong>
                            <?php else: ?>
                                <strong class="text-terracotta"><?= ($rewardInfo['threshold'] - $rewardInfo['approved_count']) ?> more approved books needed</strong> to unlock your next 50% discount voucher.
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if (!empty($activeCoupons)): ?>
                        <div class="coupon-card text-center p-3">
                            <div class="small text-muted fw-bold text-uppercase mb-1">Active Coupon Code</div>
                            <div class="coupon-code-badge mb-2" id="couponCodeBadge"><?= h($activeCoupons[0]['coupon_code']) ?></div>
                            <button type="button" class="btn btn-sm btn-outline-forest rounded-pill px-3" onclick="copyCouponCode('<?= h($activeCoupons[0]['coupon_code']) ?>')">
                                <i class="bi bi-clipboard me-1"></i>Copy Code
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Available Coupons -->
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-ticket-perforated text-terracotta me-2"></i>Available Coupons (<?= count($activeCoupons) ?>)</h5>
                <?php if (!empty($activeCoupons)): ?>
                    <div class="row g-3">
                        <?php foreach ($activeCoupons as $acp): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="coupon-card h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-success text-white rounded-pill px-2 py-1 small">ACTIVE</span>
                                            <strong class="text-terracotta fs-5"><?= (float)$acp['discount_percentage'] ?>% OFF</strong>
                                        </div>
                                        <div class="coupon-code-badge my-2 w-100 text-center"><?= h($acp['coupon_code']) ?></div>
                                        <small class="text-muted d-block mb-1">Issued: <?= date('d M Y', strtotime($acp['issued_date'])) ?></small>
                                        <small class="text-muted d-block mb-2">Expires: <strong><?= date('d M Y', strtotime($acp['expiry_date'])) ?></strong></small>
                                        <small class="text-secondary d-block"><?= h($acp['remarks'] ?: 'Reward for book donations') ?></small>
                                    </div>
                                    <div class="mt-3 pt-2 border-top d-flex gap-2">
                                        <button class="btn btn-sm btn-forest rounded-pill flex-fill" onclick="copyCouponCode('<?= h($acp['coupon_code']) ?>')">
                                            <i class="bi bi-copy me-1"></i>Copy
                                        </button>
                                        <a href="available_books.php" class="btn btn-sm btn-terracotta rounded-pill flex-fill">
                                            Use Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No active coupons currently. Donate books to achieve the milestone and earn 50% discount vouchers!</p>
                <?php endif; ?>
            </div>

            <!-- Used & Expired Coupons -->
            <?php if (!empty($usedCoupons) || !empty($expiredCoupons)): ?>
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                    <h5 class="fw-bold text-dark mb-3">Past Coupons History</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light small text-muted text-uppercase">
                                <tr>
                                    <th>Coupon Code</th>
                                    <th>Discount</th>
                                    <th>Expiry Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usedCoupons as $uc): ?>
                                    <tr>
                                        <td><span class="font-monospace fw-bold text-muted"><?= h($uc['coupon_code']) ?></span></td>
                                        <td><?= (float)$uc['discount_percentage'] ?>%</td>
                                        <td class="small"><?= date('d M Y', strtotime($uc['expiry_date'])) ?></td>
                                        <td><span class="badge bg-secondary">Used</span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php foreach ($expiredCoupons as $ec): ?>
                                    <tr>
                                        <td><span class="font-monospace text-muted"><?= h($ec['coupon_code']) ?></span></td>
                                        <td><?= (float)$ec['discount_percentage'] ?>%</td>
                                        <td class="small"><?= date('d M Y', strtotime($ec['expiry_date'])) ?></td>
                                        <td><span class="badge bg-danger">Expired</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ========================================== -->
        <!-- TAB 4: MY ORDERS (Requirement 8)           -->
        <!-- ========================================== -->
        <div class="tab-pane fade <?= $activeTab === 'orders' ? 'show active' : '' ?>" id="tab-orders" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-bag-check text-forest me-2"></i>My Orders & Requests</h5>

                <?php if (!empty($myOrders)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted text-uppercase">
                                <tr>
                                    <th>Order ID</th>
                                    <th>Book</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Order Status</th>
                                    <th>Tracking ID</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myOrders as $ord): ?>
                                    <tr>
                                        <td><span class="font-monospace fw-bold text-dark"><?= h($ord['order_code']) ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-2 bg-light p-1" style="width: 40px; height: 50px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                                    <?php 
                                                    $oImg = !empty($ord['image']) ? basename($ord['image']) : '';
                                                    if ($oImg && file_exists(__DIR__ . '/uploads/book_images/' . $oImg)): ?>
                                                        <img src="uploads/book_images/<?= h($oImg) ?>" alt="<?= h($ord['title']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                                    <?php else: ?>
                                                        <i class="bi bi-journal-bookmark fs-5 text-secondary"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <strong class="d-block text-truncate" style="max-width: 180px;"><?= h($ord['title']) ?></strong>
                                                    <small class="text-muted"><?= h($ord['author']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $ord['purchase_type'] === 'lifetime' ? 'badge-lifetime' : 'badge-borrowed' ?>">
                                                <?= $ord['purchase_type'] === 'lifetime' ? '🟣 Lifetime' : '🔵 Borrow' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($ord['purchase_type'] === 'lifetime'): ?>
                                                <strong class="text-forest"><?= format_currency($ord['final_price']) ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted small">Free (Borrow)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= h($ord['order_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="track_book.php?tracking_id=<?= urlencode($ord['tracking_id']) ?>" class="font-monospace text-terracotta text-decoration-none fw-bold small">
                                                <?= h($ord['tracking_id']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="track_book.php?tracking_id=<?= urlencode($ord['tracking_id']) ?>" class="btn btn-sm btn-forest rounded-pill px-3">
                                                <i class="bi bi-geo-alt me-1"></i>Track
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">You have not placed any orders yet. Visit our available catalog to borrow or purchase books.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 5: IN-APP NOTIFICATIONS (Req 15)       -->
        <!-- ========================================== -->
        <div class="tab-pane fade <?= $activeTab === 'notifications' ? 'show active' : '' ?>" id="tab-notifs" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-bell text-warning me-2"></i>Notifications</h5>
                <?php if (!empty($notifications)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $n): ?>
                            <div class="list-group-item px-0 py-3 d-flex align-items-start gap-3">
                                <div class="rounded-circle p-2 bg-light text-forest fs-5">
                                    <i class="bi bi-bell-fill"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="mb-1 text-dark"><?= h($n['message']) ?></p>
                                    <small class="text-muted"><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No notifications at this time.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
function copyCouponCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        alert("Coupon code " + code + " copied to clipboard!");
    });
}

function switchTab(tabId) {
    const tabTrigger = new bootstrap.Tab(document.querySelector(`#${tabId}-tab`));
    tabTrigger.show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
