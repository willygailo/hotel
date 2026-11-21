<?php
// ============================================================
// MARVELOUS HOTEL — User Portal Login Forwarder
// user/login.php -> auth/login.php
// ============================================================
$redirect = $_GET['redirect'] ?? '';
$qs = $redirect !== '' ? '?redirect=' . urlencode($redirect) : '';
header('Location: ../auth/login.php' . $qs);
exit;
