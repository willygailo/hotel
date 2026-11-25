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
        // Invalidate cached Staff/Admin AI digests so new model/enable status applies immediately
        $cacheDir = SITE_ROOT . '/storage/cache';
        @unlink($cacheDir . '/admin_insights.json');
        foreach (glob($cacheDir . '/staff_assistant_*.json') ?: [] as $cacheFile) {
            @unlink($cacheFile);
        }
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
$hasEnvKey = trim((string)(getenv('NVIDIA_API_KEY') ?: '')) !== '';
$hasDbKey  = trim((string)getSetting('ai_nvidia_key', '')) !== '';
$hasKey    = $hasEnvKey || $hasDbKey;

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
  <style>
    .ai-config-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 18px;
      align-items: start;
    }
    @media (max-width: 960px) {
      .ai-config-grid {
        grid-template-columns: 1fr;
      }
    }
    .ai-config-field {
      display: flex;
      flex-direction: column;
    }
    .ai-config-field .form-label {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      min-height: 18px;
      margin-bottom: 8px;
      white-space: nowrap;
    }
    .ai-config-field .form-control {
      height: 46px;
      padding: 10px 14px;
      font-size: 0.94rem;
    }
    .ai-select-wrap {
      position: relative;
    }
    .ai-select-wrap .form-control {
      padding-right: 38px;
    }
    .ai-select-chevron {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: 0.8rem;
      pointer-events: none;
    }
    .ai-key-wrap {
      position: relative;
    }
    .ai-key-wrap .form-control {
      padding-right: 42px;
    }
    .ai-key-toggle {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      padding: 4px 6px;
      line-height: 1;
    }
    .ai-key-toggle:hover {
      color: var(--gold);
    }
    .ai-field-note {
      margin-top: 8px;
      font-size: 0.75rem;
      color: var(--text-muted);
      line-height: 1.4;
    }
    .ai-model-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 8px;
    }
    .ai-model-chip {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: #cbd5e1;
      border-radius: 999px;
      padding: 3px 10px;
      font-size: 0.72rem;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .ai-model-chip:hover {
      border-color: var(--gold);
      color: var(--gold);
      background: rgba(201, 168, 76, 0.1);
    }
    .ai-action-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 20px;
      padding-top: 16px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    .ai-action-group {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
    }
  </style>
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
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:18px;">
          <div>
            <h3 style="font-family:var(--font-serif);font-size:1.15rem;color:var(--gold);margin:0;">
              <i class="fa-solid fa-sliders"></i> Marvi AI Configuration
            </h3>
            <div style="font-size:0.8rem;color:var(--text-muted);margin-top:3px;">
              Control live inference status, active NVIDIA NIM model, and API credentials.
            </div>
          </div>
          <?php
            $keyBadgeText = $hasDbKey && $hasEnvKey
              ? 'DB + Env Key Active'
              : ($hasDbKey ? 'DB API Key Configured' : ($hasEnvKey ? 'Env API Key Active' : 'Local Fallback Only (No API Key)'));
          ?>
          <span class="badge <?= $hasKey ? 'badge-success' : 'badge-warning' ?>">
            <i class="fa-solid <?= $hasKey ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i> <?= h($keyBadgeText) ?>
          </span>
        </div>

        <form id="ai-config-form" action="ai-config.php" method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="save_ai_config" value="1">
          <div class="ai-config-grid">
            <div class="ai-config-field">
              <label class="form-label" for="ai_enabled_select">AI Live Engine Status</label>
              <div class="ai-select-wrap">
                <select id="ai_enabled_select" name="ai_enabled" class="form-control">
                  <option value="1" <?= $aiEnabled === '1' ? 'selected' : '' ?>>Enabled (Live AI + Local Fallback)</option>
                  <option value="0" <?= $aiEnabled === '0' ? 'selected' : '' ?>>Disabled (Local FAQ &amp; Rules Only)</option>
                </select>
                <i class="fa-solid fa-chevron-down ai-select-chevron"></i>
              </div>
              <div class="ai-field-note">
                Automatic failover stays active even if cloud inference times out.
              </div>
            </div>

            <div class="ai-config-field">
              <label class="form-label" for="ai_nvidia_model_input">NVIDIA NIM Model ID</label>
              <input type="text" id="ai_nvidia_model_input" name="ai_nvidia_model" list="nvidiaModelPresets" class="form-control" value="<?= h($aiModel) ?>" placeholder="<?= h(NVIDIA_DEFAULT_MODEL) ?>" required>
              <datalist id="nvidiaModelPresets">
                <option value="nvidia/nemotron-3.5-bolt-e4b">Recommended Fast Live Chat (Default)</option>
                <option value="nvidia/nemotron-3.5-lightning-30b-a3b">Nemotron 3.5 Lightning 30B</option>
                <option value="minimaxai/minimax-m3.1-flash">MiniMax M3.1 Flash</option>
                <option value="mistralai/mistral-large-4-1t-a52b">Mistral Large 4</option>
              </datalist>
              <div class="ai-model-chips">
                <button type="button" class="ai-model-chip" onclick="setNvidiaModelPreset('nvidia/nemotron-3.5-bolt-e4b')">Bolt E4B (Default)</button>
                <button type="button" class="ai-model-chip" onclick="setNvidiaModelPreset('nvidia/nemotron-3.5-lightning-30b-a3b')">Lightning 30B</button>
                <button type="button" class="ai-model-chip" onclick="setNvidiaModelPreset('minimaxai/minimax-m3.1-flash')">MiniMax Flash</button>
              </div>
            </div>

            <div class="ai-config-field">
              <label class="form-label" for="ai_nvidia_key_input">
                <span>Update NVIDIA API Key</span>
                <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:0.74rem;">(leave blank to keep)</span>
              </label>
              <div class="ai-key-wrap">
                <input type="password" id="ai_nvidia_key_input" name="ai_nvidia_key" class="form-control" placeholder="<?= $hasKey ? '•••••••••••••••• (Configured)' : 'nvapi-...' ?>" autocomplete="new-password">
                <button type="button" class="ai-key-toggle" onclick="toggleAiKeyVisibility()" aria-label="Toggle API key visibility">
                  <i class="fa-solid fa-eye" id="ai_key_eye_icon"></i>
                </button>
              </div>
              <?php if ($hasDbKey): ?>
                <label class="ai-field-note" style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                  <input type="checkbox" name="clear_ai_key" value="1"> Remove saved DB API key
                </label>
              <?php else: ?>
                <div class="ai-field-note">
                  <?= $hasEnvKey ? 'Using server environment variable (NVIDIA_API_KEY).' : 'Paste your nvapi-... key to enable live NVIDIA NIM.' ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </form>

        <div class="ai-action-bar">
          <div class="ai-action-group">
            <form action="ai-config.php" method="POST" style="margin:0;">
              <?= csrfField() ?>
              <input type="hidden" name="test_ai_connection" value="1">
              <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-plug-circle-check"></i> Test Live AI Connection</button>
            </form>
            <form action="ai-config.php" method="POST" style="margin:0;" onsubmit="return confirm('Purge routine AI logs older than 7 days? Security threat logs will be kept.')">
              <?= csrfField() ?>
              <input type="hidden" name="clear_non_flagged_logs" value="1">
              <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-broom"></i> Clean Old Logs (&gt;7d)</button>
            </form>
          </div>
          <div class="ai-action-group">
            <button type="submit" form="ai-config-form" class="btn btn-gold"><i class="fa-solid fa-save"></i> Save AI Config</button>
          </div>
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
<script>
function toggleAiKeyVisibility() {
  const input = document.getElementById('ai_nvidia_key_input');
  const icon = document.getElementById('ai_key_eye_icon');
  if (!input || !icon) return;
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}
function setNvidiaModelPreset(modelId) {
  const input = document.getElementById('ai_nvidia_model_input');
  if (input) {
    input.value = modelId;
    input.focus();
  }
}
</script>
</body>
</html>
