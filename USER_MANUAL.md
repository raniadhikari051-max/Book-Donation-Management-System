# Book Donation and Management System
## Community Engagement Project (CEP) — BBA(CA) 5th Semester
### Comprehensive User Manual & Technical Documentation

---

## 1. System Overview & Technology Stack

The **Book Donation and Management System** is a full-featured, database-driven web application designed to empower community literacy and support underprivileged students by centralizing book donations, inventory verification, volunteer logistics, and transparent student distributions.

### Technology Stack:
- **Frontend**: HTML5, Vanilla CSS3 (Custom Design System with responsive grid, card utilities, badge systems, dark mode tokens), Vanilla JavaScript, Bootstrap 5.3 (CDN), Bootstrap Icons (CDN), Chart.js (CDN).
- **Backend**: PHP 8.2 (Procedural architecture with light OOP for database layer), MySQLi with 100% Prepared Statements (zero raw SQL string concatenations).
- **Database**: MySQL 8 (InnoDB storage engine, UTF-8 MB4 charset).
- **Server Environment**: XAMPP (Apache Web Server + MariaDB/MySQL).

---

## 2. Security Architecture & Advanced Features

1. **CSRF Protection**:
   - Every state-modifying POST form includes `<?= csrf_field() ?>`.
   - Verified via `verify_csrf_token()` with secure `hash_equals()`.
2. **Brute-Force Rate Limiting & Account Auto-Lockout**:
   - Accounts track `failed_attempts` and `locked_until`.
   - After 5 consecutive invalid login attempts, the account is automatically locked for 15 minutes.
   - Administrators have one-click ability in `manage_users.php` to unlock accounts.
3. **Audit Trail Logging**:
   - High-priority security and administrative actions are logged in `activity_log` with user ID, action code, timestamp, and IP address.
4. **Bilingual Localization (English / Marathi)**:
   - Dynamic translation helper `t('key')` serving bilingual navigation and headings in English and Marathi.
5. **Dark Mode / Light Mode**:
   - Modern theme toggle with client-side persistence (`localStorage.getItem('theme')`).
6. **Gamification & Impact Points**:
   - New accounts start at **0 points**.
   - **Donors** earn **+10 points per verified book** received and merged into inventory by an admin inside the transaction.
   - **Recipients (Students)** earn **+5 points** only upon successfully submitting verified feedback for a fulfilled book request.
   - Donors progress across 4 tiers: Bronze Supporter, Silver Patron, Gold Benefactor, and Champion of Literacy.
7. **Beneficiary Student Privacy**:
   - On the public Community Transparency Portal (`transparency.php`), student recipients are anonymized (e.g. "Rohan P., Student from Pune College" or "A student from [Institution]") to protect charity beneficiaries, while donors are publicly credited on the leaderboard.

---

## 3. Role-by-Role User Guide

### A. Public Visitors & Community Members
- **Home Page (`index.php`)**: View live counters of books in inventory, drives completed, and active volunteers. Shows featured books with cover photos.
- **Available Books (`available_books.php`)**: Search all donated books with real-time category filters and stock availability badges. Logged-in recipients can click "Request" on any book to pre-populate their request form.
- **How It Works (`how_it_works.php`)**: Illustrated 4-step guide on donating, collecting, sorting, and distributing books.
- **Community Transparency (`transparency.php`)**: Publicly auditable dashboard showing total books collected, books distributed into students' hands, Top Donors leaderboard, and verified student impact testimonials with student privacy protection.
- **Contact (`contact.php`)**: Send direct inquiries to project coordinators; secured with CSRF protection.

---

### B. Donor Experience Portal (`donor/`)
- **Dashboard (`donor/dashboard.php`)**: View total books donated, points balance, reward tier badge, and next tier progress bar.
- **Donate Books (`donor/donate_book.php`)**:
  - Interactive multi-book donation form: Click "+ Add Another Book" to add unlimited book rows in a single package.
  - Choose between **Drop-off at Community Hub** and **Request Volunteer Doorstep Pickup**.
  - Specify preferred pickup date and doorstep collection address.
- **My Donations (`donor/my_donations.php`)**:
  - View all past packages with tracking codes (e.g. `DON-9A3F12BC`).
  - Visual 4-step progress tracker (`Submitted` &rarr; `Approved` &rarr; `Pickup Scheduled` &rarr; `Received`).
  - Dynamic QR code representation for easy check-in.
