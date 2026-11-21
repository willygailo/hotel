/* ============================================================
   MARVELOUS HOTEL — Real-Time Streaming & Voice AI Chatbot UI
   assets/js/ai-chat.js
   ============================================================ */

(function () {
  const STORAGE_KEY_PREFIX = 'mh_ai_chat_v2_u';
  const VOICE_REPLY_KEY    = 'mh_ai_voice_reply';
  const VOICE_LANG_KEY     = 'mh_ai_voice_lang';

  const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition || null;
  const hasSpeechSynthesis =
    typeof window.speechSynthesis !== 'undefined' &&
    typeof window.SpeechSynthesisUtterance !== 'undefined';

  const userAgent = navigator.userAgent || '';
  const isInAppWebView = /FBAN|FBAV|Messenger|Instagram|Line\/|MicroMessenger|\bwv\b|BytedanceWebview|TikTok/i.test(userAgent);
  const isFirefoxBrowser = /Firefox\//i.test(userAgent) && !/Seamonkey/i.test(userAgent);

  let activeAbortController = null;
  let isStreaming = false;
  let recognition = null;
  let isListening = false;
  let isSpeaking = false;
  let lastInputWasVoice = false;
  let voiceReplyEnabled = localStorage.getItem(VOICE_REPLY_KEY) === '1';
  let voiceLang = localStorage.getItem(VOICE_LANG_KEY) === 'en-US' ? 'en-US' : 'fil-PH';
  let conversationTurns = []; // [{ role: 'user'|'assistant', text: string, html?: string, plain?: string, lang?: string }]

  let speechUnlocked = false;
  let cachedVoices = [];
  let activeSpeechRunId = 0;
  let speechDelayTimer = null;
  let speechWatchdogTimer = null;
  let speechKeepAliveTimer = null;
  let activeSpeakButton = null;
  let shownTtsDiagnostic = false;

  let activeRecognitionRunId = 0;
  let cancelNextVoiceSend = false;

  function getConfig() {
    return window.HOTEL_AI_CONFIG || {};
  }

  function getStorageKey() {
    const cfg = getConfig();
    return STORAGE_KEY_PREFIX + String(cfg.userId || 0);
  }

  function getChatbotEndpoint() {
    const cfg = getConfig();
    if (cfg.endpoint) return cfg.endpoint;
    if (window.HOTEL_AI_ENDPOINT) return window.HOTEL_AI_ENDPOINT;
    const isHotelSubdir = window.location.pathname.startsWith('/hotel');
    return (isHotelSubdir ? '/hotel' : '') + '/ai/chatbot.php';
  }

  function getCsrfToken() {
    const cfg = getConfig();
    if (cfg.csrfToken) return cfg.csrfToken;
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  function escHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
  }

  function isSecureOriginContext() {
    return window.isSecureContext !== false;
  }

  function getVoiceCapabilityIssue() {
    if (!isSecureOriginContext()) {
      return {
        code: 'insecure-context',
        title: 'Mic requires HTTPS / localhost',
        message:
          'Naka-block ang microphone sa plain HTTP link (tulad ng <code>http://192.168...</code>). ' +
          'Buksan ang site gamit ang <strong>HTTPS tunnel link</strong> (hal. <code>https://*.trycloudflare.com</code>) o <code>localhost</code> upang gumana ang Mic.'
      };
    }
    if (!SpeechRecognitionCtor) {
      if (isInAppWebView) {
        return {
          code: 'in-app-webview',
          title: 'Buksan sa Chrome para sa Mic',
          message:
            'Hinaharang ng Messenger / Facebook in-app browser ang Microphone at Voice features. ' +
            'I-tap ang <strong>menu (&vellip; o &bull;&bull;&bull;)</strong> sa itaas at piliin ang <strong>Open in Chrome / External Browser</strong>.'
        };
      }
      if (isFirefoxBrowser) {
        return {
          code: 'firefox-unsupported',
          title: 'Gamitin ang Chrome/Edge para sa Mic',
          message:
            'Hindi suportado ng Firefox ang built-in Web Speech Microphone. ' +
            'Buksan ang site sa <strong>Google Chrome</strong> o <strong>Microsoft Edge</strong> kung gusto mong magsalita gamit ang mic.'
        };
      }
      return {
        code: 'unsupported',
        title: 'Mic not supported on this browser',
        message:
          'Walang built-in Voice Recognition ang browser na ito. Subukan buksan sa <strong>Google Chrome</strong> o <strong>Microsoft Edge</strong>.'
      };
    }
    return null;
  }

  /**
   * Synchronously warm up/unlock speechSynthesis inside a user gesture so async SSE replies can speak.
   */
  function unlockSpeechSynthesis() {
    if (!hasSpeechSynthesis || speechUnlocked) return;
    try {
      if (window.speechSynthesis.speaking || window.speechSynthesis.pending) {
        speechUnlocked = true;
        return;
      }
      const primer = new window.SpeechSynthesisUtterance(' ');
      primer.volume = 0;
      primer.rate = 10;
      primer.onend = () => {
        speechUnlocked = true;
      };
      primer.onerror = () => {};
      window.speechSynthesis.speak(primer);
      speechUnlocked = true;
    } catch (e) {}
  }

  function refreshVoices() {
    if (!hasSpeechSynthesis) return [];
    try {
      const list = window.speechSynthesis.getVoices();
      if (Array.isArray(list) && list.length > 0) {
        cachedVoices = list;
      }
    } catch (e) {}
    return cachedVoices;
  }

  /**
   * Render safe live streaming Markdown while stripping any in-progress [[...]] action tags
   */
  function renderStreamingMarkdown(raw) {
    let cleaned = String(raw || '')
      .replace(/\[\[[^\]]*\]\]/g, '')
      .replace(/\[\[[^\]]*$/g, '');
    let safe = escHtml(cleaned);
    safe = safe.replace(/\*\*(.+?)\*\*/gs, '<strong>$1</strong>');
    safe = safe.replace(/\n/g, '<br>');
    return safe;
  }

  function saveConversation() {
    try {
      const trimmed = conversationTurns.slice(-16);
      sessionStorage.setItem(getStorageKey(), JSON.stringify(trimmed));
    } catch (e) {}
  }

  function loadSavedConversation() {
    try {
      const raw = sessionStorage.getItem(getStorageKey());
      if (!raw) return;
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed) || parsed.length === 0) return;
      conversationTurns = parsed.slice(-16);
      conversationTurns.forEach((turn) => {
        if (turn.role === 'user') {
          appendMessageElement(escHtml(turn.text || ''), true, false, '');
        } else if (turn.role === 'assistant') {
          appendMessageElement(turn.html || escHtml(turn.text || ''), false, !!turn.isWarning, turn.plain || '', turn.lang || 'tl');
        }
      });
    } catch (e) {}
  }

  function getHistoryForPayload() {
    return conversationTurns.slice(-10).map((t) => ({
      role: t.role,
      content: t.role === 'user' ? (t.text || '') : (t.plain || t.text || '')
    })).filter((t) => t.content.trim() !== '');
  }

  function setHeaderStatus(mode, text) {
    const el = document.getElementById('aiHeaderStatus');
    if (!el) return;
    const color = mode === 'streaming' ? '#facc15' : (mode === 'listening' ? '#f87171' : '#2ecc71');
    el.style.color = color;
    el.innerHTML = `<i class="fa-solid fa-circle" style="font-size:0.48rem;"></i> ${escHtml(text)}`;
  }

  function updateVoiceLangUI() {
    const label = voiceLang === 'en-US' ? 'EN' : 'PH';
    const title =
      voiceLang === 'en-US'
        ? 'Voice Language: English (US) — Click to switch to PH/Tagalog'
        : 'Voice Language: Tagalog / PH — Click to switch to English';

    const barLangBtn = document.getElementById('aiVoiceLangBtn');
    const quickLangBtn = document.getElementById('aiVoiceLangQuickBtn');
    [barLangBtn, quickLangBtn].forEach((btn) => {
      if (!btn) return;
      btn.textContent = label;
      btn.title = title;
      btn.classList.toggle('is-en', voiceLang === 'en-US');
    });
  }

  function updateMicButtonUI() {
    const micBtn = document.getElementById('aiMicBtn');
    const micIcon = document.getElementById('aiMicIcon');
    if (!micBtn || !micIcon) return;

    micBtn.style.display = 'flex';
    const issue = getVoiceCapabilityIssue();
    micBtn.classList.toggle('is-unsupported', !!issue);
    micBtn.classList.toggle('listening', isListening);
    micIcon.className = isListening ? 'fa-solid fa-microphone-lines' : 'fa-solid fa-microphone';
    micBtn.title = issue ? issue.title : (isListening ? 'Tap to send voice message' : 'Talk to Marvi (Voice Input)');
  }

  function updateVoiceBar() {
    const bar = document.getElementById('aiVoiceBar');
    const txt = document.getElementById('aiVoiceBarText');

    updateVoiceLangUI();
    updateMicButtonUI();

    if (!bar || !txt) return;

    if (isListening) {
      bar.style.display = 'flex';
      bar.className = 'ai-voice-bar is-listening';
      txt.textContent = voiceLang === 'en-US' ? 'Listening (English)... Tap mic when done' : 'Nakikinig (PH)... I-tap ang mic pag tapos';
      setHeaderStatus('listening', 'Listening via Mic...');
    } else if (isSpeaking) {
      bar.style.display = 'flex';
      bar.className = 'ai-voice-bar is-speaking';
      txt.textContent = 'Marvi is speaking...';
      setHeaderStatus('online', 'Speaking Reply...');
    } else {
      bar.style.display = 'none';
      if (!isStreaming) {
        setHeaderStatus('online', 'Real-Time Assistant · Online');
      }
    }
  }

  function updateVoiceReplyToggleUI() {
    const btn = document.getElementById('aiVoiceReplyToggle');
    const icon = document.getElementById('aiVoiceReplyIcon');
    if (!btn || !icon) return;

    btn.style.display = 'flex';
    if (!hasSpeechSynthesis) {
      btn.classList.remove('active');
      btn.classList.add('is-unsupported');
      btn.setAttribute('aria-pressed', 'false');
      icon.className = 'fa-solid fa-volume-xmark';
      btn.title = 'Text-to-Speech unavailable in this browser';
      return;
    }

    btn.classList.remove('is-unsupported');
    btn.classList.toggle('active', voiceReplyEnabled);
    btn.setAttribute('aria-pressed', voiceReplyEnabled ? 'true' : 'false');
    icon.className = voiceReplyEnabled ? 'fa-solid fa-volume-high' : 'fa-solid fa-volume-xmark';
    btn.title = voiceReplyEnabled
      ? 'AI Voice Reply: ON (Click to mute)'
      : 'AI Voice Reply: OFF (Click to enable auto-speak)';
  }

  function setSpeakButtonState(btn, speaking) {
    if (!btn) return;
    btn.classList.toggle('is-speaking', speaking);
    btn.innerHTML = speaking
      ? '<i class="fa-solid fa-stop"></i> Stop'
      : '<i class="fa-solid fa-volume-high"></i> Listen';
    btn.title = speaking ? 'Stop speaking' : 'Listen to this reply';
  }

  function clearSpeechTimers() {
    if (speechDelayTimer) {
      clearTimeout(speechDelayTimer);
      speechDelayTimer = null;
    }
    if (speechWatchdogTimer) {
      clearTimeout(speechWatchdogTimer);
      speechWatchdogTimer = null;
    }
    if (speechKeepAliveTimer) {
      clearInterval(speechKeepAliveTimer);
      speechKeepAliveTimer = null;
    }
  }

  function normalizeTextForSpeech(rawText) {
    let text = String(rawText || '').trim();
    if (!text) return '';

    // Speak Philippine peso figures naturally (e.g. "₱1,500.00" -> "1,500 pesos")
    text = text.replace(/₱\s*([\d,]+)(?:\.00)?\b/g, '$1 pesos');
    text = text.replace(/₱/g, 'pesos ');
    text = text.replace(/\s*\/gabi\b/gi, ' bawat gabi');
    text = text.replace(/\s*\/night\b/gi, ' per night');
    text = text.replace(/\s+/g, ' ').trim();
    return text;
  }

  function splitSpeechChunks(text) {
    const normalized = normalizeTextForSpeech(text);
    if (!normalized) return [];

    // Split on sentence punctuation first so Chromium never hits the ~15s cutoff bug
    const rawSentences = normalized
      .split(/(?<=[.!?])\s+|\n+/)
      .map((s) => s.trim())
      .filter(Boolean);

    const chunks = [];
    const MAX_CHUNK_LEN = 165;

    for (const sentence of rawSentences) {
      if (sentence.length <= MAX_CHUNK_LEN) {
        chunks.push(sentence);
        continue;
      }
      const words = sentence.split(/\s+/);
      let current = '';
      for (const word of words) {
        if ((current + ' ' + word).trim().length > MAX_CHUNK_LEN && current) {
          chunks.push(current.trim());
          current = word;
        } else {
          current = (current + ' ' + word).trim();
        }
      }
      if (current) chunks.push(current.trim());
    }
    return chunks;
  }

  function pickPreferredVoice(voices, langCode) {
    const targetLang = langCode === 'en' ? 'en-US' : 'fil-PH';
    if (!voices || voices.length === 0) {
      return { voice: null, lang: targetLang };
    }

    let preferred = null;
    if (langCode === 'en') {
      preferred =
        voices.find((v) => v.lang && v.lang.toLowerCase() === 'en-ph') ||
        voices.find((v) => v.lang && v.lang.toLowerCase() === 'en-us') ||
        voices.find((v) => v.lang && v.lang.toLowerCase().startsWith('en')) ||
        voices.find((v) => v.default) ||
        voices[0];
    } else {
      preferred =
        voices.find((v) => v.lang && ['fil-ph', 'tl-ph'].includes(v.lang.toLowerCase())) ||
        voices.find((v) => v.lang && /^(fil|tl)/i.test(v.lang)) ||
        voices.find((v) => v.lang && v.lang.toLowerCase() === 'en-ph') ||
        voices.find((v) => v.lang && v.lang.toLowerCase() === 'en-us') ||
        voices.find((v) => v.lang && v.lang.toLowerCase().startsWith('en')) ||
        voices.find((v) => v.default) ||
        voices[0];
    }

    return {
      voice: preferred || null,
      // Always match utter.lang to the assigned voice's language to prevent WebKit/Chromium mismatches
      lang: preferred && preferred.lang ? preferred.lang : targetLang
    };
  }

  function notifyTtsIssueOnce(errCode) {
    if (shownTtsDiagnostic) return;
    shownTtsDiagnostic = true;

    let msg;
    if (errCode === 'not-allowed') {
      msg = 'Na-block ng browser ang automatic na pagsasalita. Pindutin ang <strong>Listen</strong> button sa mensahe upang pakinggan si Marvi.';
    } else if (isInAppWebView) {
      msg = 'Walang Text-to-Speech audio sa Messenger / Facebook in-app browser. Buksan ang link sa <strong>Google Chrome</strong> para marinig si Marvi.';
    } else {
      msg = 'Walang available na Text-to-Speech voice sa browser/device na ito. Subukan sa Google Chrome na may enabled TTS/Speech engine.';
    }
    appendMessageElement(msg, false, true);
  }

  function finishSpeakingState() {
    clearSpeechTimers();
    if (activeSpeakButton) {
      setSpeakButtonState(activeSpeakButton, false);
      activeSpeakButton = null;
    }
    isSpeaking = false;
    updateVoiceBar();
  }

  function speakText(plainText, langCode, sourceBtn = null, forceEnglishFallback = false, fromManualRetry = false) {
    if (!plainText || !plainText.trim()) return;

    if (!hasSpeechSynthesis) {
      notifyTtsIssueOnce('unsupported');
      return;
    }

    // If clicking Listen on the currently speaking message, toggle stop cleanly
    if (sourceBtn && isSpeaking && activeSpeakButton === sourceBtn) {
      stopSpeaking();
      return;
    }

    const wasActive = window.speechSynthesis.speaking || window.speechSynthesis.pending;
    clearSpeechTimers();
    activeSpeechRunId++;
    const runId = activeSpeechRunId;

    if (activeSpeakButton && activeSpeakButton !== sourceBtn) {
      setSpeakButtonState(activeSpeakButton, false);
    }
    activeSpeakButton = sourceBtn;

    const chunks = splitSpeechChunks(plainText);
    if (chunks.length === 0) {
      finishSpeakingState();
      return;
    }

    if (wasActive) {
      try {
        window.speechSynthesis.cancel();
      } catch (e) {}
    }

    const startPlayback = (triedVoiceWarmup = false) => {
      if (runId !== activeSpeechRunId) return;

      const voices = refreshVoices();
      // Chromium often returns [] synchronously on first call; give voiceschanged one brief chance
      if (voices.length === 0 && !triedVoiceWarmup && !fromManualRetry) {
        speechDelayTimer = setTimeout(() => startPlayback(true), 220);
        return;
      }

      const effectiveLang = forceEnglishFallback ? 'en' : (langCode || 'tl');
      const selection = pickPreferredVoice(voices, effectiveLang);
      let chunkIdx = 0;

      // Keep long Chrome TTS utterances from pausing silently
      speechKeepAliveTimer = setInterval(() => {
        if (runId !== activeSpeechRunId) return;
        try {
          if (window.speechSynthesis.speaking) {
            window.speechSynthesis.resume();
          }
        } catch (e) {}
      }, 9000);

      const speakNextChunk = () => {
        if (runId !== activeSpeechRunId) return;
        if (chunkIdx >= chunks.length) {
          finishSpeakingState();
          return;
        }

        const utter = new window.SpeechSynthesisUtterance(chunks[chunkIdx]);
        utter.lang = selection.lang;
        if (selection.voice && !forceEnglishFallback) {
          utter.voice = selection.voice;
        }
        utter.rate = 1.0;
        utter.pitch = 1.0;

        let started = false;
        clearTimeout(speechWatchdogTimer);
        speechWatchdogTimer = setTimeout(() => {
          if (runId !== activeSpeechRunId || started) return;
          // Some Linux / WebView builds remain stuck in pending with 0 audio and no error event
          try {
            window.speechSynthesis.cancel();
          } catch (e) {}
          finishSpeakingState();
          if (voices.length === 0 || isInAppWebView) {
            notifyTtsIssueOnce('no-voices');
          }
        }, 3500);

        utter.onstart = () => {
          if (runId !== activeSpeechRunId) return;
          started = true;
          clearTimeout(speechWatchdogTimer);
          isSpeaking = true;
          setSpeakButtonState(activeSpeakButton, true);
          updateVoiceBar();
        };

        utter.onend = () => {
          if (runId !== activeSpeechRunId) return;
          clearTimeout(speechWatchdogTimer);
          chunkIdx++;
          speakNextChunk();
        };

        utter.onerror = (event) => {
          if (runId !== activeSpeechRunId) return;
          clearTimeout(speechWatchdogTimer);
          const errCode = (event && event.error) ? String(event.error) : 'synthesis-failed';
          if (errCode === 'interrupted' || errCode === 'canceled') {
            return;
          }
          // Fallback cleanly from fil-PH to en-US if language pack isn't installed on device
          if (!forceEnglishFallback && (errCode === 'language-unavailable' || errCode === 'voice-unavailable')) {
            speakText(plainText, 'en', sourceBtn, true, true);
            return;
          }
          finishSpeakingState();
          notifyTtsIssueOnce(errCode);
        };

        try {
          isSpeaking = true;
          setSpeakButtonState(activeSpeakButton, true);
          updateVoiceBar();
          window.speechSynthesis.speak(utter);
        } catch (e) {
          finishSpeakingState();
          notifyTtsIssueOnce('synthesis-failed');
        }
      };

      speakNextChunk();
    };

    // Delay briefly after cancel() to avoid Chromium's same-tick cancel()+speak() race bug
    if (wasActive) {
      speechDelayTimer = setTimeout(() => startPlayback(false), 85);
    } else {
      startPlayback(false);
    }
  }

  function stopSpeaking() {
    activeSpeechRunId++;
    if (hasSpeechSynthesis) {
      try {
        window.speechSynthesis.cancel();
      } catch (e) {}
    }
    finishSpeakingState();
  }

  function stopListening(options = {}) {
    const cancelSend = options.cancelSend !== false;
    if (cancelSend) {
      cancelNextVoiceSend = true;
    }
    if (recognition && isListening) {
      try {
        if (cancelSend && typeof recognition.abort === 'function') {
          recognition.abort();
        } else {
          recognition.stop();
        }
      } catch (e) {}
    }
    isListening = false;
    updateVoiceBar();
  }

  function attachSpeakButton(bubbleDiv, plainText, langCode) {
    if (!plainText || !plainText.trim()) return;
    const footer = document.createElement('div');
    footer.className = 'ai-msg-tools';
    const speakBtn = document.createElement('button');
    speakBtn.type = 'button';
    speakBtn.className = 'ai-msg-speak-btn';
    setSpeakButtonState(speakBtn, false);
    speakBtn.addEventListener('click', () => {
      speechUnlocked = true;
      speakText(plainText, langCode, speakBtn);
    });
    footer.appendChild(speakBtn);
    bubbleDiv.appendChild(footer);
  }

  function scrollChatToBottom(force) {
    const container = document.getElementById('aiMessages');
    if (!container) return;
    const nearBottom = (container.scrollHeight - container.scrollTop - container.clientHeight) < 140;
    if (force || nearBottom) {
      container.scrollTop = container.scrollHeight;
    }
  }

  function appendMessageElement(htmlText, isUser = false, isWarning = false, plainForSpeech = '', langCode = 'tl') {
    const container = document.getElementById('aiMessages');
    if (!container) return null;

    const msgDiv = document.createElement('div');
    msgDiv.className = `ai-msg ${isUser ? 'user' : 'bot'}`;

    const iconDiv = document.createElement('div');
    iconDiv.className = 'ai-msg-icon';
    iconDiv.innerHTML = isUser
      ? '<i class="fa-solid fa-user"></i>'
      : '<i class="fa-solid fa-robot"></i>';

    const bubbleDiv = document.createElement('div');
    bubbleDiv.className = 'ai-msg-bubble';

    if (isWarning) {
      bubbleDiv.innerHTML = `
        <div class="ai-warning-box">
          <i class="fa-solid fa-triangle-exclamation" style="font-size:1.1rem;margin-top:2px;"></i>
          <div>${htmlText}</div>
        </div>
      `;
    } else {
      bubbleDiv.innerHTML = htmlText;
      if (!isUser && plainForSpeech) {
        attachSpeakButton(bubbleDiv, plainForSpeech, langCode);
      }
    }

    msgDiv.appendChild(iconDiv);
    msgDiv.appendChild(bubbleDiv);
    container.appendChild(msgDiv);
    scrollChatToBottom(true);
    return bubbleDiv;
  }

  function createStreamingBotBubble() {
    const container = document.getElementById('aiMessages');
    if (!container) return null;

    const msgDiv = document.createElement('div');
    msgDiv.className = 'ai-msg bot';

    const iconDiv = document.createElement('div');
    iconDiv.className = 'ai-msg-icon';
    iconDiv.innerHTML = '<i class="fa-solid fa-robot"></i>';

    const bubbleDiv = document.createElement('div');
    bubbleDiv.className = 'ai-msg-bubble ai-streaming';
    bubbleDiv.innerHTML = '<span class="ai-stream-text"></span><span class="ai-stream-cursor"></span>';

    msgDiv.appendChild(iconDiv);
    msgDiv.appendChild(bubbleDiv);
    container.appendChild(msgDiv);
    scrollChatToBottom(true);
    return bubbleDiv;
  }

  function showAITyping() {
    const container = document.getElementById('aiMessages');
    if (!container || document.getElementById('aiTypingIndicator')) return;

    const typingDiv = document.createElement('div');
    typingDiv.className = 'ai-msg bot';
    typingDiv.id = 'aiTypingIndicator';
    typingDiv.innerHTML = `
      <div class="ai-msg-icon"><i class="fa-solid fa-robot"></i></div>
      <div class="ai-typing"><span></span><span></span><span></span></div>
    `;
    container.appendChild(typingDiv);
    scrollChatToBottom(true);
  }

  function removeAITyping() {
    const indicator = document.getElementById('aiTypingIndicator');
    if (indicator) indicator.remove();
  }

  function setStreamingState(active) {
    isStreaming = active;
    const input = document.getElementById('aiInput');
    const sendBtn = document.getElementById('aiSendBtn');
    const sendIcon = document.getElementById('aiSendIcon');
    const micBtn = document.getElementById('aiMicBtn');

    if (input) input.disabled = active;
    if (micBtn) micBtn.disabled = active;
    if (sendBtn && sendIcon) {
      sendBtn.classList.toggle('is-stop', active);
      sendIcon.className = active ? 'fa-solid fa-stop' : 'fa-solid fa-paper-plane';
      sendBtn.setAttribute('aria-label', active ? 'Stop generating' : 'Send message');
      sendBtn.title = active ? 'Stop generating' : 'Send message';
    }
    if (active) {
      setHeaderStatus('streaming', 'Marvi is replying live...');
    } else if (!isListening && !isSpeaking) {
      setHeaderStatus('online', 'Real-Time Assistant · Online');
    }
  }

  async function sendAIMessage(fromVoice = false) {
    const input = document.getElementById('aiInput');
    if (!input || isStreaming) return;

    const message = input.value.trim();
    if (!message) return;

    lastInputWasVoice = !!fromVoice;
    if (!fromVoice && voiceReplyEnabled) {
      unlockSpeechSynthesis();
    }
    stopSpeaking();
    stopListening({ cancelSend: true });

    appendMessageElement(escHtml(message), true);
    const historySnapshot = getHistoryForPayload();
    conversationTurns.push({ role: 'user', text: message });
    saveConversation();

    input.value = '';
    setStreamingState(true);
    showAITyping();

    activeAbortController = new AbortController();
    let streamBubble = null;
    let rawStreamText = '';
    let finalized = false;

    const finalizeReply = (fullHtml, plainText, langCode, isWarning = false) => {
      if (finalized) return;
      finalized = true;
      removeAITyping();

      let targetBubble = null;
      if (streamBubble) {
        streamBubble.classList.remove('ai-streaming');
        if (isWarning) {
          streamBubble.innerHTML = `
            <div class="ai-warning-box">
              <i class="fa-solid fa-triangle-exclamation" style="font-size:1.1rem;margin-top:2px;"></i>
              <div>${fullHtml}</div>
            </div>
          `;
        } else {
          streamBubble.innerHTML = fullHtml;
          attachSpeakButton(streamBubble, plainText, langCode);
        }
        targetBubble = streamBubble;
      } else {
        targetBubble = appendMessageElement(fullHtml, false, isWarning, plainText, langCode);
      }

      scrollChatToBottom(true);

      if (!isWarning) {
        conversationTurns.push({
          role: 'assistant',
          html: fullHtml,
          plain: plainText,
          text: plainText,
          lang: langCode
        });
        saveConversation();

        if ((voiceReplyEnabled || lastInputWasVoice) && plainText) {
          const speakBtn = targetBubble ? targetBubble.querySelector('.ai-msg-speak-btn') : null;
          speakText(plainText, langCode, speakBtn);
        }
      }
    };

    try {
      const endpoint = getChatbotEndpoint();
      const streamUrl = endpoint + (endpoint.includes('?') ? '&' : '?') + 'stream=1';
      const csrfToken = getCsrfToken();

      const res = await fetch(streamUrl, {
        method: 'POST',
        credentials: 'same-origin',
        signal: activeAbortController.signal,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/event-stream, application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
          message: message,
          stream: true,
          history: historySnapshot,
          csrf_token: csrfToken
        })
      });

      const contentType = (res.headers.get('content-type') || '').toLowerCase();

      // Path 1: Real-time SSE token-by-token stream
      if (res.ok && contentType.includes('text/event-stream') && res.body) {
        const reader = res.body.getReader();
        const decoder = new TextDecoder('utf-8');
        let buffer = '';
        let currentEvent = 'message';
        let detectedLang = 'tl';

        while (true) {
          const { value, done } = await reader.read();
          if (done) break;

          buffer += decoder.decode(value, { stream: true });
          const lines = buffer.split(/\r?\n/);
          buffer = lines.pop() || '';

          for (const line of lines) {
            if (line.startsWith('event:')) {
              currentEvent = line.slice(6).trim();
            } else if (line.startsWith('data:')) {
              const jsonStr = line.slice(5).trim();
              if (!jsonStr) continue;
              let payload = null;
              try {
                payload = JSON.parse(jsonStr);
              } catch (e) {
                continue;
              }

              if (currentEvent === 'meta') {
                if (payload.lang) detectedLang = payload.lang;
              } else if (currentEvent === 'delta') {
                const chunk = payload.text || '';
                if (chunk) {
                  if (!streamBubble) {
                    removeAITyping();
                    streamBubble = createStreamingBotBubble();
                  }
                  rawStreamText += chunk;
                  const textSpan = streamBubble.querySelector('.ai-stream-text');
                  if (textSpan) {
                    textSpan.innerHTML = renderStreamingMarkdown(rawStreamText);
                  }
                  scrollChatToBottom(false);
                }
              } else if (currentEvent === 'error') {
                finalizeReply(
                  payload.message || 'Paumanhin, may problema sa pagproseso ng iyong mensahe.',
                  '',
                  payload.lang || detectedLang,
                  !!payload.is_alert
                );
              } else if (currentEvent === 'done') {
                const finalHtml = payload.full_html || renderStreamingMarkdown(rawStreamText);
                const plainText = payload.plain_text || rawStreamText;
                finalizeReply(finalHtml, plainText, payload.lang || detectedLang, false);
              }
            } else if (line === '') {
              currentEvent = 'message';
            }
          }
        }

        // If stream closed before explicit done event, finalize gracefully with partial output
        if (!finalized) {
          if (rawStreamText.trim()) {
            const html = renderStreamingMarkdown(rawStreamText);
            finalizeReply(html, rawStreamText.replace(/\[\[[^\]]*\]\]/g, '').trim(), detectedLang, false);
          } else {
            finalizeReply('Paumanhin, walang naibalik na tugon. Pakisubukan muli.', '', detectedLang, false);
          }
        }
      } else {
        // Path 2: JSON fallback if proxy or server returned standard JSON
        const data = await res.json().catch(() => ({}));
        if (!res.ok && !data.response) {
          throw new Error(`HTTP ${res.status}`);
        }
        const isWarn = data.status === 'warning' || !!data.is_alert;
        finalizeReply(
          data.response || 'Paumanhin, walang naibalik na tugon. Maaari mong subukan muli.',
          data.plain_text || '',
          data.lang || 'tl',
          isWarn
        );
      }
    } catch (err) {
      removeAITyping();
      if (err && err.name === 'AbortError') {
        if (streamBubble && rawStreamText.trim()) {
          finalizeReply(renderStreamingMarkdown(rawStreamText), rawStreamText.trim(), 'tl', false);
        } else if (streamBubble) {
          streamBubble.closest('.ai-msg')?.remove();
        }
      } else {
        console.error('AI Chatbot error:', err);
        finalizeReply(
          'Paumanhin, nagkaroon ng sandaling aberya sa koneksyon ng aming AI assistant. Pakisubukang magtanong muli.',
          '',
          'tl',
          false
        );
      }
    } finally {
      activeAbortController = null;
      setStreamingState(false);
      if (input) input.focus();
    }
  }

  function handleAISendOrStop() {
    if (isStreaming && activeAbortController) {
      activeAbortController.abort();
      return;
    }
    if (voiceReplyEnabled) {
      unlockSpeechSynthesis();
    }
    sendAIMessage(false);
  }

  function sendQuickPrompt(promptText) {
    const input = document.getElementById('aiInput');
    if (!input || isStreaming) return;
    if (voiceReplyEnabled) {
      unlockSpeechSynthesis();
    }
    input.value = promptText;
    sendAIMessage(false);
  }

  function toggleVoiceReply() {
    if (!hasSpeechSynthesis) {
      notifyTtsIssueOnce('unsupported');
      return;
    }
    unlockSpeechSynthesis();
    voiceReplyEnabled = !voiceReplyEnabled;
    localStorage.setItem(VOICE_REPLY_KEY, voiceReplyEnabled ? '1' : '0');
    if (!voiceReplyEnabled) {
      stopSpeaking();
    } else if (refreshVoices().length === 0) {
      setHeaderStatus('online', 'Voice Reply ON (checking voices...)');
    }
    updateVoiceReplyToggleUI();
  }

  function toggleVoiceLang() {
    voiceLang = voiceLang === 'fil-PH' ? 'en-US' : 'fil-PH';
    localStorage.setItem(VOICE_LANG_KEY, voiceLang);
    if (isListening) {
      stopListening({ cancelSend: true });
      setTimeout(() => startVoiceInput(), 200);
    } else {
      updateVoiceBar();
    }
  }

  function formatRecognitionErrorMessage(errCode) {
    switch (errCode) {
      case 'not-allowed':
      case 'service-not-allowed': {
        const envIssue = getVoiceCapabilityIssue();
        if (envIssue) return envIssue.message;
        if (isInAppWebView) {
          return 'Naka-block ang microphone sa in-app browser. I-tap ang menu at piliing <strong>Open in Chrome</strong>.';
        }
        return 'Naka-block ang microphone permission sa iyong browser. Paki-allow ang Mic access sa address bar icon upang makapagsalita kay Marvi.';
      }
      case 'audio-capture':
        return 'Walang ma-detect na microphone sa device mo, o ginagamit ito ng ibang app.';
      case 'network':
        return 'Hindi makakonekta sa Speech Recognition service ng browser. Pakisiguro ang internet connection o subukan mag-type muna.';
      case 'language-not-supported':
        return 'Hindi suportado ng device ang napiling voice language. Ilipat ito sa EN sa header pill at subukan muli.';
      case 'no-speech':
        return 'Walang boses na narinig. Pindutin ang Mic button at magsalita nang malinaw malapit sa mic.';
      default:
        return `Hindi nag-umpisa ang voice input (${escHtml(errCode)}). Subukan muli o i-type ang iyong tanong.`;
    }
  }

  function startVoiceInput(retriedEnglishFallback = false) {
    if (isStreaming) return;

    const issue = getVoiceCapabilityIssue();
    if (issue) {
      appendMessageElement(issue.message, false, true);
      return;
    }

    stopSpeaking();
    unlockSpeechSynthesis();

    const runId = ++activeRecognitionRunId;
    cancelNextVoiceSend = false;
    let hadError = false;
    let gotAnyTranscript = false;

    try {
      const rec = new SpeechRecognitionCtor();
      recognition = rec;
      rec.lang = voiceLang;
      rec.interimResults = true;
      rec.continuous = false;
      rec.maxAlternatives = 1;

      const input = document.getElementById('aiInput');
      let startedOnce = false;

      rec.onstart = () => {
        if (runId !== activeRecognitionRunId) return;
        startedOnce = true;
        isListening = true;
        updateVoiceBar();
      };

      rec.onresult = (event) => {
        if (runId !== activeRecognitionRunId) return;
        let fullTranscript = '';
        for (let i = 0; i < event.results.length; i++) {
          fullTranscript += event.results[i][0].transcript;
        }
        const cleaned = fullTranscript.trim();
        if (cleaned) {
          gotAnyTranscript = true;
        }
        if (input) {
          input.value = cleaned;
        }
      };

      rec.onerror = (event) => {
        if (runId !== activeRecognitionRunId) return;
        const errCode = (event && event.error) ? String(event.error) : 'unknown';

        // Ignore benign abort when user manually stopped/switched languages
        if (errCode === 'aborted' && cancelNextVoiceSend) {
          isListening = false;
          updateVoiceBar();
          return;
        }

        // Automatically fallback once from fil-PH to en-US if device lacks Filipino recognition pack
        if (errCode === 'language-not-supported' && voiceLang === 'fil-PH' && !retriedEnglishFallback) {
          hadError = true;
          isListening = false;
          voiceLang = 'en-US';
          localStorage.setItem(VOICE_LANG_KEY, voiceLang);
          updateVoiceBar();
          appendMessageElement(
            'Walang <code>fil-PH</code> voice recognition pack sa device na ito — awtomatikong inilipat sa <strong>EN (en-US)</strong> at sinusubukan muli.',
            false,
            false
          );
          setTimeout(() => startVoiceInput(true), 220);
          return;
        }

        hadError = true;
        isListening = false;
        updateVoiceBar();

        // Don't complain about no-speech if user already produced text or manually cancelled
        if (errCode === 'no-speech' && gotAnyTranscript) {
          return;
        }
        appendMessageElement(formatRecognitionErrorMessage(errCode), false, true);
      };

      rec.onend = () => {
        if (runId !== activeRecognitionRunId) return;
        const shouldSend = startedOnce && !cancelNextVoiceSend && !hadError;
        isListening = false;
        recognition = null;
        updateVoiceBar();

        if (shouldSend && input && input.value.trim() !== '') {
          sendAIMessage(true);
        }
      };

      // Optimistic listening state so tapping mic again cleanly stops even during startup
      isListening = true;
      updateVoiceBar();
      rec.start();
    } catch (e) {
      isListening = false;
      recognition = null;
      updateVoiceBar();
      appendMessageElement(
        'Hindi mabuksan ang microphone sa browser na ito. Pakisubukan i-refresh ang page o gamitin ang Google Chrome.',
        false,
        true
      );
    }
  }

  function toggleVoiceInput() {
    unlockSpeechSynthesis();
    if (isListening) {
      // Stopping mic manually by tapping the mic button submits any captured text
      stopListening({ cancelSend: false });
    } else {
      startVoiceInput(false);
    }
  }

  function stopMarviVoiceAll() {
    stopListening({ cancelSend: true });
    stopSpeaking();
  }

  function openAIChat() {
    const box = document.getElementById('aiChatBox');
    const icon = document.getElementById('aiFabIcon');
    if (box) {
      box.classList.add('open');
      scrollChatToBottom(true);
      const input = document.getElementById('aiInput');
      if (input) setTimeout(() => input.focus(), 220);
    }
    if (icon) icon.className = 'fa-solid fa-xmark';
  }

  function closeAIChat() {
    const box = document.getElementById('aiChatBox');
    const icon = document.getElementById('aiFabIcon');
    stopMarviVoiceAll();
    if (box) box.classList.remove('open');
    if (icon) icon.className = 'fa-solid fa-robot';
  }

  function toggleAIChat() {
    const box = document.getElementById('aiChatBox');
    if (box && box.classList.contains('open')) {
      closeAIChat();
    } else {
      openAIChat();
    }
  }

  async function resetAIChat() {
    if (isStreaming && activeAbortController) {
      activeAbortController.abort();
    }
    stopMarviVoiceAll();
    shownTtsDiagnostic = false;
    conversationTurns = [];
    try {
      sessionStorage.removeItem(getStorageKey());
    } catch (e) {}

    const container = document.getElementById('aiMessages');
    if (container) {
      const greeting = container.querySelector('.ai-msg.bot[data-initial-greeting="1"]') || container.querySelector('.ai-msg.bot');
      if (greeting) {
        const clone = greeting.cloneNode(true);
        container.innerHTML = '';
        container.appendChild(clone);
      }
    }
    const input = document.getElementById('aiInput');
    if (input) input.value = '';

    // Also reset server-side session memory
    try {
      await fetch(getChatbotEndpoint(), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': getCsrfToken()
        },
        body: JSON.stringify({ action: 'reset', csrf_token: getCsrfToken() })
      });
    } catch (e) {}
  }

  // Initialize UI & restore conversation across pages
  document.addEventListener('DOMContentLoaded', () => {
    refreshVoices();
    if (hasSpeechSynthesis) {
      if (typeof window.speechSynthesis.onvoiceschanged !== 'undefined') {
        window.speechSynthesis.onvoiceschanged = () => {
          refreshVoices();
          updateVoiceReplyToggleUI();
        };
      }
      if (typeof window.speechSynthesis.addEventListener === 'function') {
        window.speechSynthesis.addEventListener('voiceschanged', () => {
          refreshVoices();
          updateVoiceReplyToggleUI();
        });
      }
    }

    updateVoiceLangUI();
    updateMicButtonUI();
    updateVoiceReplyToggleUI();
    loadSavedConversation();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const box = document.getElementById('aiChatBox');
      if (box && box.classList.contains('open')) {
        closeAIChat();
      }
    }
  });

  // Expose clean global hooks for inline button handlers
  window.openAIChat = openAIChat;
  window.closeAIChat = closeAIChat;
  window.toggleAIChat = toggleAIChat;
  window.sendAIMessage = sendAIMessage;
  window.handleAISendOrStop = handleAISendOrStop;
  window.sendQuickPrompt = sendQuickPrompt;
  window.resetAIChat = resetAIChat;
  window.toggleVoiceInput = toggleVoiceInput;
  window.toggleVoiceReply = toggleVoiceReply;
  window.toggleVoiceLang = toggleVoiceLang;
  window.stopMarviVoiceAll = stopMarviVoiceAll;
})();
