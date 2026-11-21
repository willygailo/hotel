<?php
// ============================================================
// MARVELOUS HOTEL — User Portal Registration Forwarder
// user/register.php -> auth/register.php
// ============================================================
$redirect = $_GET['redirect'] ?? '';
$qs = $redirect !== '' ? '?redirect=' . urlencode($redirect) : '';
header('Location: ../auth/register.php' . $qs);
exit;
