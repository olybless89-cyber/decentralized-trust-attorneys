<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/wallet.php';
require_once __DIR__ . '/includes/mailer.php';
$user = require_login();

ensure_asset_balances_table();
ensure_investment_tables();

$errors = [];

// ── Handle actions ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired, please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'invest') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $asset  = strtoupper(trim($_POST['asset'] ?? ''));
            $amount = (float) ($_POST['amount'] ?? 0);

            $planStmt = db()->prepare("SELECT * FROM investment_plans WHERE id = ? AND status = 'active'");
            $planStmt->execute([$planId]);
            $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                $errors[] = 'Select a valid, currently available plan.';
            } elseif ($asset === '') {
                $errors[] = 'Choose which asset to fund this investment from.';
            } elseif ($amount < (float) $plan['min_amount_usd']) {
                $errors[] = 'Minimum for ' . $plan['name'] . ' is ' . fmt_money((float) $plan['min_amount_usd']) . '.';
            } elseif ($plan['max_amount_usd'] !== null && $amount > (float) $plan['max_amount_usd']) {
                $errors[] = 'Maximum for ' . $plan['name'] . ' is ' . fmt_money((float) $plan['max_amount_usd']) . '.';
            } else {
                db()->beginTransaction();
                try {
                    $abStmt = db()->prepare('SELECT id, demo_usd_amount, crypto_amount FROM asset_balances WHERE user_id = ? AND asset_symbol = ? FOR UPDATE');
                    $abStmt->execute([$user['id'], $asset]);
                    $ab = $abStmt->fetch(PDO::FETCH_ASSOC);
                    $availUsd = $ab ? (float) $ab['demo_usd_amount'] : 0;

                    if ($amount > $availUsd) {
                        db()->rollBack();
                        $errors[] = 'Amount exceeds your ' . e($asset) . ' balance (' . fmt_money($availUsd) . ').';
                    } else {
                        // Deduct proportionally, same pattern as send.php / withdrawals.
                        $newUsd       = $availUsd - $amount;
                        $newCrypto    = $availUsd > 0 ? (float) $ab['crypto_amount'] * ($newUsd / $availUsd) : 0;
                        $lockedCrypto = $availUsd > 0 ? (float) $ab['crypto_amount'] - $newCrypto : 0;

                        db()->prepare('UPDATE asset_balances SET demo_usd_amount=?, crypto_amount=?, updated_at=NOW() WHERE id=?')
                            ->execute([$newUsd, $newCrypto, $ab['id']]);

                        $rate      = (float) $plan['interest_rate_percent'];
                        $duration  = (int) $plan['duration_days'];
                        $interest  = round($amount * $rate / 100, 2);
                        $payout    = round($amount + $interest, 2);
                        $startsAt  = date('Y-m-d H:i:s');
                        $maturesAt = date('Y-m-d H:i:s', strtotime('+' . $duration . ' days'));

                        db()->prepare('INSERT INTO investments
                                (user_id, plan_id, plan_name, asset_symbol, principal_usd, locked_crypto_amount, interest_rate_percent, duration_days, interest_usd, payout_usd, status, starts_at, matures_at)
                                VALUES (?,?,?,?,?,?,?,?,?,?,\'active\',?,?)')
                            ->execute([$user['id'], $plan['id'], $plan['name'], $asset, $amount, $lockedCrypto, $rate, $duration, $interest, $payout, $startsAt, $maturesAt]);

                        db()->commit();

                        log_roi_transaction($user['id'], 'roi_lock', $asset, $amount, $plan['name'] . ' — locked for ' . $duration . ' days');

                        send_email($user['email'], $user['full_name'], 'Crypto ROI — Investment Started',
                            '<p>Hi ' . e($user['full_name']) . ',</p><p>You locked <strong>' . fmt_money($amount) . '</strong> worth of ' . e($asset) . ' into the <strong>' . e($plan['name']) . '</strong> plan.</p><p>Matures on ' . e(date('M j, Y', strtotime($maturesAt))) . ' &mdash; projected payout <strong>' . fmt_money($payout) . '</strong> (principal + ' . fmt_money($interest) . ' interest).</p>');

                        flash_set('Locked ' . fmt_money($amount) . ' into ' . $plan['name'] . '. Matures ' . date('M j, Y', strtotime($maturesAt)) . '.');
                        header('Location: invest.php');
                        exit;
                    }
                } catch (PDOException $ex) {
                    db()->rollBack();
                    $errors[] = 'Something went wrong starting this investment. Please try again.';
                }
            }
        } elseif ($action === 'claim') {
            $invId = (int) ($_POST['investment_id'] ?? 0);
            $invStmt = db()->prepare("SELECT * FROM investments WHERE id = ? AND user_id = ? AND status = 'active'");
            $invStmt->execute([$invId, $user['id']]);
            $inv = $invStmt->fetch(PDO::FETCH_ASSOC);

            if (!$inv) {
                $errors[] = 'Investment not found.';
            } elseif (strtotime($inv['matures_at']) > time()) {
                $errors[] = 'This investment has not matured yet.';
            } else {
                db()->beginTransaction();
                try {
                    $asset        = $inv['asset_symbol'];
                    $payout       = (float) $inv['payout_usd'];
                    $principal    = (float) $inv['principal_usd'];
                    $lockedCrypto = (float) $inv['locked_crypto_amount'];
                    $unitPrice    = $lockedCrypto > 0 ? $principal / $lockedCrypto : 0;
                    $creditCrypto = $unitPrice > 0 ? $payout / $unitPrice : 0;

                    db()->prepare('INSERT INTO asset_balances (user_id, asset_symbol, asset_name, crypto_amount, demo_usd_amount)
                            VALUES (?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE
                              crypto_amount = crypto_amount + VALUES(crypto_amount),
                              demo_usd_amount = demo_usd_amount + VALUES(demo_usd_amount),
                              updated_at = NOW()')
                        ->execute([$user['id'], $asset, asset_label($asset), $creditCrypto, $payout]);

                    db()->prepare("UPDATE investments SET status='claimed', claimed_at=NOW() WHERE id=?")
                        ->execute([$inv['id']]);

                    db()->commit();

                    log_roi_transaction($user['id'], 'roi_payout', $asset, $payout, $inv['plan_name'] . ' — matured payout');

                    send_email($user['email'], $user['full_name'], 'Crypto ROI — Payout Claimed',
                        '<p>Hi ' . e($user['full_name']) . ',</p><p>Your <strong>' . e($inv['plan_name']) . '</strong> investment matured and <strong>' . fmt_money($payout) . '</strong> (principal + interest) has been credited to your ' . e($asset) . ' balance.</p>');

                    flash_set('Claimed ' . fmt_money($payout) . ' from ' . $inv['plan_name'] . '.');
                } catch (PDOException $ex) {
                    db()->rollBack();
                    $errors[] = 'Something went wrong claiming this payout. Please try again.';
                }
            }
            header('Location: invest.php');
            exit;
        }
    }
}

