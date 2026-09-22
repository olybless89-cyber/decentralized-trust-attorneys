<?php
/**
 * Admin — Send a notification / message email to any user.
 */
require_once __DIR__ . '/../auth.php';
$admin = require_admin();
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $userId  = (int) ($_POST['user_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['message'] ?? '');

    $stmt = db()->prepare('SELECT id, full_name, email FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target || $subject === '' || $body === '') {
        flash_set('Please select a user and fill in both subject and message.', 'error');
    } else {
        $html = '<p>Hi ' . e($target['full_name']) . ',</p>'
              . nl2br(e($body))
              . '<p style="margin-top:24px;font-size:13px;color:#64748b">This message was sent by the ' . e(SITE_NAME) . ' team.</p>';
        $sent = send_email($target['email'], $target['full_name'], $subject, $html);
        if ($sent) {
            flash_set('Message sent to ' . $target['full_name'] . ' (' . $target['email'] . ').');
        } else {
            flash_set('Failed to send email — check SMTP settings.', 'error');
        }
    }
    header('Location: notify-user.php');
    exit;
}

$users = db()->query("SELECT id, full_name, email FROM users ORDER BY full_name ASC")->fetchAll();
$selectedId = (int) ($_GET['user'] ?? 0);

$pageTitle = 'Notify User';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-panel" style="max-width:680px">
  <div class="adm-panel-head">
    <div>
      <h3>&#128140; Send Message to User</h3>
      <div class="sub">Compose and send an email notification directly to any registered user.</div>
    </div>
  </div>

  <form method="post" id="notifyForm">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="user_id" id="notifyUserId" value="<?= $selectedId ?: '' ?>">

    <div class="adm-field" style="position:relative">
      <label>Recipient <span class="req">*</span></label>
      <input type="text" id="notifySearch" placeholder="Search and select a user..." autocomplete="off" required>
      <div id="notifyDropdown" style="display:none;position:absolute;z-index:30;left:0;right:0;top:100%;margin-top:4px;background:#fff;border:1px solid var(--adm-line);border-radius:10px;box-shadow:var(--adm-shadow);max-height:240px;overflow-y:auto"></div>
    </div>

    <div class="adm-field">
      <label>Subject <span class="req">*</span></label>
      <input type="text" name="subject" placeholder="e.g. Important Account Notice" required value="<?= e($_POST['subject'] ?? '') ?>">
    </div>

    <div class="adm-field">
      <label>Message <span class="req">*</span></label>
      <textarea name="message" rows="7" placeholder="Type your message here..." required style="resize:vertical"><?= e($_POST['message'] ?? '') ?></textarea>
      <div class="hint">Plain text only — line breaks are preserved in the email.</div>
    </div>

    <button type="submit" class="adm-btn adm-btn-primary" id="notifySubmit">Send Message</button>
  </form>
</div>

<script>
(function () {
  var users = <?= json_encode(array_map(fn($u) => ['id' => (int)$u['id'], 'name' => $u['full_name'], 'email' => $u['email']], $users)) ?>;
  var selectedId = <?= (int) $selectedId ?>;
  var search = document.getElementById('notifySearch');
  var dropdown = document.getElementById('notifyDropdown');
  var hiddenId = document.getElementById('notifyUserId');

  function filterUsers(q) {
    q = (q || '').toLowerCase();
    return users.filter(function (u) { return !q || (u.name + ' ' + u.email).toLowerCase().indexOf(q) !== -1; });
  }

  function renderDropdown(list) {
    if (!list.length) { dropdown.style.display = 'none'; return; }
    dropdown.innerHTML = '';
    list.slice(0, 30).forEach(function (u) {
      var row = document.createElement('div');
      row.textContent = u.name + ' (' + u.email + ')';
      row.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:14px;border-bottom:1px solid #f1f1f3';
      row.addEventListener('mouseenter', function () { row.style.background = '#fafafb'; });
      row.addEventListener('mouseleave', function () { row.style.background = ''; });
      row.addEventListener('click', function () {
        hiddenId.value = u.id;
        search.value = u.name + ' (' + u.email + ')';
        dropdown.style.display = 'none';
      });
      dropdown.appendChild(row);
    });
    dropdown.style.display = 'block';
  }

  search.addEventListener('focus', function () { renderDropdown(filterUsers(search.value)); });
  search.addEventListener('input', function () { hiddenId.value = ''; renderDropdown(filterUsers(search.value)); });
  document.addEventListener('click', function (e) {
    if (!dropdown.contains(e.target) && e.target !== search) dropdown.style.display = 'none';
  });

  if (selectedId) {
    var found = users.find(function (u) { return u.id === selectedId; });
    if (found) { hiddenId.value = found.id; search.value = found.name + ' (' + found.email + ')'; }
  }

  document.getElementById('notifyForm').addEventListener('submit', function (e) {
    if (!hiddenId.value) { e.preventDefault(); admToast('Please select a recipient first.', 'error'); }
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
