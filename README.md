# 🏨 Marvelous Hotel and Dormitory Booking and Reservation Management System with AI-Based Room Recommendation

A comprehensive **3-Tier Hotel & Dormitory Web Application** structured cleanly into **`frontend/`** (**Tailwind CSS + Alpine.js 3**, HTML5, CSS3, ES6+ JavaScript), **`backend/`** (**Slim 4 PHP 8 REST API Framework**, PSR-4 Services/Controllers/Middleware, Real-Time NVIDIA NIM SSE AI), and **`database/`** (**MySQL / MariaDB SQL** via PDO with 17 versioned migrations), designed to run natively on **XAMPP**.

---

## 🚀 How to Setup and Run on XAMPP

### 1. Place the Project in `htdocs`
Ensure the hotel project directory is located inside your XAMPP `htdocs` folder:
* **Linux:** `/opt/lampp/htdocs/hotel`
* **Windows:** `C:/xampp/htdocs/hotel`

### 2. Start Apache and MySQL
* **Linux (Terminal):**
  ```bash
  sudo /opt/lampp/lampp start
  ```
* **Windows:** Open the **XAMPP Control Panel** and click **Start** next to **Apache** and **MySQL**.

### 3. Import the Database
Navigate to `http://localhost/phpmyadmin/` in your browser or run the following terminal command:
```bash
/opt/lampp/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS marvelous_hotel;"
/opt/lampp/bin/mysql -u root marvelous_hotel < database/marvelous_hotel.sql
```

Pagkatapos ng import (at sa bawat bagong release na may schema change), patakbuhin ang migration runner:
```bash
cd /opt/lampp/htdocs/hotel
php migrate.php           # ilapat ang mga pending migration
php migrate.php --status  # i-list ang applied/pending
```
Naka-track ang bawat migration sa `schema_migrations` table — isang beses lang ito binubuksan bawat file sa `migrations/`.

### 4. Set Up Secure Uploads Directory (Optional - for file serving)
```bash
mkdir -p /opt/lampp/htdocs/hotel/uploads/rooms /opt/lampp/htdocs/hotel/uploads/proofs
chmod -R 755 /opt/lampp/htdocs/hotel/uploads
```

### 5. Run Tests (Optional)
```bash
cd /opt/lampp/htdocs/hotel
php phpunit.phar --bootstrap tests/bootstrap.php
```

### 6. Enable Multi-Server VirtualHosts in XAMPP (Optional)
To host each portal on its dedicated port (`:8080`, `:8081`, `:8082`):
1. Open `/opt/lampp/etc/httpd.conf` and uncomment:
   ```apache
   Include etc/extra/httpd-vhosts.conf
   ```
2. Copy the configuration file:
   ```bash
   sudo cp /opt/lampp/htdocs/hotel/apache-vhosts-setup.conf /opt/lampp/etc/extra/httpd-vhosts.conf
   ```
3. Restart Apache:
   ```bash
   sudo /opt/lampp/lampp restartapache
   ```

---

## 🌐 Multi-Server Architecture & How to Access Each Portal

