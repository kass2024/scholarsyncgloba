<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/student_portal_schema.php';
require_once __DIR__ . '/auth.php';

pcvc_student_portal_ensure_schema($conn);

$pageTitle = 'Student contract';
require_once __DIR__ . '/layout.php';
?>

<h1 class="h4 fw-bold mb-1">Student contract</h1>
<div class="muted mb-3">If your consultant generated a contract for you, it will appear here.</div>
<div class="card"><div class="card-body">Coming next.</div></div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>

