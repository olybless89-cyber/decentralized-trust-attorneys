<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/countries.php';
require_once __DIR__ . '/includes/mailer.php';

$user = current_user();
$errors = [];
$countriesData = countries_with_states();
$countryList = all_countries();

$entityLabels = [
    'LLC' => 'Limited Liability Company (LLC)',
    'CCORP' => 'Corporation (C-CORP)',
    'CLOSE_LLC' => 'Close LLC',
    'CLOSE_CORP' => 'Close Corporation',
];
$recommendedJurisdictions = recommended_jurisdictions();

$old = $_POST; // repopulate on validation error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please resubmit the form.';
    } else {
        $full_name = trim($_POST['full_name'] ?? ($user['full_name'] ?? ''));
        $email = trim($_POST['email'] ?? ($user['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $street_address = trim($_POST['street_address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $state_region = trim($_POST['state_region'] ?? '');
        $ssn_last4 = trim($_POST['ssn_last4'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        $entity_type = $_POST['entity_type'] ?? '';
        $business_name = trim($_POST['business_name'] ?? '');
        $formation_country = trim($_POST['formation_country'] ?? 'United States');
        $formation_region = trim($_POST['formation_region'] ?? '');
        $formation_state = $formation_region !== '' ? ($formation_region . ', ' . $formation_country) : $formation_country;

        // --- validation ---
        if ($full_name === '' || $email === '' || $country === '' || $business_name === '' || !isset($entityLabels[$entity_type])) {
            $errors[] = 'Please complete all required fields marked with *.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($country === 'United States' && $ssn_last4 !== '' && !preg_match('/^\d{4}$/', $ssn_last4)) {
            $errors[] = 'Last 4 digits of SSN must be exactly 4 numbers.';
        }
        if (!$user) {
            if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
            if ($password !== $password_confirm) $errors[] = 'Passwords do not match.';
            if (!$errors) {
                $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) $errors[] = 'An account with that email already exists. Please log in first.';
            }
        }

        if (!$errors) {
            $idDocPath = handle_id_upload('id_document', $user['id_document_path'] ?? null);

            $isNewUser = !$user;
            if ($user) {
                $stmt = db()->prepare('UPDATE users SET full_name=?, phone=?, street_address=?, city=?, country=?, state_region=?, ssn_last4=?, id_document_path=? WHERE id=?');
                $stmt->execute([$full_name, $phone, $street_address, $city, $country, $state_region, $ssn_last4 ?: null, $idDocPath, $user['id']]);
                $userId = $user['id'];
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = db()->prepare('INSERT INTO users (full_name, email, password_hash, phone, street_address, city, country, state_region, ssn_last4, id_document_path) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$full_name, $email, $hash, $phone, $street_address, $city, $country, $state_region, $ssn_last4 ?: null, $idDocPath]);
                $userId = (int) db()->lastInsertId();
                $_SESSION['user_id'] = $userId;
            }

            $stmt = db()->prepare('INSERT INTO applications (user_id, entity_type, business_name, state, owner_name, owner_email, owner_phone, address) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$userId, $entity_type, $business_name, $formation_state, $full_name, $email, $phone, $street_address . ($city ? ', ' . $city : '')]);

            if ($isNewUser) {
                send_email($email, $full_name, 'Welcome to ' . SITE_NAME,
                    '<p>Hi ' . e($full_name) . ',</p><p>Your account has been created. You can log in any time at <a href="' . e(SITE_URL) . '/login.php">' . e(SITE_URL) . '/login.php</a> to track your application and manage your account.</p>');
            }
            send_email($email, $full_name, 'Application Received — ' . $business_name,
                '<p>Hi ' . e($full_name) . ',</p><p>We\'ve received your formation application for <strong>' . e($business_name) . '</strong> (' . e($entityLabels[$entity_type]) . ', jurisdiction: ' . e($formation_state) . ').</p><p>Our team will review it and update the status on your dashboard. You\'ll get another email as soon as that happens.</p>');

            flash_set('Application submitted! We will review it shortly.');
            header('Location: dashboard.php');
            exit;
        }
    }
}

$presetType = $_GET['type'] ?? ($old['entity_type'] ?? 'LLC');
if (!isset($entityLabels[$presetType])) $presetType = 'LLC';

$__base = '';
$pageTitle = 'Start Your Application';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:44px">
  <div class="container">
    <div class="section-head">
      <h2>Business Formation Application</h2>
      <p>Complete your identity verification and business details below to get started.</p>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error" style="max-width:680px;margin:0 auto 18px"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" id="appForm">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="form-card" style="max-width:680px">

        <div class="section-label"><div class="n">1</div><h3>Personal Information</h3></div>

        <div class="form-row-2">
          <div class="field"><label>Full Name *</label>
            <input type="text" name="full_name" required placeholder="John Smith" value="<?= e($old['full_name'] ?? $user['full_name'] ?? '') ?>">
          </div>
          <div class="field"><label>Email *</label>
            <input type="email" name="email" required placeholder="john@example.com" <?= $user ? 'readonly' : '' ?> value="<?= e($old['email'] ?? $user['email'] ?? '') ?>">
          </div>
        </div>

        <div class="form-row-2">
          <div class="field"><label>Phone *</label>
            <input type="text" name="phone" required placeholder="(555) 123-4567" value="<?= e($old['phone'] ?? $user['phone'] ?? '') ?>">
          </div>
          <div class="field"><label>Street Address *</label>
            <input type="text" name="street_address" required placeholder="123 Main St" value="<?= e($old['street_address'] ?? $user['street_address'] ?? '') ?>">
          </div>
        </div>

        <div class="form-row-2">
          <div class="field"><label>City *</label>
            <input type="text" name="city" required placeholder="Cheyenne" value="<?= e($old['city'] ?? $user['city'] ?? '') ?>">
          </div>
          <div class="field"><label>Country *</label>
            <select name="country" id="country" required onchange="onCountryChange()">
              <option value="">Select country&hellip;</option>
              <?php $selCountry = $old['country'] ?? $user['country'] ?? ''; foreach ($countryList as $c): ?>
                <option value="<?= e($c) ?>" <?= $selCountry === $c ? 'selected' : '' ?>><?= e($c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" id="stateWrap">
          <label>State / Province / Region</label>
          <select name="state_region" id="state_region_select" style="display:none"></select>
          <input type="text" name="state_region_text" id="state_region_text" placeholder="State / Province / Region" value="<?= e($old['state_region'] ?? $user['state_region'] ?? '') ?>">
        </div>

        <div class="field conditional-field" id="ssnField" style="display:none">
          <label>Last 4 Digits of SSN <span style="color:var(--muted);font-weight:400">(U.S. citizens/residents only)</span></label>
          <input type="text" name="ssn_last4" maxlength="4" pattern="\d{4}" placeholder="1234" value="<?= e($old['ssn_last4'] ?? $user['ssn_last4'] ?? '') ?>">
          <div class="hint">Used for identity verification only. We never store your full SSN.</div>
        </div>

        <div class="field">
          <label>Government-Issued ID Upload <?= $user && $user['id_document_path'] ? '' : '*' ?></label>
          <input type="file" name="id_document" accept=".jpg,.jpeg,.png,.pdf" <?= ($user && $user['id_document_path']) ? '' : 'required' ?>>
          <div class="hint">
            <?php if ($user && $user['id_document_path']): ?>
              A document is already on file. Upload a new one to replace it.
            <?php else: ?>
              JPG, PNG or PDF, up to 5MB.
            <?php endif; ?>
          </div>
        </div>

        <?php if (!$user): ?>
        <div class="form-row-2">
          <div class="field"><label>Password *</label><input type="password" name="password" required placeholder="At least 6 characters"></div>
          <div class="field"><label>Confirm Password *</label><input type="password" name="password_confirm" required></div>
        </div>
        <?php endif; ?>

        <hr class="section-divider">

        <div class="section-label"><div class="n">2</div><h3>Business Information</h3></div>

        <div class="field">
          <label>Business Name *</label>
          <input type="text" name="business_name" required placeholder="Acme Holdings LLC" value="<?= e($old['business_name'] ?? '') ?>">
        </div>

        <div class="form-row-2">
          <div class="field"><label>Formation Country (Jurisdiction) *</label>
            <select name="formation_country" id="formation_country" required onchange="onFormationCountryChange()">
              <optgroup label="Recommended Jurisdictions">
                <?php $selFc = $old['formation_country'] ?? 'United States'; foreach ($recommendedJurisdictions as $c): ?>
                  <option value="<?= e($c) ?>" <?= $selFc === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
              </optgroup>
              <optgroup label="All Countries">
                <?php foreach ($countryList as $c): ?>
                  <option value="<?= e($c) ?>" <?= $selFc === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
              </optgroup>
            </select>
          </div>
          <div class="field"><label>Entity Type *</label>
            <select name="entity_type" required>
              <?php foreach ($entityLabels as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $presetType === $key ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" id="formationRegionWrap">
          <label>Formation State / Province / Region</label>
          <select name="formation_region" id="formation_region_select" style="display:none"></select>
          <input type="text" name="formation_region_text" id="formation_region_text" placeholder="State / Province / Region (if applicable)">
          <div class="hint">Wyoming, Delaware, and Nevada are the most common U.S. formation states — but you can register in any country/state above.</div>
        </div>

        <button type="submit" class="btn btn-gold btn-block" style="margin-top:10px">Submit Application</button>
        <p style="text-align:center;font-size:13px;margin-top:14px">By submitting this form, you agree to our Terms of Service and Privacy Policy.</p>
      </div>
    </form>
  </div>
</section>

<script>
const countryStates = <?= json_encode($countriesData) ?>;
const selectedState = <?= json_encode($old['state_region'] ?? $user['state_region'] ?? '') ?>;

function onCountryChange() {
  const country = document.getElementById('country').value;
  const select = document.getElementById('state_region_select');
  const text = document.getElementById('state_region_text');
  const ssnField = document.getElementById('ssnField');

  if (countryStates[country]) {
    select.innerHTML = '<option value="">Select state/province&hellip;</option>' +
      countryStates[country].map(s => `<option value="${s}" ${s === selectedState ? 'selected' : ''}>${s}</option>`).join('');
    select.style.display = '';
    select.name = 'state_region';
    text.style.display = 'none';
    text.name = 'state_region_text';
  } else {
    select.style.display = 'none';
    select.name = 'state_region_select_unused';
    text.style.display = '';
    text.name = 'state_region';
  }

  ssnField.style.display = (country === 'United States') ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', onCountryChange);

const selectedFormationRegion = <?= json_encode($old['formation_region'] ?? 'Wyoming') ?>;

function onFormationCountryChange() {
  const country = document.getElementById('formation_country').value;
  const select = document.getElementById('formation_region_select');
  const text = document.getElementById('formation_region_text');

  if (countryStates[country]) {
    select.innerHTML = '<option value="">Select state/province&hellip;</option>' +
      countryStates[country].map(s => `<option value="${s}" ${s === selectedFormationRegion ? 'selected' : ''}>${s}</option>`).join('');
    select.style.display = '';
    select.name = 'formation_region';
    text.style.display = 'none';
    text.name = 'formation_region_text';
  } else {
    select.style.display = 'none';
    select.name = 'formation_region_select_unused';
    text.style.display = '';
    text.name = 'formation_region';
    text.value = selectedFormationRegion && country !== 'United States' ? '' : text.value;
  }
}
document.addEventListener('DOMContentLoaded', onFormationCountryChange);
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
