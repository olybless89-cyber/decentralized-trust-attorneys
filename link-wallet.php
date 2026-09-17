<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/wallet.php';
$user = require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired, please try again.';
    } elseif (($_POST['action'] ?? '') === 'unlink') {
        $id = (int) ($_POST['connection_id'] ?? 0);
        if ($id && wallet_unlink($user['id'], $id)) {
            // If the wallet we just unlinked was the one pre-filling withdrawals, swap in the next most recent (or clear it).
            $remaining = wallet_linked_list($user['id']);
            $next = $remaining[0]['address'] ?? null;
            $stmt = db()->prepare('UPDATE users SET linked_wallet_address = ? WHERE id = ?');
            $stmt->execute([$next, $user['id']]);
            flash_set('Wallet unlinked.');
        }
        header('Location: link-wallet.php');
        exit;
    } else {
        $addr = trim($_POST['wallet_address'] ?? '');
        $label = trim($_POST['wallet_name'] ?? '');
        $provider = trim($_POST['provider'] ?? '') ?: null;

        if ($addr === '') {
            $errors[] = 'Enter a wallet address to link.';
        } else {
            $stmt = db()->prepare('UPDATE users SET linked_wallet_address = ? WHERE id = ?');
            $stmt->execute([$addr, $user['id']]);
            log_wallet_connection($user['id'], $addr, 'manual', 'success', $provider, $label ?: null);
            send_email($user['email'], $user['full_name'], 'Wallet Linked to Your Account',
                '<p>Hi ' . e($user['full_name']) . ',</p><p>A wallet address has been linked to your account for withdrawals' . ($provider ? ' (' . e($provider) . ')' : '') . ':</p><p><code>' . e($addr) . '</code></p><p>If this wasn\'t you, contact support immediately at ' . e(SUPPORT_EMAIL) . '.</p>');
            flash_set('Wallet linked. It will be pre-filled on your withdrawal requests.');
            header('Location: link-wallet.php');
            exit;
        }
    }
}

$linked = wallet_linked_list($user['id']);
$providers = wallet_provider_list();

$pageTitle = 'Wallet Management';
require __DIR__ . '/includes/dash_header.php';
?>
<div class="panel" style="max-width:720px;margin:0 auto">
  <h3 style="margin-bottom:6px">Wallet Management</h3>
  <p style="margin-bottom:24px">Link and manage your wallet addresses for faster withdrawals.</p>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <div class="section-label" style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
    <span class="n">&#128279;</span> <strong>Linked Wallets</strong>
  </div>

  <?php if (!$linked): ?>
    <div class="alert alert-info">No wallets linked yet. Pick a provider below, or link one manually.</div>
  <?php else: foreach ($linked as $w): ?>
    <div class="address-box" style="margin-bottom:12px">
      <?php [$g1, $g2] = wallet_provider_gradient($w['provider'] ?: $w['address']); ?>
      <span class="wallet-logo sm" style="background:linear-gradient(135deg,<?= e($g1) ?>,<?= e($g2) ?>)"><?= e(wallet_provider_initials($w['provider'] ?: '??')) ?></span>
      <div style="flex:1;min-width:0;margin-left:12px">
        <div style="font-weight:700;color:var(--navy);font-size:14px"><?= e($w['label'] ?: ($w['provider'] ?: 'Wallet')) ?></div>
        <div style="font-size:12.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($w['address']) ?></div>
      </div>
      <form method="post" onsubmit="return confirm('Unlink this wallet?')">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="unlink">
        <input type="hidden" name="connection_id" value="<?= (int) $w['id'] ?>">
        <button type="submit" class="btn btn-outline btn-sm">Unlink</button>
      </form>
    </div>
  <?php endforeach; endif; ?>

  <div class="section-label" style="display:flex;align-items:center;gap:10px;margin:28px 0 14px">
    <span class="n">&#128203;</span> <strong>Available Providers</strong>
  </div>

  <div class="provider-grid">
    <?php foreach ($providers as $p): [$g1, $g2] = wallet_provider_gradient($p); ?>
      <div class="provider-card" data-provider="<?= e($p) ?>" data-g1="<?= e($g1) ?>" data-g2="<?= e($g2) ?>" onclick="openLinkModal('<?= e($p) ?>','<?= e($g1) ?>','<?= e($g2) ?>')">
        <span class="wallet-logo" style="background:linear-gradient(135deg,<?= e($g1) ?>,<?= e($g2) ?>)"><?= e(wallet_provider_initials($p)) ?></span>
        <span class="wallet-name"><?= e($p) ?></span>
        <span class="wallet-tag">Compatible</span>
      </div>
    <?php endforeach; ?>
    <div class="provider-card" data-provider="" data-g1="#64748b" data-g2="#334155" onclick="openLinkModal('','#64748b','#334155')">
      <span class="wallet-logo" style="background:linear-gradient(135deg,#64748b,#334155)">&#43;</span>
      <span class="wallet-name">Other / Manual</span>
      <span class="wallet-tag">Any address</span>
    </div>
  </div>
</div>

<div id="linkModalOverlay" class="wallet-modal-overlay" style="display:none">
  <div class="wallet-modal">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
      <div class="wallet-modal-head">
        <span id="linkModalLogo" class="wallet-logo">&#43;</span>
        <div>
          <h3 id="linkModalTitle" style="margin:0">Link Wallet</h3>
          <p id="linkModalSub" style="margin:2px 0 0;font-size:12.5px;color:var(--muted)">Connect a public address</p>
        </div>
      </div>
      <button type="button" class="btn btn-outline btn-sm" onclick="closeLinkModal()">&#10005;</button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="provider" id="linkProvider" value="">
      <div class="field">
        <label>Wallet Name</label>
        <input type="text" name="wallet_name" id="linkWalletName" placeholder="e.g. My Main Wallet">
      </div>
      <div class="field">
        <label>Wallet Address</label>
        <input type="text" name="wallet_address" placeholder="0x... or wallet address" required autofocus>
        <p class="hint">Only ever paste a <strong>public</strong> wallet address here. This site will never ask for your recovery phrase or private key — never enter one anywhere.</p>
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" class="btn btn-outline btn-block" onclick="closeLinkModal()">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">Link Wallet</button>
      </div>
    </form>
  </div>
</div>

<script>
function walletInitials(name) {
  if (!name) return '+';
  var words = name.trim().split(/\s+/).slice(0, 2);
  return words.map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || '?';
}
function openLinkModal(provider, g1, g2) {
  document.getElementById('linkProvider').value = provider;
  document.getElementById('linkModalTitle').textContent = provider ? 'Link ' + provider : 'Link Wallet';
  document.getElementById('linkModalSub').textContent = provider ? 'Connect your ' + provider + ' address' : 'Connect a public address';
  document.getElementById('linkWalletName').value = provider;
  var logo = document.getElementById('linkModalLogo');
  logo.textContent = walletInitials(provider);
  logo.style.background = 'linear-gradient(135deg,' + (g1 || '#64748b') + ',' + (g2 || '#334155') + ')';
  document.getElementById('linkModalOverlay').style.display = 'flex';
}
function closeLinkModal() {
  document.getElementById('linkModalOverlay').style.display = 'none';
}
document.getElementById('linkModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeLinkModal();
});
</script>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
