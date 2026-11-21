<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';
require_once __DIR__ . '/../../backend/ai/nvidia-client.php';

requireRole('admin');
releaseReadOnlySession();

// Save AI Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ai_config'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $aiEnabled = isset($_POST['ai_enabled']) && $_POST['ai_enabled'] === '1' ? '1' : '0';
        $aiModel   = trim($_POST['ai_nvidia_model'] ?? NVIDIA_DEFAULT_MODEL);
        $newKey    = trim($_POST['ai_nvidia_key'] ?? '');

        $upsert = function (string $key, string $val) {
            dbExec(
                "INSERT INTO settings (`key`, `value`, `updated_at`) VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()",
                [$key, $val]
            );
        };

        $upsert('ai_enabled', $aiEnabled);
        if ($aiModel !== '') {
            $upsert('ai_nvidia_model', $aiModel);
        }
        if (!empty($_POST['clear_ai_key'])) {
            $upsert('ai_nvidia_key', '');
        } elseif ($newKey !== '') {
            $upsert('ai_nvidia_key', $newKey);
        }

        clearSettingsCache();
        auditLog('Updated AI Configuration', 'Admin Module', "AI Enabled: {$aiEnabled}, Model: {$aiModel}");
        setFlash('success', 'AI Engine settings updated successfully.');
    }
    header('Location: ai-config.php');
    exit;
}

// Clear older non-flagged AI logs cleanly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_non_flagged_logs'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $deleted = dbExec("DELETE FROM ai_logs WHERE flagged = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        auditLog('Cleaned AI Logs', 'Admin Module', "Purged {$deleted} non-flagged AI log entries older than 7 days");
        setFlash('success', "Cleaned {$deleted} older routine AI log record(s). Flagged security logs were kept intact.");
    }
    header('Location: ai-config.php');
    exit;
}

// Test AI Connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_ai_connection'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $probe = queryNvidiaAI('Reply strictly with: AI Engine Online.', ['role' => 'admin', 'max_tokens' => 24, 'temperature' => 0.1]);
        $meta  = getNvidiaLastMeta();
        if (!empty($probe)) {
            $usedModel = $meta['model'] ?? NVIDIA_DEFAULT_MODEL;
            $latency   = (int)($meta['latency_ms'] ?? 0);
            setFlash('success', "Live AI Engine ONLINE [{$usedModel}, {$latency}ms]: " . truncateText($probe, 120));
        } else {
            $errDetail = $meta['error'] ?? 'No response from upstream endpoint';
            $httpCode  = (int)($meta['http_code'] ?? 0);
            $codeText  = $httpCode > 0 ? " (HTTP {$httpCode})" : '';
            setFlash('warning', "Live AI Engine did not respond{$codeText}: {$errDetail}. Check your NVIDIA API key or model ID (local FAQ/fallback remains active).");
        }
    }
    header('Location: ai-config.php');
    exit;
}

$aiEnabled = getSetting('ai_enabled', '1');
$aiModel   = getSetting('ai_nvidia_model', NVIDIA_DEFAULT_MODEL);
if ($aiModel === 'google/diffusiongemma-26b-a4b-it' || $aiModel === '') {
    $aiModel = NVIDIA_DEFAULT_MODEL;
}
$hasKey    = !empty(getenv('NVIDIA_API_KEY') ?: getSetting('ai_nvidia_key', ''));

