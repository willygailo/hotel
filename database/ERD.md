# Marvelous Hotel — Entity-Relationship Diagram (ERD) & Data Dictionary

Database Engine: **MySQL / MariaDB (InnoDB, `utf8mb4_unicode_ci`)**  
Database Name: `marvelous_hotel`  
Access Layer: **PHP PDO Prepared Statements** (`backend/core/db.php`)

---

## 1. Entity-Relationship Diagram

```mermaid
erDiagram
    users ||--o{ bookings : "makes"
    users ||--o{ payments : "submits"
    users ||--o{ reviews : "writes"
    users ||--o{ notifications : "receives"
    users ||--o{ audit_logs : "triggers"
    users ||--o{ ai_logs : "chats"
    users ||--o{ password_reset_requests : "requests"

    room_types ||--o{ rooms : "categorizes"
    rooms ||--o{ bookings : "reserved_in"
    rooms ||--o{ reviews : "rated_in"

    bookings ||--o{ payments : "settled_by"
    bookings ||--o| reviews : "reviewed_in"

    users {
        int id PK
        varchar first_name
        varchar middle_name
        varchar last_name
        varchar suffix
        varchar email UK
        varchar phone
        varchar password
        enum role "user|staff|admin"
        enum status "active|banned"
        enum verification_status "unverified|pending|verified|rejected"
        tinyint profile_verified
        varchar id_type
        varchar id_number
        varchar id_document
        varchar reset_token
        datetime reset_expires
        varchar remember_token
        datetime created_at
    }

    room_types {
        int id PK
        varchar name
        text description
        varchar icon
    }

    rooms {
        int id PK
        varchar room_number UK
        int room_type_id FK
        varchar name
        int floor
        int capacity
        decimal price_per_night
        decimal weekend_rate
        json amenities
        enum status "available|occupied|reserved|dirty|maintenance"
    }

    bookings {
        int id PK
        varchar booking_code UK
        int user_id FK
        int room_id FK
        date check_in
        date check_out
        int nights
        int num_guests
        decimal total_amount
        varchar payment_method
        enum status "pending|confirmed|checked_in|checked_out|cancelled|rejected"
        datetime accepted_at
        datetime checked_in_at
        datetime checked_out_at
        datetime cancelled_at
    }

    payments {
        int id PK
        int booking_id FK
        int user_id FK
        decimal amount
        enum method "maya|gcash"
        varchar reference_number
        varchar proof_image
        enum status "pending|verified|rejected"
        int verified_by FK
        datetime verified_at
    }

    reviews {
        int id PK
        int user_id FK
        int room_id FK
        int booking_id FK
        tinyint rating
        varchar title
        text comment
        enum status "pending|approved|hidden"
    }

    notifications {
        int id PK
        int user_id FK
        varchar title
        text message
        varchar type
        tinyint is_read
        varchar link
    }

    password_reset_requests {
        int id PK
        int user_id FK
        varchar email
        varchar request_token
        enum status "pending|completed|dismissed"
        enum dispatch_type "password|link"
        varchar new_password_assigned
        datetime requested_at
        datetime resolved_at
        datetime user_notified_at
    }

    audit_logs {
        int id PK
        int user_id FK
        varchar action
        varchar module
        text details
        varchar ip_address
        datetime created_at
    }

    ai_logs {
        int id PK
        int user_id FK
        text user_message
        text ai_response
        varchar intent
        tinyint flagged
        varchar flag_reason
        datetime created_at
    }
```

---

## 2. Complete 14-Table Data Dictionary

| # | Table | Purpose | Key Constraints |
| :--- | :--- | :--- | :--- |
| 1 | `users` | Guest, Staff, and Super Admin accounts, KYC identity verification, email/reset/remember tokens | `UNIQUE(email)`, normalized full-name index |
| 2 | `room_types` | Accommodation categories (Standard, Deluxe, Suite, Family, Dormitory, Studio) | `PRIMARY KEY(id)` |
| 3 | `rooms` | Physical room & dormitory bed inventory, pricing, amenities, housekeeping status | `UNIQUE(room_number)`, `FK(room_type_id)` |
| 4 | `bookings` | Room reservations, date windows `[check_in, check_out)`, lifecycle timestamps | `UNIQUE(booking_code)`, `FK(user_id)`, `FK(room_id)` |
| 5 | `payments` | Maya & GCash payment submissions, reference numbers, receipt proofs, verification timestamps | `FK(booking_id)`, `FK(user_id)` |
| 6 | `reviews` | Verified stay ratings and general hotel guest feedback | `FK(user_id)`, nullable `FK(room_id)` & `FK(booking_id)` |
| 7 | `notifications` | In-app alerts for booking updates, payment verification, KYC decisions, and admin alerts | `FK(user_id)` |
| 8 | `audit_logs` | Immutable security & operational audit trail (actor, module, action, IP, timestamp) | Indexed on `created_at` & `user_id` |
| 9 | `ai_logs` | Marvi AI conversation logs, intent classification, and flagged security threat records | Indexed on `flagged` & `created_at` |
| 10 | `settings` | Dynamic key-value hotel configuration (Maya/GCash details, Gmail SMTP, NVIDIA NIM AI config) | `UNIQUE(key)` |
| 11 | `contact_messages` | Public contact form guest inquiries | `PRIMARY KEY(id)` |
| 12 | `rate_limits` | Sliding-window brute-force and AI quota throttle records | Indexed on `(key, created_at)` |
| 13 | `password_reset_requests` | Live Admin-assisted password reset queue with SHA-256 session token & one-time view tracking | `FK(user_id)`, indexed on `status` & `request_token` |
| 14 | `schema_migrations` | Versioned SQL migration ledger maintained by `database/migrate.php` | `PRIMARY KEY(migration)` |
