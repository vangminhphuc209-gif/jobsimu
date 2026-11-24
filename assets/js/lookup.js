/**
 * JobSimu - Step 2 / 4: Lookup ("Tra cứu") interactive controller
 * Supports Ngành học, Trường đào tạo, Tổ hợp xét tuyển with race-safe async filtering,
 * URL sync, loading/error/retry states, and persistent compare basket.
 */
(function () {
  'use strict';

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

  // Shared Compare Basket helpers (used on both lookup list and major detail pages)
  const basketHolder = document.querySelector('[data-basket-key]');
  const basketKey = basketHolder ? basketHolder.dataset.basketKey : 'jobsimu_compare_basket_guest';

  function readBasket() {
    try {
      const raw = localStorage.getItem(basketKey);
      const arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return [];
    }
  }

  function writeBasket(items) {
    try {
      localStorage.setItem(basketKey, JSON.stringify(items.slice(0, 7)));
    } catch (e) {}
    updateBasketIndicators();
  }

  function toggleBasketItem(item) {
    const items = readBasket();
    const idx = items.findIndex(x => x.id === item.id);
    if (idx >= 0) {
      items.splice(idx, 1);
      writeBasket(items);
      if (window.showToast) window.showToast(`Đã bỏ "${item.label}" khỏi danh sách so sánh.`);
      return false;
    }
    if (items.length >= 7) {
      if (window.showToast) window.showToast('Danh sách so sánh hỗ trợ tối đa 7 phương án.');
      return false;
    }
    items.push(item);
    writeBasket(items);
    if (window.showToast) window.showToast(`Đã thêm "${item.label}" vào danh sách so sánh.`);
    return true;
  }

  function updateBasketIndicators() {
    const items = readBasket();
    const ids = new Set(items.map(i => i.id));
    const countEl = document.getElementById('lookupBasketCount');
    if (countEl) countEl.textContent = String(items.length);

    document.querySelectorAll('[data-add-compare]').forEach(btn => {
      const cid = btn.dataset.compareId;
      const inBasket = ids.has(cid);
      btn.classList.toggle('is-added', inBasket);
      btn.setAttribute('aria-pressed', inBasket ? 'true' : 'false');
      btn.textContent = inBasket ? '✓ Đã thêm so sánh' : '+ Thêm vào so sánh';
    });
  }

  function bindBasketButtons(scope) {
    (scope || document).querySelectorAll('[data-add-compare]').forEach(btn => {
      if (btn.dataset.basketBound === '1') return;
      btn.dataset.basketBound = '1';
      btn.addEventListener('click', () => {
        toggleBasketItem({
          id: btn.dataset.compareId,
          label: btn.dataset.compareLabel,
          type: btn.dataset.compareType || 'major',
          major_id: btn.dataset.compareMajorId ? Number(btn.dataset.compareMajorId) : null,
          program_id: btn.dataset.compareProgramId ? Number(btn.dataset.compareProgramId) : null,
        });
      });
    });
    updateBasketIndicators();
  }

  bindBasketButtons(document);

  // Main interactive lookup tabs
  const app = document.getElementById('lookupApp');
  const dataNode = document.getElementById('lookupBootstrapData');
  if (!app || !dataNode) return;

  const bootstrap = JSON.parse(dataNode.textContent || '{}');
  const tab = bootstrap.tab || '';
  if (!tab) return;

  const apiUrl = app.dataset.apiUrl || 'index.php?page=lookup-api';
  const form = document.getElementById('lookupFilterForm');
  const resultsEl = document.getElementById('lookupResultsContainer');
  const paginationEl = document.getElementById('lookupPagination');
  const statusBar = document.getElementById('lookupStatusBar');
  const countEl = document.getElementById('lookupResultCount');
  const resetBtn = document.getElementById('lookupResetBtn');
  const combPicker = document.getElementById('lookupCombPicker');

  let currentPage = (bootstrap.initial && bootstrap.initial.page) || 1;
  let selectedCombCodes = new Set((bootstrap.initial && bootstrap.initial.selected_codes) || []);
  let requestSeq = 0;
  let activeController = null;
  let debounceTimer = null;

  function setStatus(type, text, showRetry) {
    if (!statusBar) return;
    if (!type) {
      statusBar.hidden = true;
      statusBar.innerHTML = '';
      return;
    }
    statusBar.hidden = false;
    statusBar.className = `lookup-status-bar is-${type}`;
    statusBar.innerHTML = `<span>${esc(text)}</span>` + (showRetry
      ? ' <button type="button" class="school-retry-btn" id="lookupRetryBtn">Thử lại</button>'
      : '');
    const retryBtn = document.getElementById('lookupRetryBtn');
    if (retryBtn) {
      retryBtn.addEventListener('click', () => fetchResults(currentPage));
    }
  }

  function renderCombPicker(catalog) {
    if (!combPicker) return;
    const list = Array.isArray(catalog) ? catalog : (bootstrap.meta?.combinations || []);
    const qInput = document.getElementById('f-q');
    const kw = stripAccents(qInput ? qInput.value : '');
    const visible = kw
      ? list.filter(c => stripAccents(`${c.code} ${c.subjects_text} ${c.category}`).includes(kw))
      : list;

    if (!visible.length) {
      combPicker.innerHTML = '<p class="text-xs text-slate-500">Không tìm thấy tổ hợp khớp từ khóa.</p>';
      return;
    }

    combPicker.innerHTML = visible.map(c => {
      const checked = selectedCombCodes.has(c.code);
      return `<button type="button" class="lookup-comb-card${checked ? ' is-selected' : ''}" data-comb-code="${esc(c.code)}" aria-pressed="${checked ? 'true' : 'false'}">
        <span class="lookup-comb-card-top">
          <strong>${esc(c.code)}</strong>
          <span class="lookup-comb-check" aria-hidden="true">${checked ? '✓' : ''}</span>
        </span>
        <span class="lookup-comb-subjects">${esc(c.subjects_text)}</span>
      </button>`;
    }).join('');

    combPicker.querySelectorAll('[data-comb-code]').forEach(btn => {
      btn.addEventListener('click', () => {
        const code = btn.dataset.combCode;
        if (selectedCombCodes.has(code)) {
          selectedCombCodes.delete(code);
        } else {
          selectedCombCodes.add(code);
        }
        renderCombPicker(bootstrap.meta?.combinations || []);
        fetchResults(1);
      });
    });
  }

  function renderMajors(data) {
    const items = data.items || [];
    if (countEl) {
      countEl.innerHTML = `Tìm thấy <strong>${Number(data.total || 0)}</strong> ngành`;
    }
    if (!items.length) {
      resultsEl.innerHTML = '<div class="lookup-empty">Không tìm thấy ngành học phù hợp với bộ lọc hiện tại. Hãy thử xóa bộ lọc hoặc đổi từ khóa.</div>';
      renderPagination(data);
      return;
    }

    const detailBase = bootstrap.urls?.major_detail_base || 'index.php?page=lookup&tab=majors&major_id=';
    resultsEl.innerHTML = items.map(m => {
      const skillsHtml = (m.skills && m.skills.length)
        ? `<div class="lookup-card-skills">${m.skills.map(s => `<span class="lookup-skill-chip">${esc(s)}</span>`).join('')}</div>`
        : '';
      const combsHtml = (m.combinations && m.combinations.length)
        ? `<div class="lookup-card-combs">${m.combinations.map(c => `<span class="lookup-comb-pill" title="${esc(c.subjects_text)}">${esc(c.code)}${c.subjects_text ? ' · ' + esc(c.subjects_text) : ''}</span>`).join('')}</div>`
        : '<p class="text-xs text-slate-400 mt-2">Tổ hợp đối chiếu: Chưa có dữ liệu xác minh</p>';
      const yearLabel = (m.years && m.years.length)
        ? `Dữ liệu xác minh năm ${m.years.join(', ')}`
        : 'Chưa có bản ghi điểm chuẩn xác minh';

      return `<article class="lookup-major-card">
        <div class="lookup-major-top">
          <span class="badge badge-subtle">${esc(m.group_name)}</span>
          <span class="text-xs font-semibold text-slate-500">Mã: ${esc(m.code)}</span>
        </div>
        <h3><a href="${detailBase}${m.id}">${esc(m.name)}</a></h3>
        <p class="lookup-major-desc">${esc(m.description || 'Chưa có mô tả chi tiết cho ngành này.')}</p>
        ${skillsHtml}
        ${combsHtml}
        <p class="text-[11px] text-slate-500 mt-2">${esc(yearLabel)}</p>
        <div class="lookup-card-actions">
          <a class="btn btn-primary btn-sm" href="${detailBase}${m.id}">Xem ngành</a>
          <button
            type="button"
            class="btn btn-outline btn-sm"
            data-add-compare
            data-compare-id="major:${m.id}"
            data-compare-label="${esc(m.name)}"
            data-compare-type="major"
            data-compare-major-id="${m.id}"
          >+ Thêm vào so sánh</button>
        </div>
      </article>`;
    }).join('');

    bindBasketButtons(resultsEl);
    renderPagination(data);
  }

  function renderSchools(data) {
    const items = data.items || [];
    if (countEl) {
      countEl.innerHTML = `Tìm thấy <strong>${Number(data.total || 0)}</strong> bản ghi chương trình/trường`;
    }
    if (!items.length) {
      resultsEl.innerHTML = '<div class="lookup-empty">Không tìm thấy trường hoặc chương trình phù hợp với bộ lọc hiện tại.</div>';
      renderPagination(data);
      return;
    }

    const tableRows = items.map(r => {
      const combText = (r.combinations && r.combinations.length)
        ? r.combinations.map(c => `<span class="lookup-comb-pill">${esc(c.code)}${c.subjects_text ? ' (' + esc(c.subjects_text) + ')' : ''}</span>`).join(' ')
        : '<span class="text-xs text-slate-400">Chưa có dữ liệu tổ hợp</span>';
      const verifyBadge = r.verification_status === 'verified'
        ? ''
        : ' <span class="badge badge-warning text-[11px]">Chưa xác minh</span>';
      const sourceLink = r.source_url
        ? `<a class="text-brand-600 font-semibold hover:underline" href="${esc(r.source_url)}" target="_blank" rel="noopener noreferrer">Xem nguồn ↗</a>${verifyBadge}`
        : `<span class="badge badge-warning text-[11px]">Chưa xác minh · Chưa có nguồn</span>`;
      const compareBtn = r.program_id > 0
        ? `<button type="button" class="btn btn-outline btn-sm" data-add-compare data-compare-id="program:${r.program_id}" data-compare-label="${esc(r.program_name + ' — ' + r.institution_name)}" data-compare-type="program" data-compare-major-id="${r.major_id}" data-compare-program-id="${r.program_id}">+ Thêm vào so sánh</button>`
        : '';

      return `<tr>
        <td>
          <strong>${esc(r.institution_name)}</strong>
          <div class="text-xs text-slate-500">Cơ sở/Địa phương: ${esc(r.campus)} · Mã: ${esc(r.institution_code)}</div>
        </td>
        <td>
          <strong>${esc(r.program_name)}</strong>
          <div class="text-xs text-slate-500">Ngành: ${esc(r.major_name)}${r.program_code ? ' · Mã CT: ' + esc(r.program_code) : ''}</div>
        </td>
        <td>
          <div>${r.admission_year ? 'Năm ' + r.admission_year : '—'}</div>
          <div class="text-xs text-slate-500">${esc(r.method)}${r.round_name ? ' · ' + esc(r.round_name) : ''}</div>
        </td>
        <td><div class="flex flex-wrap gap-1">${combText}</div></td>
        <td>
          <div class="font-semibold text-slate-900">${esc(r.score_display)}</div>
          ${r.conditions_note ? `<div class="text-[11px] text-slate-500 mt-1">${esc(r.conditions_note)}</div>` : ''}
        </td>
        <td>
          <div>${sourceLink}</div>
          <div class="text-[11px] text-slate-500">${esc(r.publisher)}${r.verified_on ? ' · Kiểm tra: ' + esc(r.verified_on) : ''}</div>
          <div class="mt-2">${compareBtn}</div>
        </td>
      </tr>`;
    }).join('');

    const mobileCards = items.map(r => {
      const combText = (r.combinations && r.combinations.length)
        ? r.combinations.map(c => `<span class="lookup-comb-pill">${esc(c.code)}${c.subjects_text ? ' — ' + esc(c.subjects_text) : ''}</span>`).join(' ')
        : '<span class="text-xs text-slate-400">Chưa có dữ liệu tổ hợp</span>';
      const verifyBadge = r.verification_status === 'verified'
        ? ''
        : ' <span class="badge badge-warning text-[11px]">Chưa xác minh</span>';
      const sourceLink = r.source_url
        ? `<a class="text-brand-600 font-semibold hover:underline" href="${esc(r.source_url)}" target="_blank" rel="noopener noreferrer">Xem nguồn ↗</a>${verifyBadge}`
        : `<span class="badge badge-warning text-[11px]">Chưa xác minh · Chưa có nguồn</span>`;
      const compareBtn = r.program_id > 0
        ? `<button type="button" class="btn btn-outline btn-sm" data-add-compare data-compare-id="program:${r.program_id}" data-compare-label="${esc(r.program_name + ' — ' + r.institution_name)}" data-compare-type="program" data-compare-major-id="${r.major_id}" data-compare-program-id="${r.program_id}">+ Thêm vào so sánh</button>`
        : '';

      return `<article class="lookup-school-mobile-card">
        <div class="text-xs font-semibold text-brand-600">${esc(r.institution_name)} · ${esc(r.campus)}</div>
        <h3 class="text-base font-bold text-slate-900 mt-1">${esc(r.program_name)}</h3>
        <p class="text-xs text-slate-500">Ngành: ${esc(r.major_name)}${r.program_code ? ' · Mã CT: ' + esc(r.program_code) : ''}</p>
        <div class="lookup-mobile-meta">
          <div><span>Năm &amp; Phương thức:</span> <strong>${r.admission_year ? 'Năm ' + r.admission_year + ' · ' : ''}${esc(r.method)}${r.round_name ? ' (' + esc(r.round_name) + ')' : ''}</strong></div>
          <div><span>Điểm tham khảo:</span> <strong>${esc(r.score_display)}</strong></div>
          <div><span>Tổ hợp:</span> <div class="flex flex-wrap gap-1 mt-1">${combText}</div></div>
          <div><span>Nguồn:</span> ${sourceLink} ${r.verified_on ? `<span class="text-[11px] text-slate-500">· Kiểm tra: ${esc(r.verified_on)}</span>` : ''}</div>
        </div>
        ${compareBtn ? `<div class="mt-3">${compareBtn}</div>` : ''}
      </article>`;
    }).join('');

    resultsEl.innerHTML = `
      <div class="lookup-desktop-table-wrap">
        <table class="lookup-table">
          <thead>
            <tr>
              <th>Trường &amp; Cơ sở</th>
              <th>Ngành / Chương trình</th>
              <th>Năm · Phương thức</th>
              <th>Tổ hợp</th>
              <th>Điểm tham khảo</th>
              <th>Nguồn &amp; Thao tác</th>
            </tr>
          </thead>
          <tbody>${tableRows}</tbody>
        </table>
      </div>
      <div class="lookup-mobile-cards-wrap">${mobileCards}</div>
    `;

    bindBasketButtons(resultsEl);
    renderPagination(data);
  }

  function renderCombinations(data) {
    const items = data.items || [];
    if (countEl) {
      countEl.innerHTML = `Tìm thấy <strong>${Number(data.total || 0)}</strong> chương trình có dữ liệu xác minh`;
    }
    if (!items.length) {
      const ruleNote = data.match_mode === 'all'
        ? 'Không tìm thấy chương trình nào có dữ liệu xác minh nhận đồng thời TẤT CẢ tổ hợp bạn đã chọn trong cùng năm & phương thức.'
        : 'Chưa có chương trình nào trong kho dữ liệu xác minh khớp tổ hợp đã chọn. Hệ thống không tự suy diễn tổ hợp khi chưa có nguồn chính thức.';
      resultsEl.innerHTML = `<div class="lookup-empty">${esc(ruleNote)}</div>`;
      return;
    }

    const detailBase = bootstrap.urls?.major_detail_base || 'index.php?page=lookup&tab=majors&major_id=';
    resultsEl.innerHTML = `<div class="lookup-comb-results-list">${items.map(r => {
      const combsHtml = r.combinations.map(c =>
        `<span class="lookup-comb-pill"><strong>${esc(c.code)}</strong> — ${esc(c.subjects_text)}</span>`
      ).join('');
      const srcLink = r.source_url
        ? `<a class="text-brand-600 font-semibold hover:underline" href="${esc(r.source_url)}" target="_blank" rel="noopener noreferrer">Xem nguồn ↗</a>`
        : '';

      return `<article class="lookup-comb-result-card">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <span class="badge badge-purple">Năm ${r.admission_year} · ${esc(r.method)}</span>
            <h3 class="text-base font-bold text-slate-900 mt-2">
              <a href="${detailBase}${r.major_id}" class="hover:text-brand-600">${esc(r.major_name)}</a> — ${esc(r.program_name)}
            </h3>
            <p class="text-xs text-slate-600 mt-0.5">${esc(r.institution_name)} (${esc(r.province)})</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <a class="btn btn-outline btn-sm" href="${detailBase}${r.major_id}">Xem ngành</a>
            <button
              type="button"
              class="btn btn-outline btn-sm"
              data-add-compare
              data-compare-id="program:${r.program_id}"
              data-compare-label="${esc(r.program_name + ' — ' + r.institution_name)}"
              data-compare-type="program"
              data-compare-major-id="${r.major_id}"
              data-compare-program-id="${r.program_id}"
            >+ Thêm vào so sánh</button>
          </div>
        </div>
        <div class="flex flex-wrap gap-1.5 mt-3">${combsHtml}</div>
        <div class="lookup-comb-result-footer">
          <span><strong>${esc(r.score_display)}</strong></span>
          <span class="text-xs text-slate-500">${srcLink}${r.verified_on ? ' · Kiểm tra: ' + esc(r.verified_on) : ''}</span>
        </div>
        ${r.conditions_note ? `<p class="text-[11px] text-slate-500 mt-2">${esc(r.conditions_note)}</p>` : ''}
      </article>`;
    }).join('')}</div>`;

    bindBasketButtons(resultsEl);
  }

  function renderPagination(data) {
    if (!paginationEl) return;
    const totalPages = Number(data.total_pages || 1);
    const page = Number(data.page || 1);
    if (totalPages <= 1) {
      paginationEl.innerHTML = '';
      return;
    }
    paginationEl.innerHTML = `
      <button type="button" class="btn btn-outline btn-sm" id="lookupPrevPage" ${page <= 1 ? 'disabled' : ''}>← Trang trước</button>
      <span class="text-xs font-semibold text-slate-600">Trang ${page} / ${totalPages}</span>
      <button type="button" class="btn btn-outline btn-sm" id="lookupNextPage" ${page >= totalPages ? 'disabled' : ''}>Trang sau →</button>
    `;
    const prev = document.getElementById('lookupPrevPage');
    const next = document.getElementById('lookupNextPage');
    if (prev) prev.addEventListener('click', () => fetchResults(page - 1));
    if (next) next.addEventListener('click', () => fetchResults(page + 1));
  }

  function collectQueryParams(pageNum) {
    const params = new URLSearchParams();
    params.set('tab', tab);
    if (form) {
      const fd = new FormData(form);
      for (const [k, v] of fd.entries()) {
        if (k === 'page' || k === 'tab') continue;
        const trimmed = String(v).trim();
        if (trimmed !== '') params.set(k, trimmed);
      }
    }
    if (tab === 'combinations' && selectedCombCodes.size > 0) {
      params.set('codes', Array.from(selectedCombCodes).join(','));
    }
    if (pageNum > 1) {
      params.set('p', String(pageNum));
    }
    return params;
  }

  async function fetchResults(pageNum) {
    currentPage = Math.max(1, pageNum || 1);
    const params = collectQueryParams(currentPage);

    // Sync browser URL for back/forward and sharing
    const urlParams = new URLSearchParams(params.toString());
    urlParams.set('page', 'lookup');
    const nextUrl = `index.php?${urlParams.toString()}`;
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, '', nextUrl);
    }

    if (activeController) {
      activeController.abort();
      activeController = null;
    }
    const seq = ++requestSeq;
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    activeController = controller;

    setStatus('loading', 'Đang tải dữ liệu tra cứu...', false);

    try {
      const sep = apiUrl.includes('?') ? '&' : '?';
      const res = await fetch(`${apiUrl}${sep}${params.toString()}`, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: controller ? controller.signal : undefined,
      });
      if (seq !== requestSeq) return;
      const payload = await res.json();
      if (seq !== requestSeq) return;
      if (!res.ok || !payload.ok) {
        throw new Error(payload.error || 'Không thể tải dữ liệu.');
      }
      setStatus(null, '', false);
      if (tab === 'majors') renderMajors(payload.data);
      else if (tab === 'schools') renderSchools(payload.data);
      else if (tab === 'combinations') {
        renderCombPicker(bootstrap.meta?.combinations || []);
        renderCombinations(payload.data);
      }
    } catch (err) {
      if (err && err.name === 'AbortError') return;
      if (seq !== requestSeq) return;
      setStatus('error', 'Không thể tải dữ liệu tra cứu. Vui lòng thử lại.', true);
    } finally {
      if (activeController === controller) activeController = null;
    }
  }

  if (form) {
    form.addEventListener('submit', e => {
      e.preventDefault();
      fetchResults(1);
    });
    form.querySelectorAll('select, input[type="checkbox"], input[type="radio"]').forEach(el => {
      el.addEventListener('change', () => fetchResults(1));
    });
    const qInput = document.getElementById('f-q');
    if (qInput) {
      qInput.addEventListener('input', () => {
        if (tab === 'combinations') {
          renderCombPicker(bootstrap.meta?.combinations || []);
        }
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchResults(1), 220);
      });
    }
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      if (form) {
        form.reset();
        form.querySelectorAll('input[type="search"], select').forEach(el => {
          el.value = '';
        });
        form.querySelectorAll('input[type="checkbox"]').forEach(el => {
          el.checked = false;
        });
        const anyRadio = form.querySelector('input[name="match_mode"][value="any"]');
        if (anyRadio) anyRadio.checked = true;
      }
      selectedCombCodes.clear();
      if (tab === 'combinations') {
        renderCombPicker(bootstrap.meta?.combinations || []);
      }
      fetchResults(1);
    });
  }

  // Initial render from SSR bootstrap data
  if (tab === 'majors') {
    renderMajors(bootstrap.initial || { items: [], total: 0 });
  } else if (tab === 'schools') {
    renderSchools(bootstrap.initial || { items: [], total: 0 });
  } else if (tab === 'combinations') {
    renderCombPicker(bootstrap.meta?.combinations || []);
    renderCombinations(bootstrap.initial || { items: [], total: 0 });
  }
})();
