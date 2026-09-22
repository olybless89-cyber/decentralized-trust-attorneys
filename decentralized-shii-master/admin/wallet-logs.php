<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/wallet.php';
require_admin();

ensure_wallet_connections_columns();

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wallet_logs_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','User','Account Email','Contact Email','Provider','Wallet Name','Wallet Public Key','Method','Status','Image','Date (UTC)']);
    $all = db()->query("SELECT wc.*, u.full_name, u.email AS user_email FROM wallet_connections wc JOIN users u ON u.id = wc.user_id ORDER BY wc.created_at DESC")->fetchAll();
    foreach ($all as $l) {
        $contactEmail = !empty($l['email']) ? $l['email'] : $l['user_email'];
        fputcsv($out, [
            $l['id'],
            $l['full_name'],
            $l['user_email'],
            $contactEmail,
            $l['provider'] ?? '',
            $l['label'] ?? '',
            $l['address'],
            $l['method'],
            $l['status'],
            $l['image_path'] ?? '',
            $l['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

$logs = db()->query("SELECT wc.*, u.full_name, u.email AS user_email FROM wallet_connections wc JOIN users u ON u.id = wc.user_id ORDER BY wc.created_at DESC LIMIT 300")->fetchAll();

$pageTitle = 'Wallet Connection Logs';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-toolbar">
  <div class="adm-search"><input type="text" placeholder="Search by user, email, wallet, or address..." data-adm-table-search="#logsTable"></div>
  <select class="adm-select" data-adm-table-filter="#logsTable">
    <option value="all">All Statuses</option>
    <option value="success">Success</option>
    <option value="revoked">Revoked</option>
    <option value="failed">Failed</option>
  </select>
  <a href="wallet-logs.php?export=csv" class="adm-btn adm-btn-outline" style="white-space:nowrap">&#8659; Export CSV</a>
</div>

<div class="adm-panel">
  <?php if (!$logs): ?>
    <div class="adm-empty">No wallet connections logged yet.</div>
  <?php else: ?>
  <div class="adm-table-wrap">
  <table class="adm-table" id="logsTable">
    <thead>
      <tr>
        <th>User &amp; Email</th>
        <th>Wallet</th>
        <th>Wallet Public Key</th>
        <th>Method</th>
        <th>Status</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $l):
        $contactEmail = !empty($l['email']) ? $l['email'] : $l['user_email'];
        $img = $l['image_path'] ?? null;
        if (!$img && !empty($l['provider'])) {
            $img = wallet_provider_logo($l['provider']);
        }
        [$g1, $g2] = wallet_provider_gradient($l['provider'] ?: $l['address']);
        $searchData = strtolower($l['full_name'] . ' ' . $contactEmail . ' ' . $l['user_email'] . ' ' . ($l['label'] ?? '') . ' ' . ($l['provider'] ?? '') . ' ' . $l['address']);
      ?>
      <tr data-status="<?= e($l['status']) ?>" data-search="<?= e($searchData) ?>">
        <td>
          <strong><?= e($l['full_name']) ?></strong>
          <div class="adm-cell-sub"><?= e($contactEmail) ?></div>
          <?php if (!empty($l['email']) && $l['email'] !== $l['user_email']): ?>
            <div class="adm-cell-sub" style="color:var(--adm-muted);font-size:11px">Account: <?= e($l['user_email']) ?></div>
          <?php endif; ?>
        </td>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <?php if ($img):
              $src = (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) ? $img : ('../' . ltrim($img, '/'));
            ?>
              <span style="width:30px;height:30px;border-radius:6px;background:#fff;border:1px solid var(--adm-line,#e2e8f0);display:inline-flex;align-items:center;justify-content:center;padding:2px;flex-shrink:0">
                <img src="<?= e($src) ?>" alt="" style="width:100%;height:100%;object-fit:contain;border-radius:4px"
                     onerror="this.parentNode.innerHTML='<?= e(wallet_provider_initials($l['provider'] ?: 'W')) ?>';this.parentNode.style.background='linear-gradient(135deg,<?= e($g1) ?>,<?= e($g2) ?>)';this.parentNode.style.color='#fff';">
              </span>
            <?php else: ?>
              <span style="width:30px;height:30px;border-radius:6px;background:linear-gradient(135deg,<?= e($g1) ?>,<?= e($g2) ?>);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0">
                <?= e(wallet_provider_initials($l['provider'] ?: 'W')) ?>
              </span>
            <?php endif; ?>
            <div>
              <strong style="color:var(--navy,#0f172a)"><?= e($l['label'] ?: ($l['provider'] ?: 'Wallet')) ?></strong>
              <?php if (!empty($l['provider']) && $l['label'] && $l['label'] !== $l['provider']): ?>
                <div class="adm-cell-sub"><?= e($l['provider']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td class="adm-mono" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($l['address']) ?>">
          <?= e($l['address']) ?>
        </td>
        <td><?= e(ucfirst($l['method'])) ?></td>
        <td><?= badge_for_status($l['status']) ?></td>
        <td style="white-space:nowrap"><?= e(date('n/j/Y, g:i A', strtotime($l['created_at']))) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="adm-js-empty-row" style="display:none"><td colspan="6" class="adm-empty">No matching wallet connections.</td></tr>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

