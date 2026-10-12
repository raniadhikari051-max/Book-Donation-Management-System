<?php
/**
 * available_books.php
 * Upgraded Public Catalog: Search by title, author, ISBN; Category & Condition filters;
 * Stock availability badges; Lifetime price (50% off) & Borrowing options.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Available Books Catalog';
$activePage = 'books';

// Fetch categories
$categories = [];
$catSql = "SELECT category_id, category_name FROM categories ORDER BY category_name ASC";
if ($res = $conn->query($catSql)) {
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch all books (available and total)
$books = [];
$sql = "SELECT b.*, c.category_name 
        FROM books b 
        JOIN categories c ON b.category_id = c.category_id 
        ORDER BY b.available_quantity > 0 DESC, b.title ASC";

if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $orig = (float)($row['original_price'] > 0 ? $row['original_price'] : 500.00);
        $life = (float)($row['lifetime_price'] > 0 ? $row['lifetime_price'] : round($orig * 0.5, 2));
        $row['calc_orig_price'] = $orig;
        $row['calc_life_price'] = $life;
        $row['calc_you_save'] = $orig - $life;
        $books[] = $row;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Catalog Header Banner -->
<div class="bg-forest text-white py-5 position-relative overflow-hidden">
    <div class="container position-relative z-2">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-terracotta text-white rounded-pill px-3 py-2 mb-3 fw-bold">
                    <i class="bi bi-book-half me-1"></i> Community Library Catalog
                </span>
                <h1 class="display-5 fw-bold mb-2 text-white">Explore Available Books</h1>
                <p class="lead text-light mb-0" style="opacity: 0.95;">
                    Choose between <strong>Lifetime Ownership (50% Off)</strong> or <strong>Flexible Borrowing</strong> with free community access.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <div class="d-inline-flex flex-column gap-2 text-lg-end">
                    <span class="badge bg-white text-forest px-4 py-2 rounded-pill fs-6 shadow-sm">
                        <i class="bi bi-journal-check me-1"></i> <?= count($books) ?> Titles in Catalog
                    </span>
                    <a href="donate.php" class="btn btn-terracotta btn-sm rounded-pill px-3 fw-bold shadow-sm">
                        <i class="bi bi-heart-fill me-1"></i>Donate More Books
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <?= render_flash() ?>

    <!-- Search & Multi-Filter Controls (Requirement 16) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
        <div class="row g-3">
            <!-- Search by Title, Author, ISBN -->
            <div class="col-lg-4 col-md-6">
                <label class="form-label small fw-bold text-muted mb-1">Search Catalog</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="catalogSearch" class="form-control bg-light border-start-0" 
                           placeholder="Search by title, author, or ISBN...">
                </div>
            </div>

            <!-- Filter by Category -->
            <div class="col-lg-3 col-md-6">
                <label class="form-label small fw-bold text-muted mb-1">Category</label>
                <select id="categoryFilter" class="form-select bg-light">
                    <option value="all">All Categories (<?= count($categories) ?>)</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['category_id'] ?>"><?= h($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter by Condition -->
            <div class="col-lg-2 col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">Condition</label>
                <select id="conditionFilter" class="form-select bg-light">
                    <option value="all">All Conditions</option>
                    <option value="New">New</option>
                    <option value="Like New">Like New</option>
                    <option value="Good">Good</option>
                    <option value="Acceptable">Acceptable</option>
                </select>
            </div>

            <!-- Filter by Availability / Stock -->
            <div class="col-lg-2 col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">Availability</label>
                <select id="availabilityFilter" class="form-select bg-light">
                    <option value="all">All Books</option>
                    <option value="available" selected>In Stock Only</option>
                    <option value="borrowed">Currently Borrowed</option>
                </select>
            </div>

            <!-- Reset Button -->
            <div class="col-lg-1 col-md-4 d-flex align-items-end">
                <button id="resetFilters" class="btn btn-outline-secondary w-100 rounded-pill py-2" title="Reset all filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Active Results Counter -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="small text-muted" id="resultsCount">
            Showing all <strong class="text-dark"><?= count($books) ?></strong> books in collection
        </div>
        <div class="d-flex gap-2 align-items-center small">
            <span class="badge badge-available">🟢 Available</span>
            <span class="badge badge-borrowed">🔵 Borrowed</span>
            <span class="badge badge-overdue">🔴 Out of Stock</span>
        </div>
    </div>

    <!-- Books Grid -->
    <div class="row g-4" id="booksGrid">
        <?php if (!empty($books)): ?>
            <?php foreach ($books as $b): ?>
                <?php
                $isAvail = ($b['available_quantity'] > 0 && $b['status'] === 'Available');
                $availKey = $isAvail ? 'available' : ($b['status'] === 'Borrowed' ? 'borrowed' : 'unavailable');
                $statusClass = $isAvail ? 'badge-available' : ($b['status'] === 'Borrowed' ? 'badge-borrowed' : 'badge-overdue');
                $statusText = $isAvail ? '● Available (' . (int)$b['available_quantity'] . ')' : ($b['status'] === 'Borrowed' ? '● Borrowed' : '● ' . $b['status']);
                ?>
                <div class="col-sm-6 col-lg-4 col-xl-3 col-book-item"
                     data-title="<?= strtolower(h($b['title'])) ?>"
                     data-author="<?= strtolower(h($b['author'] ?? '')) ?>"
                     data-isbn="<?= strtolower(h($b['isbn'] ?? '')) ?>"
                     data-category-id="<?= (int)$b['category_id'] ?>"
                     data-condition="<?= h($b['book_condition']) ?>"
                     data-avail="<?= $availKey ?>">
                    <div class="custom-card h-100 d-flex flex-column book-item-card overflow-hidden bg-white shadow-sm border rounded-4 position-relative">
                        
                        <!-- Availability Badge on top of image -->
                        <div class="position-absolute top-0 start-0 m-2 z-2">
                            <span class="badge <?= $statusClass ?> rounded-pill px-2 py-1 small">
                                <?= h($statusText) ?>
                            </span>
                        </div>

                        <!-- 50% Off Lifetime Ribbon -->
                        <?php if ($isAvail): ?>
                            <div class="position-absolute top-0 end-0 m-2 z-2">
                                <span class="badge-save">50% OFF BUY</span>
                            </div>
                        <?php endif; ?>

                        <!-- Cover Image with Fallback -->
                        <?php 
                        $bookImgFile = !empty($b['image']) ? basename($b['image']) : '';
                        if ($bookImgFile && file_exists(__DIR__ . '/uploads/book_images/' . $bookImgFile)): ?>
                            <div class="book-cover-showcase">
                                <img src="uploads/book_images/<?= h($bookImgFile) ?>" alt="<?= h($b['title']) ?>" 
                                     class="book-3d-cover">
                            </div>
                        <?php else: ?>
                            <div class="bg-light p-4 text-center border-bottom d-flex align-items-center justify-content-center" style="height: 200px;">
                                <i class="bi bi-journal-bookmark display-4 text-muted opacity-50"></i>
                            </div>
                        <?php endif; ?>

                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <!-- Category & Condition -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-light text-forest border text-truncate" style="max-width: 130px;">
                                    <?= h($b['category_name']) ?>
                                </span>
                                <span class="badge badge-condition-<?= strtolower(str_replace(' ', '', h($b['book_condition']))) ?>">
                                    <?= h($b['book_condition']) ?>
                                </span>
                            </div>

                            <!-- Title & Author -->
                            <h5 class="fw-bold text-dark mb-1 text-truncate" title="<?= h($b['title']) ?>">
                                <a href="book_details.php?id=<?= (int)$b['book_id'] ?>" class="text-dark text-decoration-none hover-primary">
                                    <?= h($b['title']) ?>
                                </a>
                            </h5>
                            <p class="small text-muted mb-2 text-truncate">By <?= h($b['author'] ?: 'Unknown Author') ?></p>

                            <!-- Pricing Breakdown (Requirement 3 & 16) -->
                            <div class="bg-light p-2 rounded-3 mb-3 border">
                                <div class="d-flex justify-content-between align-items-center small">
                                    <span class="text-muted">Original: <span class="price-original"><?= format_currency($b['calc_orig_price']) ?></span></span>
                                    <span class="badge bg-forest-light text-forest">Borrow: FREE</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <span class="small fw-bold text-dark">Lifetime Price:</span>
                                    <span class="price-lifetime fs-6"><?= format_currency($b['calc_life_price']) ?></span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="mt-auto pt-2 border-top d-flex gap-2">
                                <a href="book_details.php?id=<?= (int)$b['book_id'] ?>" 
                                   class="btn btn-sm btn-outline-secondary rounded-pill flex-fill" title="View Specifications">
                                    <i class="bi bi-info-circle"></i> Details
                                </a>
                                <?php if ($isAvail): ?>
                                    <a href="checkout.php?book_id=<?= (int)$b['book_id'] ?>&type=lifetime" 
                                       class="btn btn-sm btn-terracotta rounded-pill flex-fill fw-bold shadow-sm" title="Buy for Lifetime at 50% price">
                                        <i class="bi bi-bag-check"></i> Buy
                                    </a>
                                    <a href="book_details.php?id=<?= (int)$b['book_id'] ?>#borrowDurationModal" 
                                       class="btn btn-sm btn-forest rounded-pill px-3 shadow-sm" title="Borrow for limited period">
                                        <i class="bi bi-arrow-repeat"></i> Borrow
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-light border rounded-pill flex-fill text-muted" disabled>
                                        Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-journal-x fs-1 text-muted d-block mb-3"></i>
                <h4 class="text-muted">No books are currently in the catalog.</h4>
                <p class="text-secondary">Be the first to donate and support our community literacy project!</p>
                <a href="donate.php" class="btn btn-terracotta rounded-pill px-4">Donate Books</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Interactive Client-side Filter Script (Requirement 16) -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('catalogSearch');
    const categorySelect = document.getElementById('categoryFilter');
    const conditionSelect = document.getElementById('conditionFilter');
    const availabilitySelect = document.getElementById('availabilityFilter');
    const resetButton = document.getElementById('resetFilters');
    const bookItems = document.querySelectorAll('.col-book-item');
    const resultsCount = document.getElementById('resultsCount');

    function filterBooks() {
        const query = searchInput.value.toLowerCase().trim();
        const selectedCat = categorySelect.value;
        const selectedCond = conditionSelect.value;
        const selectedAvail = availabilitySelect.value;

        let visibleCount = 0;

        bookItems.forEach(item => {
            const title = item.getAttribute('data-title') || '';
            const author = item.getAttribute('data-author') || '';
            const isbn = item.getAttribute('data-isbn') || '';
            const catId = item.getAttribute('data-category-id') || '';
            const condition = item.getAttribute('data-condition') || '';
            const avail = item.getAttribute('data-avail') || '';

            const matchesQuery = query === '' || title.includes(query) || author.includes(query) || isbn.includes(query);
            const matchesCat = selectedCat === 'all' || catId === selectedCat;
            const matchesCond = selectedCond === 'all' || condition === selectedCond;
            const matchesAvail = selectedAvail === 'all' || (selectedAvail === 'available' ? avail === 'available' : avail === 'borrowed');

            if (matchesQuery && matchesCat && matchesCond && matchesAvail) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        if (resultsCount) {
            resultsCount.innerHTML = `Showing <strong class="text-dark">${visibleCount}</strong> of ${bookItems.length} books in catalog`;
        }
    }

    if (searchInput) searchInput.addEventListener('input', filterBooks);
    if (categorySelect) categorySelect.addEventListener('change', filterBooks);
    if (conditionSelect) conditionSelect.addEventListener('change', filterBooks);
    if (availabilitySelect) availabilitySelect.addEventListener('change', filterBooks);

    if (resetButton) {
        resetButton.addEventListener('click', () => {
            searchInput.value = '';
            categorySelect.value = 'all';
            conditionSelect.value = 'all';
            availabilitySelect.value = 'all';
            filterBooks();
        });
    }

    // Default trigger
    filterBooks();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
