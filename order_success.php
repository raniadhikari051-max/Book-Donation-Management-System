<?php
/**
 * order_success.php
 * Order Confirmation & Success Screen.
 * Provides the user with their unique Order ID, Tracking ID, receipt summary,
 * and instant links to visual tracking and their dashboard.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

require_login();
$userId = $_SESSION['user_id'];

$orderCode = clean_input($_GET['order_code'] ?? '');
if (empty($orderCode)) {
    redirect('user_dashboard.php');
}

// Fetch order details ensuring user ownership (Requirement 19: Security)
$stmt = $conn->prepare("SELECT o.*, b.title, b.author, b.image, b.book_condition, c.category_name,
                        brw.start_date, brw.return_date, brw.duration_days, brw.borrowing_code
                        FROM orders o
                        JOIN books b ON o.book_id = b.book_id
                        JOIN categories c ON b.category_id = c.category_id
                        LEFT JOIN borrowings brw ON o.borrowing_id = brw.borrowing_id
                        WHERE o.order_code = ? AND o.user_id = ?");
$stmt->bind_param("si", $orderCode, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    set_flash('danger', 'Order not found or unauthorized.');
    redirect('user_dashboard.php');
}

$pageTitle = 'Order Confirmed — ' . $order['order_code'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white text-center p-4 p-md-5">
                <div class="mb-3">
                    <div class="d-inline-flex p-3 bg-success-subtle text-success rounded-circle">
                        <i class="bi bi-check-circle-fill display-4"></i>
                    </div>
                </div>

                <h2 class="fw-bold text-dark mb-1">
                    <?= $order['purchase_type'] === 'lifetime' ? 'Lifetime Purchase Confirmed!' : 'Borrow Request Placed Successfully!' ?>
                </h2>
                <p class="text-muted mb-4">
                    Thank you! Your request has been confirmed and our fulfillment team is preparing your book.
                </p>

                <!-- Order & Tracking Badges Card -->
                <div class="p-4 rounded-4 bg-light mb-4 text-start">
                    <div class="row g-3 align-items-center">
                        <div class="col-sm-6 border-end-sm">
                            <span class="small text-muted text-uppercase fw-bold">Order ID</span>
                            <h4 class="fw-bold text-dark font-monospace mb-0"><?= h($order['order_code']) ?></h4>
                        </div>
                        <div class="col-sm-6">
                            <span class="small text-muted text-uppercase fw-bold">Tracking ID</span>
                            <h4 class="fw-bold text-terracotta font-monospace mb-0"><?= h($order['tracking_id']) ?></h4>
                        </div>
                    </div>
                </div>

                <!-- Book & Order Summary -->
                <div class="border rounded-4 p-4 text-start mb-4">
                    <h6 class="fw-bold text-dark mb-3">Item Details</h6>
                    <div class="d-flex gap-3 align-items-center mb-3 pb-3 border-bottom">
                        <div class="rounded-3 bg-light p-2 text-center" style="width: 70px; height: 90px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                            <?php 
                            $bookImg = !empty($order['image']) ? basename($order['image']) : '';
                            if ($bookImg && file_exists(__DIR__ . '/uploads/book_images/' . $bookImg)): ?>
                                <img src="uploads/book_images/<?= h($bookImg) ?>" alt="<?= h($order['title']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                            <?php else: ?>
                                <i class="bi bi-journal-bookmark fs-2 text-secondary"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <span class="badge bg-light text-forest border mb-1"><?= h($order['category_name']) ?></span>
                            <h5 class="fw-bold text-dark mb-1"><?= h($order['title']) ?></h5>
                            <small class="text-muted">By <?= h($order['author'] ?: 'Unknown') ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge <?= $order['purchase_type'] === 'lifetime' ? 'badge-lifetime' : 'badge-borrowed' ?> px-3 py-2 rounded-pill">
                                <?= $order['purchase_type'] === 'lifetime' ? '🟣 Lifetime Owned' : '🔵 Borrowed' ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($order['purchase_type'] === 'borrow'): ?>
                        <div class="p-3 bg-forest-light text-forest rounded-3 mb-3">
                            <div class="row text-center g-2">
                                <div class="col-4">
                                    <small class="d-block text-muted">Borrow Period</small>
                                    <strong><?= (int)$order['duration_days'] ?> Days</strong>
                                </div>
                                <div class="col-4">
                                    <small class="d-block text-muted">Start Date</small>
                                    <strong><?= date('d M Y', strtotime($order['start_date'])) ?></strong>
                                </div>
                                <div class="col-4">
                                    <small class="d-block text-muted">Return Due Date</small>
                                    <strong class="text-terracotta"><?= date('d M Y', strtotime($order['return_date'])) ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row small mb-2 text-muted">
                            <div class="col-6">Payment Method: <strong><?= h($order['payment_method']) ?></strong></div>
                            <div class="col-6 text-end">Amount Paid: <strong class="text-dark fs-6"><?= format_currency($order['final_price']) ?></strong></div>
                        </div>
                    <?php endif; ?>

                    <div class="small text-muted">
                        <i class="bi bi-geo-alt me-1"></i><strong>Delivery Address:</strong> <?= h($order['shipping_address']) ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="track_book.php?tracking_id=<?= urlencode($order['tracking_id']) ?>" 
                       class="btn btn-terracotta rounded-pill px-4 py-2 fw-bold shadow-sm">
                        <i class="bi bi-geo-alt-fill me-2"></i>Track My Book Live
                    </a>
                    <a href="user_dashboard.php?tab=books" 
                       class="btn btn-forest rounded-pill px-4 py-2 fw-bold shadow-sm">
                        <i class="bi bi-journal-bookmark me-2"></i>View in My Books
                    </a>
                    <a href="available_books.php" 
                       class="btn btn-outline-secondary rounded-pill px-4 py-2">
                        Browse More Books
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
