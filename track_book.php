<?php
/**
 * track_book.php
 * Visual Book Tracking System & Timeline.
 * Accepts any Tracking ID (e.g. BOOK-TRK-2026-00452) or Order ID,
 * renders the 8-stage visual delivery stepper, loan countdown timers, and return reminders.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Track My Book';
$activePage = 'track';

$trackingIdInput = clean_input($_GET['tracking_id'] ?? ($_GET['track_id'] ?? ($_POST['tracking_id'] ?? '')));
$orderData = null;
$borrowData = null;
$trackingEvents = [];
$searchError = '';

if ($trackingIdInput !== '') {
    // Search orders table by tracking_id or order_code
    $stmt = $conn->prepare("SELECT o.*, b.title, b.author, b.image, b.book_condition, c.category_name,
                            u.full_name AS customer_name, u.phone AS customer_phone
                            FROM orders o
                            JOIN books b ON o.book_id = b.book_id
                            JOIN categories c ON b.category_id = c.category_id
                            LEFT JOIN users u ON o.user_id = u.user_id
                            WHERE o.tracking_id = ? OR o.order_code = ? LIMIT 1");
    $stmt->bind_param("ss", $trackingIdInput, $trackingIdInput);
    $stmt->execute();
    $orderData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // If order found, check if it has a linked borrowing
    if ($orderData) {
        $actualTrackingId = $orderData['tracking_id'];

        if ($orderData['borrowing_id']) {
            $bStmt = $conn->prepare("SELECT * FROM borrowings WHERE borrowing_id = ?");
            $bStmt->bind_param("i", $orderData['borrowing_id']);
            $bStmt->execute();
            $borrowData = $bStmt->get_result()->fetch_assoc();
            $bStmt->close();
        }

        // Fetch tracking milestones
        $tStmt = $conn->prepare("SELECT * FROM tracking WHERE tracking_id = ? ORDER BY tracking_entry_id ASC");
        $tStmt->bind_param("s", $actualTrackingId);
        $tStmt->execute();
        $trackingEvents = $tStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $tStmt->close();
    } else {
        // Search directly in borrowings table
        $bStmt = $conn->prepare("SELECT brw.*, b.title, b.author, b.image, b.book_condition, c.category_name,
                                 u.full_name AS customer_name, u.phone AS customer_phone
                                 FROM borrowings brw
                                 JOIN books b ON brw.book_id = b.book_id
                                 JOIN categories c ON b.category_id = c.category_id
                                 LEFT JOIN users u ON brw.user_id = u.user_id
                                 WHERE brw.tracking_id = ? OR brw.borrowing_code = ? LIMIT 1");
        $bStmt->bind_param("ss", $trackingIdInput, $trackingIdInput);
        $bStmt->execute();
        $borrowData = $bStmt->get_result()->fetch_assoc();
        $bStmt->close();

        if ($borrowData) {
            $actualTrackingId = $borrowData['tracking_id'];
            $tStmt = $conn->prepare("SELECT * FROM tracking WHERE tracking_id = ? ORDER BY tracking_entry_id ASC");
            $tStmt->bind_param("s", $actualTrackingId);
            $tStmt->execute();
            $trackingEvents = $tStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $tStmt->close();
        } else {
            $searchError = "No tracking record found for ID: '{$trackingIdInput}'. Please verify and try again.";
        }
    }
}

// 8 Timeline Steps defined in Requirement 6 & 14
$timelineStages = [
    'Order/Request Placed' => ['icon' => 'bi-journal-check', 'label' => 'Order/Request Placed'],
    'Book Verified'        => ['icon' => 'bi-patch-check',   'label' => 'Book Verified'],
    'Book Packed'          => ['icon' => 'bi-box-seam',      'label' => 'Book Packed'],
    'Shipped'              => ['icon' => 'bi-truck',         'label' => 'Shipped'],
    'In Transit'           => ['icon' => 'bi-geo-alt',       'label' => 'In Transit'],
    'Out for Delivery'     => ['icon' => 'bi-bicycle',       'label' => 'Out for Delivery'],
    'Delivered'            => ['icon' => 'bi-house-check',   'label' => 'Delivered'],
    'Currently With You'   => ['icon' => 'bi-book-half',     'label' => 'Currently With You']
];

// Determine highest reached stage
$latestStage = 'Order/Request Placed';
$latestLocation = 'Pune Logistics Hub';
$latestTimestamp = null;

if (!empty($trackingEvents)) {
    $lastEvent = end($trackingEvents);
    $latestStage = $lastEvent['stage'];
    $latestLocation = $lastEvent['location'];
    $latestTimestamp = $lastEvent['timestamp'];
}

// Calculate index in timeline
$stageKeys = array_keys($timelineStages);
$currentStageIndex = array_search($latestStage, $stageKeys);
if ($currentStageIndex === false) {
    $currentStageIndex = 0;
}

// Return reminder calculation if borrowed
$returnStatusInfo = null;
if ($borrowData) {
    $returnStatusInfo = get_borrow_status_info($borrowData);
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Tracking Hero Header -->
<div class="bg-forest text-white py-5 position-relative overflow-hidden">
    <div class="container position-relative z-2">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="badge bg-terracotta text-white rounded-pill px-3 py-2 mb-3 fw-bold">
                    <i class="bi bi-geo-alt-fill me-1"></i> Live Logistics Tracker
                </span>
                <h1 class="display-5 fw-bold mb-2 text-white">Track Your Book</h1>
                <p class="lead text-light mb-4" style="opacity: 0.95;">
                    Follow your book's real-time journey from verification, packing, and dispatch to your doorstep.
                </p>

                <!-- Search Input Bar -->
                <form method="GET" action="track_book.php" class="card border-0 shadow-lg p-2 rounded-pill bg-white">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 ps-3 text-muted">
                            <i class="bi bi-search fs-5"></i>
                        </span>
                        <input type="text" name="tracking_id" class="form-control border-0 font-monospace fs-6" 
                               required placeholder="Enter Tracking ID (e.g. BOOK-TRK-2026-00452) or Order ID" 
                               value="<?= h($trackingIdInput) ?>">
                        <button type="submit" class="btn btn-terracotta rounded-pill px-4 fw-bold shadow-sm">
                            Track Book
                        </button>
                    </div>
                </form>
            </div>
            <div class="col-lg-5 text-center mt-4 mt-lg-0">
                <div class="p-4 bg-white text-dark rounded-4 shadow-lg text-start">
                    <h6 class="fw-bold text-forest mb-2"><i class="bi bi-shield-check text-success me-2"></i>Every Movement Logged</h6>
                    <p class="small text-muted mb-3">Both borrowed and purchased books feature complete immutable dispatch history.</p>
                    <div class="d-flex align-items-center gap-3 p-2 bg-light rounded-3">
                        <i class="bi bi-clock-history fs-3 text-terracotta"></i>
                        <small class="text-muted">Includes automatic return date reminders (7 days, 3 days, due today, and overdue alerts).</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <?= render_flash() ?>

    <?php if ($searchError): ?>
        <div class="alert alert-warning rounded-4 shadow-sm p-4 text-center mb-5">
            <i class="bi bi-exclamation-triangle-fill display-5 text-warning d-block mb-2"></i>
            <h5 class="fw-bold text-dark"><?= h($searchError) ?></h5>
            <p class="text-muted mb-3">Make sure you copied the exact Tracking ID from your confirmation email or orders dashboard.</p>
            <a href="user_dashboard.php?tab=orders" class="btn btn-forest rounded-pill px-4">View My Orders</a>
        </div>
    <?php elseif ($orderData || $borrowData): ?>
        <?php 
        $activeItem = $orderData ?: $borrowData; 
        $trackingId = $orderData['tracking_id'] ?? $borrowData['tracking_id'];
        $isBorrow = ($borrowData !== null);
        ?>

        <!-- Return Reminder Banner (Requirement 7) -->
        <?php if ($isBorrow && $returnStatusInfo): ?>
            <div class="alert <?= $returnStatusInfo['alert_class'] ?> rounded-4 shadow-sm p-4 mb-4 border-0">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi <?= $returnStatusInfo['icon'] ?> display-5"></i>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h5 class="fw-bold mb-0">Return Status: <?= $returnStatusInfo['label'] ?></h5>
                            <span class="badge <?= $returnStatusInfo['badge_class'] ?> rounded-pill px-3 py-1">
                                <?= $returnStatusInfo['days_remaining'] !== null ? ($returnStatusInfo['days_remaining'] >= 0 ? $returnStatusInfo['days_remaining'] . ' Days Left' : abs($returnStatusInfo['days_remaining']) . ' Days Overdue') : '' ?>
                            </span>
                        </div>
                        <p class="mb-0 fs-6"><?= $returnStatusInfo['message'] ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Active Tracking Summary Box -->
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-5">
            <div class="row g-4 align-items-center">
                <div class="col-md-7 border-end-md">
                    <div class="d-flex gap-3 align-items-center">
                        <div class="rounded-3 bg-light p-2 text-center" style="width: 80px; height: 100px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                            <?php 
                            $bookImg = !empty($activeItem['image']) ? basename($activeItem['image']) : '';
                            if ($bookImg && file_exists(__DIR__ . '/uploads/book_images/' . $bookImg)): ?>
                                <img src="uploads/book_images/<?= h($bookImg) ?>" alt="<?= h($activeItem['title']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                            <?php else: ?>
                                <i class="bi bi-journal-bookmark fs-2 text-secondary"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="badge <?= $isBorrow ? 'badge-borrowed' : 'badge-lifetime' ?> rounded-pill px-3 py-1 mb-1">
                                <?= $isBorrow ? '🔵 Limited Period Borrow' : '🟣 Lifetime Owned' ?>
                            </span>
                            <h4 class="fw-bold text-dark mb-1"><?= h($activeItem['title']) ?></h4>
                            <p class="text-muted small mb-1">By <?= h($activeItem['author'] ?: 'Unknown') ?></p>
                            <span class="font-monospace fw-bold text-terracotta fs-6">ID: <?= h($trackingId) ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-md-5">
                    <?php if ($isBorrow): ?>
                        <div class="bg-light p-3 rounded-4">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Borrowed On:</span>
                                <strong class="text-dark"><?= date('d F Y', strtotime($borrowData['start_date'])) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Return Due Date:</span>
                                <strong class="text-terracotta"><?= date('d F Y', strtotime($borrowData['return_date'])) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Duration:</span>
                                <strong><?= (int)$borrowData['duration_days'] ?> Days</strong>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-light p-3 rounded-4">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Order Reference:</span>
                                <strong class="text-dark font-monospace"><?= h($orderData['order_code']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Ordered On:</span>
                                <strong class="text-dark"><?= date('d M Y, h:i A', strtotime($orderData['created_at'])) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Amount Paid:</span>
                                <strong class="text-success"><?= format_currency($orderData['final_price']) ?></strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Visual Tracking Stepper Timeline (Requirement 6 & 14) -->
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 p-md-5 mb-5">
            <h4 class="fw-bold text-dark mb-4 text-center">Visual Tracking Progress</h4>

            <div class="tracking-stepper">
                <ul class="tracking-timeline">
                    <?php foreach ($timelineStages as $stageKey => $meta): ?>
                        <?php 
                        $stepIdx = array_search($stageKey, $stageKeys);
                        $isCompleted = ($stepIdx <= $currentStageIndex);
                        $isActive = ($stepIdx === $currentStageIndex);
                        
                        // Find matching logged event if any
                        $matchedEvent = null;
                        foreach ($trackingEvents as $ev) {
                            if ($ev['stage'] === $stageKey) {
                                $matchedEvent = $ev;
                                break;
                            }
                        }
                        ?>
                        <li class="tracking-item <?= $isCompleted ? 'completed' : '' ?> <?= $isActive ? 'active' : '' ?>">
                            <div class="tracking-icon">
                                <i class="bi <?= $isCompleted ? 'bi-check-lg' : $meta['icon'] ?>"></i>
                            </div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center">
                                <div>
                                    <h6 class="fw-bold mb-0 <?= $isActive ? 'text-terracotta' : ($isCompleted ? 'text-dark' : 'text-muted') ?>">
                                        <?= $meta['label'] ?>
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-terracotta text-white ms-2 rounded-pill small">Current Stage</span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?= $matchedEvent ? h($matchedEvent['description']) : ($isCompleted ? 'Completed and verified' : 'Upcoming milestone') ?>
                                    </small>
                                </div>
                                <div class="text-md-end text-muted small mt-1 mt-md-0">
                                    <?php if ($matchedEvent && !empty($matchedEvent['timestamp'])): ?>
                                        <div><i class="bi bi-clock me-1"></i><?= date('d M Y, h:i A', strtotime($matchedEvent['timestamp'])) ?></div>
                                        <div class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= h($matchedEvent['location'] ?: 'Hub Logistics') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Detailed Tracking Logs Table -->
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-list-check text-forest me-2"></i>Activity History</h5>
            <?php if (!empty($trackingEvents)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-muted text-uppercase">
                                <th>Timestamp</th>
                                <th>Stage</th>
                                <th>Location</th>
                                <th>Remarks & Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trackingEvents as $tLog): ?>
                                <tr>
                                    <td class="text-nowrap small text-muted">
                                        <?= date('d M Y, h:i A', strtotime($tLog['timestamp'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-forest-light text-forest border">
                                            <?= h($tLog['stage']) ?>
                                        </span>
                                    </td>
                                    <td class="small">
                                        <i class="bi bi-geo-alt text-terracotta me-1"></i><?= h($tLog['location'] ?: 'Pune Hub') ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= h($tLog['description'] ?: 'Milestone recorded successfully.') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0 small">Initial dispatch record placed. Live logistics checkpoints will appear here as the package progresses.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Default State: How to Track Guide -->
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100 bg-white">
                    <div class="fs-1 text-terracotta mb-3"><i class="bi bi-upc-scan"></i></div>
                    <h5 class="fw-bold text-dark mb-2">1. Get Tracking ID</h5>
                    <p class="small text-muted mb-0">
                        Every borrowed or lifetime purchased book receives a unique tracking ID formatted as <code>BOOK-TRK-2026-XXXXX</code>.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100 bg-white">
                    <div class="fs-1 text-forest mb-3"><i class="bi bi-truck"></i></div>
                    <h5 class="fw-bold text-dark mb-2">2. Live Dispatch Updates</h5>
                    <p class="small text-muted mb-0">
                        Watch your package pass through verification, packaging, transit, and doorstep delivery with date stamps.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100 bg-white">
                    <div class="fs-1 text-primary mb-3"><i class="bi bi-calendar-event"></i></div>
                    <h5 class="fw-bold text-dark mb-2">3. Return Countdown</h5>
                    <p class="small text-muted mb-0">
                        For borrowed books, the tracker calculates your remaining days and alerts you when your return date approaches.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
