# Marvelous Hotel — 3-Tier System Architecture & Directory Structure

Ipinapaliwanag ng dokumentong ito ang malinis na **Framework-Powered 3-Tier Architecture** (`frontend/`, `backend/`, `database/`) ng **Marvelous Hotel & Dormitory Booking System**.

---

## 1. Technology Stack & Frameworks by Tier

| Tier | Root Folder | Languages | Frameworks / Libraries |
| :--- | :--- | :--- | :--- |
| **Tier 1: Frontend** | `frontend/` | HTML5, CSS3, JavaScript (ES6+) | **Tailwind CSS v3** (`preflight: false`), **Alpine.js 3**, Font Awesome 6, `HotelAPI` (`fetch`) |
| **Tier 2: Backend** | `backend/` | PHP 8 (JSON & SSE) | **Slim 4 Framework** (`slim/slim`, `slim/psr7`), **PSR-4 / PSR-15**, PHPMailer, NVIDIA NIM AI |
| **Tier 3: Database** | `database/` | SQL (MySQL / MariaDB) | **PDO** (Prepared Statements), 17 Versioned SQL Migrations (`database/migrate.php`) |
| **Testing / QA** | `tests/` | PHP 8 | **PHPUnit 10** (Unit + Slim 4 PSR-7 in-process route & middleware tests) |

---

## 2. Directory Tree Overview

