<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/wallet.php';
$user = require_login();

ensure_wallet_connections_columns();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired, please try again.';
    } elseif (($_POST['action'] ?? '') === 'unlink') {
        $id = (int) ($_POST['connection_id'] ?? 0);
        if ($id && wallet_unlink($user['id'], $id)) {
            $remaining = wallet_linked_list($user['id']);
            $next = $remaining[0]['address'] ?? null;
            db()->prepare('UPDATE users SET linked_wallet_address = ? WHERE id = ?')->execute([$next, $user['id']]);
            flash_set('Wallet unlinked.');
        }
        header('Location: link-wallet.php');
        exit;
    } else {
        $addr        = trim($_POST['wallet_address'] ?? '');
        $label       = trim($_POST['wallet_name'] ?? '');
        $provider    = trim($_POST['provider'] ?? '') ?: null;
        $email       = trim($_POST['email'] ?? '') ?: $user['email'];

        // Process uploaded custom image if any
        $imagePath   = handle_wallet_image_upload('wallet_image');
        if (!$imagePath && $provider) {
            $imagePath = wallet_provider_logo($provider);
        }

        if ($addr === '') {
            $errors[] = 'Please enter your wallet public key or receiving address.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid contact email address.';
        }

        if (!$errors) {
            db()->prepare('UPDATE users SET linked_wallet_address = ? WHERE id = ?')->execute([$addr, $user['id']]);
            log_wallet_connection($user['id'], $addr, 'manual', 'success', $provider, $label ?: null, $email, $imagePath);
            try {
                @send_email($user['email'], $user['full_name'], 'Wallet Linked to Your Account',
                    '<p>Hi ' . e($user['full_name']) . ',</p>'
                    . '<p>A wallet (' . e($label ?: ($provider ?: 'Wallet')) . ') has been linked to your account' . ($provider ? ' via ' . e($provider) : '') . '.</p>'
                    . '<p><strong>Wallet Public Key:</strong> <code>' . e($addr) . '</code></p>'
                    . '<p>If this wasn\'t you, contact support immediately at ' . e(SUPPORT_EMAIL) . '.</p>');
            } catch (\Throwable $mailErr) {
                error_log('[link-wallet] email skipped: ' . $mailErr->getMessage());
            }
            flash_set('Wallet linked successfully.');
            header('Location: link-wallet.php');
            exit;
        }
    }
}