// ── Data for render ──────────────────────────────────────────────────────
$plans = db()->query("SELECT * FROM investment_plans WHERE status = 'active' ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$balStmt = db()->prepare('SELECT asset_symbol, crypto_amount, demo_usd_amount FROM asset_balances WHERE user_id = ? AND demo_usd_amount > 0 ORDER BY demo_usd_amount DESC');
$balStmt->execute([$user['id']]);
$fundingAssets = $balStmt->fetchAll(PDO::FETCH_ASSOC);

$myInvStmt = db()->prepare('SELECT * FROM investments WHERE user_id = ? ORDER BY created_at DESC');
$myInvStmt->execute([$user['id']]);
$myInvestments = $myInvStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Crypto ROI';
require __DIR__ . '/includes/dash_header.php';
?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if (!$plans): ?>
  <div class="roi-empty">
    <div class="icon">&#128200;</div>
    <h3 style="margin-bottom:6px">Crypto ROI is coming soon</h3>
    <p style="margin:0">Fixed-term crypto plans with interest at maturity &mdash; we're putting the finishing touches on the available plans. Check back soon.</p>
  </div>
<?php else: ?>

  <div class="roi-plan-grid">
    <?php foreach ($plans as $p): $isPopular = (bool) $p['is_popular']; ?>
      <div class="roi-plan-card<?= $isPopular ? ' roi-popular' : '' ?>" data-plan-id="<?= (int) $p['id'] ?>">
        <?php if ($isPopular): ?><span class="roi-popular-tag">Most Popular</span><?php endif; ?>
        <h3 class="roi-plan-name"><?= e($p['name']) ?></h3>
        <p class="roi-plan-desc"><?= e($p['description'] ?? '') ?></p>
        <div class="roi-plan-rate"><?= e(rtrim(rtrim(number_format((float) $p['interest_rate_percent'], 2), '0'), '.')) ?>%<small> at maturity</small></div>
        <ul class="roi-plan-meta">
          <li><span class="k">Term</span><span class="v"><?= e(roi_duration_label((int) $p['duration_days'])) ?></span></li>
          <li><span class="k">Minimum</span><span class="v"><?= fmt_money((float) $p['min_amount_usd']) ?></span></li>
          <li><span class="k">Maximum</span><span class="v"><?= $p['max_amount_usd'] !== null ? fmt_money((float) $p['max_amount_usd']) : 'No limit' ?></span></li>
        </ul>
        <button type="button" class="btn btn-gold btn-block roi-pick-btn"
          data-plan-id="<?= (int) $p['id'] ?>"
          data-plan-name="<?= e($p['name']) ?>"
          data-rate="<?= e($p['interest_rate_percent']) ?>"
          data-duration="<?= (int) $p['duration_days'] ?>"
          data-min="<?= e($p['min_amount_usd']) ?>"
          data-max="<?= $p['max_amount_usd'] !== null ? e($p['max_amount_usd']) : '' ?>">Invest Now</button>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="panel roi-form-panel" id="roiFormPanel" style="display:none">
    <h3 style="margin-bottom:4px;text-align:center" id="roiFormTitle">Lock Funds</h3>
    <p style="text-align:center;font-size:13.5px;margin-bottom:18px">This is a demo feature &mdash; no real crypto is locked.</p>

    <?php if (!$fundingAssets): ?>
      <div class="alert alert-info">You don't have a funded asset yet. <a href="buy.php" style="font-weight:700">Buy crypto</a> or <a href="receive.php" style="font-weight:700">receive funds</a> first, then come back to invest.</div>
    <?php else: ?>
      <form method="post" id="roiInvestForm">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="invest">
        <input type="hidden" name="plan_id" id="roiPlanId" value="">

        <div class="field"><label>Fund From</label>
          <select name="asset" id="roiAssetSelect" required>
            <option value="">Select an asset&hellip;</option>
            <?php foreach ($fundingAssets as $fa): ?>
              <option value="<?= e($fa['asset_symbol']) ?>" data-avail="<?= e($fa['demo_usd_amount']) ?>">
                <?= e(asset_label($fa['asset_symbol'])) ?> (<?= e($fa['asset_symbol']) ?>) &mdash; <?= fmt_money((float) $fa['demo_usd_amount']) ?> available
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field"><label>Amount (USD)</label>
          <input type="number" step="0.01" name="amount" id="roiAmountInput" placeholder="e.g. 500" required>
          <div class="hint" id="roiAmountHint">Pick a plan above to see its min/max.</div>
        </div>

        <div class="roi-form-summary" id="roiSummary" style="display:none"></div>

        <button type="submit" class="btn btn-gold btn-block" id="roiSubmitBtn" disabled>Confirm Investment</button>
      </form>
    <?php endif; ?>
  </div>

<?php endif; ?>

<div class="panel" style="margin-top:8px">
  <h3 style="margin-bottom:16px">My Investments</h3>
  <?php if (!$myInvestments): ?>
    <div class="empty-state"><p style="margin:0">You haven't started a Crypto ROI investment yet.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Plan</th><th>Asset</th><th>Principal</th><th>Interest</th><th>Payout</th><th>Matures</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($myInvestments as $inv):
            $matured = $inv['status'] === 'active' && strtotime($inv['matures_at']) <= time();
          ?>
            <tr>
              <td><strong><?= e($inv['plan_name']) ?></strong><div class="adm-cell-sub" style="font-size:12px;color:var(--muted)"><?= e(roi_duration_label((int) $inv['duration_days'])) ?> &middot; <?= e(rtrim(rtrim(number_format((float) $inv['interest_rate_percent'], 2), '0'), '.')) ?>%</div></td>
              <td><?= e($inv['asset_symbol']) ?></td>
              <td><?= fmt_money((float) $inv['principal_usd']) ?></td>
              <td style="color:#15803d;font-weight:600">+<?= fmt_money((float) $inv['interest_usd']) ?></td>
              <td><strong><?= fmt_money((float) $inv['payout_usd']) ?></strong></td>
              <td><?= e(date('M j, Y', strtotime($inv['matures_at']))) ?></td>
              <td><?= $matured ? '<span class="badge" style="color:#b45309;background:#fef3c7">Matured</span>' : roi_status_badge($inv['status']) ?></td>
              <td>
                <?php if ($matured): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="claim">
                    <input type="hidden" name="investment_id" value="<?= (int) $inv['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Claim Payout</button>
                  </form>
                <?php elseif ($inv['status'] === 'claimed'): ?>
                  <span style="color:var(--muted);font-size:13px"><?= e(date('M j, Y', strtotime($inv['claimed_at']))) ?></span>
                <?php elseif ($inv['status'] === 'cancelled'): ?>
                  <span style="color:var(--muted);font-size:13px">Principal refunded</span>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:13px">Locked</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var cards      = document.querySelectorAll('.roi-pick-btn');
  var formPanel  = document.getElementById('roiFormPanel');
  var formTitle  = document.getElementById('roiFormTitle');
  var planIdIn   = document.getElementById('roiPlanId');
  var assetSel   = document.getElementById('roiAssetSelect');
  var amountIn   = document.getElementById('roiAmountInput');
  var amountHint = document.getElementById('roiAmountHint');
  var summary    = document.getElementById('roiSummary');
  var submitBtn  = document.getElementById('roiSubmitBtn');
  if (!formPanel) return;

  var current = null;

  function fmtUsd(v) {
    return '$' + v.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }

  // Mirrors roi_duration_label() in includes/wallet.php.
  function fmtDuration(days) {
    if (days > 0 && days % 365 === 0) { var y = days / 365; return y + ' Year' + (y > 1 ? 's' : ''); }
    if (days > 0 && days % 30 === 0 && days >= 30) { var m = days / 30; return m + ' Month' + (m > 1 ? 's' : ''); }
    return days + ' Day' + (days !== 1 ? 's' : '');
  }

  function updateSummary() {
    if (!current) return;
    var avail = assetSel && assetSel.selectedOptions.length ? parseFloat(assetSel.selectedOptions[0].dataset.avail || '0') : 0;
    var effectiveMax = current.max ? Math.min(current.max, avail) : avail;
    amountIn.min = current.min;
    if (effectiveMax > 0) amountIn.max = effectiveMax;
    amountHint.textContent = 'Min ' + fmtUsd(current.min) + (current.max ? ' – Max ' + fmtUsd(current.max) : '') + '. You have ' + fmtUsd(avail) + ' available in the selected asset.';

    var amt = parseFloat(amountIn.value);
    var valid = assetSel && assetSel.value && amt > 0 && amt >= current.min && (!current.max || amt <= current.max) && amt <= avail;
    submitBtn.disabled = !valid;

    if (amt > 0) {
      var interest = amt * (current.rate / 100);
      var payout   = amt + interest;
      var maturesOn = new Date(Date.now() + current.duration * 86400000);
      summary.style.display = 'block';
      summary.innerHTML = 'Lock <strong>' + fmtUsd(amt) + '</strong> for <strong>' + fmtDuration(current.duration) + '</strong> &mdash; ' +
        'matures ' + maturesOn.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) +
        ' with a payout of <strong>' + fmtUsd(payout) + '</strong> (' + fmtUsd(interest) + ' interest).';
    } else {
      summary.style.display = 'none';
    }
  }

  cards.forEach(function (btn) {
    btn.addEventListener('click', function () {
      current = {
        id: btn.dataset.planId,
        name: btn.dataset.planName,
        rate: parseFloat(btn.dataset.rate),
        duration: parseInt(btn.dataset.duration, 10),
        min: parseFloat(btn.dataset.min),
        max: btn.dataset.max ? parseFloat(btn.dataset.max) : null
      };
      planIdIn.value = current.id;
      formTitle.textContent = 'Lock Funds — ' + current.name;
      formPanel.style.display = 'block';
      formPanel.scrollIntoView({behavior: 'smooth', block: 'start'});
      updateSummary();
    });
  });

  if (assetSel) assetSel.addEventListener('change', updateSummary);
  if (amountIn) amountIn.addEventListener('input', updateSummary);
})();
</script>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
