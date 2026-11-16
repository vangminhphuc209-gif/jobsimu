/**
 * assets/js/may-chatbot.js
 * Mây — Bạn đồng hành hướng nghiệp tại JobSimu (Client-side Widget)
 */
(function() {
  'use strict';

  const STORAGE_KEY_MESSAGES = 'jobsimu_may_messages';
  const STORAGE_KEY_OPEN = 'jobsimu_may_is_open';
  const STORAGE_KEY_PROFILE_OPTIN = 'jobsimu_may_profile_optin';

  // Elements
  let triggerBtn = null;
  let chatWindow = null;
  let messagesList = null;
  let chipsContainer = null;
  let textarea = null;
  let sendBtn = null;
  let closeBtn = null;
  let resetBtn = null;
  let profileOptinCheckbox = null;
  let quotaPill = null;

  let isSending = false;
  let lastFailedMessage = '';

  // Get current page context from URL
  function getPageContext() {
    const params = new URLSearchParams(window.location.search);
    const pageId = params.get('page') || 'home';
    const entityId = params.get('id') || params.get('code') || params.get('search') || '';
    return { page_id: pageId, entity_id: entityId };
  }

  // Safe HTML Escaping
  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // Safe Markdown Formatter
  function renderMarkdownSafe(text) {
    if (!text) return '';
    let escaped = escapeHtml(text);

    // Code blocks `code`
    escaped = escaped.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Bold **text**
    escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

    // Italic *text*
    escaped = escaped.replace(/\*([^*]+)\*/g, '<em>$1</em>');

    // Lists starting with - or •
    const lines = escaped.split('\n');
    let inList = false;
    let formatted = [];

    for (let line of lines) {
      const trimmed = line.trim();
      if (trimmed.startsWith('- ') || trimmed.startsWith('• ')) {
        if (!inList) {
          formatted.push('<ul>');
          inList = true;
        }
        formatted.push('<li>' + trimmed.substring(2) + '</li>');
      } else {
        if (inList) {
          formatted.push('</ul>');
          inList = false;
        }
        if (trimmed !== '') {
          formatted.push('<p style="margin:4px 0">' + line + '</p>');
        }
      }
    }
    if (inList) formatted.push('</ul>');

    return formatted.join('');
  }

  // Retrieve stored messages
  function getStoredMessages() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY_MESSAGES);
      if (raw) {
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch (e) {}

    // Initial greeting if no messages exist
    return [{
      role: 'ai',
      text: 'Chào bạn, mình là Mây ☁️\nMình có thể cùng bạn tìm hiểu ngành nghề, hiểu kết quả đánh giá và tìm đúng mục trên JobSimu. Bạn muốn bắt đầu từ đâu?',
      actions: []
    }];
  }

  function saveStoredMessages(messages) {
    try {
      sessionStorage.setItem(STORAGE_KEY_MESSAGES, JSON.stringify(messages.slice(-30)));
    } catch (e) {}
  }

  // Render message bubble
  function appendMessageToDOM(msg, shouldScroll = true) {
    if (!messagesList) return;
    const msgEl = document.createElement('div');
    msgEl.className = 'may-message ' + (msg.role === 'user' ? 'is-user' : 'is-ai');

    if (msg.role === 'ai') {
      const avatar = document.createElement('div');
      avatar.className = 'may-msg-avatar';
      avatar.textContent = '☁️';
      avatar.setAttribute('aria-hidden', 'true');
      msgEl.appendChild(avatar);
    }

    const bubbleWrapper = document.createElement('div');
    bubbleWrapper.style.maxWidth = '100%';

    const bubble = document.createElement('div');
    bubble.className = 'may-msg-bubble';
    bubble.innerHTML = renderMarkdownSafe(msg.text);
    bubbleWrapper.appendChild(bubble);

    // Render action buttons if any
    if (msg.actions && msg.actions.length > 0) {
      const actionsBox = document.createElement('div');
      actionsBox.className = 'may-actions-container';
      msg.actions.forEach(act => {
        const a = document.createElement('a');
        a.className = 'may-action-btn';
        a.href = act.url;
        a.innerHTML = '<span class="may-action-icon">' + escapeHtml(act.icon || '✦') + '</span><span>' + escapeHtml(act.label) + '</span>';
        actionsBox.appendChild(a);
      });
      bubbleWrapper.appendChild(actionsBox);
    }

    msgEl.appendChild(bubbleWrapper);
    messagesList.appendChild(msgEl);

    if (shouldScroll) {
      messagesList.scrollTop = messagesList.scrollHeight;
    }
  }

  // Render all messages
  function renderAllMessages() {
    if (!messagesList) return;
    messagesList.innerHTML = '';
    const messages = getStoredMessages();
    messages.forEach(msg => appendMessageToDOM(msg, false));
    messagesList.scrollTop = messagesList.scrollHeight;
  }

  // Contextual Chips
  function getContextualChips() {
    const ctx = getPageContext();
    const chips = [];

    // Base starter chips
    const baseChips = [
      { label: 'Tìm hiểu ngành nghề', query: 'Giới thiệu cho mình các nhóm ngành nghề phổ biến trên JobSimu' },
      { label: 'Hiểu kết quả của mình', query: 'Cách đọc hiểu mã kết quả trắc nghiệm Holland RIASEC' },
      { label: 'Tình huống công việc', query: 'Cho mình xem các tình huống thử thách của người trong nghề' },
      { label: 'Hướng dẫn sử dụng website', query: 'JobSimu có những chức năng gì và nên bắt đầu từ đâu?' }
    ];

    // Page-specific contextual additions
    if (ctx.page_id === 'majors' || ctx.page_id === 'major-detail') {
      chips.push({ label: '🎓 Ngành này làm nghề gì?', query: 'Ngành học này sau khi tốt nghiệp có thể làm những nghề gì?', contextual: true });
      chips.push({ label: '🏫 Trường nào đào tạo?', query: 'Những trường đại học nào đào tạo ngành này?', contextual: true });
    } else if (ctx.page_id === 'university-explorer' || ctx.page_id === 'university-compare') {
      chips.push({ label: '📊 Tra cứu điểm chuẩn', query: 'Xem điểm chuẩn và phương thức xét tuyển các trường', contextual: true });
      chips.push({ label: '💰 Mức học phí', query: 'Thông tin học phí của các trường đại học', contextual: true });
    } else if (ctx.page_id === 'assessment-hub' || ctx.page_id === 'assessment-report') {
      chips.push({ label: '🧭 Nên làm bài test nào trước?', query: 'Học sinh nên bắt đầu làm bài đánh giá nào trước trên JobSimu?', contextual: true });
    } else if (ctx.page_id === 'career' || ctx.page_id === 'careers') {
      chips.push({ label: '💼 Kỹ năng cần có?', query: 'Nghề này yêu cầu những kỹ năng và môn học nào?', contextual: true });
      chips.push({ label: '📈 Mức lương & triển vọng?', query: 'Thu nhập tham khảo và triển vọng của nghề này', contextual: true });
    }

    return [...chips, ...baseChips];
  }

  function renderChips() {
    if (!chipsContainer) return;
    chipsContainer.innerHTML = '';
    const chips = getContextualChips();

    chips.forEach(c => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'may-chip' + (c.contextual ? ' is-contextual' : '');
      btn.textContent = c.label;
      btn.addEventListener('click', () => {
        if (!isSending) {
          sendMessage(c.query);
        }
      });
      chipsContainer.appendChild(btn);
    });
  }

  // Show Typing Indicator
  function showTypingIndicator() {
    removeTypingIndicator();
    const indicator = document.createElement('div');
    indicator.id = 'mayTypingIndicator';
    indicator.className = 'may-message is-ai';
    indicator.innerHTML = `
      <div class="may-msg-avatar" aria-hidden="true">☁️</div>
      <div class="may-typing-indicator" aria-label="Mây đang soạn câu trả lời">
        <span></span><span></span><span></span>
      </div>
    `;
    messagesList.appendChild(indicator);
    messagesList.scrollTop = messagesList.scrollHeight;
  }

  function removeTypingIndicator() {
    const el = document.getElementById('mayTypingIndicator');
    if (el) el.remove();
  }

  // Show Error Banner
  function showErrorBanner(msg, canRetry = false) {
    removeErrorBanner();
    const banner = document.createElement('div');
    banner.id = 'mayErrorBanner';
    banner.className = 'may-error-banner';
    banner.innerHTML = '<span>' + escapeHtml(msg) + '</span>';

    if (canRetry && lastFailedMessage) {
      const retryBtn = document.createElement('button');
      retryBtn.className = 'may-retry-btn';
      retryBtn.textContent = 'Thử lại';
      retryBtn.onclick = () => {
        banner.remove();
        sendMessage(lastFailedMessage);
      };
      banner.appendChild(retryBtn);
    }

    messagesList.appendChild(banner);
    messagesList.scrollTop = messagesList.scrollHeight;
  }

  function removeErrorBanner() {
    const b = document.getElementById('mayErrorBanner');
    if (b) b.remove();
  }

  // Send Message function
  async function sendMessage(text) {
    text = (text || textarea.value || '').trim();
    if (!text || isSending) return;

    removeErrorBanner();
    isSending = true;
    lastFailedMessage = text;

    // Reset textarea
    textarea.value = '';
    textarea.style.height = 'auto';
    sendBtn.disabled = true;

    // Append user message
    const messages = getStoredMessages();
    const userMsg = { role: 'user', text: text, actions: [] };
    messages.push(userMsg);
    saveStoredMessages(messages);
    appendMessageToDOM(userMsg);

    showTypingIndicator();

    // Prepare payload
    const csrfToken = window.JOBSIMU_CSRF || '';
    const allowProfile = profileOptinCheckbox ? profileOptinCheckbox.checked : false;
    const ctx = getPageContext();

    const historyPayload = messages.slice(-6).map(m => ({
      role: m.role === 'user' ? 'user' : 'assistant',
      message: m.text
    }));

    try {
      const response = await fetch('index.php?page=may-chat-api', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
          message: text,
          csrf: csrfToken,
          context: {
            page_id: ctx.page_id,
            entity_id: ctx.entity_id,
            allow_profile_context: allowProfile
          },
          history: historyPayload
        })
      });

      removeTypingIndicator();

      const data = await response.json();

      if (!response.ok || !data.ok) {
        const errorText = data.message || 'Không thể kết nối tới Mây. Vui lòng thử lại sau.';
        showErrorBanner(errorText, response.status !== 429);
        return;
      }

      // Update quota pill
      if (quotaPill && typeof data.quota_remaining === 'number') {
        quotaPill.textContent = 'Còn ' + data.quota_remaining + '/' + data.quota_limit + ' câu';
      }

      // Append AI message
      const aiMsg = {
        role: 'ai',
        text: data.answer,
        actions: data.actions || []
      };
      messages.push(aiMsg);
      saveStoredMessages(messages);
      appendMessageToDOM(aiMsg);
      lastFailedMessage = '';

    } catch (err) {
      removeTypingIndicator();
      showErrorBanner('Lỗi mạng hoặc kết nối máy chủ bị ngắt. Vui lòng thử lại.', true);
    } finally {
      isSending = false;
      sendBtn.disabled = textarea.value.trim() === '';
      textarea.focus();
    }
  }

  // Window Open/Close
  function openChat() {
    if (!chatWindow) return;
    chatWindow.classList.add('is-open');
    chatWindow.setAttribute('aria-hidden', 'false');
    sessionStorage.setItem(STORAGE_KEY_OPEN, '1');
    renderChips();
    textarea.focus();
  }

  function closeChat() {
    if (!chatWindow) return;
    chatWindow.classList.remove('is-open');
    chatWindow.setAttribute('aria-hidden', 'true');
    sessionStorage.setItem(STORAGE_KEY_OPEN, '0');
    if (triggerBtn) triggerBtn.focus();
  }

  function resetChat() {
    if (confirm('Bạn có muốn bắt đầu cuộc trò chuyện mới với Mây?')) {
      sessionStorage.removeItem(STORAGE_KEY_MESSAGES);
      renderAllMessages();
      renderChips();
    }
  }

  // Initialize DOM Widget
  function initWidget() {
    triggerBtn = document.getElementById('mayChatTrigger');
    chatWindow = document.getElementById('mayChatWindow');
    if (!triggerBtn || !chatWindow) return;

    messagesList = document.getElementById('mayMessagesList');
    chipsContainer = document.getElementById('mayChipsContainer');
    textarea = document.getElementById('mayInputTextarea');
    sendBtn = document.getElementById('maySendBtn');
    closeBtn = document.getElementById('mayCloseBtn');
    resetBtn = document.getElementById('mayResetBtn');
    profileOptinCheckbox = document.getElementById('mayProfileOptin');
    quotaPill = document.getElementById('mayQuotaPill');

    // Trigger toggle
    triggerBtn.addEventListener('click', () => {
      if (chatWindow.classList.contains('is-open')) {
        closeChat();
      } else {
        openChat();
      }
    });

    closeBtn.addEventListener('click', closeChat);
    resetBtn.addEventListener('click', resetChat);

    // Profile opt-in persistence
    if (profileOptinCheckbox) {
      const savedOptin = localStorage.getItem(STORAGE_KEY_PROFILE_OPTIN);
      if (savedOptin !== null) {
        profileOptinCheckbox.checked = (savedOptin === '1');
      }
      profileOptinCheckbox.addEventListener('change', () => {
        localStorage.setItem(STORAGE_KEY_PROFILE_OPTIN, profileOptinCheckbox.checked ? '1' : '0');
      });
    }

    // Textarea input & keyboard handlers
    textarea.addEventListener('input', () => {
      textarea.style.height = 'auto';
      textarea.style.height = Math.min(textarea.scrollHeight, 80) + 'px';
      sendBtn.disabled = textarea.value.trim() === '' || isSending;
    });

    textarea.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      } else if (e.key === 'Escape') {
        closeChat();
      }
    });

    sendBtn.addEventListener('click', () => sendMessage());

    // Global Escape key closes chat
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && chatWindow.classList.contains('is-open')) {
        closeChat();
      }
    });

    // Initial render
    renderAllMessages();
    renderChips();

    // Restore open state if previously opened in same session
    if (sessionStorage.getItem(STORAGE_KEY_OPEN) === '1') {
      openChat();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWidget);
  } else {
    initWidget();
  }
})();