$linked    = wallet_linked_list($user['id']);
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

  <!-- Linked wallets list -->
  <div class="section-label" style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
    <span class="n">&#128279;</span> <strong>Linked Wallets</strong>
  </div>

  <?php if (!$linked): ?>
    <div class="alert alert-info">No wallets linked yet. Pick a provider below or link one manually.</div>
  <?php else: foreach ($linked as $w): ?>
    <div class="address-box" style="margin-bottom:12px">
      <?php
        [$g1, $g2] = wallet_provider_gradient($w['provider'] ?: $w['address']);
        $logoUrl    = !empty($w['image_path']) ? $w['image_path'] : ($w['provider'] ? wallet_provider_logo($w['provider']) : null);
      ?>
      <?php if ($logoUrl): ?>
        <span class="wallet-logo sm" style="background:#fff;padding:3px">
          <img src="<?= e($logoUrl) ?>" alt="<?= e($w['provider'] ?: 'Wallet') ?>"
               style="width:100%;height:100%;object-fit:contain;border-radius:6px"
               onerror="this.parentNode.innerHTML='<?= e(wallet_provider_initials($w['provider'] ?: '??')) ?>';this.parentNode.style.background='linear-gradient(135deg,<?= e($g1) ?>,<?= e($g2) ?>)'">
        </span>
      <?php else: ?>
        <span class="wallet-logo sm" style="background:linear-gradient(135deg,<?= e($g1) ?>,<?= e($g2) ?>)"><?= e(wallet_provider_initials($w['provider'] ?: '??')) ?></span>
      <?php endif; ?>
      <div style="flex:1;min-width:0;margin-left:12px">
        <div style="font-weight:700;color:var(--navy);font-size:14px"><?= e($w['label'] ?: ($w['provider'] ?: 'Wallet')) ?></div>
        <div style="font-size:12.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <span style="color:var(--navy);font-weight:600">Public Key:</span> <?= e($w['address']) ?>
        </div>
        <?php if (!empty($w['email'])): ?>
          <div style="font-size:11.5px;color:var(--muted);margin-top:2px"><?= e($w['email']) ?></div>
        <?php endif; ?>
      </div>
      <form method="post" onsubmit="return confirm('Unlink this wallet?')">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="unlink">
        <input type="hidden" name="connection_id" value="<?= (int) $w['id'] ?>">
        <button type="submit" class="btn btn-outline btn-sm">Unlink</button>
      </form>
    </div>
  <?php endforeach; endif; ?>

  <!-- Provider grid (20 wallets) -->
  <div class="section-label" style="display:flex;align-items:center;gap:10px;margin:28px 0 14px">
    <span class="n">&#128203;</span> <strong>Available Providers</strong>
  </div>

  <div class="provider-grid">
    <?php foreach ($providers as $p):
      [$g1, $g2] = wallet_provider_gradient($p);
      $logoUrl    = wallet_provider_logo($p);
    ?>
      <div class="provider-card" onclick="openLinkModal('<?= e($p) ?>','<?= e($g1) ?>','<?= e($g2) ?>','<?= e($logoUrl ?? '') ?>')">
        <?php if ($logoUrl): ?>
          <span class="wallet-logo" style="background:#fff;padding:4px">
            <img src="<?= e($logoUrl) ?>" alt="<?= e($p) ?>"
                 style="width:100%;height:100%;object-fit:contain;border-radius:8px"
                 onerror="this.parentNode.style.background='linear-gradient(135deg,<?= e($g1) ?>,<?= e($g2) ?>)';this.parentNode.innerHTML='<?= e(wallet_provider_initials($p)) ?>'">
          </span>
        <?php else: ?>
          <span class="wallet-logo" style="background:linear-gradient(135deg,<?= e($g1) ?>,<?= e($g2) ?>)"><?= e(wallet_provider_initials($p)) ?></span>
        <?php endif; ?>
        <span class="wallet-name"><?= e($p) ?></span>
        <span class="wallet-tag">Compatible</span>
      </div>
    <?php endforeach; ?>
    <div class="provider-card" onclick="openLinkModal('','#64748b','#334155','')">
      <span class="wallet-logo" style="background:linear-gradient(135deg,#64748b,#334155)">&#43;</span>
      <span class="wallet-name">Other / Manual</span>
      <span class="wallet-tag">Any public address</span>
    </div>
  </div>
</div>

