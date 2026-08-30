<?php
declare(strict_types=1);

/**
 * Legacy public video URL — redirect to secured view-details when possible.
 * Old links with only ?t= are no longer enough; ask for a new secured link.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/fm_public_share.php';

fm_ensure_schema($conn);
fm_public_noindex_headers();

$token = trim((string) ($_GET['t'] ?? ''));
$secret = trim((string) ($_GET['s'] ?? ''));

if ($token !== '' && $secret !== '') {
    header('Location: fm-view-details.php?t=' . rawurlencode($token) . '&s=' . rawurlencode($secret), true, 302);
    exit;
}

http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Secured link required</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:640px">
  <div class="alert alert-warning">
    <strong>This link is no longer valid by itself.</strong><br>
    Candidate details now use a secured <em>View details</em> link (token + secret).
    Please ask the office to send an updated link.
  </div>
</div>
</body>
</html>
