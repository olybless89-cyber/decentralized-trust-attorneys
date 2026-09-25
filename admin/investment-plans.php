<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../includes/wallet.php';

ensure_investment_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int) ($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration    = (int) ($_POST['duration_days'] ?? 0);
        $rate        = (float) ($_POST['interest_rate_percent'] ?? 0);
        $min         = (float) ($_POST['min_amount_usd'] ?? 0);
        $maxRaw      = trim($_POST['max_amount_usd'] ?? '');
        $max         = $maxRaw === '' ? null : (float) $maxRaw;
        $status      = ($_POST['status'] ?? 'inactive') === 'active' ? 'active' : 'inactive';
        $isPopular   = !empty($_POST['is_popular']) ? 1 : 0;
        $sortOrder   = (int) ($_POST['sort_order'] ?? 0);

        if ($name === '') {
            flash_set('Plan name is required.', 'error');
        } elseif ($duration <= 0) {
            flash_set('Duration must be at least 1 day.', 'error');
        } elseif ($rate <= 0) {
            flash_set('Interest rate must be greater than 0.', 'error');
        } elseif ($min <= 0) {
            flash_set('Minimum amount must be greater than 0.', 'error');
        } elseif ($max !== null && $max <= $min) {
            flash_set('Maximum amount must be greater than the minimum.', 'error');
        } else {
            // Only one plan is "Most Popular" at a time.
            if ($isPopular) {
                db()->exec('UPDATE investment_plans SET is_popular = 0');
            }
            if ($id > 0) {
                db()->prepare('UPDATE investment_plans SET name=?, description=?, duration_days=?, interest_rate_percent=?, min_amount_usd=?, max_amount_usd=?, status=?, is_popular=?, sort_order=?, updated_at=NOW() WHERE id=?')
                    ->execute([$name, $description ?: null, $duration, $rate, $min, $max, $status, $isPopular, $sortOrder, $id]);
                flash_set('Plan "' . $name . '" updated.');
            } else {
                db()->prepare('INSERT INTO investment_plans (name, description, duration_days, interest_rate_percent, min_amount_usd, max_amount_usd, status, is_popular, sort_order) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$name, $description ?: null, $duration, $rate, $min, $max, $status, $isPopular, $sortOrder]);
                flash_set('Plan "' . $name . '" created.');
            }
        }
    } elseif ($action === 'set_popular') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT name FROM investment_plans WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            db()->exec('UPDATE investment_plans SET is_popular = 0');
            db()->prepare('UPDATE investment_plans SET is_popular = 1, updated_at = NOW() WHERE id = ?')->execute([$id]);
            flash_set($row['name'] . ' is now marked Most Popular.');
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT name, status FROM investment_plans WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
            db()->prepare('UPDATE investment_plans SET status=?, updated_at=NOW() WHERE id=?')->execute([$newStatus, $id]);
            flash_set($row['name'] . ' is now ' . ($newStatus === 'active' ? 'live for users.' : 'hidden from users.'));
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT name FROM investment_plans WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            db()->prepare('DELETE FROM investment_plans WHERE id = ?')->execute([$id]);
            flash_set('Plan "' . $row['name'] . '" deleted. (Existing investments already made under it are unaffected.)');
        }
    }
    header('Location: investment-plans.php');
    exit;
}