- **Contribution Certificate (`donor/certificate.php`)**:
  - Official Certificate of Appreciation recognizing book contributions to the BBA(CA) Community Engagement Project.
  - Click "Print / Save as PDF" to generate a clean, ornamental certificate with signatures and official seals.
- **Notifications & Profile (`donor/notifications.php`, `donor/profile.php`)**:
  - In-app notification alerts for verification milestones and volunteer assignments.

---

### C. Recipient (Student / Scholar) Portal (`recipient/`)
- **Dashboard (`recipient/dashboard.php`)**: Summary of books received, active book requests, and learner points.
- **Search Catalog (`recipient/search_books.php`)**:
  - Fast search by title, author, or keyword with real-time client & server filtering.
  - If a book is in stock, click "Request This Book".
  - If a book is out of stock, click "Notify Me When In Stock" to add it to your wishlist (triggers automatic notification when a donor contributes it).
- **Request a Book (`recipient/request_book.php`)**:
  - Direct request form specifying required textbook, quantity, and course purpose. Pre-fills book when coming from catalog.
- **My Requests (`recipient/my_requests.php`)**:
  - Track requests with visual progress stepper (`Submitted` &rarr; `Approved` &rarr; `Ready for Pickup` &rarr; `Received`).
  - View partial fulfillment status.
- **Feedback & Rating (`recipient/feedback.php`)**:
  - Submit 1-5 star ratings and reviews for received books.
  - Automatically awards +5 bonus points to the student profile upon submission!

---

### D. Volunteer Portal (`volunteer/`)
- **Dashboard (`volunteer/dashboard.php`)**: View active pickup missions, completed drives, and assigned coverage area.
- **Available Tasks Board (`volunteer/available_tasks.php`)**:
  - Browse unassigned donor doorstep pickup requests across Pune.
  - Click "Claim This Pickup Mission" to accept the task.
- **My Assignments (`volunteer/my_assignments.php`)**:
  - Direct contact information for donors with click-to-call links and Google Maps address links.
  - Update mission status: Set ETA (e.g., "Tomorrow 4 PM"), mark "In Progress", and mark "Collected from Donor".

---

### E. Administrator Control Panel (`admin/`)
- **Dashboard (`admin/dashboard.php`)**:
  - High-level KPIs: Total Books, Donated, Distributed, Registered Users.
  - Low stock inventory alert banner.
  - Chart.js monthly trend visualization comparing donations vs student distributions.
- **Manage Categories (`admin/manage_categories.php`)**: Add, edit, or delete book categories with foreign-key dependency protection.
- **Manage Books Catalog (`admin/manage_books.php`)**: Complete inventory management with cover photo upload, condition tagging, and manual stock adjustments.
- **Manage Donations (`admin/manage_donations.php`)**:
  - Review donor packages, assign verified student volunteers for doorstep pickups.
  - Marking a package as "Received" transactionally merges line items into the book catalog, credits +10 points per book to the donor, and notifies wishlist subscribers!
- **Manage Requests (`admin/manage_requests.php`)**: Approve or reject student requests, mark books as "Ready for Collection".
- **Manage Distributions (`admin/manage_distribution.php`)**:
  - Execute distribution: transaction-safe stock decrement, partial delivery handling, and student points crediting.
- **Manage Users (`admin/manage_users.php`)**: Activate, deactivate, reset passwords, and unlock locked accounts.
- **Manage Events (`admin/manage_events.php`)**: Schedule neighborhood book drives and community reading outreach.
- **Feedback Reviews (`admin/view_feedback.php`)**: View beneficiary star ratings and testimonials.
- **System Audit Trail (`admin/activity_log.php`)**: Immutable log of security events and administrative actions.
- **Database Backup (`admin/backup.php`)**: One-click SQL dump export for safe offline backup.
- **Reporting Engine (`reports/`)**:
  - `donation_report.php`: Comprehensive donation analytics with date filters and CSV export.
  - `inventory_report.php`: Catalog stock levels with low-stock alerts and CSV export.
  - `distribution_report.php`: Audit records of distributed books with CSV export.

---

## 4. Default Demo Accounts

All demo accounts share the password: `Pass@123`

| Role | Email Address | Password |
|---|---|---|
| **Administrator** | `admin@bookdonate.local` | `Pass@123` |
| **Donor** | `aarav.donor@example.com` | `Pass@123` |
| **Recipient (Student)** | `rohan.recipient@example.com` | `Pass@123` |
| **Volunteer** | `kunal.volunteer@example.com` | `Pass@123` |