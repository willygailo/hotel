<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

// Staff accounts are created exclusively by the Admin via admin/staff.php.
// The public staff self-registration portal is permanently closed.
header('Location: ../staff/login.php');
exit;
