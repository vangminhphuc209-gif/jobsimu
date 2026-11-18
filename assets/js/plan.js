/**
 * JobSimu - Step 4 / 4: Month/Year Action Plan interactive controller
 */
(function () {
  'use strict';

  const app = document.getElementById('planApp');
  const dataNode = document.getElementById('planBootstrapData');
  if (!app || !dataNode) return;

  const bootstrap = JSON.parse(dataNode.textContent || '{}');
  const apiUrl = app.dataset.apiUrl || 'index.php?page=plan-api';
  const shareBase = app.dataset.shareBase || 'index.php?page=plan-share&token=';
  const storageKey = app.dataset.storageKey || 'jobsimu_plan_v1_guest';
  const currentMonth = app.dataset.currentMonth || bootstrap.current_month || new Date().toISOString().slice(0, 7);
  const csrfToken = app.dataset.csrf || '';
  const fromComparison = app.dataset.fromComparison === '1';

  const primaryInput = document.getElementById('planPrimaryInput');
  const primaryTag = document.getElementById('planPrimaryTag');
  const backupInput = document.getElementById('planBackupInput');
  const gradeSelect = document.getElementById('planGradeSelect');
  const startMonthInput = document.getElementById('planStartMonth');
  const horizonSelect = document.getElementById('planHorizonSelect');
  const hoursInput = document.getElementById('planHoursInput');
  const researchWrap = document.getElementById('planResearchWrap');
  const researchList = document.getElementById('planResearchList');

  const saveBtn = document.getElementById('planSaveBtn');
  const printBtn = document.getElementById('planPrintBtn');
  const aiDraftBtn = document.getElementById('planAiDraftBtn');
  const applyTemplateBtn = document.getElementById('planApplyTemplateBtn');
  const addItemBtn = document.getElementById('planAddItemBtn');
  const sortBtn = document.getElementById('planSortBtn');
  const saveStatusEl = document.getElementById('planSaveStatus');
  const saveErrorEl = document.getElementById('planSaveError');
  const progressSummaryEl = document.getElementById('planProgressSummary');
  const itemsWrap = document.getElementById('planItemsWrap');
  const printMetaEl = document.getElementById('planPrintMeta');

  const aiPreviewEl = document.getElementById('planAiPreview');
  const aiNoteEl = document.getElementById('planAiNote');
  const aiPreviewListEl = document.getElementById('planAiPreviewList');
  const aiCloseBtn = document.getElementById('planAiCloseBtn');
  const aiAppendBtn = document.getElementById('planAiAppendBtn');
  const aiReplaceUntouchedBtn = document.getElementById('planAiReplaceUntouchedBtn');

  const shareToggleBtn = document.getElementById('planShareToggleBtn');
  const shareBoxEl = document.getElementById('planShareBox');
  const shareUrlInput = document.getElementById('planShareUrlInput');
  const shareCopyBtn = document.getElementById('planShareCopyBtn');

  function esc(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatMonthVi(monthKey) {
    const m = /^(\d{4})-(0[1-9]|1[0-2])$/.exec(String(monthKey || ''));
    if (!m) return monthKey || 'Chưa chọn tháng';
    return `Tháng ${Number(m[2])}/${m[1]}`;
  }

  function addMonths(ym, offset) {
    const m = /^(\d{4})-(0[1-9]|1[0-2])$/.exec(String(ym || ''));
    let y = m ? Number(m[1]) : Number(currentMonth.slice(0, 4));
    let mo = (m ? Number(m[2]) : Number(currentMonth.slice(5, 7))) + Math.max(0, offset);
    while (mo > 12) {
      mo -= 12;
      y++;
    }
    return `${String(y).padStart(4, '0')}-${String(mo).padStart(2, '0')}`;
  }

  function autoResize(el) {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.max(64, el.scrollHeight) + 'px';
  }

  let plan = bootstrap.plan || {};
  // Merge local draft only if not coming fresh from a new comparison and no server uid yet
  if (!fromComparison && !plan.uid) {
    try {
      const localRaw = localStorage.getItem(storageKey);
      if (localRaw) {
        const parsed = JSON.parse(localRaw);
        if (parsed && parsed.primary_option && Array.isArray(parsed.items)) {
          plan = { ...plan, ...parsed };
        }
      }
    } catch (e) {}
  }

  if (!Array.isArray(plan.items)) plan.items = [];
  if (!plan.primary_option) plan.primary_option = { id: 'custom:primary', label: '', type: 'custom', major_id: null, program_id: null };

  let pendingAiDraft = [];
  let isSaving = false;

  function saveLocal() {
    try {
      localStorage.setItem(storageKey, JSON.stringify(plan));
    } catch (e) {}
  }

  function syncHeaderFromInputs() {
    plan.primary_option.label = (primaryInput.value || '').trim();
    const bLabel = (backupInput.value || '').trim();
    if (bLabel) {
      plan.backup_option = plan.backup_option || { id: 'custom:backup', label: bLabel, type: 'custom', major_id: null, program_id: null };
      plan.backup_option.label = bLabel;
    } else {
      plan.backup_option = null;
    }
    plan.grade = gradeSelect.value || 'Lớp 11';
    plan.start_month = startMonthInput.value && startMonthInput.value >= currentMonth ? startMonthInput.value : currentMonth;
    plan.target_horizon = horizonSelect.value || '6_months';
    plan.hours_per_week = hoursInput.value !== '' ? Number(hoursInput.value) : null;
    updatePrintMeta();
    saveLocal();
  }

  function updatePrintMeta() {
    if (!printMetaEl) return;
    const parts = [
      `Phương án ưu tiên: ${plan.primary_option?.label || 'Chưa đặt'}`,
      plan.backup_option?.label ? `Dự phòng: ${plan.backup_option.label}` : '',
      plan.grade ? `Khối: ${plan.grade}` : '',
      `Bắt đầu: ${formatMonthVi(plan.start_month || currentMonth)}`,
    ].filter(Boolean);
    printMetaEl.textContent = parts.join(' · ');
  }

  function renderHeader() {
    primaryInput.value = plan.primary_option?.label || '';
    backupInput.value = plan.backup_option?.label || '';
    if (plan.grade) gradeSelect.value = plan.grade;
    startMonthInput.value = plan.start_month || currentMonth;
    horizonSelect.value = plan.target_horizon || '6_months';
    hoursInput.value = plan.hours_per_week != null ? String(plan.hours_per_week) : '';

    if (primaryTag) {
      if (plan.primary_option?.type === 'program') {
        primaryTag.textContent = 'Liên kết từ chương trình đào tạo đã tra cứu.';
      } else if (plan.primary_option?.type === 'major') {
        primaryTag.textContent = 'Liên kết từ ngành học trong danh mục JobSimu.';
      } else {
        primaryTag.textContent = 'Phương án tự nhập (không gắn dữ liệu tuyển sinh tự suy đoán).';
      }
    }

    const rItems = Array.isArray(plan.research_items) ? plan.research_items : [];
    if (rItems.length > 0) {
      researchWrap.hidden = false;
      researchList.innerHTML = rItems.map(x => `<li>${esc(x)}</li>`).join('');
    } else {
      researchWrap.hidden = true;
    }

    updateShareUI();
    updatePrintMeta();
  }

  function updateShareUI() {
    if (!shareToggleBtn || !shareBoxEl || !shareUrlInput) return;
    if (plan.share_enabled && plan.share_token) {
      shareToggleBtn.textContent = 'Thu hồi liên kết chia sẻ';
      shareToggleBtn.classList.add('is-danger');
      shareBoxEl.hidden = false;
      shareUrlInput.value = `${window.location.origin}${new URL(shareBase + plan.share_token, window.location.href).pathname}${new URL(shareBase + plan.share_token, window.location.href).search}`;
    } else {
      shareToggleBtn.textContent = 'Bật liên kết chỉ đọc';
      shareToggleBtn.classList.remove('is-danger');
      shareBoxEl.hidden = true;
      shareUrlInput.value = '';
    }
  }

  function updateProgress() {
    const total = plan.items.length;
    const done = plan.items.filter(it => it.status === 'completed').length;
    const inProg = plan.items.filter(it => it.status === 'in_progress').length;
    if (progressSummaryEl) {
      progressSummaryEl.textContent = `${done}/${total} mốc hoàn thành · ${inProg} mốc đang làm`;
    }
  }

  function renderItems() {
    updateProgress();
    if (!plan.items.length) {
      itemsWrap.innerHTML = '<div class="lookup-empty">Chưa có mốc hành động nào. Hãy bấm “+ Thêm mốc mới” hoặc “Tạo mốc mẫu theo khối lớp”.</div>';
      return;
    }

    itemsWrap.innerHTML = plan.items.map((it, idx) => {
      const monthLabel = formatMonthVi(it.month_key);
      const isOfficial = it.deadline_kind === 'official';
      return `<article class="plan-item-row" data-item-idx="${idx}">
        <div class="plan-item-col-time">
          <div class="plan-month-badge">${esc(monthLabel)}</div>
          <label class="plan-mini-label">Tháng/Năm
            <input type="month" class="form-input plan-input-sm" min="${esc(currentMonth)}" value="${esc(it.month_key)}" data-field="month_key">
          </label>
          <label class="plan-mini-label">Ngày chi tiết (tùy chọn)
            <input type="date" class="form-input plan-input-sm" value="${esc(it.due_date || '')}" data-field="due_date">
          </label>
          <label class="plan-mini-label">Loại mốc
            <select class="form-select plan-input-sm" data-field="deadline_kind">
              <option value="self"${!isOfficial ? ' selected' : ''}>Mốc tự đặt</option>
              <option value="official"${isOfficial ? ' selected' : ''}>Hạn chính thức (có nguồn)</option>
            </select>
          </label>
          <div class="plan-official-src" ${!isOfficial ? 'hidden' : ''}>
            <label class="plan-mini-label">Link nguồn chính thức
              <input type="url" class="form-input plan-input-sm" value="${esc(it.official_source_url || '')}" placeholder="https://..." data-field="official_source_url">
            </label>
          </div>
        </div>

        <div class="plan-item-col-main">
          <div class="form-group">
            <label class="plan-mini-label">Mục tiêu</label>
            <input type="text" class="form-input" maxlength="255" value="${esc(it.goal)}" placeholder="Ví dụ: Tìm hiểu chương trình học" data-field="goal">
          </div>
          <div class="form-group">
            <label class="plan-mini-label">Việc cụ thể</label>
            <textarea class="form-input plan-textarea" rows="2" placeholder="Liệt kê việc cụ thể bạn sẽ làm..." data-field="tasks">${esc(it.tasks)}</textarea>
          </div>
          <div class="form-group">
            <label class="plan-mini-label">Kết quả cần đạt</label>
            <textarea class="form-input plan-textarea" rows="2" placeholder="Sản phẩm hoặc kết quả đo được..." data-field="expected_outcome">${esc(it.expected_outcome)}</textarea>
          </div>
        </div>

        <div class="plan-item-col-status">
          <label class="plan-mini-label">Trạng thái
            <select class="form-select plan-status-select status-${esc(it.status)}" data-field="status">
              <option value="not_started"${it.status === 'not_started' ? ' selected' : ''}>Chưa bắt đầu</option>
              <option value="in_progress"${it.status === 'in_progress' ? ' selected' : ''}>Đang làm</option>
              <option value="completed"${it.status === 'completed' ? ' selected' : ''}>Hoàn thành</option>
            </select>
          </label>
          <button type="button" class="btn btn-ghost btn-sm text-rose-600 plan-no-print mt-2" data-delete-item="${idx}">Xóa mốc</button>
        </div>
      </article>`;
    }).join('');

    itemsWrap.querySelectorAll('.plan-item-row').forEach(row => {
      const idx = Number(row.dataset.itemIdx);
      const item = plan.items[idx];
      if (!item) return;

      row.querySelectorAll('[data-field]').forEach(el => {
        if (el.tagName === 'TEXTAREA') autoResize(el);
        const evt = el.tagName === 'SELECT' ? 'change' : 'input';
        el.addEventListener(evt, () => {
          const field = el.dataset.field;
          item[field] = el.value;
          item.user_edited = true;
          if (el.tagName === 'TEXTAREA') autoResize(el);
          if (field === 'month_key') {
            const badge = row.querySelector('.plan-month-badge');
            if (badge) badge.textContent = formatMonthVi(el.value);
          }
          if (field === 'deadline_kind') {
            const srcBox = row.querySelector('.plan-official-src');
            if (srcBox) srcBox.hidden = el.value !== 'official';
          }
          if (field === 'status') {
            el.className = `form-select plan-status-select status-${el.value}`;
            updateProgress();
          }
          saveLocal();
        });
      });

      const delBtn = row.querySelector('[data-delete-item]');
      if (delBtn) {
        delBtn.addEventListener('click', () => {
          plan.items.splice(idx, 1);
          saveLocal();
          renderItems();
        });
      }
    });
  }

  function sortItemsChronologically() {
    plan.items.sort((a, b) => {
      if (a.month_key !== b.month_key) return String(a.month_key).localeCompare(String(b.month_key));
      const da = a.due_date || '9999-12-31';
      const db = b.due_date || '9999-12-31';
      return da.localeCompare(db);
    });
    saveLocal();
    renderItems();
  }

  async function savePlanToServer() {
    if (isSaving) return null;
    syncHeaderFromInputs();
    saveErrorEl.hidden = true;

    if (!plan.primary_option?.label) {
      saveErrorEl.textContent = 'Vui lòng nhập phương án ưu tiên trước khi lưu.';
      saveErrorEl.hidden = false;
      primaryInput.focus();
      return null;
    }

    isSaving = true;
    saveBtn.disabled = true;
    saveStatusEl.textContent = 'Đang lưu kế hoạch...';

    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          csrf: csrfToken,
          action: 'save',
          uid: plan.uid || '',
          comparison_uid: plan.comparison_uid || '',
          primary_option: plan.primary_option,
          backup_option: plan.backup_option,
          grade: plan.grade,
          start_month: plan.start_month,
          target_horizon: plan.target_horizon,
          hours_per_week: plan.hours_per_week,
          research_items: plan.research_items || [],
          items: plan.items,
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Không thể lưu kế hoạch.');
      }
      if (data.plan) {
        plan = data.plan;
        saveLocal();
        renderHeader();
        renderItems();
      }
      saveStatusEl.textContent = `✓ Đã lưu kế hoạch lúc ${data.saved_at || ''}`;
      if (window.showToast) window.showToast('Đã lưu kế hoạch thành công.');
      return data.plan;
    } catch (err) {
      saveStatusEl.textContent = 'Lưu máy chủ thất bại — bản nháp vẫn được giữ trên trình duyệt.';
      saveErrorEl.textContent = err.message || 'Lỗi kết nối khi lưu kế hoạch.';
      saveErrorEl.hidden = false;
      return null;
    } finally {
      isSaving = false;
      saveBtn.disabled = false;
    }
  }

  // Add milestone
  addItemBtn.addEventListener('click', () => {
    syncHeaderFromInputs();
    const lastMonth = plan.items.length
      ? plan.items[plan.items.length - 1].month_key
      : (plan.start_month || currentMonth);
    const nextMonth = lastMonth >= currentMonth ? addMonths(lastMonth, 1) : currentMonth;
    plan.items.push({
      month_key: nextMonth,
      due_date: '',
      deadline_kind: 'self',
      official_source_url: '',
      goal: '',
      tasks: '',
      expected_outcome: '',
      status: 'not_started',
      source: 'manual',
      user_edited: true,
    });
    saveLocal();
    renderItems();
  });

  sortBtn.addEventListener('click', sortItemsChronologically);
  saveBtn.addEventListener('click', savePlanToServer);
  printBtn.addEventListener('click', () => {
    syncHeaderFromInputs();
    window.print();
  });

  // Generate starter template according to grade without silently wiping user-edited milestones
  applyTemplateBtn.addEventListener('click', async () => {
    syncHeaderFromInputs();
    if (!plan.primary_option?.label) {
      saveErrorEl.textContent = 'Vui lòng nhập phương án ưu tiên trước khi tạo mốc mẫu.';
      saveErrorEl.hidden = false;
      primaryInput.focus();
      return;
    }
    saveErrorEl.hidden = true;
    const editedCount = plan.items.filter(it => it.user_edited).length;
    if (editedCount > 0 && !confirm(`Bạn đang có ${editedCount} mốc đã tự chỉnh sửa. Hệ thống sẽ giữ nguyên các mốc bạn đã sửa và bổ sung mốc mẫu theo ${plan.grade}. Tiếp tục?`)) {
      return;
    }
    const kept = plan.items.filter(it => it.user_edited);
    const start = plan.start_month >= currentMonth ? plan.start_month : currentMonth;
    const base = [
      { off: 0, goal: `Tìm hiểu sâu ${plan.primary_option.label}`, tasks: 'Đọc chương trình học, kỹ năng và ghi rõ điểm thích/chưa rõ.', expected_outcome: 'Có bảng ghi chú cho từng lựa chọn.' },
      { off: 1, goal: 'Trải nghiệm thực tế', tasks: 'Làm một hoạt động nhỏ hoặc tình huống mô phỏng nghề phù hợp trên JobSimu.', expected_outcome: 'Ghi lại điều thích, điều khó và điều muốn rèn thêm.' },
      { off: 2, goal: 'Đối chiếu trường & nguồn tuyển sinh', tasks: 'Kiểm tra phương thức, tổ hợp, khoảng học phí công bố và lưu link nguồn chính thức.', expected_outcome: 'Chốt danh sách phương án chính và dự phòng có nguồn rõ ràng.' },
    ];
    const generated = base.map(b => ({
      month_key: addMonths(start, b.off),
      due_date: '',
      deadline_kind: 'self',
      official_source_url: '',
      goal: b.goal,
      tasks: b.tasks,
      expected_outcome: b.expected_outcome,
      status: 'not_started',
      source: 'template',
      user_edited: false,
    }));
    plan.items = [...kept, ...generated];
    sortItemsChronologically();
  });

  // AI Draft from Trợ lý La Bàn (preview before save, never silently overwrites edited items)
  aiDraftBtn.addEventListener('click', async () => {
    syncHeaderFromInputs();
    saveErrorEl.hidden = true;
    if (!plan.primary_option?.label) {
      saveErrorEl.textContent = 'Vui lòng nhập phương án ưu tiên trước khi nhờ Trợ lý La Bàn gợi ý.';
      saveErrorEl.hidden = false;
      primaryInput.focus();
      return;
    }
    aiDraftBtn.disabled = true;
    aiDraftBtn.textContent = '🧭 Trợ lý La Bàn đang gợi ý...';
    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          csrf: csrfToken,
          action: 'ai_draft',
          primary_label: plan.primary_option.label,
          backup_label: plan.backup_option?.label || '',
          grade: plan.grade,
          start_month: plan.start_month,
          target_horizon: plan.target_horizon,
          hours_per_week: plan.hours_per_week,
          research_items: plan.research_items || [],
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Không thể tạo bản nháp lúc này.');
      }
      pendingAiDraft = Array.isArray(data.items) ? data.items : [];
      aiNoteEl.textContent = data.note || 'Xem trước bản nháp trước khi đưa vào kế hoạch.';
      aiPreviewListEl.innerHTML = pendingAiDraft.map(it => `
        <div class="plan-ai-preview-item">
          <strong class="text-xs text-brand-700">${esc(formatMonthVi(it.month_key))} · Mốc tự đặt</strong>
          <div class="text-sm font-bold text-slate-900 mt-0.5">${esc(it.goal)}</div>
          <div class="text-xs text-slate-600 mt-0.5">${esc(it.tasks)}</div>
          <div class="text-xs text-slate-500 mt-0.5"><em>Kết quả: ${esc(it.expected_outcome)}</em></div>
        </div>
      `).join('');
      aiPreviewEl.hidden = false;
      aiPreviewEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (err) {
      saveErrorEl.textContent = 'Trợ lý La Bàn tạm chưa phản hồi — bạn vẫn có thể tự thêm hoặc sửa mốc kế hoạch thủ công.';
      saveErrorEl.hidden = false;
    } finally {
      aiDraftBtn.disabled = false;
      aiDraftBtn.textContent = '🧭 Nhờ Trợ lý La Bàn gợi ý bản nháp';
    }
  });

  aiCloseBtn.addEventListener('click', () => {
    aiPreviewEl.hidden = true;
    pendingAiDraft = [];
  });

  aiAppendBtn.addEventListener('click', () => {
    if (!pendingAiDraft.length) return;
    plan.items = [...plan.items, ...pendingAiDraft.map(it => ({ ...it, user_edited: false }))];
    pendingAiDraft = [];
    aiPreviewEl.hidden = true;
    sortItemsChronologically();
    if (window.showToast) window.showToast('Đã thêm bản nháp vào kế hoạch (giữ nguyên các mốc đã chỉnh sửa).');
  });

  aiReplaceUntouchedBtn.addEventListener('click', () => {
    if (!pendingAiDraft.length) return;
    const edited = plan.items.filter(it => it.user_edited);
    plan.items = [...edited, ...pendingAiDraft.map(it => ({ ...it, user_edited: false }))];
    pendingAiDraft = [];
    aiPreviewEl.hidden = true;
    sortItemsChronologically();
    if (window.showToast) window.showToast('Đã cập nhật các mốc chưa chỉnh sửa bằng bản nháp mới.');
  });

  // Revocable Read-Only Share Link
  shareToggleBtn.addEventListener('click', async () => {
    if (!plan.uid) {
      const saved = await savePlanToServer();
      if (!saved || !saved.uid) return;
    }
    const wantEnable = !(plan.share_enabled && plan.share_token);
    shareToggleBtn.disabled = true;
    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          csrf: csrfToken,
          action: 'toggle_share',
          uid: plan.uid,
          enable: wantEnable,
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Không thể cập nhật chia sẻ.');
      plan.share_enabled = Boolean(data.share_enabled);
      plan.share_token = data.share_token || null;
      saveLocal();
      updateShareUI();
      if (window.showToast) window.showToast(plan.share_enabled ? 'Đã bật liên kết chia sẻ chỉ đọc.' : 'Đã thu hồi liên kết chia sẻ.');
    } catch (err) {
      saveErrorEl.textContent = err.message || 'Không thể thay đổi trạng thái chia sẻ.';
      saveErrorEl.hidden = false;
    } finally {
      shareToggleBtn.disabled = false;
    }
  });

  shareCopyBtn.addEventListener('click', () => {
    if (!shareUrlInput.value) return;
    navigator.clipboard?.writeText(shareUrlInput.value).then(() => {
      if (window.showToast) window.showToast('Đã sao chép liên kết chỉ đọc.');
    }).catch(() => {
      shareUrlInput.select();
      document.execCommand('copy');
    });
  });

  [primaryInput, backupInput, gradeSelect, startMonthInput, horizonSelect, hoursInput].forEach(el => {
    el.addEventListener('change', syncHeaderFromInputs);
    if (el.tagName === 'INPUT') el.addEventListener('input', syncHeaderFromInputs);
  });

  // Boot
  renderHeader();
  renderItems();
})();
