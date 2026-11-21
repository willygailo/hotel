# Tier 2: Backend Layer (`backend/`)

Ang folder na ito ang **Application & REST API Tier** ng **Marvelous Hotel**, na gumagamit ng **PHP 8 + Slim 4 Micro-Framework (`slim/slim`, `slim/psr7`)** at **PSR-4 Autoloading (`MarvelousHotel\`)**.

## Istruktura
- `bootstrap.php` — Central backend bootstrap (Composer autoloader + `backend/core/*`).
- `core/` — Core infrastructure (`config.php`, `db.php`, `session.php`, `functions.php`, `logger.php`, `rate-limiter.php`, `email.php`). *Protected by `.htaccess` (`Deny from all`).*
- `src/` — PSR-4 `MarvelousHotel\` classes (*protected by `.htaccess`*):
  - `ApiAppFactory.php` & `JsonResponder.php` — Slim 4 app bootstrap & standard JSON envelope.
  - `Middleware/` — PSR-15 middleware (`JsonBodyParserMiddleware`, `RoleAuthMiddleware`, `CsrfProtectionMiddleware`).
  - `Controllers/` — Slim 4 REST controllers (`Health`, `Room`, `Booking`, `Payment`, `Auth`, `Dashboard`).
  - `Services/` — Reusable domain services (`RoomService`, `BookingService`, `PaymentService`, `AuthService`, `DashboardService`).
- `ai/` — Real-time NVIDIA NIM AI Engine (`nvidia-client.php`, `chatbot.php` SSE streaming, `staff-assistant.php`, `admin-insights.php`, `training-data/`).
- `endpoints/` — Non-HTML backend endpoints (`serve-file.php`, `check-name.php`, `check-reset-status.php`, `logout.php`).
- `api/` — Slim 4 RESTful API front controller (`index.php` + `.htaccess`).

## REST API v1 Endpoints (`/backend/api/v1/*`)

| Method | Endpoint | Access | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/backend/api/` | Public | API discovery & metadata |
| `GET` | `/backend/api/v1/health` | Public | Live API, MySQL DB, & NVIDIA AI status |
| `GET` | `/backend/api/v1/rooms` | Public | Room catalog (`?budget=&guests=&q=&check_in=&check_out=`) |
| `GET` | `/backend/api/v1/rooms/{id}` | Public | Single room details + active booking ranges |
| `GET` | `/backend/api/v1/availability` | Public | Real-time date conflict check & stay quote |
| `GET` | `/backend/api/v1/auth/status` | Public | Isolated session identity, role & KYC status |
| `GET` | `/backend/api/v1/bookings` | Auth | Role-scoped bookings list |
| `POST` | `/backend/api/v1/bookings/{id}/cancel` | Guest + CSRF | Cancel a pending guest booking |
| `GET` | `/backend/api/v1/payments` | Auth | Role-scoped payment records |
| `POST` | `/backend/api/v1/payments/{id}/decision` | Staff/Admin + CSRF | Atomic verify/reject payment decision |
| `GET` | `/backend/api/v1/dashboard/metrics` | Auth | Role-scoped live KPI dashboard metrics |
