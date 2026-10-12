<?php
/**
 * how_it_works.php
 * Detailed step-by-step workflow guide.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'How It Works — Step by Step Workflow';
$activePage = 'how_it_works';

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-primary text-white py-5">
    <div class="container">
        <h1 class="display-5 fw-bold mb-2">How It Works</h1>
        <p class="lead text-light mb-0">A step-by-step guide for Donors, Students, and Volunteers</p>
    </div>
</div>

<div class="container py-5">
    <!-- Role Tabs -->
    <ul class="nav nav-pills nav-justified mb-5 bg-white p-2 rounded-4 border shadow-sm" id="workflowTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-pill fw-bold py-3" id="donor-tab" data-bs-toggle="pill" data-bs-target="#donor" type="button" role="tab">
                <i class="bi bi-box-arrow-up me-2"></i>For Donors
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill fw-bold py-3" id="recipient-tab" data-bs-toggle="pill" data-bs-target="#recipient" type="button" role="tab">
                <i class="bi bi-mortarboard me-2"></i>For Students & Recipients
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill fw-bold py-3" id="volunteer-tab" data-bs-toggle="pill" data-bs-target="#volunteer" type="button" role="tab">
                <i class="bi bi-person-badge me-2"></i>For Volunteers
            </button>
        </li>
    </ul>

    <div class="tab-content" id="workflowTabContent">
        <!-- Donor Tab -->
        <div class="tab-pane fade show active" id="donor" role="tabpanel">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="custom-card p-4 h-100 text-center">
                        <div class="badge bg-primary fs-5 rounded-circle p-3 mb-3">1</div>
                        <h5 class="fw-bold">Register & Log In</h5>
                        <p class="text-muted small mb-0">Create your donor account with your contact address and optional organization details.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="custom-card p-4 h-100 text-center">
                        <div class="badge bg-primary fs-5 rounded-circle p-3 mb-3">2</div>
                        <h5 class="fw-bold">Submit Donation</h5>
                        <p class="text-muted small mb-0">Enter book titles, author, category, condition, and select either Doorstep Pickup or Drop-off.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="custom-card p-4 h-100 text-center">
                        <div class="badge bg-primary fs-5 rounded-circle p-3 mb-3">3</div>
                        <h5 class="fw-bold">Volunteer Handover</h5>
                        <p class="text-muted small mb-0">Once approved by Admin, an assigned volunteer coordinates and collects your books.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="custom-card p-4 h-100 text-center">
                        <div class="badge bg-primary fs-5 rounded-circle p-3 mb-3">4</div>
                        <h5 class="fw-bold">Receive Notification</h5>
                        <p class="text-muted small mb-0">Receive automatic in-app confirmation once books are added to live library inventory.</p>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="register.php" class="btn btn-primary rounded-pill px-4">Get Started as a Donor</a>
            </div>
        </div>

        <!-- Recipient Tab -->
        <div class="tab-pane fade" id="recipient" role="tabpanel">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="custom-card p-4 h-100 text-center">
                        <div class="badge bg-info text-dark fs-5 rounded-circle p-3 mb-3">1</div>
                        <h5 class="fw-bold">Browse Catalogue</h5>
                        <p class="text-muted small mb-0">Search thousands of titles across textbooks, competitive guides, fiction, and children's books.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="custom-card p-4 h-100 text-center">
                        <div class="badge bg-info text-dark fs-5 rounded-circle p-3 mb-3">2</div>
                        <h5 class="fw-bold">Submit Request</h5>
                        <p class="text-muted small mb-0">Select required books and submit your request explaining your educational requirement.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="custom-card p-4 h-100 text-center">
                        <div class="badge bg-info text-dark fs-5 rounded-circle p-3 mb-3">3</div>
                        <h5 class="fw-bold">Admin Verification</h5>
                        <p class="text-muted small mb-0">Admin reviews the request against real available stock and schedules distribution.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="custom-card p-4 h-100 text-center">
                        <div class="badge bg-info text-dark fs-5 rounded-circle p-3 mb-3">4</div>
                        <h5 class="fw-bold">Collect & Review</h5>
                        <p class="text-muted small mb-0">Receive your books and submit rating/feedback to support the community.</p>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="available_books.php" class="btn btn-info rounded-pill px-4 text-dark fw-bold">Explore Available Books</a>
            </div>
        </div>

        <!-- Volunteer Tab -->
        <div class="tab-pane fade" id="volunteer" role="tabpanel">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="custom-card p-4 h-100 text-center">
                        <div class="badge bg-secondary fs-5 rounded-circle p-3 mb-3">1</div>
                        <h5 class="fw-bold">Sign Up for Area</h5>
                        <p class="text-muted small mb-0">Register with your coverage area (e.g., Kothrud, Baner, Viman Nagar) and weekend/weekday availability.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="custom-card p-4 h-100 text-center">
                        <div class="badge bg-secondary fs-5 rounded-circle p-3 mb-3">2</div>
                        <h5 class="fw-bold">Accept Tasks</h5>
                        <p class="text-muted small mb-0">Review pending pickups assigned by Admin in your dashboard with donor address and contact.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="custom-card p-4 h-100 text-center">
                        <div class="badge bg-secondary fs-5 rounded-circle p-3 mb-3">3</div>
                        <h5 class="fw-bold">Complete & Update</h5>
                        <p class="text-muted small mb-0">Deliver collected bundles to the hub and mark assignments 'Completed' in one click.</p>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="register.php" class="btn btn-secondary rounded-pill px-4">Register as a Volunteer</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>