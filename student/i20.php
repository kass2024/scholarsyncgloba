<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/student_portal_schema.php';
require_once __DIR__ . '/auth.php';

pcvc_student_portal_ensure_schema($conn);

$pageTitle = 'I-20 Applications';
require_once __DIR__ . '/layout.php';
?>

<h1 class="h4 fw-bold mb-1">I-20 Applications</h1>
<div class="muted mb-3">This page will show I-20-related steps and updates.</div>
<div class="card"><div class="card-body">Coming next.</div></div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>

