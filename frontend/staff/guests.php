<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('staff');

// User account management is strictly restricted to Super Admin.
setFlash('warning', 'User account management is restricted to Super Admin. Use Check-in & Check-out for operational arrivals.');
header("Location: dashboard.php");
exit;
