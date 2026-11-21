<?php
// ============================================================
// MARVELOUS HOTEL — Tier 2 Backend Bootstrap
// backend/bootstrap.php
// Loads Composer PSR-4 autoloader (Slim 4 + MarvelousHotel\) & Core libraries
// ============================================================

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/functions.php';
