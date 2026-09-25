<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/mailer.php';

ensure_asset_balances_table();
ensure_investment_tables();

// Admin safety valve: cancel an active investment and refund just the
// principal (no interest) back to the user's balance for that asset.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = db()->prepare("SELECT i.*, u.full_name, u.email FROM investments i JOIN users u ON u.id = i.user_id WHERE i.id = ? AND i.status = 'active'");
    $stmt->execute([$id]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($inv) {
        db()->beginTransaction();
        try {
            $asset     = $inv['asset_symbol'];
            $principal = (float) $inv['principal_usd'];
            $lockedCrypto = (float) $inv['locked_crypto_amount'];

            db()->prepare('INSERT INTO asset_balances (user_id, asset_symbol, asset_name, crypto_amount, demo_usd_amount)
                    VALUES (?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                      crypto_amount = crypto_amount + VALUES(crypto_amount),
                      demo_usd_amount = demo_usd_amount + VALUES(demo_usd_amount),
                      updated_at = NOW()')
                ->execute([$inv['user_id'], $asset, asset_label($asset), $lockedCrypto, $principal]);

            db()->prepare("UPDATE investments SET status='cancelled' WHERE id=?")->execute([$inv['id']]);
            db()->commit();

            log_roi_transaction((int) $inv['user_id'], 'roi_payout', $asset, $principal, $inv['plan_name'] . ' — cancelled by admin, principal refunded');
            send_email($inv['email'], $inv['full_name'], 'Crypto ROI — Investment Cancelled',
                '<p>Hi ' . e($inv['full_name']) . ',</p><p>Your <strong>' . e($inv['plan_name']) . '</strong> investment was cancelled by an admin. Your principal of <strong>' . fmt_money($principal) . '</strong> has been refunded to your ' . e($asset) . ' balance.</p>');

            flash_set('Investment cancelled and principal refunded to ' . $inv['full_name'] . '.');
        } catch (PDOException $ex) {
            db()->rollBack();
            flash_set('Something went wrong cancelling this investment.', 'error');
        }
    } else {
        flash_set('Investment not found or already settled.', 'error');
    }
    header('Location: investments.php');
    exit;
}

$investments = db()->query("SELECT i.*, u.full_name, u.email FROM investments i JOIN users u ON u.id = i.user_id ORDER BY i.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$kpiActive   = 0; $kpiLockedUsd = 0.0; $kpiClaimedUsd = 0.0; $kpiMatured = 0;
foreach ($investments as $inv) {
    if ($inv['status'] === 'active') {
        $kpiActive++;
        $kpiLockedUsd += (float) $inv['principal_usd'];
        if (strtotime($inv['matures_at']) <= time()) $kpiMatured++;
    } elseif ($inv['status'] === 'claimed') {
        $kpiClaimedUsd += (float) $inv['payout_usd'];
    }
}

$statusLabels = ['active' => 'Active', 'claimed' => 'Claimed', 'cancelled' => 'Cancelled'];

$pageTitle = 'Crypto ROI Investments';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-grid adm-kpis">
  <div class="adm-kpi">
    <div class="top"><span class="label">Active Locks</span><span class="icon">&#128274;</span></div>
    <div class="num"><?= $kpiActive ?></div>
  </div>
  <div class="adm-kpi">
    <div class="top"><span class="label">Awaiting Claim</span><span class="icon">&#8987;</span></div>
    <div class="num"><?= $kpiMatured ?></div>
  </div>
  <div class="adm-kpi">
    <div class="top"><span class="label">Currently Locked</span><span class="icon">&#128181;</span></div>
    <div class="num" style="font-size:22px"><?= fmt_money($kpiLockedUsd) ?></div>
  </div>
  <div class="adm-kpi">
    <div class="top"><span class="label">Paid Out</span><span class="icon">&#9989;</span></div>
    <div class="num" style="font-size:22px"><?= fmt_money($kpiClaimedUsd) ?></div>
  </div>
</div>

<div class="adm-toolbar">
  <select class="adm-select" data-adm-table-filter="#invTable">
    <option value="all">All Investments</option>
    <?php foreach ($statusLabels as $val => $label): ?>
      <option value="<?= e($val) ?>"><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <div class="adm-search"><input type="text" placeholder="Search by user name, email..." data-adm-table-search="#invTable"></div>
</div>

<div class="adm-panel">
  <div class="adm-table-wrap">
    <table class="adm-table" id="invTable">
      <thead><tr><th>User</th><th>Plan</th><th>Asset</th><th>Principal</th><th>Payout</th><th>Matures</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($investments as $inv):
          $matured = $inv['status'] === 'active' && strtotime($inv['matures_at']) <= time();
        ?>
        <tr data-status="<?= e($inv['status']) ?>" data-search="<?= e(strtolower($inv['full_name'] . ' ' . $inv['email'])) ?>">
          <td><strong><?= e($inv['full_name']) ?></strong><div class="adm-cell-sub"><?= e($inv['email']) ?></div></td>
          <td><?= e($inv['plan_name']) ?><div class="adm-cell-sub"><?= (int) $inv['duration_days'] ?> days &middot; <?= e(rtrim(rtrim(number_format((float) $inv['interest_rate_percent'], 2), '0'), '.')) ?>%</div></td>
          <td><?= e($inv['asset_symbol']) ?></td>
          <td><?= fmt_money((float) $inv['principal_usd']) ?></td>
          <td><strong><?= fmt_money((float) $inv['payout_usd']) ?></strong></td>
          <td><?= e(date('n/j/Y', strtotime($inv['matures_at']))) ?><?php if ($matured): ?><div class="adm-cell-sub" style="color:var(--adm-amber)">Matured &mdash; awaiting claim</div><?php endif; ?></td>
          <td><?= badge_for_status($inv['status'], $statusLabels) ?></td>
          <td style="white-space:nowrap">
            <?php if ($inv['status'] === 'active'): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                <button type="submit" class="adm-btn adm-btn-danger-outline adm-btn-sm" onclick="return confirm('Cancel this investment and refund only the principal (<?= e(fmt_money((float) $inv['principal_usd'])) ?>) back to the user? This cannot be undone.')">Cancel &amp; Refund</button>
              </form>
            <?php else: ?>
              <span style="color:var(--adm-muted-2);font-size:13px">No actions</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr class="adm-js-empty-row"<?= $investments ? ' style="display:none"' : '' ?>><td colspan="8" class="adm-empty">No investments yet.</td></tr>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
