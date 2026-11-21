<?php
// ============================================================
// MARVELOUS HOTEL — Test Suite Bootstrap
// tests/bootstrap.php
// ============================================================

define('ENVIRONMENT', 'testing');
define('SITE_URL', 'http://localhost/hotel');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/session.php';
require_once __DIR__ . '/../backend/core/functions.php';
