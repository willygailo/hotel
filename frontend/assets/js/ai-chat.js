/* ============================================================
   MARVELOUS HOTEL — Real-Time Streaming & Voice AI Chatbot UI
   assets/js/ai-chat.js
   ============================================================ */

(function () {
  const STORAGE_KEY_PREFIX = 'mh_ai_chat_v2_u';
  const VOICE_REPLY_KEY    = 'mh_ai_voice_reply';
  const VOICE_LANG_KEY     = 'mh_ai_voice_lang';

  const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition || null;
  const hasSpeechSynthesis = typeof window.speechSynthesis !== 'undefined';

  let activeAbortController = null;
  let isStreaming = false;
  let recognition = null;
  let isListening = false;
  let isSpeaking = false;
  let lastInputWasVoice = false;
  let voiceReplyEnabled = localStorage.getItem(VOICE_REPLY_KEY) === '1';
  let voiceLang = localStorage.getItem(VOICE_LANG_KEY) || 'fil-PH';
  let conversationTurns = []; // [{ role: 'user'|'assistant', text: string, html?: string, plain?: string, lang?: string }]

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

  function updateVoiceBar() {
    const bar = document.getElementById('aiVoiceBar');
    const txt = document.getElementById('aiVoiceBarText');
    const langBtn = document.getElementById('aiVoiceLangBtn');
    const micBtn = document.getElementById('aiMicBtn');
    const micIcon = document.getElementById('aiMicIcon');

    if (langBtn) {
      langBtn.textContent = voiceLang === 'en-US' ? 'EN' : 'PH';
    }

    if (micBtn && micIcon) {
      micBtn.classList.toggle('listening', isListening);
      micIcon.className = isListening ? 'fa-solid fa-microphone-lines' : 'fa-solid fa-microphone';
    }

    if (!bar || !txt) return;

    if (isListening) {
      bar.style.display = 'flex';
      bar.className = 'ai-voice-bar is-listening';
      txt.textContent = voiceLang === 'en-US' ? 'Listening (English)...' : 'Nakikinig (Tagalog / PH)...';
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
    if (!hasSpeechSynthesis) {
      btn.style.display = 'none';
      return;
    }
    btn.style.display = 'flex';
    btn.classList.toggle('active', voiceReplyEnabled);
    icon.className = voiceReplyEnabled ? 'fa-solid fa-volume-high' : 'fa-solid fa-volume-xmark';
    btn.title = voiceReplyEnabled
      ? 'AI Voice Reply: ON (Click to mute)'
      : 'AI Voice Reply: OFF (Click to enable)';
  }

  function speakText(plainText, langCode) {
    if (!hasSpeechSynthesis || !plainText || !plainText.trim()) return;
    try {
      window.speechSynthesis.cancel();
      const utter = new SpeechSynthesisUtterance(plainText.trim());
      const targetLang = langCode === 'en' ? 'en-US' : 'fil-PH';
      utter.lang = targetLang;
      utter.rate = 1.02;
      utter.pitch = 1.0;

      const voices = window.speechSynthesis.getVoices() || [];
      if (voices.length > 0) {
        const preferred =
          voices.find((v) => v.lang && v.lang.toLowerCase() === targetLang.toLowerCase()) ||
          voices.find((v) => v.lang && v.lang.toLowerCase().startsWith(langCode === 'en' ? 'en' : 'fil')) ||
          voices.find((v) => v.lang && v.lang.toLowerCase().startsWith(langCode === 'en' ? 'en' : 'tl')) ||
          voices.find((v) => v.lang && v.lang.toLowerCase().startsWith('en'));
        if (preferred) utter.voice = preferred;
      }

      utter.onstart = () => {
        isSpeaking = true;
        updateVoiceBar();
      };
      utter.onend = () => {
        isSpeaking = false;
        updateVoiceBar();
      };
      utter.onerror = () => {
        isSpeaking = false;
        updateVoiceBar();
      };

      window.speechSynthesis.speak(utter);
    } catch (e) {
      isSpeaking = false;
      updateVoiceBar();
    }
  }

  function stopSpeaking() {
    if (hasSpeechSynthesis) {
      try {
        window.speechSynthesis.cancel();
      } catch (e) {}
    }
    isSpeaking = false;
    updateVoiceBar();
  }

  function stopListening() {
    if (recognition && isListening) {
      try {
        recognition.stop();
      } catch (e) {}
    }
    isListening = false;
    updateVoiceBar();
  }

  function attachSpeakButton(bubbleDiv, plainText, langCode) {
    if (!hasSpeechSynthesis || !plainText || !plainText.trim()) return;
    const footer = document.createElement('div');
    footer.className = 'ai-msg-tools';
    const speakBtn = document.createElement('button');
    speakBtn.type = 'button';
    speakBtn.className = 'ai-msg-speak-btn';
    speakBtn.title = 'Listen to this reply';
    speakBtn.innerHTML = '<i class="fa-solid fa-volume-high"></i> Listen';
    speakBtn.addEventListener('click', () => speakText(plainText, langCode));
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
    stopSpeaking();
    stopListening();

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
      } else {
        appendMessageElement(fullHtml, false, isWarning, plainText, langCode);
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
          speakText(plainText, langCode);
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
    sendAIMessage(false);
  }

  function sendQuickPrompt(promptText) {
    const input = document.getElementById('aiInput');
    if (!input || isStreaming) return;
    input.value = promptText;
    sendAIMessage(false);
  }

  function toggleVoiceReply() {
    if (!hasSpeechSynthesis) return;
    voiceReplyEnabled = !voiceReplyEnabled;
    localStorage.setItem(VOICE_REPLY_KEY, voiceReplyEnabled ? '1' : '0');
    if (!voiceReplyEnabled) {
      stopSpeaking();
    }
    updateVoiceReplyToggleUI();
  }

  function toggleVoiceLang() {
    voiceLang = voiceLang === 'fil-PH' ? 'en-US' : 'fil-PH';
    localStorage.setItem(VOICE_LANG_KEY, voiceLang);
    if (isListening) {
      stopListening();
      setTimeout(() => startVoiceInput(), 180);
    } else {
      updateVoiceBar();
    }
  }

  function startVoiceInput() {
    if (!SpeechRecognitionCtor || isStreaming) return;
    stopSpeaking();

    try {
      recognition = new SpeechRecognitionCtor();
      recognition.lang = voiceLang;
      recognition.interimResults = true;
      recognition.continuous = false;
      recognition.maxAlternatives = 1;

      const input = document.getElementById('aiInput');
      let finalTranscript = '';

      recognition.onstart = () => {
        isListening = true;
        updateVoiceBar();
      };

      recognition.onresult = (event) => {
        let interim = '';
        for (let i = event.resultIndex; i < event.results.length; i++) {
          const text = event.results[i][0].transcript;
          if (event.results[i].isFinal) {
            finalTranscript += text + ' ';
          } else {
            interim += text;
          }
        }
        if (input) {
          input.value = (finalTranscript + interim).trim();
        }
      };

      recognition.onerror = (event) => {
        isListening = false;
        updateVoiceBar();
        if (event.error === 'not-allowed') {
          appendMessageElement(
            'Naka-block ang microphone permission sa iyong browser. Paki-allow ang Mic access upang makapagsalita kay Marvi.',
            false,
            true
          );
        }
      };

      recognition.onend = () => {
        const hadListening = isListening;
        isListening = false;
        updateVoiceBar();
        if (hadListening && input && input.value.trim() !== '') {
          sendAIMessage(true);
        }
      };

      recognition.start();
    } catch (e) {
      isListening = false;
      updateVoiceBar();
    }
  }

  function toggleVoiceInput() {
    if (!SpeechRecognitionCtor) return;
    if (isListening) {
      stopListening();
    } else {
      startVoiceInput();
    }
  }

  function stopMarviVoiceAll() {
    stopListening();
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
    const micBtn = document.getElementById('aiMicBtn');
    if (micBtn && SpeechRecognitionCtor) {
      micBtn.style.display = 'flex';
    }
    updateVoiceReplyToggleUI();
    loadSavedConversation();

    // Warm up speechSynthesis voices list asynchronously in Chrome/Edge
    if (hasSpeechSynthesis && typeof window.speechSynthesis.onvoiceschanged !== 'undefined') {
      window.speechSynthesis.onvoiceschanged = () => {};
    }
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
