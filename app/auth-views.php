<?php
declare(strict_types=1);

/**
 * Signup & Profile Form Handlers and Reusable School Selector Views.
 */

function auth_handle_register_post(): array
{
    verify_csrf();

    $name = trim((string)($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    $grade = trim((string)($_POST['grade'] ?? ''));
    $rawProvinceId = is_scalar($_POST['province_id'] ?? null) ? trim((string)$_POST['province_id']) : '';
    $rawSchoolId = is_scalar($_POST['school_id'] ?? null) ? trim((string)$_POST['school_id']) : '';
    $agree = !empty($_POST['agree']);

    $errors = [];

    if (mb_strlen($name) < 2) {
        $errors['name'] = 'Vui lòng nhập họ và tên (tối thiểu 2 ký tự).';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Họ và tên không được vượt quá 100 ký tự.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors['email'] = 'Vui lòng nhập địa chỉ email hợp lệ.';
    }

    if (strlen($pass) < 8) {
        $errors['password'] = 'Mật khẩu cần có ít nhất 8 ký tự.';
    }

    $gradeCheck = schools_validate_grade($grade, true);
    if (!$gradeCheck['ok']) {
        $errors['grade'] = $gradeCheck['error'];
    }

    $schoolCheck = schools_validate_selection($rawProvinceId, $rawSchoolId);
    foreach ($schoolCheck['errors'] as $field => $msg) {
        $errors[$field] = $msg;
    }

    if (!$agree) {
        $errors['agree'] = 'Bạn cần đồng ý điều khoản sử dụng để tiếp tục.';
    }

    $preservedProvinceId = $schoolCheck['province'] ? (string)$schoolCheck['province']['id'] : '';
    $preservedSchoolId = ($schoolCheck['ok'] && $schoolCheck['school']) ? (string)$schoolCheck['school']['id'] : '';

    $values = [
        'name' => $name,
        'email' => $email,
        'grade' => $gradeCheck['ok'] ? $gradeCheck['grade'] : '',
        'province_id' => $preservedProvinceId,
        'school_id' => $preservedSchoolId,
        'agree' => $agree,
    ];

    if (!empty($errors)) {
        http_response_code(422);
        return ['errors' => $errors, 'values' => $values];
    }

    try {
        $colors = ['#536DFE', '#8B5CF6', '#EC4899', '#10B981', '#F59E0B'];
        $school = $schoolCheck['school'];
        $stmt = db()->prepare('INSERT INTO users(name, email, password, grade, school, school_id, avatar_color) VALUES(?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $name,
            $email,
            password_hash($pass, PASSWORD_DEFAULT),
            $gradeCheck['grade'],
            $school['name'],
            $school['id'],
            $colors[array_rand($colors)],
        ]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)db()->lastInsertId();
        audit('Đăng ký');
        redirect('index.php?page=assessment');
    } catch (PDOException $e) {
        $sqlState = (string)$e->getCode();
        $isDuplicate = $sqlState === '23000' || strpos($e->getMessage(), 'UNIQUE') !== false || strpos($e->getMessage(), 'Duplicate') !== false;
        if ($isDuplicate) {
            $errors['email'] = 'Email này đã được sử dụng.';
        } else {
            $errors['form'] = 'Không thể tạo tài khoản lúc này. Vui lòng thử lại.';
        }
        http_response_code(422);
        return ['errors' => $errors, 'values' => $values];
    }
}

function auth_handle_profile_post(): array
{
    require_login();
    verify_csrf();

    $me = user();
    $isStudent = (($me['role'] ?? 'student') === 'student');
    $name = trim((string)($_POST['name'] ?? ''));
    $grade = trim((string)($_POST['grade'] ?? ''));
    $rawProvinceId = is_scalar($_POST['province_id'] ?? null) ? trim((string)$_POST['province_id']) : '';
    $rawSchoolId = is_scalar($_POST['school_id'] ?? null) ? trim((string)$_POST['school_id']) : '';

    $errors = [];

    if (mb_strlen($name) < 2) {
        $errors['name'] = 'Vui lòng nhập họ và tên (tối thiểu 2 ký tự).';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Họ và tên không được vượt quá 100 ký tự.';
    }

    $existingGrade = trim((string)($me['grade'] ?? ''));
    $isKeepingLegacyGrade = $existingGrade !== ''
        && !in_array($existingGrade, schools_grade_options(), true)
        && $grade === $existingGrade;
    if ($isKeepingLegacyGrade) {
        $gradeCheck = ['ok' => true, 'grade' => $existingGrade, 'error' => ''];
    } else {
        $gradeCheck = schools_validate_grade($grade, $isStudent);
        if (!$gradeCheck['ok']) {
            $errors['grade'] = $gradeCheck['error'];
        }
    }

    $schoolCheck = ['ok' => false, 'errors' => [], 'province' => null, 'school' => null];
    $hasHistoricalSchoolText = empty($me['school_id']) && trim((string)($me['school'] ?? '')) !== '';
    $skipSchoolCheck = ($rawProvinceId === '' && $rawSchoolId === '') && (!$isStudent || $hasHistoricalSchoolText);
    if (!$skipSchoolCheck) {
        $schoolCheck = schools_validate_selection($rawProvinceId, $rawSchoolId);
        foreach ($schoolCheck['errors'] as $field => $msg) {
            $errors[$field] = $msg;
        }
    }

    $preservedProvinceId = $schoolCheck['province'] ? (string)$schoolCheck['province']['id'] : '';
    $preservedSchoolId = ($schoolCheck['ok'] && $schoolCheck['school']) ? (string)$schoolCheck['school']['id'] : '';

    $values = [
        'name' => $name,
        'grade' => $gradeCheck['ok'] ? $gradeCheck['grade'] : '',
        'province_id' => $preservedProvinceId,
        'school_id' => $preservedSchoolId,
    ];

    if (!empty($errors)) {
        http_response_code(422);
        return ['errors' => $errors, 'values' => $values];
    }

    $schoolName = $skipSchoolCheck ? (string)($me['school'] ?? '') : $schoolCheck['school']['name'];
    $schoolId = $skipSchoolCheck ? ($me['school_id'] ?? null) : $schoolCheck['school']['id'];

    $stmt = db()->prepare('UPDATE users SET name = ?, grade = ?, school = ?, school_id = ? WHERE id = ?');
    $stmt->execute([
        $name,
        $gradeCheck['grade'],
        $schoolName,
        $schoolId,
        $me['id'],
    ]);

    audit('Cập nhật hồ sơ', 'user', (int)$me['id']);
    flash('success', 'Đã cập nhật hồ sơ.');
    redirect('index.php?page=dashboard');
}

/**
 * Render the reusable "Thông tin trường học" fields (Tỉnh/Thành phố -> Trường học -> Lớp).
 */
function auth_render_school_fields(string $prefix, array $values, array $errors, bool $requireSchoolAndGrade = true, string $preservedLegacyGrade = ''): void
{
    $provinces = schools_list_signup_provinces();
    $schoolsCatalog = schools_grouped_by_province();
    $selectedProvinceId = schools_parse_positive_id($values['province_id'] ?? '');
    $selectedSchoolId = schools_parse_positive_id($values['school_id'] ?? '');

    // Verify selected province is still an active signup province
    $validProvinceIds = array_column($provinces, 'id');
    if ($selectedProvinceId === null || !in_array($selectedProvinceId, $validProvinceIds, true)) {
        $selectedProvinceId = null;
        $selectedSchoolId = null;
    }

    $initialSchools = $selectedProvinceId !== null ? schools_list_signup_by_province($selectedProvinceId) : [];
    $validSchoolIds = array_column($initialSchools, 'id');
    if ($selectedSchoolId !== null && !in_array($selectedSchoolId, $validSchoolIds, true)) {
        $selectedSchoolId = null;
    }

    $provIdAttr = $prefix . '-province';
    $schoolIdAttr = $prefix . '-school';
    $gradeIdAttr = $prefix . '-grade';
    $provErrId = $prefix . '-err-province';
    $schoolErrId = $prefix . '-err-school';
    $gradeErrId = $prefix . '-err-grade';
    $statusId = $prefix . '-school-status';
    $retryId = $prefix . '-school-retry';

    $provError = (string)($errors['province_id'] ?? '');
    $schoolError = (string)($errors['school_id'] ?? '');
    $gradeError = (string)($errors['grade'] ?? '');
    $selectedGrade = trim((string)($values['grade'] ?? ''));
    $hasPreservedLegacyGrade = $preservedLegacyGrade !== '' && !in_array($preservedLegacyGrade, schools_grade_options(), true);
    if (!in_array($selectedGrade, schools_grade_options(), true) && !($hasPreservedLegacyGrade && $selectedGrade === $preservedLegacyGrade)) {
        $selectedGrade = '';
    }
    ?>
    <div
      class="school-selector-group"
      data-school-selector
      data-api-url="<?=e(url('index.php?page=schools-api'))?>"
      data-schools-catalog="<?=e(json_encode($schoolsCatalog, JSON_UNESCAPED_UNICODE))?>"
    >
      <div class="school-fields-grid">
        <div class="form-group">
          <label class="form-label" for="<?=e($provIdAttr)?>">
            Tỉnh/Thành phố<?php if ($requireSchoolAndGrade): ?> <span class="req-mark" aria-hidden="true">*</span><?php endif; ?>
          </label>
          <select
            id="<?=e($provIdAttr)?>"
            name="province_id"
            class="form-select<?= $provError !== '' ? ' is-invalid' : '' ?>"
            data-province-select
            aria-required="<?= $requireSchoolAndGrade ? 'true' : 'false' ?>"
            aria-invalid="<?= $provError !== '' ? 'true' : 'false' ?>"
            aria-describedby="<?=e($provErrId)?>"
            <?= $requireSchoolAndGrade ? 'required' : '' ?>
          >
            <option value="">Chọn tỉnh/thành phố</option>
            <?php foreach ($provinces as $prov): ?>
              <option value="<?=$prov['id']?>"<?=$selectedProvinceId === $prov['id'] ? ' selected' : ''?>><?=e($prov['name'])?></option>
            <?php endforeach; ?>
          </select>
          <p id="<?=e($provErrId)?>" class="field-error" data-error-for="province_id" role="alert"<?= $provError === '' ? ' hidden' : '' ?>><?=e($provError)?></p>
        </div>

        <div class="form-group">
          <label class="form-label" for="<?=e($schoolIdAttr)?>">
            Trường học<?php if ($requireSchoolAndGrade): ?> <span class="req-mark" aria-hidden="true">*</span><?php endif; ?>
          </label>
          <select
            id="<?=e($schoolIdAttr)?>"
            name="school_id"
            class="form-select<?= $schoolError !== '' ? ' is-invalid' : '' ?>"
            data-school-select
            aria-required="<?= $requireSchoolAndGrade ? 'true' : 'false' ?>"
            aria-invalid="<?= $schoolError !== '' ? 'true' : 'false' ?>"
            aria-describedby="<?=e($schoolErrId)?> <?=e($statusId)?>"
            <?= $selectedProvinceId === null ? 'disabled' : '' ?>
            <?= $requireSchoolAndGrade ? 'required' : '' ?>
          >
            <?php if ($selectedProvinceId === null): ?>
              <option value="">Vui lòng chọn tỉnh/thành phố trước</option>
            <?php else: ?>
              <option value="">Chọn trường học</option>
              <?php foreach ($initialSchools as $sch): ?>
                <option value="<?=$sch['id']?>"<?=$selectedSchoolId === $sch['id'] ? ' selected' : ''?>><?=e($sch['name'])?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
          <div class="school-async-feedback" data-school-status-wrap hidden>
            <span id="<?=e($statusId)?>" class="school-status-text" data-school-status aria-live="polite"></span>
            <button type="button" id="<?=e($retryId)?>" class="school-retry-btn" data-school-retry hidden>Thử lại</button>
          </div>
          <p id="<?=e($schoolErrId)?>" class="field-error" data-error-for="school_id" role="alert"<?= $schoolError === '' ? ' hidden' : '' ?>><?=e($schoolError)?></p>
        </div>
      </div>

      <div class="form-group school-grade-group">
        <label class="form-label" for="<?=e($gradeIdAttr)?>">
          Khối lớp<?php if ($requireSchoolAndGrade): ?> <span class="req-mark" aria-hidden="true">*</span><?php endif; ?>
        </label>
        <select
          id="<?=e($gradeIdAttr)?>"
          name="grade"
          class="form-select<?= $gradeError !== '' ? ' is-invalid' : '' ?>"
          aria-required="<?= $requireSchoolAndGrade ? 'true' : 'false' ?>"
          aria-invalid="<?= $gradeError !== '' ? 'true' : 'false' ?>"
          aria-describedby="<?=e($gradeErrId)?>"
          <?= $requireSchoolAndGrade ? 'required' : '' ?>
        >
          <option value="">Chọn khối lớp</option>
          <?php if ($hasPreservedLegacyGrade): ?>
            <option value="<?=e($preservedLegacyGrade)?>"<?=$selectedGrade === $preservedLegacyGrade ? ' selected' : ''?>><?=e($preservedLegacyGrade)?> (lưu từ trước)</option>
          <?php endif; ?>
          <?php foreach (schools_grade_options() as $gradeOpt): ?>
            <option value="<?=e($gradeOpt)?>"<?=$selectedGrade === $gradeOpt ? ' selected' : ''?>><?=e($gradeOpt)?></option>
          <?php endforeach; ?>
        </select>
        <p id="<?=e($gradeErrId)?>" class="field-error" data-error-for="grade" role="alert"<?= $gradeError === '' ? ' hidden' : '' ?>><?=e($gradeError)?></p>
      </div>

      <p class="school-scope-note">Hiện JobSimu hỗ trợ đăng ký cho các trường trong danh sách. Dành cho học sinh THPT (Lớp 10–12).</p>
    </div>
    <?php
}

function auth_render_login_or_register(string $page, array $errors = [], array $values = []): void
{
    $isRegister = ($page === 'register');
    $pageTitle = $isRegister ? 'Đăng ký' : 'Đăng nhập';
    require __DIR__ . '/header.php';

    $nameVal = (string)($values['name'] ?? '');
    $emailVal = (string)($values['email'] ?? ($_POST['email'] ?? ''));
    $agreeChecked = !empty($values['agree']);

    $nameErr = (string)($errors['name'] ?? '');
    $emailErr = (string)($errors['email'] ?? '');
    $passErr = (string)($errors['password'] ?? '');
    $agreeErr = (string)($errors['agree'] ?? '');
    $formErr = (string)($errors['form'] ?? '');
    ?>
    <div class="auth-wrap">
      <div class="auth-card<?= $isRegister ? ' auth-card--register' : '' ?>">
        <span class="eyebrow"><?= $isRegister ? 'Bắt đầu hành trình' : 'Chào mừng trở lại' ?></span>
        <h1><?= $isRegister ? 'Tạo tài khoản' : 'Đăng nhập' ?></h1>
        <p class="muted"><?= $isRegister ? 'Lưu kết quả và theo dõi hành trình nghề nghiệp.' : 'Tiếp tục khám phá tương lai của bạn.' ?></p>

        <?php if ($formErr !== ''): ?>
          <div class="alert alert-error mt-4" role="alert"><?=e($formErr)?></div>
        <?php endif; ?>

        <?php if (!$isRegister): ?>
          <form method="post" action="<?=url('index.php?page=login')?>">
            <?=csrf_field()?>
            <div class="form-group">
              <label class="form-label" for="auth-email">Email</label>
              <input id="auth-email" class="form-input" type="email" name="email" value="<?=e($emailVal)?>" autocomplete="email" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="auth-password">Mật khẩu</label>
              <input id="auth-password" class="form-input" type="password" name="password" autocomplete="current-password" minlength="8" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg mt-5 w-full">Đăng nhập</button>
          </form>
        <?php else: ?>
          <form
            method="post"
            action="<?=url('index.php?page=register')?>"
            id="registerForm"
            class="auth-register-form"
            data-auth-form="register"
          >
            <?=csrf_field()?>

            <fieldset class="auth-fieldset">
              <legend class="auth-fieldset-legend">Thông tin tài khoản</legend>

              <div class="form-group">
                <label class="form-label" for="auth-name">
                  Họ và tên <span class="req-mark" aria-hidden="true">*</span>
                </label>
                <input
                  id="auth-name"
                  class="form-input<?= $nameErr !== '' ? ' is-invalid' : '' ?>"
                  type="text"
                  name="name"
                  value="<?=e($nameVal)?>"
                  autocomplete="name"
                  maxlength="100"
                  aria-required="true"
                  aria-invalid="<?= $nameErr !== '' ? 'true' : 'false' ?>"
                  aria-describedby="auth-err-name"
                  required
                >
                <p id="auth-err-name" class="field-error" data-error-for="name" role="alert"<?= $nameErr === '' ? ' hidden' : '' ?>><?=e($nameErr)?></p>
              </div>

              <div class="auth-account-grid">
                <div class="form-group">
                  <label class="form-label" for="auth-email">
                    Email <span class="req-mark" aria-hidden="true">*</span>
                  </label>
                  <input
                    id="auth-email"
                    class="form-input<?= $emailErr !== '' ? ' is-invalid' : '' ?>"
                    type="email"
                    name="email"
                    value="<?=e($emailVal)?>"
                    autocomplete="email"
                    maxlength="190"
                    aria-required="true"
                    aria-invalid="<?= $emailErr !== '' ? 'true' : 'false' ?>"
                    aria-describedby="auth-err-email"
                    required
                  >
                  <p id="auth-err-email" class="field-error" data-error-for="email" role="alert"<?= $emailErr === '' ? ' hidden' : '' ?>><?=e($emailErr)?></p>
                </div>

                <div class="form-group">
                  <label class="form-label" for="auth-password">
                    Mật khẩu <span class="req-mark" aria-hidden="true">*</span>
                  </label>
                  <input
                    id="auth-password"
                    class="form-input<?= $passErr !== '' ? ' is-invalid' : '' ?>"
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    minlength="8"
                    aria-required="true"
                    aria-invalid="<?= $passErr !== '' ? 'true' : 'false' ?>"
                    aria-describedby="auth-hint-password auth-err-password"
                    required
                  >
                  <p id="auth-hint-password" class="field-hint">Tối thiểu 8 ký tự.</p>
                  <p id="auth-err-password" class="field-error" data-error-for="password" role="alert"<?= $passErr === '' ? ' hidden' : '' ?>><?=e($passErr)?></p>
                </div>
              </div>
            </fieldset>

            <fieldset class="auth-fieldset">
              <legend class="auth-fieldset-legend">Thông tin trường học</legend>
              <?php auth_render_school_fields('auth', $values, $errors); ?>
            </fieldset>

            <div class="form-group auth-agree-group">
              <label class="auth-agree-label" for="auth-agree">
                <input
                  id="auth-agree"
                  class="auth-agree-checkbox"
                  type="checkbox"
                  name="agree"
                  value="1"
                  aria-required="true"
                  aria-invalid="<?= $agreeErr !== '' ? 'true' : 'false' ?>"
                  aria-describedby="auth-err-agree"
                  <?= $agreeChecked ? 'checked' : '' ?>
                  required
                >
                <span>Tôi đồng ý sử dụng kết quả với mục đích tham khảo hướng nghiệp.</span>
              </label>
              <p id="auth-err-agree" class="field-error" data-error-for="agree" role="alert"<?= $agreeErr === '' ? ' hidden' : '' ?>><?=e($agreeErr)?></p>
            </div>

            <button
              type="submit"
              id="registerSubmitBtn"
              class="btn btn-primary btn-lg mt-5 w-full"
              data-submit-btn
              data-loading-text="Đang tạo tài khoản..."
            >Đăng ký miễn phí</button>
          </form>
          <script src="<?=url('assets/js/school-selector.js')?>"></script>
        <?php endif; ?>

        <p class="mt-6 text-center">
          <?php if (!$isRegister): ?>
            Chưa có tài khoản? <a class="font-bold text-brand-600" href="<?=url('index.php?page=register')?>">Đăng ký</a>
          <?php else: ?>
            Đã có tài khoản? <a class="font-bold text-brand-600" href="<?=url('index.php?page=login')?>">Đăng nhập</a>
          <?php endif; ?>
        </p>
      </div>
    </div>
    <?php
    require __DIR__ . '/footer.php';
}

function auth_render_profile_settings(array $me, array $errors = [], ?array $overrideValues = null): void
{
    $isStudent = (($me['role'] ?? 'student') === 'student');
    $values = $overrideValues ?? [
        'name' => (string)($me['name'] ?? ''),
        'grade' => (string)($me['grade'] ?? ''),
        'province_id' => !empty($me['province_id']) ? (string)$me['province_id'] : '',
        'school_id' => !empty($me['school_id']) ? (string)$me['school_id'] : '',
    ];
    $nameErr = (string)($errors['name'] ?? '');
    $needsSchoolCompletion = !empty($me['school_needs_completion']);
    $needsGradeCompletion = !empty($me['grade_needs_completion']);
    $legacySchool = trim((string)($me['school'] ?? ''));
    $legacyGrade = trim((string)($me['grade'] ?? ''));
    ?>
    <div class="max-w-2xl mx-auto">
      <div class="card p-6">
        <h2 class="text-xl font-bold text-slate-900 mb-1">Hồ sơ cá nhân</h2>
        <p class="text-xs text-slate-500 mb-5">Thông tin giúp hệ thống cá nhân hóa đề xuất tuyển sinh và lộ trình phù hợp.</p>

        <?php if ($needsSchoolCompletion || $needsGradeCompletion): ?>
          <div class="alert alert-warning mb-5" role="status">
            <?php if ($needsSchoolCompletion): ?>
              <div>
                <?php if ($legacySchool !== ''): ?>
                  Hồ sơ hiện chưa liên kết trường trong danh sách hỗ trợ (ghi nhận trước đây: <b><?=e($legacySchool)?></b>).
                <?php else: ?>
                  Hồ sơ của bạn chưa có thông tin trường học.
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if ($needsGradeCompletion): ?>
              <div>
                <?php if ($legacyGrade !== ''): ?>
                  Khối lớp ghi nhận trước đây là <b><?=e($legacyGrade)?></b> — JobSimu hiện dành cho học sinh THPT (Lớp 10–12).
                <?php else: ?>
                  Hồ sơ của bạn chưa có thông tin khối lớp (Lớp 10–12).
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="mt-1">Vui lòng chọn đầy đủ Tỉnh/Thành phố, Trường học và Khối lớp bên dưới để hoàn thiện hồ sơ.</div>
          </div>
        <?php endif; ?>

        <?php $hasHistoricalSchoolText = empty($me['school_id']) && $legacySchool !== ''; ?>
        <form
          method="post"
          action="<?=url('index.php?page=profile')?>"
          id="profileForm"
          class="auth-register-form"
          data-auth-form="profile"
          data-require-school="<?= ($isStudent && !$hasHistoricalSchoolText) ? '1' : '0' ?>"
          data-require-grade="<?= $isStudent ? '1' : '0' ?>"
          data-preserved-grade="<?=e($needsGradeCompletion ? $legacyGrade : '')?>"
        >
          <?=csrf_field()?>

          <fieldset class="auth-fieldset">
            <legend class="auth-fieldset-legend">Thông tin tài khoản</legend>

            <div class="form-group">
              <label class="form-label" for="profile-name">
                Họ và tên <span class="req-mark" aria-hidden="true">*</span>
              </label>
              <input
                id="profile-name"
                class="form-input<?= $nameErr !== '' ? ' is-invalid' : '' ?>"
                type="text"
                name="name"
                value="<?=e($values['name'])?>"
                maxlength="100"
                aria-required="true"
                aria-invalid="<?= $nameErr !== '' ? 'true' : 'false' ?>"
                aria-describedby="profile-err-name"
                required
              >
              <p id="profile-err-name" class="field-error" data-error-for="name" role="alert"<?= $nameErr === '' ? ' hidden' : '' ?>><?=e($nameErr)?></p>
            </div>

            <div class="form-group">
              <label class="form-label" for="profile-email">Email tài khoản</label>
              <input id="profile-email" class="form-input" value="<?=e($me['email'])?>" disabled>
              <p class="field-hint">Email dùng để đăng nhập và bảo mật tài khoản.</p>
            </div>
          </fieldset>

          <fieldset class="auth-fieldset">
            <legend class="auth-fieldset-legend">Thông tin trường học</legend>
            <?php auth_render_school_fields('profile', $values, $errors, $isStudent && !$hasHistoricalSchoolText, $needsGradeCompletion ? $legacyGrade : ''); ?>
          </fieldset>

          <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-slate-100">
            <button
              type="submit"
              class="btn btn-primary"
              data-submit-btn
              data-loading-text="Đang lưu hồ sơ..."
            >Lưu thay đổi hồ sơ</button>
            <a class="btn btn-outline" href="<?=url('index.php?page=dashboard&tab=overview')?>">Hủy</a>
          </div>
        </form>
        <script src="<?=url('assets/js/school-selector.js')?>"></script>
      </div>
    </div>
    <?php
}
