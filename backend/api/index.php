<?php
// ============================================================
// MARVELOUS HOTEL — Slim 4 REST API Front Controller
// backend/api/index.php
// Routes: /backend/api/  and  /backend/api/v1/*
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use MarvelousHotel\ApiAppFactory;

// Auto-detect base path seamlessly across XAMPP (/hotel/backend/api) and root/Docker/multi-port (/backend/api)
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath  = ($scriptDir !== '/' && $scriptDir !== '.') ? rtrim($scriptDir, '/') : '';

$app = ApiAppFactory::create($basePath);
$app->run();
