<?php
/**
 * checkout.php
 * Unified Checkout for Lifetime Purchase & Borrowing.
 * Includes coupon code validation (50% reward coupons), price calculation,
 * real-time inventory locking, order creation, tracking ID generation, and notification.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

// User must be logged in to checkout/borrow/buy
if (!is_logged_in()) {
    $redirectUrl = 'checkout.php?' . http_build_query($_GET);
    set_flash('info', 'Please log in or register to complete your book request.');
    redirect('login.php?redirect=' . urlencode($redirectUrl));
}

$userId = $_SESSION['user_id'];

// Get user profile for prefilling
$uStmt = $conn->prepare("SELECT full_name, email, phone, address FROM users WHERE user_id = ?");
$uStmt->bind_param("i", $userId);
$uStmt->execute();
$userProfile = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$bookId = (int)($_GET['book_id'] ?? ($_POST['book_id'] ?? 0));
$purchaseType = clean_input($_GET['type'] ?? ($_POST['purchase_type'] ?? 'lifetime'));
if (!in_array($purchaseType, ['lifetime', 'borrow'])) {
    $purchaseType = 'lifetime';
}

if ($bookId <= 0) {
    redirect('available_books.php');
}

// Fetch book details
$bStmt = $conn->prepare("SELECT b.*, c.category_name FROM books b JOIN categories c ON b.category_id = c.category_id WHERE b.book_id = ?");
$bStmt->bind_param("i", $bookId);
$bStmt->execute();
$book = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

if (!$book) {
    set_flash('danger', 'Book not found.');
    redirect('available_books.php');
}

// Check availability
if ($book['available_quantity'] <= 0 || $book['status'] !== 'Available') {
    set_flash('danger', 'Sorry, this physical copy has already been reserved or borrowed by another user.');
    redirect("book_details.php?id={$bookId}");
}

// Pricing calculation for Lifetime
$origPrice = (float)($book['original_price'] > 0 ? $book['original_price'] : 500.00);
$lifetimeBasePrice = (float)($book['lifetime_price'] > 0 ? $book['lifetime_price'] : round($origPrice * 0.5, 2));

// Borrow duration calculation
$presetDuration = (int)($_GET['preset_duration'] ?? ($_POST['duration_days'] ?? 15));
$startDate = clean_input($_GET['start_date'] ?? ($_POST['start_date'] ?? date('Y-m-d')));
$returnDate = clean_input($_GET['return_date'] ?? ($_POST['return_date'] ?? ''));

if (empty($returnDate)) {
    $returnDate = date('Y-m-d', strtotime("+{$presetDuration} days", strtotime($startDate)));
}
$diffTime = strtotime($returnDate) - strtotime($startDate);
$durationDays = max(1, (int)round($diffTime / 86400));

// Coupon handling
$couponCode = strtoupper(trim($_GET['coupon_code'] ?? ($_POST['coupon_code'] ?? '')));
$appliedCoupon = null;
$couponError = null;
$discountAmount = 0.00;
$finalPrice = ($purchaseType === 'lifetime') ? $lifetimeBasePrice : 0.00;

if ($couponCode !== '') {
    $valResult = validate_coupon($conn, $couponCode, $userId);
    if ($valResult['valid']) {
        $appliedCoupon = $valResult['coupon'];
        $discountPct = (float)$valResult['discount_percentage'];
        // Apply discount on lifetime price
        if ($purchaseType === 'lifetime') {
            $discountAmount = round(($lifetimeBasePrice * ($discountPct / 100)), 2);
            $finalPrice = max(0.00, $lifetimeBasePrice - $discountAmount);
        }
    } else {
        $couponError = $valResult['error'];
    }
}

$errors = [];

// Handle Order Placement (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token mismatch. Please resubmit the form.';
    } else {
        $recipientName = clean_input($_POST['recipient_name'] ?? '');
        $recipientPhone = clean_input($_POST['recipient_phone'] ?? '');
        $shippingAddress = clean_input($_POST['shipping_address'] ?? '');
        $paymentMethod = clean_input($_POST['payment_method'] ?? 'UPI / Instant Online');
        $couponCodeInput = strtoupper(trim($_POST['coupon_code'] ?? ''));

        if (empty($recipientName)) {
            $errors[] = 'Recipient name is required.';
        }
        if (empty($recipientPhone)) {
            $errors[] = 'Contact phone number is required.';
        }
        if (empty($shippingAddress)) {
            $errors[] = 'Delivery/shipping address is required.';
        }

        // Re-validate inventory inside lock check
        $conn->begin_transaction();
        try {
            $checkLock = $conn->prepare("SELECT available_quantity, status FROM books WHERE book_id = ? FOR UPDATE");
            $checkLock->bind_param("i", $bookId);
            $checkLock->execute();
            $currBook = $checkLock->get_result()->fetch_assoc();
            $checkLock->close();

            if (!$currBook || $currBook['available_quantity'] <= 0 || $currBook['status'] !== 'Available') {
                throw new Exception("Sorry, another user has just secured this physical copy. Inventory is currently unavailable.");
            }

            // Coupon server-side validation if entered
            $couponIdToRecord = null;
            $finalDiscount = 0.00;
            $calculatedFinal = ($purchaseType === 'lifetime') ? $lifetimeBasePrice : 0.00;

            if ($couponCodeInput !== '') {
                $cVal = validate_coupon($conn, $couponCodeInput, $userId);
                if (!$cVal['valid']) {
                    throw new Exception("Coupon error: " . $cVal['error']);
                }
                $couponIdToRecord = $cVal['coupon_id'];
                $discountPct = (float)$cVal['discount_percentage'];
                if ($purchaseType === 'lifetime') {
                    $finalDiscount = round(($lifetimeBasePrice * ($discountPct / 100)), 2);
                    $calculatedFinal = max(0.00, $lifetimeBasePrice - $finalDiscount);
                }
            }

            $orderCode = generate_order_code();
            $trackingId = generate_tracking_id();
            $orderStatus = 'Placed';
            $paymentStatus = 'Paid';

            // 1. If borrowing, insert into borrowings table
            $borrowingId = null;
            if ($purchaseType === 'borrow') {
                $borrowCode = generate_borrowing_code();
                $bStatus = 'Requested';
                $insBrw = $conn->prepare("INSERT INTO borrowings (borrowing_code, user_id, book_id, start_date, return_date, duration_days, status, tracking_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insBrw->bind_param('siississ', $borrowCode, $userId, $bookId, $startDate, $returnDate, $durationDays, $bStatus, $trackingId);
                $insBrw->execute();
                $borrowingId = $insBrw->insert_id;
                $insBrw->close();
            }

            // 2. Insert into orders table
            $insOrd = $conn->prepare("INSERT INTO orders (order_code, user_id, book_id, borrowing_id, purchase_type, original_price, discount, final_price, coupon_code, payment_method, payment_status, order_status, tracking_id, shipping_address, recipient_name, recipient_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insOrd->bind_param('siiisdddssssssss', $orderCode, $userId, $bookId, $borrowingId, $purchaseType, $origPrice, $finalDiscount, $calculatedFinal, $couponCodeInput, $paymentMethod, $paymentStatus, $orderStatus, $trackingId, $shippingAddress, $recipientName, $recipientPhone);
            $insOrd->execute();
            $orderId = $insOrd->insert_id;
            $insOrd->close();

            // 3. If Lifetime purchase, link to lifetime_books
            if ($purchaseType === 'lifetime') {
                $insLife = $conn->prepare("INSERT INTO lifetime_books (user_id, book_id, order_id, purchase_price) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE order_id = VALUES(order_id)");
                $insLife->bind_param('iiid', $userId, $bookId, $orderId, $calculatedFinal);
                $insLife->execute();
                $insLife->close();
            }

            // 4. Update coupon usage if coupon used
            if ($couponIdToRecord) {
                $insUsage = $conn->prepare("INSERT INTO coupon_usage (coupon_id, user_id, order_id, discount_amount) VALUES (?, ?, ?, ?)");
                $insUsage->bind_param('iiid', $couponIdToRecord, $userId, $orderId, $finalDiscount);
                $insUsage->execute();
                $insUsage->close();

                // Increment coupon used_count and update status
                $conn->query("UPDATE coupons SET used_count = used_count + 1, status = IF(used_count >= usage_limit, 'Used', 'Active') WHERE coupon_id = $couponIdToRecord");
            }

            // 5. Inventory Management: Decrement available_quantity
            $newAvail = max(0, $currBook['available_quantity'] - 1);
            $newStatus = ($newAvail === 0) ? (($purchaseType === 'lifetime') ? 'Sold' : 'Borrowed') : 'Available';
            $updStock = $conn->prepare("UPDATE books SET available_quantity = ?, status = ? WHERE book_id = ?");
            $updStock->bind_param('isi', $newAvail, $newStatus, $bookId);
            $updStock->execute();
            $updStock->close();

            // 6. Record Initial Tracking Milestones (Requirement 6 & 14)
            add_tracking_milestone($conn, $trackingId, 'Order/Request Placed', 'Placed', 'Pune Central Distribution Hub', "Request confirmed for '{$book['title']}'. Order ID: {$orderCode}", $orderId, $borrowingId);
            add_tracking_milestone($conn, $trackingId, 'Book Verified', 'Verified', 'Verification Section, Hub Pune', 'Physical condition verified against catalog standards.', $orderId, $borrowingId);

            // 7. Notification & Audit Log
            $notifMsg = ($purchaseType === 'lifetime')
                ? "🎉 Lifetime Ownership Purchase Confirmed! Book '{$book['title']}' is now permanently yours. Tracking ID: {$trackingId}"
                : "📚 Book Borrow Request Confirmed for '{$book['title']}'. Return Due Date: " . date('d M Y', strtotime($returnDate)) . ". Tracking ID: {$trackingId}";
            add_notification($conn, $userId, $notifMsg);
            log_activity($conn, 'ORDER_CREATE', "Order {$orderCode} placed for book #{$bookId} ({$purchaseType}). Tracking: {$trackingId}", $userId);

            $conn->commit();

            // Redirect to success page
            redirect("order_success.php?order_code=" . urlencode($orderCode));
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = 'Checkout — ' . ($purchaseType === 'lifetime' ? 'Lifetime Purchase' : 'Borrow Book');
$activePage = 'books';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-forest text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="available_books.php" class="text-forest text-decoration-none">Available Books</a></li>
            <li class="breadcrumb-item"><a href="book_details.php?id=<?= $bookId ?>" class="text-forest text-decoration-none"><?= h($book['title']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Checkout</li>
        </ol>
    </nav>

    <?= render_flash() ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger rounded-4 shadow-sm mb-4">
            <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Action Failed:</h6>
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Column: Checkout Form -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
                <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                    <span class="badge <?= $purchaseType === 'lifetime' ? 'bg-terracotta' : 'bg-forest' ?> rounded-pill px-3 py-2 me-2">
                        <?= $purchaseType === 'lifetime' ? 'LIFETIME OWNERSHIP' : 'LIMITED PERIOD BORROWING' ?>
                    </span>
                    <h4 class="fw-bold text-dark mb-0">Delivery & Checkout Details</h4>
                </div>

                <form method="POST" action="checkout.php" id="checkoutForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="book_id" value="<?= $bookId ?>">
                    <input type="hidden" name="purchase_type" value="<?= h($purchaseType) ?>">
                    <input type="hidden" name="start_date" value="<?= h($startDate) ?>">
                    <input type="hidden" name="return_date" value="<?= h($returnDate) ?>">
                    <input type="hidden" name="duration_days" value="<?= $durationDays ?>">
                    <input type="hidden" name="coupon_code" value="<?= h($appliedCoupon['coupon_code'] ?? $couponCode) ?>">

                    <!-- Recipient & Shipping Information -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-forest mb-3"><i class="bi bi-geo-alt-fill me-2"></i>1. Shipping & Contact Address</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Recipient Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="recipient_name" class="form-control" required value="<?= h($userProfile['full_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Contact Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" name="recipient_phone" class="form-control" required value="<?= h($userProfile['phone'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Delivery Address <span class="text-danger">*</span></label>
                                <textarea name="shipping_address" class="form-control" rows="3" required placeholder="Complete home / institution address with pincode and city..."><?= h($userProfile['address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method Section -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-forest mb-3"><i class="bi bi-credit-card-2-front-fill me-2"></i>2. Payment & Verification Method</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check p-3 border rounded-3 bg-light h-100">
                                    <input class="form-check-input" type="radio" name="payment_method" id="payUPI" value="UPI (Google Pay / PhonePe / Paytm)" checked>
                                    <label class="form-check-label ms-2" for="payUPI">
                                        <strong>UPI / QR Payment</strong>
                                        <small class="text-muted d-block">Instant automated verification</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check p-3 border rounded-3 bg-light h-100">
                                    <input class="form-check-input" type="radio" name="payment_method" id="payCard" value="Credit / Debit Card">
                                    <label class="form-check-label ms-2" for="payCard">
                                        <strong>Debit / Credit Card</strong>
                                        <small class="text-muted d-block">Visa, Mastercard, RuPay</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12 mt-2">
                                <div class="form-check p-3 border rounded-3 bg-light">
                                    <input class="form-check-input" type="radio" name="payment_method" id="payCOD" value="Free NGO Handover / Pay on Delivery">
                                    <label class="form-check-label ms-2" for="payCOD">
                                        <strong><?= $purchaseType === 'borrow' ? 'Free Community Handover' : 'Pay on Delivery' ?></strong>
                                        <small class="text-muted d-block"><?= $purchaseType === 'borrow' ? 'Zero fees for students and learners' : 'Pay when you receive the physical book' ?></small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Guarantee Note -->
                    <div class="p-3 bg-light rounded-4 mb-4 small text-muted d-flex align-items-center">
                        <i class="bi bi-shield-check text-success fs-3 me-3"></i>
                        <div>
                            <strong>Guaranteed Physical Inventory:</strong> Upon confirming this order, this book will be immediately reserved and cannot be borrowed or bought by any other user simultaneously.
                        </div>
                    </div>

                    <button type="submit" name="place_order" class="btn btn-terracotta rounded-pill w-100 py-3 fw-bold fs-6 shadow-sm">
                        <i class="bi bi-check2-circle me-2"></i>Confirm & Place <?= $purchaseType === 'lifetime' ? 'Lifetime Purchase' : 'Borrow Request' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column: Order Summary & Coupon Code Box (Requirement 9) -->
        <div class="col-lg-5">
            <!-- Book Summary Card -->
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3">Order Summary</h5>
                <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                    <div class="rounded-3 bg-light p-2 text-center" style="width: 80px; height: 100px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                        <?php 
                        $bookImg = !empty($book['image']) ? basename($book['image']) : '';
                        if ($bookImg && file_exists(__DIR__ . '/uploads/book_images/' . $bookImg)): ?>
                            <img src="uploads/book_images/<?= h($bookImg) ?>" alt="<?= h($book['title']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                        <?php else: ?>
                            <i class="bi bi-journal-bookmark fs-2 text-secondary"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="badge bg-light text-forest border mb-1"><?= h($book['category_name']) ?></span>
                        <h6 class="fw-bold text-dark mb-1"><?= h($book['title']) ?></h6>
                        <small class="text-muted d-block">By <?= h($book['author'] ?: 'Unknown') ?></small>
                        <small class="text-muted">Condition: <span class="badge badge-condition-<?= strtolower(str_replace(' ', '', h($book['book_condition']))) ?>"><?= h($book['book_condition']) ?></span></small>
                    </div>
                </div>

                <?php if ($purchaseType === 'borrow'): ?>
                    <!-- Borrow Details -->
                    <div class="p-3 rounded-4 bg-forest-light text-forest mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Borrow Period:</span>
                            <strong><?= $durationDays ?> Days</strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Start Date:</span>
                            <span><?= date('d M Y', strtotime($startDate)) ?></span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span>Return Due Date:</span>
                            <strong class="text-terracotta"><?= date('d M Y', strtotime($returnDate)) ?></strong>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Have a Coupon Code Section (Requirement 9) -->
                <?php if ($purchaseType === 'lifetime'): ?>
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small mb-1">Have a Coupon Code?</label>
                        <form method="GET" action="checkout.php" class="d-flex gap-2">
                            <input type="hidden" name="book_id" value="<?= $bookId ?>">
                            <input type="hidden" name="type" value="<?= h($purchaseType) ?>">
                            <input type="text" name="coupon_code" class="form-control font-monospace text-uppercase" 
                                   placeholder="Enter Coupon Code (e.g. DONATE50-8K4P)" value="<?= h($couponCode) ?>">
                            <button type="submit" class="btn btn-forest text-nowrap rounded-pill px-3">
                                Apply Coupon
                            </button>
                        </form>

                        <?php if ($appliedCoupon): ?>
                            <div class="alert alert-success small rounded-3 mt-2 py-2 mb-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-check-circle-fill me-1"></i>
                                    Coupon <strong><?= h($appliedCoupon['coupon_code']) ?></strong> applied: <strong><?= (float)$appliedCoupon['discount_percentage'] ?>% OFF</strong>!
                                </div>
                                <a href="checkout.php?book_id=<?= $bookId ?>&type=<?= h($purchaseType) ?>" class="text-danger text-decoration-none small fw-bold">Remove</a>
                            </div>
                        <?php elseif ($couponError): ?>
                            <div class="alert alert-danger small rounded-3 mt-2 py-2 mb-0">
                                <i class="bi bi-x-circle me-1"></i><?= h($couponError) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Price Breakdown Table (Requirement 3 & 9) -->
                <div class="border-top pt-3">
                    <?php if ($purchaseType === 'lifetime'): ?>
                        <div class="d-flex justify-content-between text-muted small mb-2">
                            <span>Normal / Original Price:</span>
                            <span class="price-original"><?= format_currency($origPrice) ?></span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small mb-2">
                            <span>Lifetime Price (50% Standard):</span>
                            <span><?= format_currency($lifetimeBasePrice) ?></span>
                        </div>
                        <?php if ($discountAmount > 0): ?>
                            <div class="d-flex justify-content-between text-success fw-bold small mb-2">
                                <span>Coupon Discount (<?= (float)$appliedCoupon['discount_percentage'] ?>%):</span>
                                <span>-<?= format_currency($discountAmount) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="fw-bold fs-6 text-dark">Final Amount Payable:</span>
                            <span class="price-lifetime"><?= format_currency($finalPrice) ?></span>
                        </div>
                        <div class="text-end small text-success mt-1">
                            <i class="bi bi-piggy-bank-fill me-1"></i>You Save: <strong><?= format_currency($origPrice - $finalPrice) ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="d-flex justify-content-between text-muted small mb-2">
                            <span>Book Borrow Fee:</span>
                            <span class="text-success fw-bold">FREE (Community Access)</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small mb-2">
                            <span>Security Deposit:</span>
                            <span>₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="fw-bold fs-6 text-dark">Total Due Now:</span>
                            <span class="price-lifetime">₹0.00</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reward Notice Box -->
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-light">
                <div class="d-flex align-items-center">
                    <i class="bi bi-gift-fill fs-3 text-terracotta me-3"></i>
                    <small class="text-muted">
                        Donating books? Remember that <strong>donating 10 or more approved books</strong> automatically generates a 50% discount coupon in your rewards wallet!
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