Every module features its own dedicated URL, distinct access credentials, and isolated CSS/JS scripts. In addition to standard XAMPP paths, the system includes a ready-to-run multi-port Apache configuration ([`apache-vhosts-setup.conf`](file:///opt/lampp/htdocs/hotel/apache-vhosts-setup.conf)) to run each portal on dedicated servers/ports:

| Server / Portal | Multi-Port URL | Standard XAMPP URL | Access Level & Permissions |
|---|---|---|---|
| 👑 **Super Admin** | `http://localhost:8081/` | `http://localhost/hotel/admin/login.php` | **Full Master Authority:** View all users & staff, Master Password Reset, ban/activate, room inventory, payment approvals, settings. |
| 👨‍💼 **Staff Frontdesk** | `http://localhost:8082/` | `http://localhost/hotel/staff/login.php` | **Strict Operations Only:** Fast Check-in/out, live room status, guest inquiries. ❌ No account creation, no password resets. |
| 🌐👤 **Guest & Public Portal** | `http://localhost:8080/` | `http://localhost/hotel/` (Website) & `auth/login.php` | **Unified Guest Self-Service & Public Showcase:** Public website showcase, room catalog, self-registration, booking, KYC ID submission, QR payments (GCash & Maya), and Marvi AI Chatbot. |

### 🔒 Independent Session Isolation Engine (Zero Cross-Portal Collisions)
To completely prevent session conflicts and the "Auto Admin Only" bug when navigating between roles, each portal operates on an **isolated PHP session cookie namespace**:
* **Admin Server:** Cookie `MH_ADMIN_SESS`
* **Staff Server:** Cookie `MH_STAFF_SESS`
* **User / Public Server:** Cookie `MH_USER_SESS`

**Key Advantages:**
1. **Parallel Multi-Role Sessions:** You can stay logged into the Super Admin panel in Tab 1 while testing the User Booking flow in Tab 2 within the exact same browser window without session drops or auto-admin redirections.
2. **Autonomous Gatekeeping:** `redirectIfLoggedIn()` and `requireRole()` inspect only the session belonging to their active portal (`getActivePortal()`), preventing accidental unauthorized bounces across portals.
3. **Multi-Port & Standard Path Parity:** The engine auto-detects VirtualHost environment flags (`MH_PORTAL`), listening ports (`8080`, `8081`, `8082`), subdomains (`admin.hotel.local`, etc.), and script paths (`/admin/`, `/staff/`). It provides 100% session isolation whether running under Apache multi-port virtual hosts or classic standard `localhost/hotel` URLs.

---

### 👑 1. Admin Portal (Super Admin Server — Port 8081)
Designed for hotel owners and general managers. Holds master control over the entire system.

* **Multi-Port URL:** `http://localhost:8081/`
* **Standard Login URL:** [`http://localhost/hotel/admin/login.php`](http://localhost/hotel/admin/login.php)
* **Access Rule:** 🔒 **Walang direct link patungong dashboard.** Required mag-login muna bago ma-access ang system.
* **Login Email:** `admin@marveloushotel.com`
* **Password:** `password`
* **Key Features:**
  * **Master Password Reset:** Directly reset passwords for any User or Staff member with auto-generator tool (`admin/users.php`, `admin/staff.php`)
  * **Full Account Lifecycle:** Activate, suspend, ban, or create Staff accounts
  * Real-time search filters across data tables
  * Approval and verification of **GCash and PayMaya (Maya)** receipts
  * Full room and dormitory inventory management (Add, Edit, Delete)
  * AI interaction and security threat monitoring
  * **Room thumbnail image upload with secure validation**

---

### 👨‍💼 2. Staff Portal (Frontdesk Operations Server — Port 8082)
Tailored for frontdesk officers and receptionists to streamline day-to-day hotel operations.

* **Multi-Port URL:** `http://localhost:8082/`
* **Standard Login URL:** [`http://localhost/hotel/staff/login.php`](http://localhost/hotel/staff/login.php)
* **Access Rule:** 🔒 **Walang direct link patungong dashboard.** Required mag-login muna ang Frontdesk staff.
* **Login Email:** `staff@marveloushotel.com`
* **Password:** `password`
* **Account Provisioning:** Staff accounts are created **only by the Admin** via Admin Portal → Staff (`admin/staff.php`). Public self-registration (`auth/register-staff.php`) is permanently **closed** and redirects to this login page.
* **Strict Security Policy:** Staff members have **zero authority over accounts and credentials**. They cannot reset passwords, change user emails, modify room pricing, or alter system configurations.
* **Key Features:**
  * Fast Check-in and Check-out workflow
  * Live Room Status board (*Available*, *Occupied*, *Under Maintenance*)
  * Filter and search tools for incoming guests and queues
  * Quick accept or decline actions for pending booking requests

---

### 🌐👤 3. Guest & Public Portal (Customer Website, Catalog & Guest Hub — Port 8080)
Built for customers and guests wishing to explore accommodations, reserve rooms or dormitories, and manage their stays.

* **Multi-Port URL:** `http://localhost:8080/`
* **Main Website / Landing Page:** [`http://localhost/hotel/`](http://localhost/hotel/)
* **Rooms Catalog:** [`http://localhost/hotel/rooms.php`](http://localhost/hotel/rooms.php)
* **Guest Dashboard:** [`http://localhost/hotel/user/dashboard.php`](http://localhost/hotel/user/dashboard.php)
* **User Login Portal URL:** [`http://localhost/hotel/auth/login.php`](http://localhost/hotel/auth/login.php) *(o `http://localhost/hotel/user/login.php`)*
* **User Registration URL:** [`http://localhost/hotel/auth/register.php`](http://localhost/hotel/auth/register.php) *(o `http://localhost/hotel/user/register.php`)*
* **Email Verification:** [`http://localhost/hotel/auth/verify-email.php`](http://localhost/hotel/auth/verify-email.php)
* **Forgot Password:** [`http://localhost/hotel/auth/forgot-password.php`](http://localhost/hotel/auth/forgot-password.php)
* **Resend Verification:** [`http://localhost/hotel/auth/resend-verification.php`](http://localhost/hotel/auth/resend-verification.php)
* **About & Contact Pages:** [`http://localhost/hotel/about.php`](http://localhost/hotel/about.php) · [`http://localhost/hotel/contact.php`](http://localhost/hotel/contact.php)
* **Access Rule:** 🔒 **Unified Guest Experience:** Ang public website at guest portal ay iisang customer domain. Dito nagba-browse ng rooms, nagpaparehistro, at nagla-login ang mga bisita upang ma-access ang buong booking funnel, magsumite ng KYC valid ID, at magbayad via QR.
* **Test Account:** `lan@gmail.com` | `password` (Verified Guest)
* **Key Features:**
  * Live room and dormitory inventory showcase with dynamic pricing and stay duration calculator
  * Mandatory KYC Identity Verification requirement & valid government ID inspection before reservation
  * **Payment Channels:** **GCash and PayMaya (Maya) ONLY** with instant screenshot receipt uploader and reference number tracking
  * **Secure file upload validation** (MIME type check, 15MB limit, random filenames)
  * **Marvi AI Chatbot Assistant:** Interactive assistant answering questions on payment steps, booking procedures, check-in/out policies, and room recommendations
  * Booking voucher generator with exact activity timestamps (Date, Time, Year)
  * Star rating submission and guest feedback reviews
  * **Instant Account Verification & Activation:** Instant access upon registration on local/development servers; full SMTP token verification supported when SMTP is configured
  * **Password reset via email with secure token (1-hour expiry)**

---

## 🔑 System Accounts Summary & Access Control Policy

> [!IMPORTANT]
> **Authentication Policy:** Only **Users / Guests** have access to a public registration form (`auth/register.php`). **Super Admin** and **Staff (Frontdesk)** accounts utilize **Direct Login Only** via their dedicated login pages to enforce system security and prevent unauthorized elevated account creation.
>
> * **`auth/register-staff.php`** — permanently **closed**; every request redirects (302) to `staff/login.php`. Staff accounts are created by the Admin in `admin/staff.php`.
> * **`auth/setup-admin.php`** — **first-time bootstrap only** (runs while zero admin accounts exist). Once an admin exists it redirects (302) to `admin/login.php`.
> * **Logout routing** — `auth/logout.php` returns each role to its **own** portal login: admin → `/admin/login.php`, staff → `/staff/login.php`, user → `/auth/login.php`.

| Role | Login URL | Has Registration? | Provisioning Note |
|---|---|---|---|
| 👑 **Super Admin** | `http://localhost/hotel/admin/login.php` | ❌ **No** (Direct Login Only) | System Administrator |
| 👨‍💼 **Staff (Frontdesk)** | `http://localhost/hotel/staff/login.php` | ❌ **No** (Direct Login Only) | Created by Admin in `admin/staff.php` |
| 👤 **User / Guest** | `http://localhost/hotel/auth/login.php` | ✅ **Yes** (`auth/register.php`) | Self-registration by real guests |

---

## 🛡️ Security Features (v1.1.1+)

### Role-Isolated Portals & Logout Routing
- **Dedicated login "servers":** `/admin/login.php` (admin only), `/staff/login.php` (staff only), `auth/login.php` (universal for all users/guests)
- **Registration is user-only:** `auth/register.php` always creates `role = 'user'` accounts
- **Elevated self-registration permanently closed:** `auth/register-staff.php` and `auth/setup-admin.php` redirect to their portals (see Access Control Policy)
- **Role-aware logout:** `auth/logout.php` sends each role back to its own login portal

### Password Reset Flow
- **Forgot Password:** `/auth/forgot-password.php` — Enter email to receive reset link
- **Reset Password:** `/auth/reset-password.php?token=...` — Secure 32-byte token, 1-hour expiry, single-use
- Tokens stored in database with expiration; cleared after use

### Email Verification & Seamless Activation
- In environments without an active SMTP host configured (e.g. local XAMPP / development), new user accounts are automatically verified (`email_verified = 1`) immediately upon registration, allowing users to log in right away without delay.
- When an external SMTP host is configured in production, new registrations receive an email verification link with a secure 32-byte token.
- Unverified accounts are prevented from logging in when SMTP is active, with a clear error prompt and **Resend verification** available at `/auth/resend-verification.php`.

### Secure File Uploads
- **MIME type validation** via `finfo` (not just extension)
- **Allowed types:** JPEG, PNG, WebP, GIF, PDF
- **Size limit:** 15MB (configurable via `MAX_UPLOAD_SIZE`)
- **Randomized filenames:** 32-char hex + correct extension
- **Protected storage:** Files served via `serve-file.php` proxy (not direct web access)
- **Applied to:** Room thumbnails (admin), Payment proofs (user)

### Rate Limiting (Brute-Force Protection)
- **5 attempts per 15 minutes** per IP address
- Applied to all auth endpoints:
  - User login, register, forgot password, reset password, resend verification
  - Staff login
  - Admin login
- **Headers returned:** `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`
- **429 response** with JSON error when exceeded

### Structured Logging
- **Log levels:** DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
- **Output:** JSON lines to `storage/logs/app-YYYY-MM-DD.log` + database (`audit_logs`)
- **Auto-captures:** PHP errors, uncaught exceptions, fatal errors
- **Context:** User ID, IP, User-Agent, URL, method, trace ID

---

## 📁 Modular Asset Architecture

To prevent style clashes, runtime script errors, and cross-portal bugs, all assets are decoupled into role-specific files:

```text
hotel/
├── assets/
│   ├── css/                  <- Role-Dedicated Stylesheets
│   │   ├── base.css              <- Shared variables, typography, badges, buttons, flash alerts
│   │   ├── admin.css             <- Exclusive to the Admin portal
│   │   ├── staff.css             <- Exclusive to the Staff frontdesk portal
│   │   ├── user.css              <- Exclusive to the User/Guest portal
│   │   ├── auth.css              <- Login and registration forms
│   │   └── public.css            <- Landing page, hero section, and room showcase
│   │
│   └── js/                   <- Role-Dedicated Scripts
│       ├── core.js               <- Global flash notification dismisser & mobile sidebar drawer
│       ├── admin.js              <- Admin real-time table search & deletion confirmation prompts
│       ├── staff.js              <- Frontdesk guest search & status category filter
│       ├── user.js               <- Real-time booking price calculation & receipt preview
│       ├── public.js             <- Navbar scroll effects & room search filters
│       └── ai-chat.js            <- Chatbot client with auto-detecting API routing
│
├── includes/
│   ├── config.php              <- Central configuration (ENV, DB, paths, security)
│   ├── db.php                  <- PDO database connection & helpers
│   ├── session.php             <- Session management, CSRF, auth guards, flash messages
│   ├── functions.php           <- Shared helpers (formatting, validation, upload, audit)
│   ├── logger.php              <- Structured logging (JSON + DB, error handlers)
│   ├── rate-limiter.php        <- Rate limiting for auth endpoints
│   └── email.php               <- Email templates & sending (mail() or SMTP)
│
├── auth/                       <- User authentication pages
│   ├── login.php
│   ├── register.php
│   ├── register-staff.php      <- CLOSED (redirects to staff login)
│   ├── setup-admin.php         <- First-time admin bootstrap only
│   ├── forgot-password.php
│   ├── reset-password.php
│   ├── verify-email.php
│   ├── resend-verification.php
│   └── logout.php              <- Role-aware (returns to own portal login)
│
├── phpunit.xml                 <- PHPUnit configuration (project root)
│
├── serve-file.php              <- Secure file proxy (serves uploads from protected path)
│
├── tests/                      <- PHPUnit test suite
│   ├── bootstrap.php
│   └── Unit/
│       ├── FunctionsTest.php
│       └── SessionAndSecurityTest.php
│
├── storage/
│   └── logs/                   <- Application logs (auto-created)
│
└── uploads/                    <- Uploaded files (rooms/, proofs/)
```

---

## 🤖 AI Module Features

* **Marvi AI Chatbot (`ai/chatbot.php`, `ai/nvidia-client.php`, `assets/js/ai-chat.js`, `includes/components/ai-chatbot.php`):** Live conversational AI powered by **NVIDIA NIM (`google/diffusiongemma-26b-a4b-it`)** with intelligent fallback to local bilingual FAQs:
  * **Automatic Bilingual Language Detection:** The AI automatically detects the user's language—when a guest asks in **English**, Marvi responds 100% in polite, professional English; when a guest asks in **Tagalog / Filipino**, Marvi responds in natural, warm Tagalog/Taglish with polite markers ("po" and "opo").
  * **Payment Inquiries:** Explains step-by-step how to pay using **GCash and PayMaya (Maya) ONLY** (official accounts, copying reference numbers, and uploading screenshots).
  * **Booking Inquiries:** Guides guests through the step-by-step reservation workflow (selecting dates, confirming rooms, and uploading receipts).
  * **Hotel Schedule & Policies:** Instant answers for Check-in (2:00 PM) and Check-out (12:00 PM / Tanghali) policies.
  * **Budget & Dormitory Recommendations:** Real-time database query recommending budget dorm beds (₱350/night) or private rooms according to the guest's budget.
* **Security & Exploits Filter:** Automatically intercepts malicious payloads (such as SQL injection patterns or script tags), flagging the incident in `ai_logs` and `audit_logs` as a security alert for Admin review.
* **AI Room Recommender (`user/ai-recommend.php`):** Matches guests with suitable room accommodations according to their stated budget and party size.
* **Admin Monitoring & Status Banner (`admin/ai-config.php`):** Displays live connection status, active model ID, and full transcript logs with flagged threat indicators.

---

## 🧪 Testing

### Unit Tests (PHPUnit 10)
```bash
cd /opt/lampp/htdocs/hotel
php phpunit.phar --bootstrap tests/bootstrap.php          # Run all tests
php phpunit.phar --bootstrap tests/bootstrap.php --testdox # Verbose output
```

**Test Coverage:**
- **FunctionsTest** — Currency, dates, booking codes, badges, stars, truncation, weekends, JSON
- **SessionTest** — CSRF tokens, login state, role checks, current user
- **RateLimiterTest** — Attempt tracking, per-IP/action limits, client IP detection
- **DatabaseTest** — CRUD operations, row/rows fetching, settings

---

## 📦 Production Deployment Checklist

- [ ] Set `ENVIRONMENT` to `'production'` in `includes/config.php`
- [ ] Configure SMTP settings in database `settings` table (`smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`, `mail_from_email`, `mail_from_name`)
- [ ] Enable HTTPS and set `session.cookie_secure = true` in `includes/session.php`
- [ ] Set strong `HASH_COST` (12+) in `includes/config.php`
- [x] Removed demo user account; system uses real user registrations only
- [ ] Configure backup for database and `uploads/` directory
- [ ] Set up log rotation for `storage/logs/`
- [ ] Run security scan: `php phpunit.phar --bootstrap tests/bootstrap.php`

---

## 📋 Changelog

### v1.1.7 (Unified Guest & Public Portal & Capstone Tunnel Optimization — 2026-11-06)
- ✅ **Unified Guest & Public Portal Architecture** — Pagsasama ng Public Hotel Website at Guest / User Portal sa iisang cohesive 3-actor model (Guest & Public Portal, Staff Portal, Super Admin Portal) sa console dashboard (`share-capstone.sh`), navigation bar, at system documentation (`README.md`, `architecture.md`).
- ✅ **Seamless Cross-Navigation** — Nagdagdag ng direct links sa Browse Rooms (`rooms.php`) at Hotel Website (`index.php`) direkta sa sidebar ng Guest Portal (`includes/components/sidebar.php`).
- ✅ **Presentation Console Synchronization** — Inayos ang `share-capstone.sh` upang 3 malinaw na actor portals ang makita ng mga panelist sa live demonstration na may kumpletong test account (`lan@gmail.com` / `password`).

### v1.1.6 (Strict Multi-Server Session Isolation & Auto-Admin Conflict Resolution — 2026-10-03)
- ✅ **Dynamic Session Isolation Engine** — Introduced `getActivePortal()` in `includes/config.php` dynamically assigning isolated session cookies (`MH_USER_SESS`, `MH_ADMIN_SESS`, `MH_STAFF_SESS`) per portal/server.
- ✅ **Auto-Admin Collision Fix** — Fixed bug where logging in as Admin caused the user website and `/auth/login.php` to auto-redirect exclusively to the admin dashboard. Admin and User accounts can now be simultaneously logged in within the same browser without interference.
- ✅ **Server-Aware Routing Constants** — Added `USER_SITE_URL`, `ADMIN_SITE_URL`, and `STAFF_SITE_URL` with dynamic port, subdomain, and folder resolution in `includes/config.php`.
- ✅ **Decoupled Auth Guards** — Upgraded `requireRole()`, `redirectIfLoggedIn()`, and `getDashboardUrl()` in `includes/session.php` to respect portal server boundaries.
- ✅ **Cross-Server Role Notifications** — Updated `auth/login.php` to gracefully direct staff and admin attempts to their exact server URLs.
- ✅ **Enhanced Apache VHost Aliases** — Updated `apache-vhosts-setup.conf` with shared aliases for `/ai`, `/serve-file.php`, `/auth`, `/assets`, and `/uploads` ensuring all features operate flawlessly across independent listening ports (8080, 8081, 8082).
- ✅ **Interactive Bilingual AI Chatbot Widget** — Created reusable component `includes/components/ai-chatbot.php` embedded across all public pages and user dashboards with quick-prompt chips.
- ✅ **Automatic Language Detection (English vs Tagalog)** — Implemented `detectMessageLanguage()` in `ai/nvidia-client.php` and `ai/chatbot.php`. When a user types in English, Marvi replies 100% in English; when typing in Tagalog, Marvi replies in natural Tagalog/Taglish with polite Filipino markers ("po" and "opo"). Bilingual fallback FAQs added in `ai/training-data/faq.json`.

### v1.1.5 (User ID Verification & KYC System — 2026-10-02)
- ✅ **KYC Verification Lifecycle** — Replaced instant self-verification with an administrative review state machine (`unverified` → `pending` → `verified` / `rejected`).
- ✅ **Government ID PII RBAC Protection** — Patched `serve-file.php` with strict access control restricting `documents/` exclusively to the document owner, Super Admin, and Frontdesk Staff.
- ✅ **Admin KYC Review & Inspection Modal** — Integrated filter tabs (`All Guests`, `Pending Review` with dynamic count badge, `Verified`, `Rejected`), side-by-side legal identity inspection modal, document preview (JPG/PNG/PDF), and one-click Approval or Rejection with preset reason feedback.
- ✅ **Guest Profile State-Aware UX** — Added real-time status cards on `user/profile.php` indicating Pending review, Verified identity badge, and specific Rejection reasons with instant re-upload guidance.
- ✅ **English Booking Guard** — Standardized `user/booking.php` verification prompt into clear English with lifecycle-aware status feedback.
- ✅ **Frontdesk Desk Inspection Awareness** — Equipped `staff/guests.php` and `staff/check-in.php` with visual KYC badges and an alert prompt for unverified guests to present a physical government ID upon arrival.
- ✅ **Unit Testing Suite** — Added PHPUnit test suite in `tests/` verifying core functions, name validation, and KYC badges (9 tests, 33 assertions, 100% passing).

### v1.1.4 (Name Segmentation, Suffix Dropdown & Seamless Activation — 2026-09-30)
- ✅ **First Name, Middle Name, Last Name, & Suffix Dropdown** — Separated fields on `auth/register.php`; Suffix is optional with a clean dropdown defaulted to `None (Walang Suffix)` alongside `Jr.`, `Sr.`, `II`, `III`, `IV`, `V`; Middle Name is optional; First & Last Name are required with 2–50 char limits.
- ✅ **Live Full-Name Uniqueness Verification** — Real-time debounced verification via `auth/check-name.php` and `validatePersonName()` prevents duplicate legal names across the platform.
- ✅ **Auto-Verification for Seamless Immediate Login** — In environments without an active external SMTP mail server (e.g. local XAMPP / development), new user registrations are automatically verified (`email_verified = 1`) so guests can immediately log in without getting blocked.
- ✅ **Total Elimination of Demo User Accounts** — Purged `user@demo.com` and all demo credential hints from database seeds, `welcome.php`, login pages, and documentation to transition the system to 100% genuine user accounts.
- ✅ **Unified Full Name Rendering** — Integrated `formatFullName()` across `auth/login.php`, `admin/users.php`, and `user/profile.php` to accurately display the complete name format.

### v1.1.3 (Multi-Server Architecture, Admin Master Password Control & GCash/Maya ONLY AI — 2026-09-29)
- ✅ **Multi-Server Port Architecture** — Dedicated servers for Port 8080 (User/Public), Port 8081 (Admin Portal), and Port 8082 (Staff Frontdesk) via `apache-vhosts-setup.conf`.
- ✅ **Dynamic Multi-Host SITE_URL** — `includes/config.php` dynamically auto-detects host, port, and subdomains for seamless multi-server execution without cross-origin collisions.
- ✅ **Admin Master Password Reset** — Admin holds full master authority over all accounts with direct password reset modals & random password generator tools in `admin/users.php` and `admin/staff.php`.
- ✅ **Strict Staff Operations Isolation** — Frontdesk staff restricted strictly to Check-in/out and room cleanliness updates; zero access to modify passwords or create accounts.
- ✅ **GCash & PayMaya (Maya) ONLY Payment System** — Phased out bank transfer and over-the-counter options; `user/payment.php`, `assets/xml/hotel_config.xml`, and settings database updated for GCash and PayMaya only.
- ✅ **Maya & InstaPay QR Interoperable Scanner** — Official Maya QR code for **WILLY GAILO (`@willygailojr`)** integrated with InstaPay cross-app QR support (scannable via Maya, GCash Scan QR, and Philippine banks), interactive channel tabs, quick copy buttons, modal zoom lightbox, and receipt image drag-and-drop live preview.
- ✅ **First Name, Middle Name, Last Name, & Suffix Registration** — Formally separated fields on `auth/register.php` with 2–50 character limits, allowed letters/hyphens/accents, optional middle name & suffix datalist (Jr., Sr., II, III, IV, etc.), live client-side duplicate verification via `auth/check-name.php`, and unique full-name constraint enforcement via `validatePersonName()`.
- ✅ **Exclusive Admin Staff Provisioning & Portal Workflow** — Staff self-registration is closed; accounts are created exclusively by the Administrator via `admin/staff.php` with auto-generation tools; created staff log in at `staff/login.php` (Port 8082) to access their operational frontdesk dashboard.
- ✅ **Staff Operations AI Co-Pilot (Role 5)** — Integrated into `staff/dashboard.php` via `ai/staff-assistant.php` with live shift operational briefings (today's arrivals, departures, room cleaning priority, and pending queues) in Taglish.
- ✅ **Admin Executive AI Insights & Receipt Inspector (Role 6)** — Integrated into `admin/dashboard.php` and `admin/payments.php` via `ai/admin-insights.php` with strategic revenue digests, Maya vs GCash channel breakdowns, occupancy analytics, and a side-by-side receipt verification modal.
- ✅ **Marvi AI FAQ Knowledge Expansion** — AI trained in Tagalog and English to guide guests on paying via GCash/PayMaya only, booking procedures, check-in/out times, and budget room recommendations.
- ✅ **NVIDIA NIM Live AI Engine Integration** — Powered by `google/diffusiongemma-26b-a4b-it` via `ai/nvidia-client.php` with real-time natural language answers in Filipino/English, dual cURL + stream context transports, and seamless local FAQ fallback.

### v1.1.2 (Security Audit Fixes & Portal Cleanup — 2026-09-28)
- ✅ **Global CSRF guard** — lahat ng POST ay sumusubok ng token (403 kung wala); booking delete GET→POST
- ✅ **Session fixation** — `session_regenerate_id(true)` sa lahat ng login/reset flows
- ✅ **Booking race condition** — transaction + `FOR UPDATE` sa `user/booking.php`
- ✅ **Exposure locks** — `.phar`, `tests/`, `uploads/` direct HTTP = 403; `serve-file.php` auth para sa payment proofs (owner/admin/staff lang)
- ✅ **Production by default** — `ENVIRONMENT=production` (i-override via `MH_ENV`), `display_errors off`, errors → `logs/php-errors.log`
- ✅ **Security headers** — `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`
- ✅ **Password minimum 8 chars** sa lahat ng flows (kasama ang `admin/staff.php`)
- ✅ **Contact form totoo na** — validated INSERT sa bagong `contact_messages` table + best-effort email notify
- ✅ **Shared sidebar component** — `includes/components/sidebar.php` (20 pages, isang source ng katotohanan; naayos ang dead link sa staff dashboard)
- ✅ **Migration system** — `migrate.php` CLI + `migrations/` (naka-track sa `schema_migrations`)
- ✅ **Custom error pages** — branded `404.php` / `500.php` via `ErrorDocument`
- ✅ **Public site layout fixes** — navbar/room-card grids/hero overlay, lahat ng placeholder images ay may laman
- ✅ **Main website access gate** — guest → `auth/welcome.php` (Register / Login) bago makapasok sa index/rooms/about/contact; pagkatapos ng user login/register → main website (`index.php`), may `redirect` passthrough balik sa pinuntang page
- ✅ **Walang direct dashboard access** — lahat ng portal pages ay naka-`requireRole`; guest → `auth/login.php` (admin/staff: login only; user: register o login)
- ✅ **100% English website** — isinalin ang 500+ strings sa lahat ng pages (auth, public, admin, staff, user, sidebar, JS/chatbot, emails, flash/validation messages) + nilinis ang luma sa DB (notifications, ai_logs) — rendered-content sweep: 0 Tagalog
- ✅ Tests/scratch files na naka-delete; phpunit baseline (29 tests) at smoke (27/27) maintained

### v1.1.1 (Auth Portal Hardening — 2026-09-28)
- ✅ Public staff/admin self-registration closed (`register-staff.php`, `setup-admin.php` redirect to their portals; setup-admin.php remains first-time bootstrap only)
- ✅ Role-aware logout — each role returns to its own login portal
- ✅ Fixed logger fatal error (`MH_LOG_*` constants) that broke all login/logout redirects
- ✅ Removed leftover debug output from `config.php` / `db.php`
- ✅ Demo account passwords fixed — live DB and seed SQL now truly use `password`
- ✅ README corrections (`phpunit.xml` path, portal policy, logout routing)

### v1.1 (Security Hardening)
- ✅ Password reset flow (forgot/reset with secure tokens)
- ✅ Email verification on registration + resend option
- ✅ Secure file upload validation (MIME, size, random names, proxy serving)
- ✅ Rate limiting on all auth endpoints (5 attempts/15min/IP)
- ✅ Structured logging (JSON + DB, error/exception handlers)
- ✅ PHPUnit test suite (29 tests)

### v1.0 (Initial Release)
- ✅ Multi-portal architecture (Admin, Staff, User, Public)
- ✅ Role-based authentication with dedicated login pages
- ✅ Room/Dormitory CRUD management
- ✅ Booking workflow with dynamic pricing
- ✅ Payment receipt upload & verification
- ✅ AI Chatbot (Marvi) with security filtering
- ✅ AI Room Recommender
- ✅ XML-driven configuration
- ✅ Modular CSS/JS per portal
- ✅ Audit logging

---

## 📄 License

MIT License — Feel free to use, modify, and distribute.

---

*Last Updated: 2026-11-06 | Marvelous Hotel & Dormitory Booking System v1.1.7*