```text
/opt/lampp/htdocs/hotel/
├── composer.json                   # Slim 4, PHPMailer, PSR-4 MarvelousHotel\ -> backend/src/
├── phpunit.xml                     # PHPUnit 10 test runner configuration
│
├── frontend/                       # 🎨 TIER 1: ALL FRONTEND FILES
│   ├── README.md                   # Frontend framework & HotelAPI docs
│   ├── package.json                # Tailwind CSS & Alpine.js build scripts
│   ├── tailwind.config.js          # Custom Navy/Gold theme tokens (preflight: false)
│   ├── src/tailwind-input.css      # Tailwind source entry
│   ├── pages/                      # Public pages (index, rooms, about, contact, 404, 500)
│   ├── auth/                       # Auth UI pages (login, register, forgot/reset-password, verify-email, welcome)
│   ├── user/                       # Guest Portal UI pages (dashboard, booking, payment, profile, reviews, ...)
│   ├── staff/                      # Frontdesk Staff Portal UI pages (login, dashboard, check-in, payments, rooms-status)
│   ├── admin/                      # Super Admin Portal UI pages (login, dashboard, bookings, payments, rooms, users, ai-config, ...)
│   ├── assets/
│   │   ├── css/                    # tailwind.css + base, public, auth, user, staff, admin, ai-chat
│   │   ├── js/                     # api-client.js (HotelAPI), core, ai-chat (SSE + Voice), portals
│   │   ├── vendor/                 # alpine.min.js (offline-ready Alpine.js 3)
│   │   └── images/                 # Hotel photos, room thumbnails, avatars, Maya/GCash QR codes
│   └── components/                 # Reusable UI Components
│       ├── frontend-frameworks.php # Tailwind + Alpine.js + HotelAPI loader
│       ├── sidebar.php             # Multi-portal sidebar navigation
│       ├── central-nav.php         # Unified top/drawer navigation
│       ├── ai-chatbot.php          # Marvi Live AI floating widget (SSE + Voice)
│       └── reset-status-modal.php  # Real-time 1-time password reset popup
│
├── backend/                        # ⚙️ TIER 2: ALL BACKEND FILES
│   ├── README.md                   # Backend Services & Slim 4 REST API v1 catalog
│   ├── bootstrap.php               # Central backend bootstrap (Composer + core)
│   ├── core/                       # Core libraries (.htaccess Deny from all)
│   │   ├── config.php              # Environment, DB, 3-Tier paths, Isolated Sessions
│   │   ├── db.php                  # PDO connection & prepared statement helpers
│   │   ├── session.php             # Role guards, CSRF protection, Remember Me
│   │   ├── functions.php           # Shared domain, KYC, AI, & reset helpers
│   │   ├── logger.php              # Structured file & DB audit logging
│   │   ├── rate-limiter.php        # Brute-force & AI quota throttling
│   │   └── email.php               # Gmail SMTP / PHPMailer dispatcher
│   ├── src/                        # PSR-4 Namespace: MarvelousHotel\ (.htaccess Deny from all)
│   │   ├── ApiAppFactory.php       # Slim 4 application & route definitions
│   │   ├── JsonResponder.php       # Standard JSON response envelope
│   │   ├── Middleware/             # JsonBodyParser, RoleAuth, CsrfProtection (PSR-15)
│   │   ├── Controllers/            # Health, Room, Booking, Payment, Auth, Dashboard
│   │   └── Services/               # RoomService, BookingService, PaymentService, AuthService, DashboardService
│   ├── ai/                         # Real-Time NVIDIA NIM AI Engine
│   │   ├── nvidia-client.php       # Multi-turn SSE streamer + auto-fallback models
│   │   ├── chatbot.php             # Streaming guest concierge + action resolver
│   │   ├── staff-assistant.php     # Frontdesk shift operational briefing
│   │   ├── admin-insights.php      # Executive BI analytics digest
│   │   └── training-data/          # Bilingual FAQs & bad-intent security guardrails
│   ├── endpoints/                  # Non-HTML Backend Endpoints
│   │   ├── serve-file.php          # Role-protected uploads proxy
│   │   ├── check-name.php          # Live unique name verification JSON
│   │   ├── check-reset-status.php  # Live password reset polling JSON
│   │   └── logout.php              # Role-aware session destroyer
│   └── api/                        # 🌐 Slim 4 REST API Front Controller (/backend/api/v1/*)
│       ├── .htaccess               # Clean REST URL rewriting
│       └── index.php               # Slim 4 entrypoint
│
├── database/                       # 🗄️ TIER 3: ALL DATABASE FILES
│   ├── .htaccess                   # Deny direct HTTP access
│   ├── README.md                   # Database import & migration guide
│   ├── ERD.md                      # Mermaid ERD + 14-table Data Dictionary
│   ├── marvelous_hotel.sql         # Master schema & initial seed data
│   ├── migrate.php                 # Canonical CLI migration runner
│   └── migrations/                 # 17 incremental SQL migrations (0001..0017)
│
├── .htaccess                       # 🔀 Clean Apache Internal Router (maps /, /rooms.php, /user/*, /staff/*, /admin/*, /auth/*, /ai/*, /serve-file.php)
├── config/                         # 🛠️ Multi-port Apache VirtualHost configs
├── scripts/                        # 💻 share-capstone.sh (+ root symlink)
├── tests/                          # 🧪 PHPUnit 10 Automated Test Suite
├── docs/                           # 📚 Architecture, syntax standards, & defense manuals
└── uploads/ storage/ logs/         # 📁 Protected runtime writable directories
```

---

## 3. Core Architectural Principles

1. **True 3-Tier Separation (`frontend/` ↔ `backend/` ↔ `database/`):**
   * Presentation frameworks (**Tailwind CSS + Alpine.js**) and API client (`HotelAPI`) live cleanly inside `frontend/`.
   * Application routing (**Slim 4**), PSR-15 security middleware, domain services, core infrastructure, and real-time AI live inside `backend/`.
   * Schema, migrations, ERD, and CLI runner live inside `database/` and are strictly blocked from HTTP access.
2. **3-Actor Isolated Session Design:**
   * Separate cookies (`MH_USER_SESS`, `MH_STAFF_SESS`, `MH_ADMIN_SESS`) allow concurrent Admin, Staff, and Guest sessions in the same browser with zero session collisions — including across shared `/backend/api/v1/*` routes via `X-Portal` / `Referer` detection.
3. **Security & Zero Direct Exposure:**
   * `backend/core/`, `backend/src/`, `database/`, and `uploads/` are all protected by `.htaccess`; user IDs and payment receipts are served exclusively through role-verified [`serve-file.php`](file:///opt/lampp/htdocs/hotel/serve-file.php).
