/**
 * JobSimu - Step 1 / 4: Quick Self-Discovery (5 questions)
 */
(function () {
  'use strict';

  const root = document.getElementById('discoverApp');
  const dataNode = document.getElementById('discoverBootstrapData');
  if (!root || !dataNode) return;

  const bootstrap = JSON.parse(dataNode.textContent || '{}');
  const QUESTIONS = Array.isArray(bootstrap.questions) ? bootstrap.questions : [];
  const apiUrl = root.dataset.apiUrl || 'index.php?page=discover-api';
  const storageKey = root.dataset.storageKey || 'jobsimu_discover_v1_guest';
  const isLoggedIn = root.dataset.loggedIn === '1';
  const csrfToken = root.dataset.csrf || '';

  const quizView = document.getElementById('discoverQuizView');
  const resultView = document.getElementById('discoverResultView');
  const stepLabel = document.getElementById('discoverStepLabel');
  const saveHint = document.getElementById('discoverSaveHint');
  const progressBarWrap = document.getElementById('discoverProgressBarWrap');
  const progressFill = document.getElementById('discoverProgressFill');
  const questionBody = document.getElementById('discoverQuestionBody');
  const prevBtn = document.getElementById('discoverPrevBtn');
  const skipBtn = document.getElementById('discoverSkipBtn');
  const nextBtn = document.getElementById('discoverNextBtn');
  const summaryContent = document.getElementById('discoverSummaryContent');
  const editBtn = document.getElementById('discoverEditBtn');

  function esc(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  let state = {
    index: 0,
    completed: false,
    answers: {},
    summary: null,
  };

  // Load saved state: prefer server for logged-in user, merge/fallback localStorage
  try {
    const localRaw = localStorage.getItem(storageKey);
    if (localRaw) {
      const parsed = JSON.parse(localRaw);
      if (parsed && typeof parsed === 'object') {
        state = {
          index: Math.max(0, Math.min(QUESTIONS.length - 1, Number(parsed.index) || 0)),
          completed: Boolean(parsed.completed),
          answers: parsed.answers && typeof parsed.answers === 'object' ? parsed.answers : {},
          summary: parsed.summary || null,
        };
      }
    }
  } catch (e) {}

  if (bootstrap.initial && bootstrap.initial.answers) {
    state.answers = bootstrap.initial.answers;
    if (bootstrap.initial.completed && bootstrap.initial.summary) {
      state.completed = true;
      state.summary = bootstrap.initial.summary;
    }
  }

  function ensureEntry(qid) {
    if (!state.answers[qid] || typeof state.answers[qid] !== 'object') {
      state.answers[qid] = { selected: [], other_text: '', skipped: false };
    }
    if (!Array.isArray(state.answers[qid].selected)) {
      state.answers[qid].selected = [];
    }
    return state.answers[qid];
  }

  function saveLocal() {
    try {
      localStorage.setItem(storageKey, JSON.stringify(state));
    } catch (e) {}
  }

  async function syncServer(completed) {
    saveLocal();
    if (saveHint) {
      saveHint.textContent = 'Đang lưu...';
    }
    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
          Accept: 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          csrf: csrfToken,
          answers: state.answers,
          completed: Boolean(completed),
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Không thể lưu lúc này.');
      }
      state.answers = data.answers || state.answers;
      state.summary = data.summary || state.summary;
      saveLocal();
      if (saveHint) {
        saveHint.textContent = data.saved_to_server
          ? 'Đã lưu vào tài khoản của bạn'
          : 'Đã lưu tạm trên trình duyệt thiết bị này';
      }
      return data;
    } catch (err) {
      if (saveHint) {
        saveHint.textContent = 'Chưa đồng bộ được máy chủ — bản nháp vẫn được giữ trên trình duyệt.';
      }
      return null;
    }
  }

  function renderQuestion() {
    const q = QUESTIONS[state.index];
    if (!q) return;

    quizView.hidden = false;
    resultView.hidden = true;

    const entry = ensureEntry(q.id);
    const total = QUESTIONS.length;
    stepLabel.textContent = `Câu ${state.index + 1}/${total}`;
    const pct = Math.round(((state.index + 1) / total) * 100);
    progressFill.style.width = `${pct}%`;
    progressBarWrap.setAttribute('aria-valuenow', String(state.index + 1));

    prevBtn.disabled = state.index === 0;
    nextBtn.textContent = state.index === total - 1 ? 'Xem gợi ý khám phá →' : 'Tiếp tục →';

    const exclusiveId = q.exclusive_option ? q.exclusive_option.id : null;
    const isExclusiveSelected = exclusiveId !== null && entry.selected.includes(exclusiveId);
    const nonExclusiveCount = isExclusiveSelected ? 0 : entry.selected.length;

    let html = `<h2 class="discover-q-title">${esc(q.text)}</h2>`;
    if (q.helper) {
      html += `<p class="discover-q-helper">${esc(q.helper)}</p>`;
    }

    if (q.type === 'multi') {
      html += `<div class="discover-counter-row">
        <span class="discover-counter-pill" id="discoverCounter">Đã chọn ${nonExclusiveCount}/${q.max}</span>
        ${isExclusiveSelected ? '<span class="discover-counter-note">Đang chọn “Mình chưa rõ”</span>' : ''}
      </div>`;
    }

    html += `<div class="discover-options-grid ${q.options.length > 8 ? 'is-compact' : ''}" role="${q.type === 'single' ? 'radiogroup' : 'group'}" aria-label="${esc(q.text)}">`;

    q.options.forEach(opt => {
      const checked = entry.selected.includes(opt.id);
      const disabled = q.type === 'multi' && !checked && !isExclusiveSelected && nonExclusiveCount >= q.max;
      html += `<button
        type="button"
        class="discover-opt-btn${checked ? ' is-selected' : ''}"
        data-opt-id="${esc(opt.id)}"
        aria-pressed="${checked ? 'true' : 'false'}"
        ${disabled ? 'disabled' : ''}
      >
        <span class="discover-opt-check" aria-hidden="true">${checked ? '✓' : ''}</span>
        <span>${esc(opt.label)}</span>
      </button>`;
    });

    if (q.allow_other) {
      const otherChecked = entry.selected.includes('khac');
      const otherDisabled = q.type === 'multi' && !otherChecked && !isExclusiveSelected && nonExclusiveCount >= q.max;
      html += `<button
        type="button"
        class="discover-opt-btn${otherChecked ? ' is-selected' : ''}"
        data-opt-id="khac"
        aria-pressed="${otherChecked ? 'true' : 'false'}"
        ${otherDisabled ? 'disabled' : ''}
      >
        <span class="discover-opt-check" aria-hidden="true">${otherChecked ? '✓' : ''}</span>
        <span>Khác (tự nhập ngắn)</span>
      </button>`;
    }

    html += `</div>`;

    if (q.allow_other && entry.selected.includes('khac')) {
      html += `<div class="discover-other-wrap">
        <label class="form-label" for="discoverOtherInput">Ghi ngắn lựa chọn khác của bạn</label>
        <input
          id="discoverOtherInput"
          type="text"
          class="form-input"
          maxlength="120"
          value="${esc(entry.other_text || '')}"
          placeholder="${esc(q.other_placeholder || 'Nhập ngắn...')}"
        >
      </div>`;
    }

    if (q.exclusive_option) {
      html += `<div class="discover-exclusive-wrap">
        <button
          type="button"
          class="discover-exclusive-btn${isExclusiveSelected ? ' is-selected' : ''}"
          data-exclusive-id="${esc(q.exclusive_option.id)}"
          aria-pressed="${isExclusiveSelected ? 'true' : 'false'}"
        >
          <span class="discover-opt-check" aria-hidden="true">${isExclusiveSelected ? '✓' : ''}</span>
          <span>${esc(q.exclusive_option.label)}</span>
        </button>
      </div>`;
    }

    questionBody.innerHTML = html;

    questionBody.querySelectorAll('[data-opt-id]').forEach(btn => {
      btn.addEventListener('click', () => {
        const oid = btn.dataset.optId;
        entry.skipped = false;
        if (q.type === 'single') {
          entry.selected = entry.selected.includes(oid) ? [] : [oid];
        } else {
          // Deselect exclusive option whenever a regular option is chosen
          if (exclusiveId) {
            entry.selected = entry.selected.filter(id => id !== exclusiveId);
          }
          if (entry.selected.includes(oid)) {
            entry.selected = entry.selected.filter(id => id !== oid);
          } else if (entry.selected.length < q.max) {
            entry.selected.push(oid);
          }
        }
        saveLocal();
        renderQuestion();
      });
    });

    const exBtn = questionBody.querySelector('[data-exclusive-id]');
    if (exBtn) {
      exBtn.addEventListener('click', () => {
        const exId = exBtn.dataset.exclusiveId;
        entry.skipped = false;
        if (entry.selected.includes(exId)) {
          entry.selected = [];
        } else {
          entry.selected = [exId];
          entry.other_text = '';
        }
        saveLocal();
        renderQuestion();
      });
    }

    const otherInput = document.getElementById('discoverOtherInput');
    if (otherInput) {
      otherInput.addEventListener('input', () => {
        entry.other_text = otherInput.value.slice(0, 120);
        saveLocal();
      });
    }
  }

  function renderResult(summary) {
    if (!summary) return;
    quizView.hidden = true;
    resultView.hidden = false;

    const listOrEmpty = (arr, fallback) =>
      Array.isArray(arr) && arr.length > 0
        ? arr.map(t => `<span class="discover-tag">${esc(t)}</span>`).join('')
        : `<span class="text-xs text-slate-400">${esc(fallback)}</span>`;

    const directions = Array.isArray(summary.directions) ? summary.directions : [];
    const lookupBase = bootstrap.urls?.lookup_majors || 'index.php?page=lookup&tab=majors';

    let html = `
      <div class="discover-recap-grid">
        <div class="discover-recap-box">
          <h3>Môn học &amp; Hoạt động nổi bật</h3>
          <div class="discover-tag-list">${listOrEmpty([...(summary.subjects_labels || []), ...(summary.activities_labels || [])], 'Chưa chọn cụ thể')}</div>
        </div>
        <div class="discover-recap-box">
          <h3>Vai trò &amp; Giá trị ưu tiên</h3>
          <div class="discover-tag-list">${listOrEmpty([...(summary.role_labels || []), ...(summary.values_labels || [])], 'Chưa chọn cụ thể')}</div>
        </div>
        <div class="discover-recap-box">
          <h3>Điều bạn đang phân vân</h3>
          <div class="discover-tag-list">${listOrEmpty(summary.concern_labels || [], 'Chưa ghi nhận băn khoăn cụ thể')}</div>
          <p class="text-xs text-slate-600 mt-2">${esc(summary.next_step_tip || '')}</p>
        </div>
      </div>

      <div class="discover-directions-section">
        <h3 class="text-base font-bold text-slate-900 mb-3">Hướng tìm hiểu gợi mở cho bạn</h3>
        <div class="discover-directions-list">
    `;

    directions.forEach(dir => {
      const queryPart = dir.lookup_query ? `&q=${encodeURIComponent(dir.lookup_query)}` : '';
      const reasons = Array.isArray(dir.reasons) && dir.reasons.length
        ? `Gợi ý vì ${dir.reasons.join(', ')}.`
        : 'Hướng gợi mở để bạn đọc thêm thông tin ngành.';
      html += `
        <article class="discover-direction-card">
          <div>
            <h4>${esc(dir.title)}</h4>
            <p>${esc(reasons)}</p>
          </div>
          <div class="discover-direction-links">
            <a class="btn btn-outline btn-sm" href="${lookupBase}${queryPart}">Xem ngành liên quan →</a>
            ${dir.sim_url ? `<a class="btn btn-ghost btn-sm" href="${esc(dir.sim_url)}">${esc(dir.sim_label || 'Trải nghiệm nghề')}</a>` : ''}
          </div>
        </article>
      `;
    });

    html += `</div></div>`;

    if (directions[0] && directions[0].lookup_query) {
      const primaryCta = document.getElementById('discoverPrimaryCta');
      if (primaryCta) {
        primaryCta.href = `${lookupBase}&q=${encodeURIComponent(directions[0].lookup_query)}`;
      }
    }

    summaryContent.innerHTML = html;
  }

  prevBtn.addEventListener('click', () => {
    if (state.index > 0) {
      state.index--;
      saveLocal();
      renderQuestion();
    }
  });

  skipBtn.addEventListener('click', async () => {
    const q = QUESTIONS[state.index];
    if (q) {
      state.answers[q.id] = { selected: [], other_text: '', skipped: true };
    }
    if (state.index < QUESTIONS.length - 1) {
      state.index++;
      saveLocal();
      renderQuestion();
    } else {
      state.completed = true;
      const res = await syncServer(true);
      if (res && res.summary) {
        renderResult(res.summary);
      }
    }
  });

  nextBtn.addEventListener('click', async () => {
    if (state.index < QUESTIONS.length - 1) {
      state.index++;
      saveLocal();
      renderQuestion();
      if (isLoggedIn) {
        syncServer(false);
      }
    } else {
      state.completed = true;
      const res = await syncServer(true);
      if (res && res.summary) {
        renderResult(res.summary);
      }
    }
  });

  if (editBtn) {
    editBtn.addEventListener('click', () => {
      state.completed = false;
      state.index = 0;
      saveLocal();
      renderQuestion();
    });
  }

  // Boot
  if (state.completed && state.summary) {
    renderResult(state.summary);
  } else {
    renderQuestion();
  }
})();
