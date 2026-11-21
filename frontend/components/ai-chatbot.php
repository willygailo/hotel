<?php
// ============================================================
// MARVELOUS HOTEL — Real-Time Interactive AI Assistant Widget
// includes/components/ai-chatbot.php
// ============================================================
$hotelName = getSetting('hotel_name', 'Marvelous Hotel');
$csrfToken = generateCsrfToken();
$activeUserName = $_SESSION['user_name'] ?? '';
$activeUserId = currentUserId() ?: 0;
$isUserLoggedIn = isLoggedIn();
$aiEndpoint = SITE_URL . '/ai/chatbot.php';
?>
<!-- AI Chatbot Stylesheet -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/ai-chat.css">

<!-- AI Chatbot Floating Widget -->
<div class="ai-fab" id="aiFab">
  <div class="ai-chat-box" id="aiChatBox" role="dialog" aria-label="Marvi Real-Time AI Assistant">
    <!-- Header -->
    <div class="ai-chat-header">
      <div style="display:flex;align-items:center;gap:10px;min-width:0;flex:1;">
        <div class="ai-chat-avatar">
          <i class="fa-solid fa-robot"></i>
        </div>
        <div style="min-width:0;">
          <div class="ai-chat-name">
            Marvi <span class="ai-chat-badge">Live AI</span>
          </div>
          <div class="ai-chat-status" id="aiHeaderStatus">
            <i class="fa-solid fa-circle" style="font-size:0.48rem;"></i> Real-Time Assistant &middot; Online
          </div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:5px;flex-shrink:0;">
        <button type="button" class="ai-chat-btn-action" id="aiVoiceReplyToggle" onclick="toggleVoiceReply()" title="Toggle AI Voice Reply (Speak Back)" aria-label="Toggle voice reply" style="display:none;">
          <i class="fa-solid fa-volume-xmark" id="aiVoiceReplyIcon"></i>
        </button>
        <button type="button" class="ai-chat-btn-action" onclick="resetAIChat()" title="Start new conversation" aria-label="Restart chat">
          <i class="fa-solid fa-rotate-right"></i>
        </button>
        <button type="button" class="ai-chat-close" onclick="closeAIChat()" aria-label="Close chat">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    </div>

    <!-- Live Voice Status Bar (shown only while listening or speaking) -->
    <div class="ai-voice-bar" id="aiVoiceBar" style="display:none;">
      <div class="ai-voice-bar-left">
        <span class="ai-voice-wave" id="aiVoiceWave"><span></span><span></span><span></span></span>
        <span id="aiVoiceBarText">Listening...</span>
      </div>
      <div class="ai-voice-bar-right">
        <button type="button" class="ai-voice-lang-pill" id="aiVoiceLangBtn" onclick="toggleVoiceLang()" title="Switch speech language">PH</button>
        <button type="button" class="ai-voice-stop-pill" onclick="stopMarviVoiceAll()" title="Stop">
          <i class="fa-solid fa-stop"></i> Stop
        </button>
      </div>
    </div>

    <!-- Messages Area -->
    <div class="ai-chat-messages" id="aiMessages">
      <div class="ai-msg bot" data-initial-greeting="1">
        <div class="ai-msg-icon"><i class="fa-solid fa-robot"></i></div>
        <div class="ai-msg-bubble">
          <?php if ($isUserLoggedIn && !empty($activeUserName)): ?>
            Kumusta, <strong><?= h($activeUserName) ?></strong>! 👋 Ako si <strong>Marvi</strong>, your real-time AI concierge at <?= h($hotelName) ?>.<br><br>
            Pwede mo akong kausapin sa <strong>Tagalog</strong> o <strong>English</strong> (type o mic 🎤) — matutulungan kita sa paghahanap ng kwarto, booking status, pagbabayad, o diretsong pagpunta sa kahit anong page!
          <?php else: ?>
            Kumusta! Hello! 👋 Ako si <strong>Marvi</strong>, your real-time AI concierge at <?= h($hotelName) ?>.<br><br>
            Pwede mo akong kausapin nang natural sa <strong>Tagalog</strong> o <strong>English</strong> (mag-type o gamitin ang mic 🎤). Sabihin lang ang hanap mo at pwede kitang dalhin agad sa tamang kwarto!
          <?php endif; ?>

          <div class="ai-chips-container">
            <div class="ai-chips-label">Quick Inquiries / Mabilisang Tanong:</div>
            <div class="ai-chips-wrap">
              <?php if ($isUserLoggedIn): ?>
                <button type="button" class="ai-chip" onclick="sendQuickPrompt('Kamusta ang booking ko?')">
                  <i class="fa-solid fa-receipt" style="color:var(--gold);"></i> 🇵🇭 Kamusta ang booking ko?
                </button>
              <?php endif; ?>
              <button type="button" class="ai-chip" onclick="sendQuickPrompt('1k ano maganda room')">
                <i class="fa-solid fa-coins" style="color:var(--gold);"></i> 🇵🇭 1k Badyet: Anong magandang room?
              </button>
              <button type="button" class="ai-chip" onclick="sendQuickPrompt('May available bang kwarto para sa mag-asawa o 2 tao?')">
                <i class="fa-solid fa-user-group" style="color:#60a5fa;"></i> 🇵🇭 Pang 2 Tao / Mag-asawa
              </button>
              <button type="button" class="ai-chip" onclick="sendQuickPrompt('Magkano ang pinakamurang dormitory bed?')">
                <i class="fa-solid fa-bed" style="color:#4ade80;"></i> 🇵🇭 Murang Dormitory Bed
              </button>
              <button type="button" class="ai-chip" onclick="sendQuickPrompt('Saan ang location ninyo at paano pumunta?')">
                <i class="fa-solid fa-location-dot" style="color:#f87171;"></i> 🇵🇭 Location &amp; Directions
              </button>
              <button type="button" class="ai-chip" onclick="sendQuickPrompt('Paano magbayad gamit ang GCash o Maya?')">
                <i class="fa-solid fa-credit-card" style="color:#38bdf8;"></i> 🇵🇭 Paano magbayad?
              </button>
              <button type="button" class="ai-chip" onclick="sendQuickPrompt('What are the check-in and check-out times?')">
                <i class="fa-solid fa-clock"></i> 🇬🇧 Check-in/out times
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Input Bar -->
    <div class="ai-chat-input-wrap">
      <button type="button" class="ai-chat-mic" id="aiMicBtn" onclick="toggleVoiceInput()" title="Talk to Marvi (Voice Input)" aria-label="Voice input" style="display:none;">
        <i class="fa-solid fa-microphone" id="aiMicIcon"></i>
      </button>
      <input
        type="text"
        class="ai-chat-input"
        id="aiInput"
        placeholder="Kausapin si Marvi (Tagalog / English)..."
        autocomplete="off"
        onkeydown="if(event.key==='Enter'){ event.preventDefault(); sendAIMessage(); }"
      >
      <button type="button" class="ai-chat-send" id="aiSendBtn" onclick="handleAISendOrStop()" aria-label="Send message">
        <i class="fa-solid fa-paper-plane" id="aiSendIcon"></i>
      </button>
    </div>
  </div>

  <!-- Floating Trigger Button -->
  <button type="button" class="ai-fab-btn" onclick="toggleAIChat()" id="aiFabBtn" aria-label="Toggle AI Chat">
    <i class="fa-solid fa-robot" id="aiFabIcon"></i>
    <div class="ai-pulse"></div>
  </button>
</div>

<script>
window.HOTEL_AI_CONFIG = <?= json_encode([
    'endpoint'   => $aiEndpoint,
    'csrfToken'  => $csrfToken,
    'isLoggedIn' => $isUserLoggedIn,
    'userId'     => $activeUserId,
    'userName'   => $activeUserName,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<!-- AI Chatbot Client Script -->
<script src="<?= ASSETS_URL ?>/js/ai-chat.js"></script>
