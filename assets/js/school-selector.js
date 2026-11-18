/**
 * JobSimu - Province -> School dependent selector & inline form validation
 * Shared between Signup (index.php?page=register) and Profile Settings (index.php?page=dashboard&tab=settings).
 */
(function () {
  'use strict';

  const PLACEHOLDER_NEED_PROVINCE = 'Vui lòng chọn tỉnh/thành phố trước';
  const PLACEHOLDER_CHOOSE_SCHOOL = 'Chọn trường học';
  const PLACEHOLDER_LOADING = 'Đang tải danh sách trường...';
  const PLACEHOLDER_ERROR = 'Không thể tải danh sách trường';

  function initAuthForm(form) {
    if (!form || form.dataset.schoolSelectorBound === '1') return;
    form.dataset.schoolSelectorBound = '1';

    const selectorRoot = form.querySelector('[data-school-selector]');
    if (!selectorRoot) return;

    const apiUrl = selectorRoot.dataset.apiUrl || 'index.php?page=schools-api';
    const provinceSelect = selectorRoot.querySelector('[data-province-select]');
    const schoolSelect = selectorRoot.querySelector('[data-school-select]');
    const statusWrap = selectorRoot.querySelector('[data-school-status-wrap]');
    const statusText = selectorRoot.querySelector('[data-school-status]');
    const retryBtn = selectorRoot.querySelector('[data-school-retry]');
    const submitBtn = form.querySelector('[data-submit-btn]');
    const mode = form.dataset.authForm || 'register';

    if (!provinceSelect || !schoolSelect) return;

    // Progressive enhancement: use accessible inline Vietnamese validation instead of browser tooltips
    form.setAttribute('novalidate', '');

    let requestSeq = 0;
    let activeController = null;
    let isLoadingSchools = false;
    let loadFailed = false;
    let isSubmitting = false;

    function getFieldInput(fieldName) {
      return form.querySelector(`[name="${fieldName}"]`);
    }

    function setFieldError(fieldName, message) {
      const errorEl = form.querySelector(`[data-error-for="${fieldName}"]`);
      const inputEl = getFieldInput(fieldName);
      if (errorEl) {
        if (message) {
          errorEl.textContent = message;
          errorEl.hidden = false;
        } else {
          errorEl.textContent = '';
          errorEl.hidden = true;
        }
      }
      if (inputEl) {
        inputEl.setAttribute('aria-invalid', message ? 'true' : 'false');
        inputEl.classList.toggle('is-invalid', Boolean(message));
      }
    }

    function setSchoolOptions(placeholderText, schools) {
      schoolSelect.innerHTML = '';
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = placeholderText;
      schoolSelect.appendChild(placeholder);

      if (Array.isArray(schools)) {
        schools.forEach(item => {
          const opt = document.createElement('option');
          opt.value = String(item.id);
          opt.textContent = String(item.name);
          schoolSelect.appendChild(opt);
        });
      }
      schoolSelect.value = '';
    }

    function setAsyncFeedback(state, message) {
      if (!statusWrap || !statusText || !retryBtn) return;
      if (state === 'idle') {
        statusWrap.hidden = true;
        statusWrap.classList.remove('is-error', 'is-loading');
        statusText.textContent = '';
        retryBtn.hidden = true;
        return;
      }
      statusWrap.hidden = false;
      statusWrap.classList.toggle('is-loading', state === 'loading');
      statusWrap.classList.toggle('is-error', state === 'error');
      statusText.textContent = message || '';
      retryBtn.hidden = state !== 'error';
    }

    function lockSchoolSelect() {
      isLoadingSchools = false;
      loadFailed = false;
      schoolSelect.disabled = true;
      setSchoolOptions(PLACEHOLDER_NEED_PROVINCE, []);
      setAsyncFeedback('idle', '');
    }

    function buildApiUrl(provinceId) {
      const sep = apiUrl.includes('?') ? '&' : '?';
      return `${apiUrl}${sep}province_id=${encodeURIComponent(provinceId)}`;
    }

    async function fetchSchoolsForProvince(provinceId) {
      if (activeController) {
        activeController.abort();
        activeController = null;
      }

      const seq = ++requestSeq;

      // Immediately clear previous school value so stale cross-province values are never kept
      schoolSelect.value = '';
      setFieldError('school_id', '');

      if (!provinceId) {
        lockSchoolSelect();
        return;
      }

      isLoadingSchools = true;
      loadFailed = false;
      schoolSelect.disabled = true;
      setSchoolOptions(PLACEHOLDER_LOADING, []);
      setAsyncFeedback('loading', 'Đang tải danh sách trường...');

      const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
      activeController = controller;

      try {
        const response = await fetch(buildApiUrl(provinceId), {
          method: 'GET',
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
          signal: controller ? controller.signal : undefined,
        });

        // Ignore stale response if user already changed province
        if (seq !== requestSeq) return;

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json();
        if (seq !== requestSeq) return;

        if (!payload || payload.ok !== true || !Array.isArray(payload.schools)) {
          throw new Error('Invalid school catalog payload');
        }

        isLoadingSchools = false;
        loadFailed = false;
        // Always require user to actively select a school, even if only 1 school exists
        setSchoolOptions(PLACEHOLDER_CHOOSE_SCHOOL, payload.schools);
        schoolSelect.disabled = false;
        setAsyncFeedback('idle', '');
      } catch (err) {
        if (err && err.name === 'AbortError') return;
        if (seq !== requestSeq) return;

        isLoadingSchools = false;
        loadFailed = true;
        schoolSelect.disabled = true;
        setSchoolOptions(PLACEHOLDER_ERROR, []);
        setAsyncFeedback('error', 'Không thể tải danh sách trường. Vui lòng thử lại.');
      } finally {
        if (activeController === controller) {
          activeController = null;
        }
      }
    }

    // Ensure initial lock state when no province is pre-selected
    if (!provinceSelect.value) {
      lockSchoolSelect();
    }

    provinceSelect.addEventListener('change', () => {
      setFieldError('province_id', '');
      fetchSchoolsForProvince(provinceSelect.value.trim());
    });

    schoolSelect.addEventListener('change', () => {
      if (schoolSelect.value.trim() !== '') {
        setFieldError('school_id', '');
      }
    });

    if (retryBtn) {
      retryBtn.addEventListener('click', () => {
        const currentProvince = provinceSelect.value.trim();
        if (currentProvince) {
          fetchSchoolsForProvince(currentProvince);
        }
      });
    }

    // Clear inline errors on user edit
    ['name', 'email', 'password', 'grade'].forEach(field => {
      const el = getFieldInput(field);
      if (!el) return;
      el.addEventListener('input', () => setFieldError(field, ''));
      el.addEventListener('change', () => setFieldError(field, ''));
    });

    const agreeInput = getFieldInput('agree');
    if (agreeInput) {
      agreeInput.addEventListener('change', () => {
        if (agreeInput.checked) setFieldError('agree', '');
      });
    }

    form.addEventListener('submit', e => {
      if (isSubmitting) {
        e.preventDefault();
        return;
      }

      let firstInvalid = null;
      function markInvalid(fieldName, message) {
        setFieldError(fieldName, message);
        if (!firstInvalid) {
          const el = getFieldInput(fieldName);
          if (el && !el.disabled) {
            firstInvalid = el;
          } else if (fieldName === 'school_id' && retryBtn && !retryBtn.hidden) {
            firstInvalid = retryBtn;
          } else if (fieldName === 'school_id') {
            firstInvalid = provinceSelect;
          }
        }
      }

      const nameInput = getFieldInput('name');
      if (nameInput) {
        const nameVal = nameInput.value.trim();
        if (nameVal.length < 2) {
          markInvalid('name', 'Vui lòng nhập họ và tên (tối thiểu 2 ký tự).');
        } else if (nameVal.length > 100) {
          markInvalid('name', 'Họ và tên không được vượt quá 100 ký tự.');
        } else {
          setFieldError('name', '');
        }
      }

      if (mode === 'register') {
        const emailInput = getFieldInput('email');
        if (emailInput) {
          const emailVal = emailInput.value.trim();
          const emailOk = emailVal !== '' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal);
          if (!emailOk) {
            markInvalid('email', 'Vui lòng nhập địa chỉ email hợp lệ.');
          } else {
            setFieldError('email', '');
          }
        }

        const passInput = getFieldInput('password');
        if (passInput) {
          if (passInput.value.length < 8) {
            markInvalid('password', 'Mật khẩu cần có ít nhất 8 ký tự.');
          } else {
            setFieldError('password', '');
          }
        }
      }

      const requireSchool = form.dataset.requireSchool !== '0';
      const requireGrade = form.dataset.requireGrade !== '0';
      const provinceVal = provinceSelect.value.trim();
      const schoolVal = schoolSelect.value.trim();

      if (requireSchool || provinceVal !== '' || schoolVal !== '') {
        if (!provinceVal) {
          markInvalid('province_id', 'Vui lòng chọn tỉnh/thành phố.');
          setFieldError('school_id', 'Vui lòng chọn tỉnh/thành phố trước khi chọn trường học.');
        } else {
          setFieldError('province_id', '');
          if (isLoadingSchools) {
            markInvalid('school_id', 'Đang tải danh sách trường, vui lòng đợi trong giây lát.');
          } else if (loadFailed) {
            markInvalid('school_id', 'Không thể tải danh sách trường. Vui lòng bấm Thử lại.');
          } else if (!schoolVal) {
            markInvalid('school_id', 'Vui lòng chọn trường học.');
          } else {
            setFieldError('school_id', '');
          }
        }
      } else {
        setFieldError('province_id', '');
        setFieldError('school_id', '');
      }

      const gradeInput = getFieldInput('grade');
      if (gradeInput) {
        const gradeVal = gradeInput.value.trim();
        const allowedGrades = ['Lớp 10', 'Lớp 11', 'Lớp 12'];
        const preservedGrade = mode === 'profile' ? (form.dataset.preservedGrade || '').trim() : '';
        if (gradeVal === '') {
          if (requireGrade) {
            markInvalid('grade', 'Vui lòng chọn khối lớp (Lớp 10, Lớp 11 hoặc Lớp 12).');
          } else {
            setFieldError('grade', '');
          }
        } else if (!allowedGrades.includes(gradeVal) && !(preservedGrade !== '' && gradeVal === preservedGrade)) {
          markInvalid('grade', 'Hệ thống chỉ hỗ trợ học sinh THPT (Lớp 10, Lớp 11, Lớp 12).');
        } else {
          setFieldError('grade', '');
        }
      }

      if (mode === 'register' && agreeInput) {
        if (!agreeInput.checked) {
          markInvalid('agree', 'Bạn cần đồng ý điều khoản sử dụng để tiếp tục.');
        } else {
          setFieldError('agree', '');
        }
      }

      if (firstInvalid) {
        e.preventDefault();
        firstInvalid.focus();
        return;
      }

      // Prevent duplicate submissions while request is in flight
      isSubmitting = true;
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.setAttribute('aria-busy', 'true');
        if (submitBtn.dataset.loadingText) {
          submitBtn.textContent = submitBtn.dataset.loadingText;
        }
      }
    });
  }

  function boot() {
    document.querySelectorAll('form[data-auth-form]').forEach(initAuthForm);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
