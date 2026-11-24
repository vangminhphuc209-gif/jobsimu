/**
 * JobSimu - Step 3 / 4: "Hiểu mình cần gì" (20-question weighted comparison)
 * Pure deterministic scoring: sum(score_j) + unallocated === 100.
 * Never normalizes answered scores up to 100. Never auto-advances on option click.
 */
(function () {
  'use strict';

  const app = document.getElementById('compareApp');
  const dataNode = document.getElementById('compareBootstrapData');
  if (!app || !dataNode) return;

  const bootstrap = JSON.parse(dataNode.textContent || '{}');
  const QUESTIONS = Array.isArray(bootstrap.questions) ? bootstrap.questions : [];
  const MAJORS = Array.isArray(bootstrap.majors) ? bootstrap.majors : [];

  const apiUrl = app.dataset.apiUrl || 'index.php?page=compare-api';
  const storageKey = app.dataset.storageKey || 'jobsimu_compare_v1_guest';
  const basketKey = app.dataset.basketKey || 'jobsimu_compare_basket_guest';
  const csrfToken = app.dataset.csrf || '';
  const planBase = app.dataset.planBase || 'index.php?page=plan';

  const stepPicker = document.getElementById('compareStepPicker');
  const stepQuiz = document.getElementById('compareStepQuiz');
  const stepResult = document.getElementById('compareStepResult');

  const selectedCountEl = document.getElementById('compareSelectedCount');
  const selectedListEl = document.getElementById('compareSelectedList');
  const limitMsgEl = document.getElementById('compareLimitMsg');
  const majorSearchEl = document.getElementById('compareMajorSearch');
  const majorListEl = document.getElementById('compareMajorList');
  const customInputEl = document.getElementById('compareCustomInput');
  const customAddBtn = document.getElementById('compareCustomAddBtn');
  const customErrEl = document.getElementById('compareCustomError');
  const schoolOnlyTipEl = document.getElementById('compareSchoolOnlyTip');
  const startReasonEl = document.getElementById('compareStartReason');
  const startBtn = document.getElementById('compareStartBtn');

  const quizProgressEl = document.getElementById('compareQuizProgress');
  const quizWeightEl = document.getElementById('compareQuizWeight');
  const progressWrapEl = document.getElementById('compareProgressWrap');
  const progressFillEl = document.getElementById('compareProgressFill');
  const quizCategoryEl = document.getElementById('compareQuizCategory');
  const draftStatusEl = document.getElementById('compareDraftStatus');
  const questionTextEl = document.getElementById('compareQuestionText');
  const infoWrapEl = document.getElementById('compareInfoWrap');
  const toggleInfoBtn = document.getElementById('compareToggleInfoBtn');
  const saveResearchBtn = document.getElementById('compareSaveResearchBtn');
  const infoBodyEl = document.getElementById('compareInfoBody');
  const quizOptionsEl = document.getElementById('compareQuizOptions');
  const prevBtn = document.getElementById('comparePrevBtn');
  const skipBtn = document.getElementById('compareSkipBtn');
  const nextBtn = document.getElementById('compareNextBtn');
  const editOptionsBtn = document.getElementById('compareEditOptionsBtn');

  const resultNoticesEl = document.getElementById('compareResultNotices');
  const resultStatsEl = document.getElementById('compareResultStats');
  const resultBarsEl = document.getElementById('compareResultBars');
  const breakdownWrapEl = document.getElementById('compareBreakdownWrap');
  const primarySelectEl = document.getElementById('comparePrimarySelect');
  const backupSelectEl = document.getElementById('compareBackupSelect');
  const confirmErrEl = document.getElementById('compareConfirmError');
  const goPlanBtn = document.getElementById('compareGoPlanBtn');
  const reviewAnswersBtn = document.getElementById('compareReviewAnswersBtn');

  function esc(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function stripAccents(str) {
    return String(str || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd');
  }

  // Pure scoring engine matching PHP compare_score_session() 1-to-1
  function scoreComparison(options, answers) {
    const totalWeight = QUESTIONS.reduce((sum, q) => sum + Number(q.weight), 0);
    const optMap = new Map();
    const scores = {};
    const contributions = {};
    options.forEach(o => {
      optMap.set(o.id, o);
      scores[o.id] = 0;
      contributions[o.id] = [];
    });

    let answeredCount = 0;
    let answeredWeight = 0;
    let unallocated = 0;
    const skippedQuestions = [];

    QUESTIONS.forEach(q => {
      const w = Number(q.weight);
      const chosen = answers[q.id] ?? answers[String(q.id)] ?? null;
      if (chosen && optMap.has(chosen)) {
        scores[chosen] += w;
        contributions[chosen].push({
          question_id: q.id,
          weight: w,
          category: q.category,
          text: q.text,
        });
        answeredCount++;
        answeredWeight += w;
      } else {
        unallocated += w;
        skippedQuestions.push({
          question_id: q.id,
          weight: w,
          category: q.category,
          text: q.text,
        });
      }
    });

    const allSkipped = answeredWeight === 0;
    const isPreliminary = !allSkipped && answeredWeight < 70;
    const ranked = options.map((o, idx) => ({
      ...o,
      score: scores[o.id],
      contributions: contributions[o.id],
      original_order: idx,
      rank: null,
    }));

    let closeRace = false;
    let topGap = null;

    if (!allSkipped) {
      ranked.sort((a, b) => (b.score !== a.score ? b.score - a.score : a.original_order - b.original_order));
      let prevScore = null;
      let curRank = 0;
      ranked.forEach((row, i) => {
        if (prevScore === null || row.score < prevScore) {
          curRank = i + 1;
          prevScore = row.score;
        }
        row.rank = curRank;
      });
      if (ranked.length >= 2) {
        topGap = ranked[0].score - ranked[1].score;
        closeRace = topGap <= 5;
      }
    }

    return {
      version: bootstrap.version || 'compare_v1',
      total_weight: totalWeight,
      answered_count: answeredCount,
      total_questions: QUESTIONS.length,
      answered_weight: answeredWeight,
      unallocated,
      all_skipped: allSkipped,
      is_preliminary: isPreliminary,
      close_race: closeRace,
      top_gap: topGap,
      ranked_options: ranked,
      skipped_questions: skippedQuestions,
    };
  }

  let state = {
    uid: '',
    step: 'picker', // 'picker' | 'quiz' | 'result'
    qIndex: 0,
    started: false,
    options: [],
    answers: {},
    research_notes: [],
    primary_option_id: '',
    backup_option_id: '',
  };

  let infoCache = null;

  // Restore saved state (server first if available, else localStorage) + merge lookup basket
  try {
    const raw = localStorage.getItem(storageKey);
    if (raw) {
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === 'object') {
        state = {
          ...state,
          uid: String(parsed.uid || ''),
          step: ['picker', 'quiz', 'result'].includes(parsed.step) ? parsed.step : 'picker',
          qIndex: Math.max(0, Math.min(QUESTIONS.length - 1, Number(parsed.qIndex) || 0)),
          started: Boolean(parsed.started),
          options: Array.isArray(parsed.options) ? parsed.options : [],
          answers: parsed.answers && typeof parsed.answers === 'object' ? parsed.answers : {},
          research_notes: Array.isArray(parsed.research_notes) ? parsed.research_notes : [],
          primary_option_id: String(parsed.primary_option_id || ''),
          backup_option_id: String(parsed.backup_option_id || ''),
        };
      }
    }
  } catch (e) {}

  if (bootstrap.saved && Array.isArray(bootstrap.saved.options) && bootstrap.saved.options.length >= 2) {
    state.uid = bootstrap.saved.uid || state.uid;
    state.options = bootstrap.saved.options;
    state.answers = bootstrap.saved.answers || {};
    state.research_notes = bootstrap.saved.research_notes || [];
    state.primary_option_id = bootstrap.saved.primary_option_id || '';
    state.backup_option_id = bootstrap.saved.backup_option_id || '';
    state.started = Object.keys(state.answers).length > 0 || bootstrap.saved.status !== 'draft';
    if (bootstrap.saved.status === 'completed' || bootstrap.saved.status === 'confirmed') {
      state.step = 'result';
    }
  }

  // Merge items added from "Tra cứu" basket when not yet started
  if (!state.started) {
    try {
      const basketRaw = localStorage.getItem(basketKey);
      const basketItems = basketRaw ? JSON.parse(basketRaw) : [];
      if (Array.isArray(basketItems)) {
        const existingIds = new Set(state.options.map(o => o.id));
        basketItems.forEach(item => {
          if (item && item.id && item.label && !existingIds.has(item.id) && state.options.length < 7) {
            state.options.push({
              id: String(item.id),
              label: String(item.label).slice(0, 120),
              type: item.type || 'major',
              major_id: item.major_id || null,
              program_id: item.program_id || null,
            });
            existingIds.add(item.id);
          }
        });
      }
    } catch (e) {}
  }

  function saveLocal() {
    try {
      localStorage.setItem(storageKey, JSON.stringify(state));
    } catch (e) {}
  }

  async function saveServer(status) {
    saveLocal();
    if (state.options.length < 2) return null;
    if (draftStatusEl) draftStatusEl.textContent = 'Đang lưu...';
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
          action: 'save',
          uid: state.uid,
          status: status || 'draft',
          options: state.options,
          answers: state.answers,
          research_notes: state.research_notes,
          primary_option_id: state.primary_option_id,
          backup_option_id: state.backup_option_id,
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Không thể lưu lúc này.');
      }
      if (data.uid) state.uid = data.uid;
      saveLocal();
      if (draftStatusEl) draftStatusEl.textContent = 'Đã lưu bản nháp an toàn';
      return data;
    } catch (err) {
      if (draftStatusEl) draftStatusEl.textContent = 'Đang giữ bản nháp trên trình duyệt (chưa đồng bộ máy chủ).';
      return null;
    }
  }

  function showStep(stepName) {
    state.step = stepName;
    stepPicker.hidden = stepName !== 'picker';
    stepQuiz.hidden = stepName !== 'quiz';
    stepResult.hidden = stepName !== 'result';
    saveLocal();
  }

  // ==================== STEP A: PICKER ====================
  function renderPicker() {
    showStep('picker');
    const count = state.options.length;
    selectedCountEl.textContent = `Đã chọn ${count} / 7 phương án`;
    limitMsgEl.hidden = count < 7;
    if (count >= 7) {
      limitMsgEl.textContent = 'Bạn đã đạt giới hạn tối đa 7 phương án. Hãy bỏ bớt một phương án nếu muốn thêm mới.';
    }

    if (!count) {
      selectedListEl.innerHTML = '<p class="text-xs text-slate-400">Chưa có phương án nào. Hãy chọn từ danh sách ngành bên dưới hoặc tự nhập phương án.</p>';
    } else {
      selectedListEl.innerHTML = state.options.map((opt, idx) => {
        const typeLabel = opt.type === 'program' ? 'Chương trình' : (opt.type === 'major' ? 'Ngành' : 'Tự nhập');
        return `<div class="compare-selected-item" data-opt-idx="${idx}">
          <span class="compare-opt-type">${typeLabel}</span>
          <span class="compare-opt-label">${esc(opt.label)}</span>
          <div class="compare-opt-item-actions">
            <button type="button" class="compare-mini-btn" data-rename-opt="${esc(opt.id)}" aria-label="Đổi tên ${esc(opt.label)}">Sửa tên</button>
            <button type="button" class="compare-mini-btn is-danger" data-remove-opt="${esc(opt.id)}" aria-label="Xóa ${esc(opt.label)}">Xóa</button>
          </div>
        </div>`;
      }).join('');
    }

    selectedListEl.querySelectorAll('[data-rename-opt]').forEach(btn => {
      btn.addEventListener('click', () => {
        const oid = btn.dataset.renameOpt;
        const opt = state.options.find(o => o.id === oid);
        if (!opt) return;
        const nextName = prompt('Đổi tên hiển thị phương án (giữ nguyên ID và điểm số đã chọn):', opt.label);
        if (nextName === null) return;
        const trimmed = nextName.replace(/\s+/g, ' ').trim().slice(0, 120);
        if (!trimmed) return;
        opt.label = trimmed;
        saveLocal();
        renderPicker();
      });
    });

    selectedListEl.querySelectorAll('[data-remove-opt]').forEach(btn => {
      btn.addEventListener('click', () => {
        const oid = btn.dataset.removeOpt;
        if (state.started && Object.keys(state.answers).length > 0) {
          if (!confirm('Thay đổi danh sách phương án sau khi đã trả lời sẽ cần làm lại bảng câu hỏi từ đầu. Bạn có chắc muốn xóa và đặt lại câu trả lời?')) {
            return;
          }
          state.answers = {};
          state.qIndex = 0;
          state.started = false;
        }
        state.options = state.options.filter(o => o.id !== oid);
        infoCache = null;
        saveLocal();
        renderPicker();
      });
    });

    // Major list picker
    const kw = stripAccents(majorSearchEl.value || '');
    const selectedIds = new Set(state.options.map(o => o.id));
    const filteredMajors = MAJORS.filter(m => !kw || stripAccents(`${m.name} ${m.code} ${m.group_name}`).includes(kw));

    majorListEl.innerHTML = filteredMajors.map(m => {
      const id = `major:${m.id}`;
      const checked = selectedIds.has(id);
      const disabled = !checked && state.options.length >= 7;
      return `<label class="compare-major-opt${checked ? ' is-selected' : ''}">
        <input type="checkbox" value="${esc(id)}" data-major-id="${m.id}" data-label="${esc(m.name)}" ${checked ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
        <span><strong>${esc(m.name)}</strong> <small class="text-slate-500">(${esc(m.code)}${m.group_name ? ' · ' + esc(m.group_name) : ''})</small></span>
      </label>`;
    }).join('') || '<p class="text-xs text-slate-500">Không tìm thấy ngành phù hợp.</p>';

    majorListEl.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      cb.addEventListener('change', () => {
        const id = cb.value;
        if (cb.checked) {
          if (state.options.length >= 7) {
            cb.checked = false;
            return;
          }
          if (state.started && Object.keys(state.answers).length > 0) {
            if (!confirm('Thêm phương án mới sau khi đã trả lời sẽ cần làm lại 20 câu hỏi từ đầu. Bạn có đồng ý đặt lại câu trả lời?')) {
              cb.checked = false;
              return;
            }
            state.answers = {};
            state.qIndex = 0;
            state.started = false;
          }
          state.options.push({
            id,
            label: cb.dataset.label,
            type: 'major',
            major_id: Number(cb.dataset.majorId),
            program_id: null,
          });
        } else {
          if (state.started && Object.keys(state.answers).length > 0) {
            if (!confirm('Bỏ phương án sau khi đã trả lời sẽ cần làm lại 20 câu hỏi từ đầu. Bạn có đồng ý đặt lại câu trả lời?')) {
              cb.checked = true;
              return;
            }
            state.answers = {};
            state.qIndex = 0;
            state.started = false;
          }
          state.options = state.options.filter(o => o.id !== id);
        }
        infoCache = null;
        saveLocal();
        renderPicker();
      });
    });

    // Enable/disable Start button with explicit reason
    const canStart = state.options.length >= 2;
    startBtn.disabled = !canStart;
    if (!canStart) {
      startReasonEl.textContent = 'Cần chọn ít nhất 2 phương án để bắt đầu trả lời 20 câu hỏi.';
    } else if (state.options.length > 4) {
      startReasonEl.textContent = `Bạn đã chọn ${state.options.length} phương án (khuyến nghị 2–4 để dễ cân nhắc, vẫn có thể so sánh tối đa 7).`;
    } else {
      startReasonEl.textContent = `Sẵn sàng so sánh ${state.options.length} phương án qua 20 câu hỏi.`;
    }
  }

  function addCustomOption() {
    customErrEl.hidden = true;
    schoolOnlyTipEl.hidden = true;

    const raw = (customInputEl.value || '').replace(/\s+/g, ' ').trim();
    if (!raw) {
      customErrEl.textContent = 'Vui lòng nhập tên phương án trước khi thêm.';
      customErrEl.hidden = false;
      return;
    }
    if (raw.length > 120) {
      customErrEl.textContent = 'Tên phương án tối đa 120 ký tự.';
      customErrEl.hidden = false;
      return;
    }
    if (state.options.length >= 7) {
      customErrEl.textContent = 'Đã đạt tối đa 7 phương án. Vui lòng xóa bớt trước khi thêm.';
      customErrEl.hidden = false;
      return;
    }

    const normNew = stripAccents(raw);
    if (state.options.some(o => stripAccents(o.label) === normNew)) {
      customErrEl.textContent = 'Phương án này đã có trong danh sách.';
      customErrEl.hidden = false;
      return;
    }

    if (state.started && Object.keys(state.answers).length > 0) {
      if (!confirm('Thêm phương án mới sau khi đã trả lời sẽ cần làm lại 20 câu hỏi từ đầu. Bạn có đồng ý đặt lại câu trả lời?')) {
        return;
      }
      state.answers = {};
      state.qIndex = 0;
      state.started = false;
    }

    // Gentle non-blocking tip if user typed only a school name without a major
    if (/^(đại học|dai hoc|học viện|hoc vien|trường|truong|cao đẳng|cao dang)\b/i.test(raw) && !/[-—–,:]/.test(raw)) {
      schoolOnlyTipEl.textContent = 'Gợi ý: Bạn đang nhập tên trường — nên bổ sung tên ngành hoặc chương trình (ví dụ: “Công nghệ thông tin — ' + raw + '”) bằng nút Sửa tên để so sánh rõ ràng hơn.';
      schoolOnlyTipEl.hidden = false;
    }

    let seq = state.options.length + 1;
    let id = 'custom:' + Date.now().toString(36) + '_' + seq;
    while (state.options.some(o => o.id === id)) {
      seq += 1;
      id = 'custom:' + Date.now().toString(36) + '_' + seq;
    }
    state.options.push({
      id,
      label: raw,
      type: 'custom',
      major_id: null,
      program_id: null,
    });
    customInputEl.value = '';
    infoCache = null;
    saveLocal();
    renderPicker();
  }

  majorSearchEl.addEventListener('input', renderPicker);
  customAddBtn.addEventListener('click', addCustomOption);
  customInputEl.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      e.preventDefault();
      addCustomOption();
    }
  });

  startBtn.addEventListener('click', () => {
    if (state.options.length < 2) return;
    state.started = true;
    saveLocal();
    renderQuiz();
  });

  // ==================== STEP B: 20 QUESTIONS ====================
  async function loadReferenceInfo() {
    if (infoCache) return infoCache;
    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ csrf: csrfToken, action: 'info', options: state.options }),
      });
      const data = await res.json();
      if (res.ok && data.ok) {
        infoCache = data.info || {};
      }
    } catch (e) {}
    return infoCache || {};
  }

  function renderQuiz() {
    if (state.options.length < 2) {
      renderPicker();
      return;
    }
    showStep('quiz');
    const q = QUESTIONS[state.qIndex];
    if (!q) return;

    const total = QUESTIONS.length;
    quizProgressEl.textContent = `Câu ${state.qIndex + 1}/${total}`;
    quizWeightEl.textContent = `Trọng số: ${q.weight} điểm / 100`;
    progressWrapEl.setAttribute('aria-valuenow', String(state.qIndex + 1));
    progressFillEl.style.width = `${Math.round(((state.qIndex + 1) / total) * 100)}%`;
    quizCategoryEl.textContent = q.category;
    questionTextEl.textContent = q.text;

    const currentAnswer = state.answers[q.id] || state.answers[String(q.id)] || '';
    prevBtn.disabled = state.qIndex === 0;
    nextBtn.disabled = !currentAnswer;
    nextBtn.textContent = state.qIndex === total - 1 ? 'Xem kết quả so sánh →' : 'Tiếp tục →';

    // Optional info panel for cost / curriculum / work_env
    if (q.info_topic) {
      infoWrapEl.hidden = false;
      infoBodyEl.hidden = true;
      infoBodyEl.innerHTML = '';
      toggleInfoBtn.textContent = 'Xem thông tin nguồn';
    } else {
      infoWrapEl.hidden = true;
      infoBodyEl.hidden = true;
    }

    // Render options — NEVER pre-select, NEVER auto-advance on click
    quizOptionsEl.innerHTML = state.options.map(opt => {
      const selected = currentAnswer === opt.id;
      return `<button
        type="button"
        class="compare-choice-btn${selected ? ' is-selected' : ''}"
        role="radio"
        aria-checked="${selected ? 'true' : 'false'}"
        data-choice-id="${esc(opt.id)}"
      >
        <span class="compare-choice-radio" aria-hidden="true">${selected ? '✓' : ''}</span>
        <span class="compare-choice-label">${esc(opt.label)}</span>
      </button>`;
    }).join('');

    quizOptionsEl.querySelectorAll('[data-choice-id]').forEach(btn => {
      btn.addEventListener('click', () => {
        const oid = btn.dataset.choiceId;
        state.answers[q.id] = oid;
        // Recompute cleanly without advancing automatically
        saveLocal();
        renderQuiz();
      });
    });
  }

  toggleInfoBtn.addEventListener('click', async () => {
    const q = QUESTIONS[state.qIndex];
    if (!q || !q.info_topic) return;
    if (!infoBodyEl.hidden) {
      infoBodyEl.hidden = true;
      toggleInfoBtn.textContent = 'Xem thông tin nguồn';
      return;
    }
    infoBodyEl.hidden = false;
    infoBodyEl.innerHTML = '<p class="text-xs text-slate-500">Đang tải thông tin tham khảo...</p>';
    toggleInfoBtn.textContent = 'Ẩn thông tin';
    const snippets = await loadReferenceInfo();
    infoBodyEl.innerHTML = state.options.map(opt => {
      const s = snippets[opt.id] || {};
      const text = s[q.info_topic] || 'Chưa có dữ liệu nguồn cho mục này.';
      return `<div class="compare-info-item"><strong>${esc(opt.label)}:</strong> <span>${esc(text)}</span></div>`;
    }).join('');
  });

  saveResearchBtn.addEventListener('click', async () => {
    const q = QUESTIONS[state.qIndex];
    if (!q) return;
    const note = `[Câu ${q.id} · ${q.category}] ${q.text}`;
    if (!state.research_notes.includes(note)) {
      state.research_notes.push(note);
    }
    delete state.answers[q.id];
    delete state.answers[String(q.id)];
    if (window.showToast) window.showToast('Đã lưu vào danh sách câu hỏi cần tìm hiểu và bỏ qua câu này.');
    await advanceQuestion();
  });

  async function advanceQuestion() {
    if (state.qIndex < QUESTIONS.length - 1) {
      state.qIndex++;
      saveLocal();
      renderQuiz();
      saveServer('draft');
    } else {
      await saveServer('completed');
      renderResult();
    }
  }

  prevBtn.addEventListener('click', () => {
    if (state.qIndex > 0) {
      state.qIndex--;
      saveLocal();
      renderQuiz();
    }
  });

  nextBtn.addEventListener('click', async () => {
    const q = QUESTIONS[state.qIndex];
    if (!q || !(state.answers[q.id] || state.answers[String(q.id)])) return;
    await advanceQuestion();
  });

  skipBtn.addEventListener('click', async () => {
    const q = QUESTIONS[state.qIndex];
    if (q) {
      delete state.answers[q.id];
      delete state.answers[String(q.id)];
    }
    await advanceQuestion();
  });

  editOptionsBtn.addEventListener('click', () => {
    renderPicker();
  });

  // ==================== STEP D: RESULT & CONFIRMATION ====================
  function renderResult() {
    showStep('result');
    const res = scoreComparison(state.options, state.answers);

    // Notices for edge cases
    const notices = [];
    if (res.all_skipped) {
      notices.push('<div class="alert alert-warning">Bạn đã bỏ qua toàn bộ 20 câu hỏi nên hệ thống <strong>không xếp hạng</strong>. Hãy thử trả lời một vài tiêu chí bạn thấy rõ nhất, hoặc tìm hiểu thêm ngành học trước khi so sánh lại.</div>');
    } else {
      if (res.is_preliminary) {
        notices.push(`<div class="alert alert-warning"><strong>Kết quả còn sơ bộ</strong> (tổng trọng số đã trả lời là ${res.answered_weight}/100, dưới mức 70). Lưu ý: mốc 70 là quy tắc trải nghiệm (UX) để nhắc bạn còn nhiều tiêu chí chưa rõ, không phải ngưỡng khoa học.</div>`);
      }
      if (res.close_race) {
        notices.push(`<div class="alert alert-info"><strong>Hai lựa chọn đang khá gần nhau</strong> (chênh lệch ${res.top_gap} điểm). Bạn nên đối chiếu thêm chương trình học, học phí hoặc thử mô phỏng nghề trước khi quyết định.</div>`);
      }
    }
    resultNoticesEl.innerHTML = notices.join('');

    resultStatsEl.innerHTML = `
      <div class="compare-stat-chip"><span>Số câu đã trả lời</span><strong>${res.answered_count} / ${res.total_questions}</strong></div>
      <div class="compare-stat-chip"><span>Điểm đã phân bổ</span><strong>${res.answered_weight} / 100</strong></div>
      <div class="compare-stat-chip"><span>Chưa phân bổ (bỏ qua)</span><strong>${res.unallocated} điểm</strong></div>
    `;

    // Bars
    resultBarsEl.innerHTML = res.ranked_options.map(opt => {
      const rankBadge = res.all_skipped
        ? '<span class="badge badge-subtle">Chưa xếp hạng</span>'
        : `<span class="badge badge-purple">Hạng ${opt.rank}</span>`;
      return `<div class="compare-result-bar-card">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
          <div class="flex items-center gap-2">
            ${rankBadge}
            <strong class="text-base text-slate-900">${esc(opt.label)}</strong>
          </div>
          <span class="compare-score-label">Điểm ưu tiên: <strong>${opt.score}/100</strong></span>
        </div>
        <div class="compare-bar-track">
          <div class="compare-bar-fill" style="width:${Math.max(0, Math.min(100, opt.score))}%"></div>
        </div>
      </div>`;
    }).join('');

    // Breakdown: contributions + skipped criteria + saved research notes
    let breakHtml = '<div class="compare-breakdown-grid">';
    res.ranked_options.forEach(opt => {
      const list = opt.contributions.length
        ? `<ul class="compare-contrib-list">${opt.contributions.map(c => `<li><span>Câu ${c.question_id} (+${c.weight}đ):</span> ${esc(c.text)}</li>`).join('')}</ul>`
        : '<p class="text-xs text-slate-400">Chưa có câu trả lời nào chọn phương án này.</p>';
      breakHtml += `<details class="compare-details-card">
        <summary><strong>${esc(opt.label)}</strong> · ${opt.contributions.length} câu đóng góp (${opt.score} điểm)</summary>
        <div class="pt-3">${list}</div>
      </details>`;
    });
    breakHtml += '</div>';

    if (res.skipped_questions.length > 0 || state.research_notes.length > 0) {
      const skippedList = res.skipped_questions.map(sq => `<li><span>Câu ${sq.question_id} (${sq.weight}đ · ${esc(sq.category)}):</span> ${esc(sq.text)}</li>`).join('');
      const notesList = state.research_notes.map(n => `<li>${esc(n)}</li>`).join('');
      breakHtml += `<div class="compare-research-box">
        <h3 class="text-sm font-bold text-slate-900">Tiêu chí bạn còn chưa rõ &amp; Việc nên tìm hiểu tiếp</h3>
        ${res.skipped_questions.length ? `<ul class="compare-contrib-list mt-2">${skippedList}</ul>` : ''}
        ${state.research_notes.length ? `<div class="mt-3 pt-3 border-t border-slate-200"><strong class="text-xs text-slate-700">Câu hỏi bạn đã đánh dấu cần tìm hiểu:</strong><ul class="compare-contrib-list mt-1">${notesList}</ul></div>` : ''}
      </div>`;
    }
    breakdownWrapEl.innerHTML = breakHtml;

    // Populate user-confirmed Primary & Backup selectors (suggested by score order, never auto-confirmed!)
    const suggestedPrimary = !res.all_skipped && res.ranked_options[0] ? res.ranked_options[0].id : '';
    const suggestedBackup = !res.all_skipped && res.ranked_options[1] ? res.ranked_options[1].id : '';
    const currentPrimary = state.options.some(o => o.id === state.primary_option_id) ? state.primary_option_id : suggestedPrimary;
    const currentBackup = state.options.some(o => o.id === state.backup_option_id) && state.backup_option_id !== currentPrimary
      ? state.backup_option_id
      : (suggestedBackup !== currentPrimary ? suggestedBackup : '');

    primarySelectEl.innerHTML = '<option value="">Chọn phương án ưu tiên</option>' +
      res.ranked_options.map(o => `<option value="${esc(o.id)}"${o.id === currentPrimary ? ' selected' : ''}>${esc(o.label)}${!res.all_skipped ? ` (${o.score}/100)` : ''}</option>`).join('');

    function syncBackupOptions(selectedPrimary, selectedBackup) {
      backupSelectEl.innerHTML = '<option value="">Chưa chọn phương án dự phòng</option>' +
        res.ranked_options
          .filter(o => o.id !== selectedPrimary)
          .map(o => `<option value="${esc(o.id)}"${o.id === selectedBackup ? ' selected' : ''}>${esc(o.label)}${!res.all_skipped ? ` (${o.score}/100)` : ''}</option>`).join('');
    }
    syncBackupOptions(primarySelectEl.value, currentBackup);

    primarySelectEl.onchange = () => {
      confirmErrEl.hidden = true;
      syncBackupOptions(primarySelectEl.value, backupSelectEl.value === primarySelectEl.value ? '' : backupSelectEl.value);
    };
  }

  reviewAnswersBtn.addEventListener('click', () => {
    state.qIndex = 0;
    renderQuiz();
  });

  goPlanBtn.addEventListener('click', async () => {
    confirmErrEl.hidden = true;
    const primaryId = primarySelectEl.value.trim();
    const backupId = backupSelectEl.value.trim();
    if (!primaryId) {
      confirmErrEl.textContent = 'Vui lòng chọn phương án ưu tiên trước khi chuyển sang lập kế hoạch.';
      confirmErrEl.hidden = false;
      primarySelectEl.focus();
      return;
    }

    state.primary_option_id = primaryId;
    state.backup_option_id = backupId !== primaryId ? backupId : '';
    goPlanBtn.disabled = true;
    goPlanBtn.textContent = 'Đang chuẩn bị kế hoạch...';

    const saved = await saveServer('confirmed');
    if (saved && saved.plan_url) {
      window.location.href = saved.plan_url;
      return;
    }
    // Fallback if server unavailable (guest offline): carry via uid/localStorage
    window.location.href = state.uid
      ? `${planBase}&comparison_id=${encodeURIComponent(state.uid)}`
      : planBase;
  });

  // Boot
  if (state.step === 'result' && state.options.length >= 2) {
    renderResult();
  } else if (state.step === 'quiz' && state.options.length >= 2) {
    renderQuiz();
  } else {
    renderPicker();
  }
})();
