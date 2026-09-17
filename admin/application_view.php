<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../includes/mailer.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT a.*, u.full_name AS user_name, u.email AS user_email, u.phone AS user_phone, u.country AS user_country, u.state_region AS user_state_region, u.ssn_last4 AS user_ssn_last4, u.id_document_path AS user_id_document_path FROM applications a JOIN users u ON u.id = a.user_id WHERE a.id = ?');
$stmt->execute([$id]);
$app = $stmt->fetch();
if (!$app) { flash_set('Application not found.', 'error'); header('Location: applications.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $status = $_POST['status'] ?? $app['status'];
    $notes = trim($_POST['admin_notes'] ?? '');
    $validStatuses = ['pending', 'in_review', 'approved', 'rejected'];
    if (in_array($status, $validStatuses, true)) {
        $stmt = db()->prepare('UPDATE applications SET status = ?, admin_notes = ? WHERE id = ?');
        $stmt->execute([$status, $notes, $id]);
        if ($status !== $app['status']) {
            $statusLabels = ['pending' => 'Pending Review', 'in_review' => 'In Review', 'approved' => 'Approved', 'rejected' => 'Rejected'];
            send_email($app['user_email'], $app['user_name'], 'Application Update — ' . $app['business_name'],
                '<p>Hi ' . e($app['user_name']) . ',</p><p>The status of your application for <strong>' . e($app['business_name']) . '</strong> has changed to <strong>' . e($statusLabels[$status] ?? $status) . '</strong>.</p>' . ($notes ? '<p>Note from our team: ' . nl2br(e($notes)) . '</p>' : '') . '<p>Log in to your dashboard for full details.</p>');
        }
        flash_set('Application updated.');
        header('Location: application_view.php?id=' . $id);
        exit;
    }
}

$entityLabels = ['LLC' => 'Limited Liability Company', 'CCORP' => 'Corporation (C-Corp)', 'CLOSE_LLC' => 'Close LLC', 'CLOSE_CORP' => 'Close Corporation'];

$pageTitle = 'Application Detail';
require __DIR__ . '/includes/header.php';
?>
<div style="margin-bottom:16px"><a href="applications.php" style="color:var(--muted);font-size:14px">&larr; Back to Applications</a></div>

<div class="panel" style="margin-bottom:24px">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
    <div>
      <h3 style="margin-bottom:4px"><?= e($app['business_name']) ?></h3>
      <p style="margin:0"><?= e($entityLabels[$app['entity_type']] ?? $app['entity_type']) ?> &middot; <?= e($app['state']) ?></p>
    </div>
    <?= status_badge($app['status']) ?>
  </div>
  <div class="detail-grid">
    <div><div class="k">Applicant</div><div class="v"><?= e($app['user_name']) ?></div></div>
    <div><div class="k">Account Email</div><div class="v"><?= e($app['user_email']) ?></div></div>
    <div><div class="k">Owner Name (on filing)</div><div class="v"><?= e($app['owner_name']) ?></div></div>
    <div><div class="k">Owner Email (on filing)</div><div class="v"><?= e($app['owner_email']) ?></div></div>
    <div><div class="k">Owner Phone</div><div class="v"><?= e($app['owner_phone'] ?: '—') ?></div></div>
    <div><div class="k">Mailing Address</div><div class="v"><?= e($app['address'] ?: '—') ?></div></div>
    <div><div class="k">Submitted</div><div class="v"><?= e(date('M j, Y g:ia', strtotime($app['created_at']))) ?></div></div>
    <div><div class="k">Last Updated</div><div class="v"><?= e(date('M j, Y g:ia', strtotime($app['updated_at']))) ?></div></div>
  </div>
</div>

<div class="panel" style="margin-bottom:24px">
  <h3 style="margin-bottom:16px">Identity Verification</h3>
  <div class="detail-grid">
    <div><div class="k">Country / Region</div><div class="v"><?= e(trim(($app['user_country'] ?? '—') . ($app['user_state_region'] ? ', ' . $app['user_state_region'] : ''))) ?></div></div>
    <div><div class="k">SSN (last 4)</div><div class="v"><?= $app['user_ssn_last4'] ? '••• ' . e($app['user_ssn_last4']) : '—' ?></div></div>
    <div><div class="k">ID Document</div><div class="v">
      <?php if ($app['user_id_document_path']): ?>
        <a href="../<?= e($app['user_id_document_path']) ?>" target="_blank" class="btn btn-outline btn-sm">View Uploaded ID</a>
      <?php else: ?> Not uploaded <?php endif; ?>
    </div></div>
  </div>
</div>

<div class="panel">
  <h3 style="margin-bottom:16px">Update Status</h3>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field">
      <label>Status</label>
      <select name="status">
        <?php foreach (['pending' => 'Pending Review', 'in_review' => 'In Review', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $label): ?>
          <option value="<?= $val ?>" <?= $app['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Internal Notes</label>
      <textarea name="admin_notes" rows="4" placeholder="Notes visible to staff only"><?= e($app['admin_notes']) ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Save Update</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
