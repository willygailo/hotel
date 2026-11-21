# Tier 3: Database Layer (`database/`)

Ang folder na ito ang kumakatawan sa **Persistence / Database Tier** ng **Marvelous Hotel** (MySQL / MariaDB SQL, InnoDB, `utf8mb4_unicode_ci`).

## Mga Nilalaman
- `marvelous_hotel.sql` — Master database schema, foreign keys, at initial seed data.
- `migrations/` — 17 versioned incremental SQL migrations (`0001_...` hanggang `0017_...`).
- `migrate.php` — Canonical CLI migration runner (naka-track sa `schema_migrations` table; ang root `migrate.php` ay wrapper lang dito).
- `ERD.md` — Kumpletong Mermaid Entity-Relationship Diagram at 14-table Data Dictionary.
- `.htaccess` — Harang laban sa direktang HTTP access ng mga `.sql` at PHP CLI scripts.

## Paano Mag-import (Fresh Install)
```bash
# Linux XAMPP
/opt/lampp/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS marvelous_hotel;"
/opt/lampp/bin/mysql -u root marvelous_hotel < database/marvelous_hotel.sql

# Windows XAMPP
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS marvelous_hotel;"
C:\xampp\mysql\bin\mysql.exe -u root marvelous_hotel < database\marvelous_hotel.sql
```

## Paano Mag-apply ng Migrations
```bash
php database/migrate.php
php database/migrate.php --status
```