$plans = db()->query('SELECT * FROM investment_plans ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);

$editId = (int) ($_GET['edit'] ?? 0);
$editPlan = null;
if ($editId) {
    foreach ($plans as $p) {
        if ((int) $p['id'] === $editId) { $editPlan = $p; break; }
    }
}

$activeCount = count(array_filter($plans, fn($p) => $p['status'] === 'active'));

$pageTitle = 'Crypto ROI Plans';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-grid adm-2col">
  <div class="adm-panel" style="margin-bottom:0">
    <div class="adm-panel-head">
      <div>
        <h3><?= $editPlan ? '&#9999;&#65039; Edit Plan' : '&#10133; New Plan' ?></h3>
        <div class="sub"><?= $editPlan ? 'Editing "' . e($editPlan['name']) . '".' : 'Add another Crypto ROI plan alongside the 3 recommended ones.' ?></div>
      </div>
      <?php if ($editPlan): ?><a href="investment-plans.php" class="viewall">Cancel edit</a><?php endif; ?>
    </div>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editPlan ? (int) $editPlan['id'] : '' ?>">

      <div class="adm-field"><label>Plan Name <span class="req">*</span></label>
        <input type="text" name="name" maxlength="100" required value="<?= e($editPlan['name'] ?? '') ?>" placeholder="e.g. Growth Lock">
      </div>

      <div class="adm-field"><label>Description</label>
        <textarea name="description" maxlength="255" placeholder="Shown on the plan card, e.g. a one-line pitch"><?= e($editPlan['description'] ?? '') ?></textarea>
      </div>

      <div class="adm-field"><label>Duration (Days) <span class="req">*</span></label>
        <input type="number" name="duration_days" min="1" required value="<?= e($editPlan['duration_days'] ?? '365') ?>">
        <div class="hint">How long funds stay locked before a user can claim (e.g. 365 for 1 year, 90 for 90 days) &mdash; shown to users as "<?= isset($editPlan['duration_days']) ? e(roi_duration_label((int) $editPlan['duration_days'])) : '1 Year' ?>".</div>
      </div>

      <div class="adm-field"><label>Interest Rate (%) <span class="req">*</span></label>
        <input type="number" name="interest_rate_percent" step="0.01" min="0.01" required value="<?= e($editPlan['interest_rate_percent'] ?? '') ?>">
        <div class="hint">Total return paid at maturity, not annualized (e.g. 20 = 20% on top of principal after the full term).</div>
      </div>

      <div class="form-row-2">
        <div class="adm-field"><label>Minimum Amount (USD) <span class="req">*</span></label>
          <input type="number" name="min_amount_usd" step="0.01" min="0.01" required value="<?= e($editPlan['min_amount_usd'] ?? '') ?>">
        </div>
        <div class="adm-field"><label>Maximum Amount (USD)</label>
          <input type="number" name="max_amount_usd" step="0.01" min="0.01" value="<?= isset($editPlan['max_amount_usd']) && $editPlan['max_amount_usd'] !== null ? e($editPlan['max_amount_usd']) : '' ?>" placeholder="Leave blank for no limit">
        </div>
      </div>

      <div class="form-row-2">
        <div class="adm-field"><label>Sort Order</label>
          <input type="number" name="sort_order" value="<?= e($editPlan['sort_order'] ?? '0') ?>">
          <div class="hint">Lower numbers show first on the plans page.</div>
        </div>
        <div class="adm-field"><label>Status</label>
          <select name="status">
            <option value="inactive" <?= (($editPlan['status'] ?? 'inactive') === 'inactive') ? 'selected' : '' ?>>Inactive (hidden from users)</option>
            <option value="active" <?= (($editPlan['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active (live now)</option>
          </select>
        </div>
      </div>

      <div class="adm-field">
        <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer">
          <input type="checkbox" name="is_popular" value="1" style="width:16px;height:16px" <?= !empty($editPlan['is_popular']) ? 'checked' : '' ?>>
          Mark as "Most Popular"
        </label>
        <div class="hint">Shows a gold ribbon on this plan's card. Only one plan can be marked at a time &mdash; checking this unchecks it on any other plan.</div>
      </div>

      <button type="submit" class="adm-btn adm-btn-primary adm-btn-block"><?= $editPlan ? 'Save Changes' : 'Create Plan' ?></button>
    </form>
  </div>

  <div>
    <div class="adm-card-dark" style="margin-bottom:16px">
      <h3 style="color:#fff;font-size:15px;margin-bottom:16px">&#128640; Launch Status</h3>
      <div style="margin-bottom:14px"><div class="k">Live Plans</div><div class="v"><?= $activeCount ?> of <?= count($plans) ?> active</div></div>
      <div><div class="k">User Page</div><div class="v"><a href="../invest.php" target="_blank" style="color:#f0b90b">/invest.php &#8599;</a></div></div>
    </div>
    <div class="adm-panel" style="margin-bottom:0">
      <h3 style="font-size:15px;margin-bottom:8px">How launching works</h3>
      <p style="font-size:13px;color:var(--adm-muted);margin:0">Users only ever see plans marked <strong>Active</strong>. Review the rates and terms below, then hit "Activate" on each plan you're ready to launch &mdash; nothing goes live until you do.</p>
    </div>
  </div>
</div>

<div class="adm-panel">
  <div class="adm-panel-head"><div><h3>&#128200; All Plans</h3><div class="sub">The 3 recommended plans ship inactive &mdash; activate when ready to launch.</div></div></div>
  <?php if (!$plans): ?>
    <div class="adm-empty">No plans yet.</div>
  <?php else: ?>
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead><tr><th>Plan</th><th>Term</th><th>Rate</th><th>Min</th><th>Max</th><th>Status</th><th>Popular</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($plans as $p): ?>
            <tr>
              <td><strong><?= e($p['name']) ?></strong><?php if ($p['description']): ?><div class="adm-cell-sub"><?= e($p['description']) ?></div><?php endif; ?></td>
              <td><?= e(roi_duration_label((int) $p['duration_days'])) ?></td>
              <td><?= e(rtrim(rtrim(number_format((float) $p['interest_rate_percent'], 2), '0'), '.')) ?>%</td>
              <td><?= fmt_money((float) $p['min_amount_usd']) ?></td>
              <td><?= $p['max_amount_usd'] !== null ? fmt_money((float) $p['max_amount_usd']) : 'No limit' ?></td>
              <td><?= badge_for_status($p['status'], ['active' => 'Active', 'inactive' => 'Inactive']) ?></td>
              <td>
                <?php if ($p['is_popular']): ?>
                  <span class="adm-badge adm-badge-amber">&#9733; Popular</span>
                <?php else: ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="set_popular">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="adm-btn adm-btn-outline adm-btn-sm">Mark Popular</button>
                  </form>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap">
                <a href="investment-plans.php?edit=<?= (int) $p['id'] ?>" class="adm-btn adm-btn-outline adm-btn-sm">Edit</a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button type="submit" class="adm-btn <?= $p['status'] === 'active' ? 'adm-btn-outline' : 'adm-btn-primary' ?> adm-btn-sm"><?= $p['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button type="submit" class="adm-btn adm-btn-danger-outline adm-btn-sm" onclick="return confirm('Delete this plan? Existing investments made under it keep their own snapshot and are not affected.')">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
