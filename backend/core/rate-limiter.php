<?php
// ============================================================
// MARVELOUS HOTEL — Rate Limiter
// includes/rate-limiter.php
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Check and record rate limit for an identifier (IP, email, etc.)
 * 
 * @param string $identifier Unique identifier (e.g., IP address, email)
 * @param string $action Action name (e.g., 'login', 'register', 'reset')
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $windowSeconds Time window in seconds
 * @return array ['allowed' => bool, 'remaining' => int, 'retryAfter' => int|null]
 */
function checkRateLimit(string $identifier, string $action, int $maxAttempts = 5, int $windowSeconds = 900): array {
    $key = $action . ':' . $identifier;
    $now = time();
    $windowStart = $now - $windowSeconds;

    // Clean old entries
    dbExec("DELETE FROM rate_limits WHERE `key` = ? AND created_at < ?", [$key, date('Y-m-d H:i:s', $windowStart)]);

    // Get current count
    $row = dbRow("SELECT COUNT(*) as cnt FROM rate_limits WHERE `key` = ?", [$key]);
    $currentCount = (int) ($row['cnt'] ?? 0);

    if ($currentCount >= $maxAttempts) {
        // Get oldest entry to calculate retry-after
        $oldest = dbRow("SELECT created_at FROM rate_limits WHERE `key` = ? ORDER BY created_at ASC LIMIT 1", [$key]);
        $retryAfter = $oldest ? (strtotime($oldest['created_at']) + $windowSeconds - $now) : $windowSeconds;
        return ['allowed' => false, 'remaining' => 0, 'retryAfter' => max(1, $retryAfter)];
    }

    // Record this attempt
    dbExec("INSERT INTO rate_limits (`key`, `identifier`, `action`, created_at) VALUES (?, ?, ?, NOW())", [$key, $identifier, $action]);

    return ['allowed' => true, 'remaining' => $maxAttempts - $currentCount - 1, 'retryAfter' => null];
}

/**
 * Get client IP address (handles proxies)
 */
function getClientIp(): string {
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            return trim($ips[0]);
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Apply rate limiting to auth endpoints
 * Call this at the top of login/register/forgot/reset pages
 * Default: 5 attempts per 1 minute (60 seconds) cooldown
 */
function applyAuthRateLimit(string $action, int $maxAttempts = 5, int $windowSeconds = 60): void {
    // Only apply rate limiting to POST form submissions; allow normal page viewing/refreshing
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $ip = getClientIp();

    // Relaxed threshold for local development / testing
    if (in_array($ip, ['127.0.0.1', '::1', 'localhost', 'unknown'])) {
        $maxAttempts = max($maxAttempts, 30);
    }

    $result = checkRateLimit($ip, $action, $maxAttempts, $windowSeconds);

    if (!$result['allowed']) {
        $retrySecs = max(1, (int)$result['retryAfter']);
        http_response_code(429);

        // Log the rate limit event for Admin Audit Trail
        if (function_exists('auditLog')) {
            auditLog('Rate Limit Blocked', 'Security', "IP {$ip} exceeded {$maxAttempts} attempts for '{$action}'. Locked for {$retrySecs}s.");
        }

        $isJson = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        if ($isJson) {
            die(json_encode([
                'error' => "Too many attempts. Please try again in {$retrySecs} second(s).",
                'retry_after' => $retrySecs
            ]));
        }

        die('<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rate Limit Exceeded — Marvelous Hotel</title>
  <link rel="stylesheet" href="' . ASSETS_URL . '/css/base.css">
  <style>
    body{background:#0b132b;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:sans-serif;margin:0;padding:20px;}
    .lock-card{background:#1c2541;padding:36px;border-radius:14px;max-width:440px;text-align:center;box-shadow:0 15px 35px rgba(0,0,0,0.5);border:1px solid rgba(255,255,255,0.1);}
    .timer-badge{display:inline-block;background:rgba(234,179,8,0.15);border:1px solid #eab308;color:#facc15;padding:8px 18px;border-radius:999px;font-weight:700;font-size:1.1rem;margin:12px 0;}
  </style>
</head>
<body>
  <div class="lock-card">
    <div style="font-size:3rem;margin-bottom:10px;">⏳</div>
    <h2 style="margin:0 0 10px;font-size:1.35rem;color:#fff;">Too Many Attempts</h2>
    <p style="color:#cbd5e1;font-size:0.92rem;line-height:1.5;">
      You have exceeded <strong>' . (int)$maxAttempts . ' attempts</strong>. For your security, please wait ' . $retrySecs . ' second(s) before trying again.
    </p>
    <div class="timer-badge" id="cdTimer">' . $retrySecs . 's remaining</div>
    <div style="margin-top:20px;">
      <a href="javascript:history.back()" style="background:#c5a880;color:#0b132b;padding:10px 22px;text-decoration:none;border-radius:6px;font-weight:bold;font-size:0.88rem;display:inline-block;">Go Back</a>
    </div>
  </div>
  <script>
    let secs = ' . $retrySecs . ';
    const el = document.getElementById("cdTimer");
    const interval = setInterval(() => {
      secs--;
      if (secs <= 0) {
        clearInterval(interval);
        el.innerHTML = "You can now try again!";
        el.style.borderColor = "#22c55e";
        el.style.color = "#4ade80";
      } else {
        el.innerHTML = secs + "s remaining";
      }
    }, 1000);
  </script>
</body>
</html>');
    }

    // Add rate limit headers
    header('X-RateLimit-Limit: ' . $maxAttempts);
    header('X-RateLimit-Remaining: ' . $result['remaining']);
    header('X-RateLimit-Reset: ' . (time() + ($result['retryAfter'] ?? $windowSeconds)));
}

/**
 * Get rate limit status for display (e.g., in login form)
 */
function getRateLimitStatus(string $identifier, string $action, int $maxAttempts = 5, int $windowSeconds = 900): array {
    $key = $action . ':' . $identifier;
    $windowStart = time() - $windowSeconds;
    
    $row = dbRow("SELECT COUNT(*) as cnt FROM rate_limits WHERE `key` = ? AND created_at > ?", [$key, date('Y-m-d H:i:s', $windowStart)]);
    $used = (int) ($row['cnt'] ?? 0);
    
    return [
        'used' => $used,
        'remaining' => max(0, $maxAttempts - $used),
        'limit' => $maxAttempts
    ];
}