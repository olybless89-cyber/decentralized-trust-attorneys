<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/wallet.php';
$user = require_login();

$stmt = db()->prepare('SELECT * FROM applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$user['id']]);
$apps = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 8');
$stmt->execute([$user['id']]);
$txs = $stmt->fetchAll();

$entityLabels = [
    'LLC' => 'Limited Liability Company',
    'CCORP' => 'Corporation (C-Corp)',
    'CLOSE_LLC' => 'Close LLC',
    'CLOSE_CORP' => 'Close Corporation',
];

$pageTitle = 'Overview';
require __DIR__ . '/includes/dash_header.php';
?>
<div id="crypto-ticker" class="ticker-bar"><div class="ticker-item" style="color:#94a3b8">Loading live prices&hellip;</div></div>

<div class="balance-card">
  <div>
    <div class="label">Total Portfolio Value</div>
    <div class="amount"><?= fmt_money((float) $user['balance']) ?></div>
  </div>
  <div class="quick-actions">
    <a href="send.php" class="qa-btn"><span class="qa-icon">&#8593;</span>Send</a>
    <a href="receive.php" class="qa-btn"><span class="qa-icon">&#8595;</span>Receive</a>
    <a href="swap.php" class="qa-btn"><span class="qa-icon">&#8646;</span>Swap</a>
    <a href="buy.php" class="qa-btn"><span class="qa-icon">&#43;</span>Buy</a>
    <a href="withdraw.php" class="qa-btn"><span class="qa-icon">&#128176;</span>Withdraw</a>
  </div>
</div>

<div class="grid grid-2" style="align-items:start">
  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="margin:0">Recent Activity</h3>
    </div>
    <?php if (!$txs): ?>
      <p style="color:var(--muted);font-size:14px">No wallet activity yet — try Buy or Receive to fund your wallet.</p>
    <?php else: ?>
      <div class="tx-list">
        <?php foreach ($txs as $t): [$label, $tone] = tx_label($t['type']); ?>
          <div class="tx-row">
            <div class="tx-dot tx-<?= $tone ?>"></div>
            <div class="tx-main">
              <div class="tx-title"><?= e($label) ?><?= $t['counter_asset'] ? ' &rarr; ' . e($t['counter_asset']) : '' ?></div>
              <div class="tx-sub"><?= e($t['asset']) ?> &middot; <?= e(date('M j, g:ia', strtotime($t['created_at']))) ?></div>
            </div>
            <div class="tx-amount tx-<?= $tone ?>"><?= $tone === 'down' ? '&minus;' : ($tone === 'up' ? '+' : '') ?><?= fmt_money(abs((float) $t['amount_usd'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="margin:0">Your Applications</h3>
      <a href="applications.php" style="font-size:13.5px;font-weight:600;color:var(--navy)">View all &rarr;</a>
    </div>
    <?php if (!$apps): ?>
      <div class="empty-state">
        <h3 style="margin-bottom:8px;font-size:17px">No applications yet</h3>
        <p style="font-size:14px">Start your first business formation application.</p>
        <a href="application.php" class="btn btn-primary btn-sm" style="margin-top:6px">Start Your Formation</a>
      </div>
    <?php else: ?>
      <?php foreach ($apps as $a): ?>
        <div class="review-row">
          <div>
            <div class="v"><?= e($a['business_name']) ?></div>
            <div class="k" style="margin-top:2px"><?= e($entityLabels[$a['entity_type']] ?? $a['entity_type']) ?> &middot; <?= e($a['state']) ?></div>
          </div>
          <?= status_badge($a['status']) ?>
        </div>
      <?php endforeach; ?>
      <a href="application.php" class="btn btn-outline btn-sm btn-block" style="margin-top:16px">+ New Application</a>
    <?php endif; ?>
  </div>
</div>

<script src="assets/js/crypto-ticker.js"></script>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
