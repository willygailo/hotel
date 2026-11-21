# Tier 1: Frontend Layer (`frontend/`)

Ang folder na ito ang **Presentation / Frontend Tier** ng **Marvelous Hotel**, na gumagamit ng **Tailwind CSS + Alpine.js 3** kasama ng modular CSS3 at ES6+ JavaScript.

## Frameworks & Libraries
- **Tailwind CSS (`v3.4`)** — Pre-compiled sa `frontend/assets/css/tailwind.css` gamit ang custom Marvelous Navy & Gold color palette (`tailwind.config.js`, `preflight: false` para compatible sa existing design system).
- **Alpine.js (`v3.14`)** — Local offline-ready reactive JS framework (`frontend/assets/vendor/alpine.min.js`) para sa live reactive components (tulad ng live room availability checker).
- **`HotelAPI` Client (`frontend/assets/js/api-client.js`)** — Unified `fetch` client na nakikipag-ugnayan sa **Tier 2 Slim 4 REST API** (`/backend/api/v1/*`) na may awtomatikong `X-CSRF-Token` at `X-Portal` headers.

## Istruktura
- `pages/` — Public pages (`index.php`, `rooms.php`, `about.php`, `contact.php`, `404.php`, `500.php`).
- `auth/` — Authentication UI pages (`login.php`, `register.php`, `forgot-password.php`, `reset-password.php`, `verify-email.php`, `welcome.php`, `setup-admin.php`).
- `user/` — Guest Portal pages (`dashboard.php`, `booking.php`, `my-bookings.php`, `payment.php`, `profile.php`, `reviews.php`, `ai-recommend.php`, atbp.).
- `staff/` — Frontdesk Staff Portal pages (`login.php`, `dashboard.php`, `check-in.php`, `payments.php`, `rooms-status.php`).
- `admin/` — Super Admin Portal pages (`login.php`, `dashboard.php`, `bookings.php`, `payments.php`, `rooms.php`, `users.php`, `staff.php`, `ai-config.php`, `audit-logs.php`, `settings.php`).
- `assets/css/` — `tailwind.css` + portal stylesheets (`base`, `public`, `auth`, `user`, `staff`, `admin`, `ai-chat`).
- `assets/js/` — `api-client.js`, `core.js`, `ai-chat.js` (Real-Time SSE + Voice Mode), at portal scripts.
- `assets/vendor/` — `alpine.min.js` (offline-ready Alpine.js bundle).
- `assets/images/` — Hotel photos, room thumbnails, avatars, Maya & GCash QR images.
- `components/` — Reusable UI components (`sidebar.php`, `central-nav.php`, `ai-chatbot.php`, `reset-status-modal.php`, `frontend-frameworks.php`).

## Rebuilding Frontend CSS / Alpine Bundle (Optional)
```bash
cd frontend
npm install
npm run build
```
*(Hindi kailangan ng running Node server sa production/demo — diretsong sine-serve ng XAMPP Apache ang compiled files.)*
