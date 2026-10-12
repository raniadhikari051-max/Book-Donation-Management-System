<?php
/**
 * about.php
 * About the project & mission.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'About Project';
$activePage = 'about';

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-primary text-white py-5">
    <div class="container">
        <h1 class="display-5 fw-bold mb-2">About The Project</h1>
        <p class="lead text-light mb-0">Community Engagement Project &mdash; BBA(CA) 5th Semester</p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5 align-items-center mb-5">
        <div class="col-lg-6">
            <span class="badge bg-primary-subtle text-primary text-uppercase fw-bold px-3 py-2 rounded-pill mb-3">Our Mission</span>
            <h2 class="fw-bold display-6 mb-4">Bridging Educational Resource Gaps Through Community Giving</h2>
            <p class="text-muted leading-relaxed mb-3">
                Every academic year, thousands of students finish their classes with high-value reference textbooks and study materials lying unused on shelves. Meanwhile, underprivileged students, rural study circles, and underfunded schools struggle with prohibitive textbook prices.
            </p>
            <p class="text-muted leading-relaxed">
                The <strong>Book Donation and Management System</strong> is a dedicated web platform built to solve this imbalance. By pairing direct donor listings with a volunteer-supported collection and transparent distribution pipeline, we ensure that every donated book reaches hands that genuinely need it.
            </p>
        </div>
        <div class="col-lg-6">
            <div class="p-4 bg-light rounded-4 border shadow-sm">
                <h4 class="fw-bold mb-3"><i class="bi bi-mortarboard text-primary me-2"></i>Academic Scope & Context</h4>
                <ul class="list-unstyled text-muted small mb-0">
                    <li class="mb-3 d-flex align-items-start gap-2">
                        <i class="bi bi-check-circle-fill text-success mt-1"></i>
                        <span><strong>Program:</strong> Bachelor of Business Administration (Computer Applications) &mdash; 5th Semester</span>
                    </li>
                    <li class="mb-3 d-flex align-items-start gap-2">
                        <i class="bi bi-check-circle-fill text-success mt-1"></i>
                        <span><strong>Domain:</strong> Community Engagement, Social Impact & Database-driven Web Applications</span>
                    </li>
                    <li class="mb-3 d-flex align-items-start gap-2">
                        <i class="bi bi-check-circle-fill text-success mt-1"></i>
                        <span><strong>Architecture:</strong> Secure PHP 8, MySQL 8 normalized relational database, prepared statements, and Bootstrap 5 UI</span>
                    </li>
                    <li class="d-flex align-items-start gap-2">
                        <i class="bi bi-check-circle-fill text-success mt-1"></i>
                        <span><strong>Key Feature:</strong> Complete donate &rarr; collect &rarr; catalog &rarr; request &rarr; distribute workflow</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Core Values -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="custom-card p-4 h-100">
                <i class="bi bi-eye text-primary fs-1 mb-3"></i>
                <h4 class="fw-bold mb-2">100% Transparent</h4>
                <p class="text-muted small mb-0">Every donation, volunteer pickup, and book distribution is tracked step-by-step with audit-ready records.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="custom-card p-4 h-100">
                <i class="bi bi-recycle text-success fs-1 mb-3"></i>
                <h4 class="fw-bold mb-2">Zero Waste</h4>
                <p class="text-muted small mb-0">Extend the lifecycle of paper textbooks, encyclopedias, and literature, saving trees and reducing environmental footprint.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="custom-card p-4 h-100">
                <i class="bi bi-people text-warning fs-1 mb-3"></i>
                <h4 class="fw-bold mb-2">Community Driven</h4>
                <p class="text-muted small mb-0">Powered by active neighborhood volunteers who collect and deliver books right at the grassroots level.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>