$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($filter === 'flagged') {
    $where[] = 'a.flagged = 1';
} elseif ($filter === 'budget') {
    $where[] = "a.intent LIKE '%budget%'";
}
if ($search !== '') {
    $where[] = '(a.user_message LIKE ? OR a.ai_response LIKE ? OR u.email LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$aiLogs = dbRows("SELECT a.*, u.email FROM ai_logs a LEFT JOIN users u ON a.user_id = u.id {$whereSql} ORDER BY a.id DESC LIMIT 100", $params);
$logStats = dbRow("SELECT COUNT(*) AS total, SUM(CASE WHEN flagged = 1 THEN 1 ELSE 0 END) AS flagged_cnt FROM ai_logs");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Logs &amp; Monitoring — Admin Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/admin.css')) ?>">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('admin'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="admin-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">AI Chatbot Management &amp; Intelligence Logs</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">AI Engine Control &amp; Threat Monitoring</h1>
          <div class="page-header-sub">Configure Marvi AI settings, test live inference, and audit blocked bad-intent attempts.</div>
        </div>
      </div>

      <!-- AI Engine Configuration Card -->
      <div class="glass-card" style="padding:24px;margin-bottom:24px;border:1px solid rgba(201,168,76,0.28);">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
          <h3 style="font-family:var(--font-serif);font-size:1.15rem;color:var(--gold);margin:0;">
            <i class="fa-solid fa-sliders"></i> Marvi AI Configuration
          </h3>
          <span class="badge <?= $hasKey ? 'badge-success' : 'badge-warning' ?>">
            <?= $hasKey ? 'API Key Configured' : 'Local Fallback Only (No API Key)' ?>
          </span>
        </div>

        <form action="ai-config.php" method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;align-items:end;">
          <?= csrfField() ?>
          <input type="hidden" name="save_ai_config" value="1">
          <div>
            <label class="form-label">AI Live Engine Status</label>
            <select name="ai_enabled" class="form-control">
              <option value="1" <?= $aiEnabled === '1' ? 'selected' : '' ?>>Enabled (Live AI + Local Fallback)</option>
              <option value="0" <?= $aiEnabled === '0' ? 'selected' : '' ?>>Disabled (Local FAQ &amp; Rules Only)</option>
            </select>
          </div>
          <div>
            <label class="form-label">NVIDIA NIM Model ID</label>
            <input type="text" name="ai_nvidia_model" list="nvidiaModelPresets" class="form-control" value="<?= h($aiModel) ?>" placeholder="<?= h(NVIDIA_DEFAULT_MODEL) ?>" required>
            <datalist id="nvidiaModelPresets">
              <option value="nvidia/nemotron-3.5-bolt-e4b">Recommended Fast Live Chat (Default)</option>
              <option value="nvidia/nemotron-3.5-lightning-30b-a3b">Nemotron 3.5 Lightning 30B</option>
              <option value="minimaxai/minimax-m3.1-flash">MiniMax M3.1 Flash</option>
              <option value="mistralai/mistral-large-4-1t-a52b">Mistral Large 4</option>
            </datalist>
          </div>
          <div>
            <label class="form-label">Update NVIDIA API Key <small style="color:var(--text-muted);">(leave blank to keep current)</small></label>
            <input type="password" name="ai_nvidia_key" class="form-control" placeholder="<?= $hasKey ? '•••••••• (Configured)' : 'nvapi-...' ?>" autocomplete="new-password">
            <?php if ($hasKey): ?>
              <label style="display:inline-flex;align-items:center;gap:6px;margin-top:6px;font-size:0.74rem;color:var(--text-muted);cursor:pointer;">
                <input type="checkbox" name="clear_ai_key" value="1"> Remove saved DB API key
              </label>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-gold"><i class="fa-solid fa-save"></i> Save AI Config</button>
          </div>
        </form>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
          <form action="ai-config.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="test_ai_connection" value="1">
            <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-plug-circle-check"></i> Test Live AI Connection</button>
          </form>
          <form action="ai-config.php" method="POST" onsubmit="return confirm('Purge routine AI logs older than 7 days? Security threat logs will be kept.')">
            <?= csrfField() ?>
            <input type="hidden" name="clear_non_flagged_logs" value="1">
            <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-broom"></i> Clean Old Logs (&gt;7d)</button>
          </form>
        </div>
      </div>

      <!-- Log Filters -->
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="ai-config.php?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-gold' : 'btn-ghost' ?>">
            All Logs (<?= (int)($logStats['total'] ?? 0) ?>)
          </a>
          <a href="ai-config.php?filter=flagged" class="btn btn-sm <?= $filter === 'flagged' ? 'btn-danger' : 'btn-ghost' ?>">
            <i class="fa-solid fa-shield-halved"></i> Flagged Threats (<?= (int)($logStats['flagged_cnt'] ?? 0) ?>)
          </a>
          <a href="ai-config.php?filter=budget" class="btn btn-sm <?= $filter === 'budget' ? 'btn-gold' : 'btn-ghost' ?>">
            Budget Recommendations
          </a>
        </div>
        <form action="ai-config.php" method="GET" style="display:flex;gap:8px;">
          <input type="hidden" name="filter" value="<?= h($filter) ?>">
          <input type="text" name="q" class="form-control" value="<?= h($search) ?>" placeholder="Search message or email..." style="min-width:220px;">
          <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
      </div>

      <!-- Threat logs and monitoring table -->
      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>User</th>
                  <th>User Message</th>
                  <th>AI Response</th>
                  <th>Type (Intent)</th>
                  <th>Flagged</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($aiLogs)): ?>
                  <tr><td colspan="6" class="text-center" style="padding:28px;color:var(--text-muted);">No matching AI logs found.</td></tr>
                <?php else: ?>
                  <?php foreach ($aiLogs as $log): ?>
                    <tr style="<?= $log['flagged'] ? 'background:rgba(233,69,96,0.06);' : '' ?>">
                      <td><?= formatDate($log['created_at'], 'M d, Y h:i A') ?></td>
                      <td><?= h($log['email'] ?: 'Visitor') ?></td>
                      <td><strong><?= h($log['user_message']) ?></strong></td>
                      <td style="font-size:0.8rem;color:var(--text-muted);max-width:300px;"><?= h(truncateText(strip_tags($log['ai_response']), 180)) ?></td>
                      <td>
                        <?php
                          $intentRaw = (string)($log['intent'] ?? '');
                          $intentClean = str_ireplace(['nvidia_nim', 'nvidia'], ['Live AI', 'AI'], $intentRaw);
                          $intentClean = str_ireplace(['budget_recommend_nvidia', 'budget_recommend_ai'], 'Room Recommender (AI)', $intentClean);
                          $intentClean = str_replace('_', ' ', $intentClean);
                          $intentLabel = strtoupper($intentClean ?: 'INQUIRY');
                        ?>
                        <span class="badge badge-info"><?= h($intentLabel) ?></span>
                      </td>
                      <td>
                        <?php if ($log['flagged']): ?>
                          <span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= h($log['flag_reason']) ?></span>
                        <?php else: ?>
                          <span class="badge badge-success">Normal</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script src="<?= h(assetUrl('js/admin.js')) ?>"></script>
</body>
</html>