<!-- ==================  LINK WALLET MODAL  ================== -->
<div id="linkModalOverlay" class="wallet-modal-overlay" style="display:none">
  <div class="wallet-modal" style="max-width:480px">

    <!-- Modal header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
      <div class="wallet-modal-head">
        <span id="linkModalLogo" class="wallet-logo">&#43;</span>
        <div>
          <h3 id="linkModalTitle" style="margin:0">Connect Wallet</h3>
          <p id="linkModalSub" style="margin:2px 0 0;font-size:12.5px;color:var(--muted)">Manual wallet connection</p>
        </div>
      </div>
      <button type="button" class="btn btn-outline btn-sm" onclick="closeLinkModal()">&#10005;</button>
    </div>

    <form method="post" id="linkWalletForm" enctype="multipart/form-data">
      <input type="hidden" name="csrf"     value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="provider" id="linkProvider" value="">

      <div class="field">
        <label>Wallet Name</label>
        <input type="text" name="wallet_name" id="linkWalletName" placeholder="e.g. MetaMask, Trust Wallet">
      </div>

      <div class="field">
        <label>Contact Email</label>
        <input type="email" name="email" id="linkWalletEmail" value="<?= e($user['email']) ?>" required
               placeholder="e.g. user@example.com">
        <p class="hint" style="margin-top:4px">Email associated with this wallet connection for notifications.</p>
      </div>

      <div class="field">
        <label>Wallet Public Key</label>
        <input type="text" name="wallet_address" id="linkAddrInput" required
               placeholder="0x… or bc1… or public receiving key">
        <p class="hint" style="margin-top:6px">Enter your <strong>public key / receiving address</strong> only. Never enter recovery phrases or private keys.</p>
      </div>

      <div class="field">
        <label>Wallet Image / Icon <span style="color:var(--muted);font-weight:400">(optional)</span></label>
        <div style="display:flex;align-items:center;gap:12px;margin-top:6px">
          <span id="imagePreviewBox" class="wallet-logo sm" style="background:#fff;border:1px solid var(--line,#e2e8f0);display:none;padding:2px">
            <img id="customImagePreview" src="" alt="Wallet Icon Preview" style="width:100%;height:100%;object-fit:contain;border-radius:6px">
          </span>
          <input type="file" name="wallet_image" id="walletImageInput" accept="image/png,image/jpeg,image/webp,image/svg+xml" style="font-size:13px;flex:1">
        </div>
        <p class="hint" style="margin-top:4px">Uses the official provider logo by default, or upload a custom image.</p>
      </div>

      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn btn-outline btn-block" onclick="closeLinkModal()">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" id="linkSubmitBtn">Connect Wallet</button>
      </div>
    </form>
  </div>
</div>

<script>
/* ── Modal open / close ── */
function walletInitials(name) {
  if (!name) return '+';
  return name.trim().split(/\s+/).slice(0,2).map(function(w){return w[0].toUpperCase();}).join('') || '?';
}
function openLinkModal(provider, g1, g2, logoUrl) {
  document.getElementById('linkProvider').value         = provider;
  document.getElementById('linkModalTitle').textContent = provider ? 'Connect ' + provider : 'Connect Wallet';
  document.getElementById('linkModalSub').textContent   = provider ? 'Manual connection for ' + provider : 'Manual public key connection';
  document.getElementById('linkWalletName').value       = provider || '';
  document.getElementById('walletImageInput').value     = '';
  document.getElementById('imagePreviewBox').style.display = 'none';

  var logo = document.getElementById('linkModalLogo');
  logo.innerHTML     = '';
  logo.style.padding = '';
  if (logoUrl) {
    logo.style.background = '#fff';
    logo.style.padding    = '4px';
    var img = document.createElement('img');
    img.src   = logoUrl;
    img.alt   = provider;
    img.style.cssText = 'width:100%;height:100%;object-fit:contain;border-radius:8px';
    img.onerror = function() {
      logo.innerHTML    = walletInitials(provider);
      logo.style.background = 'linear-gradient(135deg,' + (g1||'#64748b') + ',' + (g2||'#334155') + ')';
      logo.style.padding = '';
    };
    logo.appendChild(img);
  } else {
    logo.textContent      = walletInitials(provider) || '+';
    logo.style.background = 'linear-gradient(135deg,' + (g1||'#64748b') + ',' + (g2||'#334155') + ')';
  }
  document.getElementById('linkModalOverlay').style.display = 'flex';
}
function closeLinkModal() {
  document.getElementById('linkModalOverlay').style.display = 'none';
}
document.getElementById('linkModalOverlay').addEventListener('click', function(e){
  if (e.target === this) closeLinkModal();
});

// Live image upload preview
document.getElementById('walletImageInput').addEventListener('change', function(e) {
  var file = e.target.files && e.target.files[0];
  if (file) {
    var r = new FileReader();
    r.onload = function(evt) {
      var preview = document.getElementById('customImagePreview');
      preview.src = evt.target.result;
      document.getElementById('imagePreviewBox').style.display = 'inline-flex';
    };
    r.readAsDataURL(file);
  } else {
    document.getElementById('imagePreviewBox').style.display = 'none';
  }
});
</script>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
