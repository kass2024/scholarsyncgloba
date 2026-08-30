<?php
$cfg = require __DIR__ . '/config.php';
$defaultProvider = $cfg['default_provider'] ?? 'mopay';
header('Location: checkout.php?provider=' . urlencode($defaultProvider));
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Payments Hub</title>
  <style>
    body{font-family:Arial,sans-serif;background:#f8fafc;margin:0;padding:1rem;color:#111827}
    .box{max-width:980px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin-top:1rem}
    .card{border:1px solid #e5e7eb;border-radius:12px;padding:1rem;background:#fff}
    .btn{display:inline-block;padding:.7rem 1rem;border-radius:10px;background:#1f2937;color:#fff;text-decoration:none;font-weight:700}
    .small{color:#6b7280}
    .default{font-size:.85rem;color:#059669;font-weight:700}
  </style>
</head>
<body>
  <div class="box">
    <h2 style="margin:0;">Payments Hub</h2>
    <p class="small">Choose payment provider. Current default: <b><?= htmlspecialchars(strtoupper($defaultProvider)) ?></b></p>
    <div class="cards">
      <div class="card">
        <h3 style="margin-top:0;">MTN Mobile Money (MoPay)</h3>
        <p class="small">Use for Rwanda mobile money collection and transfer-to-receiver flow.</p>
        <?php if ($defaultProvider === 'mopay'): ?><div class="default">DEFAULT PROVIDER</div><?php endif; ?>
        <p><a class="btn" href="mopay/index.php">Open MTN/MoPay</a></p>
      </div>
      <div class="card">
        <h3 style="margin-top:0;">Stripe</h3>
        <p class="small">Use for card payments. Works after you provide Stripe publishable/secret keys.</p>
        <?php if ($defaultProvider === 'stripe'): ?><div class="default">DEFAULT PROVIDER</div><?php endif; ?>
        <p><a class="btn" href="stripe/index.php">Open Stripe</a></p>
      </div>
    </div>
  </div>
</body>
</html>

