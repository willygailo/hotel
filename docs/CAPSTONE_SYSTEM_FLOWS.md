# Marvelous Hotel & Dormitory — Complete System Flows & Tech Stack (Capstone Defense Master Guide)

> **Edition:** November 20, 2026 (Framework-Powered 3-Tier Architecture — Defense Ready)  
> **Project:** Marvelous Hotel & Dormitory Booking and Reservation Management System with Marvi Live AI  
> **Architecture:** Clean 3-Tier Separation (`frontend/` ↔ `backend/` ↔ `database/`)  
> **Automated QA Status:** PHPUnit 10 — **32/32 Tests Passed (185 Assertions)**, 0 Lint Errors  

---

## 1. Kumpletong Tech Stack: Languages, Frameworks & Architecture

Ito ang opisyal na breakdown ng lahat ng **Languages**, **Frameworks**, **Libraries**, at **Tools** na ginamit sa bawat tier ng proyekto—handa para sa *Methodology / System Architecture* slide at *Panel Q&A*.

| Tier / Layer | Root Folder | Languages Used | Frameworks & Core Libraries | Purpose & Defense Justification (*Bakit Ito Pinili?*) |
| :--- | :--- | :--- | :--- | :--- |
| **Tier 1: Frontend (Presentation Layer)** | `frontend/` | **HTML5**, **CSS3**, **JavaScript (ES6+)** | **Tailwind CSS v3.4** (`preflight: false`, custom Navy/Gold theme), **Alpine.js v3.14** (offline local bundle), **Font Awesome 6**, **`HotelAPI`** (`fetch` REST client), **Web Speech API** (Mic STT & TTS) | Malinis at responsive na UI nang walang mabigat na SPA overhead; reactive live room availability widgets, token-by-token SSE AI chat streaming, Voice Mode, at live password reset popup. |
| **Tier 2: Backend (Application & REST API)** | `backend/` | **PHP 8** (JSON & Server-Sent Events / SSE) | **Slim 4 Framework** (`slim/slim 4.15`, `slim/psr7`, `FastRoute`), **PSR-4 Autoloading** (`MarvelousHotel\`), **PSR-15 Middleware** (`JsonBodyParser`, `RoleAuth`, `CsrfProtection`), **Composer**, **PHPMailer 7** (Gmail SMTP) | Standard REST API (`/backend/api/v1/*`), malinis na Controller → Service separation, 3-actor isolated sessions, CSRF & RBAC middleware protection, at automated Gmail SMTP notifications. |
| **AI Intelligence Engine** | `backend/ai/` | **PHP 8**, **JSON**, **SSE Stream** | **NVIDIA NIM API** — Primary: `nvidia/nemotron-3.5-bolt-e4b` (~0.8s latency), Auto-Failover: `nemotron-3.5-lightning-30b-a3b` & `minimax-m3.1-flash`, Local Bilingual Rule Engine | Real-time streaming bilingual concierge (Tagalog/English/Taglish), multi-turn memory, interactive Room Cards & navigation buttons, plus Staff Shift Co-Pilot, Admin Executive BI, at 0% downtime offline fallback. |
| **Tier 3: Database (Data Persistence)** | `database/` | **SQL** | **MySQL / MariaDB** (**InnoDB**, `utf8mb4_unicode_ci`), **PHP PDO** (Prepared Statements & Row Locking `SELECT ... FOR UPDATE`), **14 Normalized Tables**, **17 Versioned SQL Migrations** (`database/migrate.php`) | ACID-compliant booking & payment transactions, 100% SQL injection prevention via prepared statements, date-overlap double-booking protection, at reproducible schema migration history. |
| **Routing, Security, QA & Deployment** | `tests/`, `scripts/`, `.htaccess` | **PHP 8**, **Bash**, **Apache Conf** | **PHPUnit 10** (32 tests / 185 assertions), **Apache 2.4** (`mod_rewrite` / `.htaccess`), **XAMPP / Docker**, **Cloudflare & Ngrok Tunnel** (`share-capstone.sh`) | Clean URLs habang naka-HTTP 403 block ang internal folders (`backend/core/`, `backend/src/`, `database/`, `uploads/`), automated regression testing, at instant live mobile demo sa defense. |

---

## 2. Overall 3-Tier Clean Architecture & Request Routing Flow

Zero loose `.php` files ang nasa root directory. Ang root `.htaccess` ang nagsisilbing malinis na internal router na nagre-route ng public, portal, API, at AI requests papunta sa tamang tier habang hinaharang ang direct access sa sensitive internal files.

```mermaid
flowchart LR
    subgraph CLIENT["Browser / Mobile Client"]
        USR["Guest / Staff / Admin Browser"]
    end

    subgraph ROUTER["Apache 2.4 Clean Internal Router (.htaccess)"]
        R1["Public & Portals -> frontend/pages, auth, user, staff, admin"]
        R2["REST API /backend/api/v1/* -> backend/api/index.php"]
        R3["AI & Utility /ai/*, /serve-file.php -> backend/ai, backend/endpoints"]
        R4["HTTP 403 Block -> backend/core, backend/src, database, uploads"]
    end

    subgraph T1["Tier 1: Frontend (frontend/)"]
        FE1["HTML5 + CSS3 + Tailwind CSS v3"]
        FE2["Alpine.js 3 + HotelAPI (api-client.js)"]
        FE3["Marvi Live Widget (ai-chat.js SSE + Voice)"]
        FE4["Live Reset Modal (reset-status-modal.php)"]
    end

    subgraph T2["Tier 2: Backend (backend/)"]
        SLIM["Slim 4 App (ApiAppFactory.php)"]
        MW["PSR-15 Middleware: JsonBodyParser -> RoleAuth -> CsrfProtection"]
        CTRL["Controllers (6) -> Services (5) (PSR-4 MarvelousHotel)"]
        CORE["Core Services: Isolated Sessions, CSRF, RateLimiter, PHPMailer"]
        AIE["Marvi AI Engine: nvidia-client.php, chatbot, staff-assistant, admin-insights"]
        PROXY["Protected File Proxy (serve-file.php)"]
    end

    subgraph T3["Tier 3: Database (database/)"]
        MYSQL["MySQL / MariaDB InnoDB (14 Tables, 17 Migrations, PDO)"]
        STORE["Private Uploads Storage (KYC IDs & Payment Receipts)"]
    end

    USR --> R1
    USR --> R2
    USR --> R3
    R1 --> FE1
    FE1 --> FE2
    FE1 --> FE3
    FE1 --> FE4
    FE2 --> R2
    FE3 --> R3
    FE4 --> R3
    R2 --> SLIM --> MW --> CTRL --> MYSQL
    R1 --> CORE --> MYSQL
    R3 --> AIE --> MYSQL
    R3 --> PROXY --> STORE
```

### Isolated 3-Actor Session Design
Upang maiwasan ang session overwrite o privilege bleed kapag sabay-sabay na naka-login ang Guest, Staff, at Admin sa iisang browser (napakahalaga sa live capstone demo!):
- **Guest / Public:** `MH_USER_SESS` cookie
- **Frontdesk Staff:** `MH_STAFF_SESS` cookie
- **Super Admin:** `MH_ADMIN_SESS` cookie
- **Shared Slim 4 API (`/backend/api/v1/*`):** Gumagamit ng `X-Portal` header / `Referer` detection para laging tamang session cookie ang i-load sa bawat API call.

---

## 3. Complete AI Flow (Marvi Live AI — Tri-Role Architecture)

Ang **Marvi AI** ay hindi simpleng static FAQ chatbot. Isa itong **Tri-Role Real-Time AI Ecosystem** na may **Server-Sent Events (SSE) token-by-token streaming**, **Voice Mode (Mic + TTS)**, **multi-turn memory**, **live database awareness**, **security guardrails**, at **automatic model failover**.

### 3.1 Guest Concierge Real-Time Streaming & Voice Pipeline (`POST /ai/chatbot.php?stream=1`)

```mermaid
flowchart TD
    A["Guest opens Marvi Widget (Text Input or Web Speech Mic PH/EN)"] --> B["ai-chat.js sends POST /ai/chatbot.php?stream=1"]
    B --> C{"Rate Limit OK? (rate_limits table)"}
    C -- "No (Too many requests)" --> C1["Return 429 Cooldown Notice"]
    C -- "Yes" --> D{"Bad-Intent Security Guard (bad-intent-keywords.json)"}
    D -- "SQLi / XSS / Port Scan / Prompt Injection" --> D1["Block Request + Log flagged=1 to ai_logs + Safe Refusal"]
    D -- "Clean Message" --> E["Auto-Detect Language (Tagalog / English / Taglish)"]
    E --> F["Assemble Live Context: Active Rooms + IDs + Logged-in Guest Bookings & KYC + 12-Turn Session History"]
    F --> G{"NVIDIA NIM Primary Model: nvidia/nemotron-3.5-bolt-e4b"}
    G -- "Stream OK (~0.8s)" --> H["Stream SSE Tokens Live to Browser (with Stop Button)"]
    G -- "Timeout / 410 / 5xx" --> G2{"Auto-Failover Chain: nemotron-3.5-lightning-30b-a3b -> minimax-m3.1-flash"}
    G2 -- "Fallback Stream OK" --> H
    G2 -- "All Cloud Models Offline" --> G3["Local Bilingual FAQ & Live Room Rule Engine (0% Downtime)"]
    G3 --> H
    H --> I["Output Sanitizer (Hide ports, internal paths, secrets)"]
    I --> J["Server-Verified Action Parser: [[BOOK_ROOM:id]] & [[GOTO:route]] + Contextual Follow-up Detector"]
    J --> K["Render Streaming Bubble + Clickable Room Card + Take Me There Button"]
    K --> L["Save Turn to Session History & ai_logs Table"]
    L --> M{"Voice Reply Enabled or Listen Clicked?"}
    M -- "Yes" --> M1["Browser Web Speech Synthesis (TTS Tagalog/English)"]
    M -- "No" --> N["Ready for Next Turn (Persisted via sessionStorage across pages)"]
```

### 3.2 Tri-Role AI Modules Overview

| AI Module | Endpoint / File | Target Actor | Live Database Context Injected | Output Delivered |
| :--- | :--- | :--- | :--- | :--- |
| **Marvi Live Concierge** | `backend/ai/chatbot.php` + `nvidia-client.php` | Public & Guest (all 10+ guest pages) | Live room catalog with IDs/prices, guest's own booking status & KYC status, 12-turn memory | Token-by-token SSE reply, clickable **Room Cards** (`[[BOOK_ROOM:id]]`), **Take Me There →** buttons (`[[GOTO:route]]`), Voice Mic & TTS |
| **Smart Room Matcher** | `frontend/user/ai-recommend.php` | Guest | Budget range, guest count, room type preference, live room inventory & ratings | Ranked Top Room / Dormitory Matches with match score and direct 1-click Book button |
| **Staff Shift Co-Pilot** | `backend/ai/staff-assistant.php` | Frontdesk Staff | Today's check-ins, check-outs, pending bookings/payments, `dirty` & `maintenance` rooms | Actionable shift briefing & prioritized frontdesk checklist |
| **Admin Executive BI** | `backend/ai/admin-insights.php` | Super Admin | Total & monthly verified revenue, Maya vs GCash ratio, occupancy %, KYC backlog, flagged AI threats | Executive business insights, pricing/promo recommendations, security summary |

---

## 4. Complete User / Guest Flow (`MH_USER_SESS`)

Saklaw nito ang kumpletong paglalakbay ng bisita—mula pag-browse, real-time room availability check, account registration, live password reset popup, KYC ID verification, booking, Maya/GCash payment, hanggang post-stay review.

```mermaid
flowchart TD
    U1["Public Home (/) & Rooms Catalog (/rooms.php)"] --> U2["Alpine.js + Slim 4 Live Availability Check (GET /backend/api/v1/availability)"]
    U2 --> U3{"Has Verified Account?"}
    U3 -- "New Guest" --> U4["Register (/auth/register.php) + Live Unique Name Check (check-name.php)"]
    U4 --> U5["Email Verification via Gmail SMTP (/auth/verify-email.php)"]
    U3 -- "Forgot Password" --> FP["Forgot Password (/auth/forgot-password.php) -> Live 3s Popup Modal"]
    U3 -- "Existing Guest" --> U6["Guest Login (/auth/login.php) -> MH_USER_SESS + Brute-Force Rate Limit"]
    U5 --> U6
    FP --> U6
    U6 --> U7["Guest Dashboard (/user/dashboard.php) + Marvi Live AI Concierge"]
    U7 --> U8{"KYC Identity Status (profile_verified)?"}
    U8 -- "unverified / rejected" --> U9["Upload Govt or Student ID (/user/profile.php) -> Stored in Private uploads/"]
    U9 --> U10["Status = pending (Awaiting Super Admin KYC Approval)"]
    U10 --> U8
    U8 -- "verified" --> U11["Select Room & Dates (/user/booking.php) + Live Slim 4 Availability Check"]
    U11 --> U12{"Atomic Date-Overlap Check [check_in, check_out) OK?"}
    U12 -- "Conflict Found" --> U11
    U12 -- "Available" --> U13["Booking Created (status = pending, unique booking_code)"]
    U13 --> U14["Payment Portal (/user/payment.php): Scan Maya or GCash QR + Upload Receipt & Ref No"]
    U14 --> U15["Payment Submitted (status = pending -> Staff/Admin Verification)"]
    U15 --> U16{"Payment Decision"}
    U16 -- "Rejected" --> U14
    U16 -- "Verified" --> U17["Booking Confirmed + Official Printable Voucher (/user/booking-detail.php)"]
    U17 --> U18["Frontdesk Check-In -> Stay -> Check-Out"]
    U18 --> U19["Submit Verified Stay Review & Star Rating (/user/reviews.php)"]
```

### 4.1 Real-Time Password Reset Popup Flow (Zero-Email-Delay Support)

Kung makalimutan ng bisita ang password (at kahit hindi agad mabuksan ang inbox o offline ang Gmail SMTP), may **Live Session-Bound Password Reset Popup** ang system:

```mermaid
sequenceDiagram
    participant Guest as Guest Browser (forgot-password.php)
    participant API as Backend (check-reset-status.php)
    participant DB as MySQL (password_reset_requests)
    participant Admin as Super Admin (admin/users.php)

    Guest->>DB: Submit email -> Insert pending row + SHA-256 request_token (stored in session)
    Guest->>Guest: Show persistent Waiting Card + Mount reset-status-modal.php
    loop Every 3 seconds (non-blocking session lock released)
        Guest->>API: GET /backend/endpoints/check-reset-status.php
        API->>DB: Verify session token (hash_equals) & 15-min expiry
        API-->>Guest: { status: "pending" }
    end
    Admin->>DB: Click "Send Temp Password" or "Send Reset Link" (resolves pending rows + attempts Gmail SMTP)
    Guest->>API: Next 3s poll -> GET check-reset-status.php
    API-->>Guest: { status: "completed", dispatch_type: "password"|"link", payload }
    Guest->>Guest: Auto-open Popup Modal (Masked Temp Password + Show/Copy OR Direct Reset Link)
    Guest->>API: POST acknowledge (CSRF verified)
    API->>DB: Set user_notified_at = NOW() & clear session watch
    Admin->>DB: Admin table updates badge from "Waiting for user" -> "Viewed by user"
```

---

## 5. Complete Staff (Frontdesk) Flow (`MH_STAFF_SESS`)

Ang **Frontdesk Staff Portal** ay sumusunod sa **Principle of Least Privilege**—nakatutok sa mabilis na check-in/check-out, delegated Maya/GCash verification, at automated housekeeping monitoring. Walang access ang staff sa user password reset, staff creation, system settings, o audit log deletion.

```mermaid
flowchart TD
    S1["Isolated Staff Login (/staff/login.php) -> MH_STAFF_SESS"] --> S2["Frontdesk Dashboard (/staff/dashboard.php)"]
    S2 --> S3["One-Click Marvi Staff Co-Pilot (POST /ai/staff-assistant.php)"]
    S2 --> S4["Pending Booking Requests -> Approve / Decline"]
    S2 --> S5["Delegated Payment Verification (/staff/payments.php or Slim 4 POST /v1/payments/{id}/decision)"]
    S5 --> S5A["Inspect Receipt via Protected serve-file.php + BEGIN TRANSACTION + SELECT FOR UPDATE"]
    S5A --> S5B["Approve -> Payment=verified, Booking=confirmed, Audit Logged with Staff ID"]
    S2 --> S6["Check-In / Check-Out Desk (/staff/check-in.php)"]
    S6 --> S6A["Execute Check-In: Booking confirmed -> checked_in, Room -> occupied, Log checked_in_at"]
    S6 --> S6B["Execute Check-Out: Booking checked_in -> checked_out, Room AUTO -> dirty, Log checked_out_at"]
    S2 --> S7["Housekeeping Room Board (/staff/rooms-status.php)"]
    S7 --> S7A["Inspect Dirty Room -> Mark Clean (dirty -> available) OR Flag Maintenance"]
```

### 5.1 Automated Room & Housekeeping State Machine

```mermaid
stateDiagram-v2
    [*] --> available : Room Ready in Inventory
    available --> reserved : Booking Confirmed (Payment Verified)
    reserved --> occupied : Staff/Admin Executes Check-In
    reserved --> available : Booking Cancelled / Expired
    occupied --> dirty : Staff/Admin Executes Check-Out (Auto-Triggered)
    dirty --> available : Housekeeping Completed (Staff Marks Clean)
    available --> maintenance : Repair / Inspection Needed
    dirty --> maintenance : Damage Found During Cleaning
    maintenance --> available : Repair Completed
```

---

## 6. Complete Super Admin Flow (`MH_ADMIN_SESS`)

Ang **Super Admin Portal** ang executive command center na may buong governance sa pananalapi, KYC adjudication, live password resets, staff provisioning, room inventory, Gmail SMTP, Marvi AI configuration, at immutable security audit logs.

```mermaid
flowchart TD
    A1["Isolated Admin Login (/admin/login.php) -> MH_ADMIN_SESS"] --> A2["Executive Dashboard (/admin/dashboard.php)"]
    A2 --> A3["Marvi Executive BI Digest (POST /ai/admin-insights.php): Revenue, Maya vs GCash, Occupancy, Promo Advice"]
    A2 --> A4["Master Bookings & Visual Timeline (/admin/bookings.php): Override Status, Check-In/Out, Export"]
    A2 --> A5["Central Payment Hub (/admin/payments.php): Atomic Verify/Reject Maya & GCash Proofs"]
    A2 --> A6["Users & KYC Governance (/admin/users.php)"]
    A6 --> A6A["KYC Lightbox (via serve-file.php): Approve or Reject ID with Custom Reason"]
    A6 --> A6B["Live Password Reset Dispatcher: Send Temp Password / Reset Link + Track Viewed vs Waiting"]
    A6 --> A6C["Account Controls: Ban / Unban Users"]
    A2 --> A7["Staff Provisioning (/admin/staff.php): Create, Activate, Deactivate Frontdesk Accounts"]
    A2 --> A8["Room & Pricing Inventory (/admin/rooms.php): Full CRUD + Weekend Rates + Image Cleanup"]
    A2 --> A9["System Settings (/admin/settings.php): Maya/GCash QR Numbers + Gmail SMTP Credentials"]
    A2 --> A10["Marvi AI Control Center (/admin/ai-config.php): API Key, Model Preset Selector, Live Latency Probe, Flagged AI Logs"]
    A2 --> A11["Immutable Audit Logs (/admin/audit-logs.php): Filterable Actor, Module, Action, IP & Timestamp Trail"]
```

---

## 7. Role-Based Access Control (RBAC) Matrix

| Feature / Capability | Public / Guest (`MH_USER_SESS`) | Frontdesk Staff (`MH_STAFF_SESS`) | Super Admin (`MH_ADMIN_SESS`) | Marvi AI Engine (`backend/ai/`) |
| :--- | :---: | :---: | :---: | :---: |
| Browse Rooms & Live Slim 4 Availability | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Reads Live Catalog |
| Marvi Live SSE Chat & Voice Mode | ✅ Yes (All Guest Pages) | ❌ Uses Shift Co-Pilot | ❌ Uses Executive BI | ✅ Powers All 3 Roles |
| Upload KYC Valid ID (Govt / Student ID) | ✅ Yes (`profile.php`) | ❌ No | ❌ No | ❌ No |
| Approve / Reject KYC Identity Documents | ❌ Blocked | ❌ Blocked | ✅ Master Control | ❌ No |
| Create Room Booking (Requires Verified KYC) | ✅ Yes (`booking.php`) | ❌ No | ✅ Override Only | ✅ Recommends & Links |
| Cancel Own Pending Booking (`POST /v1/bookings/{id}/cancel`) | ✅ Yes (CSRF Protected) | ❌ No | ✅ Master Control | ❌ No |
| Upload Maya / GCash Payment Receipt | ✅ Yes (`payment.php`) | ❌ No | ❌ No | ✅ Guides Process |
| Atomic Payment Verification (`FOR UPDATE`) | ❌ Blocked | ✅ Delegated Approval | ✅ Master Approval | ❌ No |
| Execute Guest Check-In & Check-Out | ❌ Blocked | ✅ Daily Operations | ✅ Master Override | ❌ No |
| Toggle Room Housekeeping (`dirty` → `available`) | ❌ Blocked | ✅ Yes | ✅ Yes | ✅ Monitors Dirty Rooms |
| Live Password Reset Dispatch & Tracking | 📥 Receives Popup Only | ❌ Blocked | ✅ Master Dispatcher | ❌ No |
| Create / Manage Staff Accounts | ❌ Blocked | ❌ Blocked | ✅ Exclusive | ❌ No |
| Room Inventory & Pricing CRUD | ❌ Blocked | ❌ Blocked | ✅ Exclusive | ❌ No |
| Gmail SMTP & Marvi AI Config / Probe | ❌ Blocked | ❌ Blocked | ✅ Exclusive | ❌ Read-Only Config |
| Security Audit Trail (`audit_logs`) | ❌ Blocked | ❌ Blocked | 🔒 View-Only (Immutable) | 📝 Logs AI Prompts |

---

## 8. End-to-End Cross-Actor Collaboration Sequence

Ipinapakita nito kung paano nagtutulungan ang **Guest**, **Marvi AI**, **Slim 4 API**, **MySQL Database**, **Frontdesk Staff**, at **Super Admin** sa iisang kumpletong reservation lifecycle:

```mermaid
sequenceDiagram
    actor Guest as Guest (Browser + Alpine.js)
    participant AI as Marvi Live AI (NVIDIA NIM SSE)
    participant API as Slim 4 Backend API (/backend/api/v1)
    participant DB as MySQL InnoDB (PDO)
    actor Admin as Super Admin
    actor Staff as Frontdesk Staff

    Guest->>AI: Ask in Taglish: "May room ba under 1500?" (Text or Mic)
    AI->>DB: Read active rooms + prices + guest context
    AI-->>Guest: Stream SSE reply + Clickable Room Card [[BOOK_ROOM:id]]
    Guest->>API: GET /backend/api/v1/availability?room_id=3&check_in=...&check_out=...
    API->>DB: Check [check_in, check_out) date overlap
    API-->>Guest: { available: true, nights: 2, total_amount: 3000 }
    Guest->>DB: Upload Govt/Student ID (KYC status = pending)
    Admin->>DB: Review ID via serve-file.php -> Approve KYC (verified)
    Guest->>DB: Confirm Booking (status = pending) + Upload Maya/GCash Receipt
    Staff->>API: POST /backend/api/v1/payments/{id}/decision (CSRF + RoleAuth)
    API->>DB: BEGIN TRANSACTION + SELECT FOR UPDATE -> Payment verified, Booking confirmed
    API-->>Staff: { success: true, booking_status: "confirmed" }
    Staff->>DB: Guest arrives -> Check-In (Booking checked_in, Room occupied)
    Staff->>DB: Guest departs -> Check-Out (Booking checked_out, Room auto-set dirty)
    Staff->>DB: Housekeeping finishes -> Room set available
    Guest->>DB: Submit Verified Stay Review & Rating
    Admin->>AI: Generate Executive BI Digest (Revenue, Occupancy, Payment Ratio)
```

---

## 9. Slim 4 REST API v1 & Database Quick Reference

### 9.1 Slim 4 REST API Endpoints (`backend/src/ApiAppFactory.php`)

| Method | Endpoint | Auth / Middleware | Controller → Service | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/backend/api/v1/health` | Public | `HealthController` | API health, version, and runtime status |
| `GET` | `/backend/api/v1/rooms` | Public | `RoomController` → `RoomService` | Filterable active room catalog |
| `GET` | `/backend/api/v1/rooms/{id}` | Public | `RoomController` → `RoomService` | Single room details & amenities |
| `GET` | `/backend/api/v1/availability` | Public | `RoomController` → `RoomService` | Real-time date-range availability & dynamic pricing |
| `GET` | `/backend/api/v1/auth/status` | Portal-Aware | `AuthController` → `AuthService` | Active session role, user summary & CSRF token |
| `GET` | `/backend/api/v1/bookings` | `RoleAuth(user,staff,admin)` | `BookingController` → `BookingService` | Role-scoped booking list |
| `POST` | `/backend/api/v1/bookings/{id}/cancel` | `RoleAuth(user)` + `CsrfProtection` | `BookingController` → `BookingService` | Guest self-cancellation of pending bookings |
| `GET` | `/backend/api/v1/payments` | `RoleAuth(user,staff,admin)` | `PaymentController` → `PaymentService` | Role-scoped payment records |
| `POST` | `/backend/api/v1/payments/{id}/decision` | `RoleAuth(staff,admin)` + `CsrfProtection` | `PaymentController` → `PaymentService` | Atomic payment verification / rejection (`FOR UPDATE`) |
| `GET` | `/backend/api/v1/dashboard/metrics` | `RoleAuth(user,staff,admin)` | `DashboardController` → `DashboardService` | Live KPI metrics per active actor role |

### 9.2 Database Summary (`database/ERD.md`)
- **Engine:** MySQL / MariaDB InnoDB (`utf8mb4_unicode_ci`)
- **14 Normalized Tables:** `users`, `room_types`, `rooms`, `bookings`, `payments`, `reviews`, `notifications`, `audit_logs`, `ai_logs`, `settings`, `contact_messages`, `rate_limits`, `password_reset_requests`, `schema_migrations`
- **17 Versioned Migrations:** `database/migrations/0001_*.sql` through `0017_update_nvidia_ai_model.sql`, managed via `php database/migrate.php`

---

## 10. Capstone Defense Panel Q&A Cheat Sheet (12 High-Impact Questions)

1. **Q: Ano ang architecture at mga frameworks na ginamit ninyo sa project?**  
   **A:** Gumamit po kami ng **Framework-Powered 3-Tier Architecture** na malinis na nakahiwalay sa `frontend/`, `backend/`, at `database/`. Sa **Frontend**, **HTML5, CSS3, ES6+ JavaScript** kasama ang **Tailwind CSS v3** at **Alpine.js 3**. Sa **Backend**, **PHP 8** gamit ang **Slim 4 Framework** (PSR-4 Controllers/Services at PSR-15 Middleware) at **PHPMailer 7**. Sa **Database**, **MySQL/MariaDB SQL** gamit ang **PDO Prepared Statements** at 17 versioned migrations.

2. **Q: Bakit Slim 4 + Tailwind + Alpine.js ang pinili ninyo imbes na Laravel o React?**  
   **A:** Dahil ang target deployment ng local hotel/dormitory ay lightweight at madaling i-host sa standard Apache/XAMPP o Docker nang walang mabigat na Node build server sa runtime. Binibigyan kami ng **Slim 4** ng modern PSR-4/PSR-15 REST API architecture nang napakabilis, habang ang **Tailwind CSS + Alpine.js** ay nagbibigay ng reactive real-time UI (live availability checks, SSE AI streaming, modals) na mabilis mag-load kahit sa mobile data.

3. **Q: Paano ninyo sinisigurado na hindi nagkakaroon ng double-booking o race condition sa bayad?**  
   **A:** Dalawang layer po ang proteksyon namin: Una, ang real-time date-overlap algorithm `[check_in, check_out)` sa `RoomService` at `booking.php`. Pangalawa, ang **ACID database transactions (`BEGIN TRANSACTION` + `SELECT ... FOR UPDATE`)** sa MySQL InnoDB upang i-lock ang booking/payment row habang nagve-verify ang Staff o Admin—siguradong walang dalawang actor ang makakapag-overwrite nang sabay.

4. **Q: Paano gumagana ang Marvi AI? Live LLM ba talaga o hardcoded lang?**  
   **A:** **Hybrid Real-Time Live LLM** po. Nakakonekta ito sa **NVIDIA NIM API** gamit ang `nvidia/nemotron-3.5-bolt-e4b` na may **Server-Sent Events (SSE) token-by-token streaming** (~0.8s response time), multi-turn conversation memory, at optional **Voice Mode (Web Speech Mic + TTS)**. May auto-failover ito sa backup cloud models at local bilingual rule engine kapag nawalan ng internet—kaya **0% downtime** sa defense o production.

5. **Q: Paano ninyo pinoprotektahan ang Marvi AI laban sa prompt injection o hacking queries?**  
   **A:** May tatlong guardrail layers po kami: (1) Rate limiting sa `rate_limits` table, (2) Bad-Intent Security Filter (`bad-intent-keywords.json`) na humaharang sa SQLi/XSS/port-scan/prompt-injection at nagla-log ng `flagged = 1` sa `ai_logs`, at (3) Output Sanitizer na nagtatago ng internal file paths, port numbers, at credentials.

6. **Q: Paano nakakatulong ang AI sa Staff at Admin, hindi lang sa Guest?**  
   **A:** Tri-role po ang Marvi AI: (1) **Guest Concierge & Smart Room Matcher** para sa booking guidance at clickable room cards; (2) **Staff Shift Co-Pilot** (`staff-assistant.php`) na bumubuo ng daily arrivals, departures, at dirty-rooms briefing; at (3) **Admin Executive BI** (`admin-insights.php`) na nagsusuri ng revenue, Maya vs GCash ratio, occupancy rate, at promo strategy.

7. **Q: Paano ninyo pinoprotektahan ang uploaded Government/Student IDs at GCash/Maya receipts?**  
   **A:** Naka-HTTP 403 block ang `uploads/` directory via `.htaccess` kaya hindi mabubuksan kahit alam ang URL. Lahat ng sensitive images ay dumadaan lamang sa **`backend/endpoints/serve-file.php`**, na nagbe-verify muna ng active session role at ownership (sariling file ng guest, o Admin/Staff role) bago i-stream ang image.

8. **Q: Bakit magkahiwalay ang session cookies ng Guest, Staff, at Admin?**  
   **A:** Gumagamit kami ng **Isolated Session Namespaces** (`MH_USER_SESS`, `MH_STAFF_SESS`, `MH_ADMIN_SESS`) upang maiwasan ang session fixation at privilege bleed, at upang ma-demo nang sabay-sabay sa iisang browser ang Guest, Staff, at Admin portals nang hindi nagla-logout ang isa't isa.

9. **Q: Ano ang ginagawa ng Live Password Reset Popup kung nakalimutan ng user ang password?**  
   **A:** Kapag nag-request ang user sa `forgot-password.php`, gumagawa ang system ng SHA-256 session-bound watch token. Nagpo-poll ang `reset-status-modal.php` kada 3 segundo (nang naka-release ang PHP session lock para walang lag). Pag-click ng Admin ng *Send Temp Password* o *Send Reset Link*, lalabas agad ang 1-time masked popup sa screen ng user (15-min expiry) at mag-a-update sa Admin screen mula *Waiting for user* patungong *Viewed by user*—kahit walang internet o delay ang email!

10. **Q: Bakit kailangan pa ng Frontdesk Staff portal kung may Super Admin naman?**  
    **A:** Para sa **Separation of Duties at Principle of Least Privilege**. Ang Staff ay para sa araw-araw na desk operations (check-in, check-out, housekeeping status, delegated payment verification) nang walang kakayahang mag-delete ng audit logs, mag-reset ng password, o magbago ng room pricing/system settings.

11. **Q: Ano ang nangyayari sa kwarto pagkatapos mag-check-out ang guest?**  
    **A:** May **Automated Housekeeping State Machine** kami: pag-click ng Staff ng Check-Out, awtomatikong lumilipat ang booking sa `checked_out` at ang room status sa `dirty`. Hindi ito agad maibebenta sa susunod na walk-in hangga't hindi minamarkahan ng Staff sa `rooms-status.php` na malinis na (`available`).

12. **Q: Paano ninyo napatunayan na gumagana at reliable ang codebase bago ang defense?**  
    **A:** Gumagamit kami ng **PHPUnit 10 Automated Test Suite** (`tests/`) na may **32 passing unit & integration tests (185 assertions)** na sumusubok sa Slim 4 routes, PSR-15 RoleAuth at CSRF middleware, Room availability math, at Booking/Payment services, plus zero PHP/JS lint errors at 100% applied SQL migrations (`0001`–`0017`).
