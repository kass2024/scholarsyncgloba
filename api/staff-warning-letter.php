<?php
declare(strict_types=1);

/**
 * ======================================================
 * STAFF WARNING LETTER — API
 * Actions:
 *   - search        (GET)  Select2 staff search
 *   - load_staff    (GET)  Pre-fill phone/email for a staff
 *   - preview       (POST) Generate (or update) PDF, return URL
 *   - send          (POST) Generate + persist + deliver via email + WhatsApp
 *   - list          (GET)  History (paginated)
 *   - delete        (POST) Remove a record (superadmin)
 * ======================================================
 */

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/role.php';
require_once __DIR__ . '/../helpers/staff_warning_letter.php';

/* ============ AUTH ============ */
$adminId = 0;
if (!empty($_SESSION['id'])) {
    $adminId = (int) $_SESSION['id'];
} elseif (!empty($_SESSION['admin_id'])) {
    $adminId = (int) $_SESSION['admin_id'];
}
if ($adminId <= 0) {
    jsonResponse('Unauthorized', false, 401);
}

$sessionRole = isset($_SESSION['role']) ? trim((string) $_SESSION['role']) : '';
$dbRole = '';
$stmt = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) $dbRole = trim((string) ($row['role'] ?? ''));
}
$isSuperadmin = xander_is_superadmin_role($dbRole) || xander_is_superadmin_role($sessionRole);
if (!$isSuperadmin) {
    jsonResponse('Forbidden — superadmin only.', false, 403);
}

pcvc_swl_ensure_schema($conn);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* =====================================================
   SEARCH STAFF (Select2)
===================================================== */
if ($action === 'search') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $like = '%' . $q . '%';
    if ($q === '') {
        $res = $conn->query("
            SELECT id, full_name, first_name, last_name, email, phone_number, position, role
            FROM admins ORDER BY full_name ASC LIMIT 20
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT id, full_name, first_name, last_name, email, phone_number, position, role
            FROM admins
            WHERE full_name LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ?
            ORDER BY full_name ASC LIMIT 25
        ");
        $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
    }
    $items = [];
    while ($res && ($r = $res->fetch_assoc())) {
        $name = trim((string) ($r['full_name'] ?: ($r['first_name'] . ' ' . $r['last_name'])));
        $items[] = [
            'id'    => (int) $r['id'],
            'name'  => $name,
            'email' => $r['email'] ?? '',
            'phone' => $r['phone_number'] ?? '',
            'position' => $r['position'] ?? '',
            'role'  => $r['role'] ?? '',
            'text'  => $name . ' — ' . ($r['email'] ?? '—'),
        ];
    }
    jsonResponse(['items' => $items]);
    exit;
}

/* =====================================================
   LOAD ONE STAFF (pre-fill modal)
===================================================== */
if ($action === 'load_staff') {
    $sid = (int) ($_GET['id'] ?? 0);
    if ($sid <= 0) jsonResponse('Invalid staff id', false, 400);
    $staff = pcvc_swl_load_staff($conn, $sid);
    if (!$staff) jsonResponse('Staff not found', false, 404);
    $staff['full_name'] = trim((string) ($staff['full_name'] ?: ($staff['first_name'] . ' ' . $staff['last_name'])));
    jsonResponse(['staff' => $staff]);
    exit;
}

/* =====================================================
   PREVIEW PDF
===================================================== */
if ($action === 'preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid     = (int) ($_POST['staff_id'] ?? 0);
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $content = (string) ($_POST['content_html'] ?? '');
    if ($sid <= 0 || $subject === '' || trim(strip_tags($content)) === '') {
        jsonResponse('Staff, subject and message body are required.', false, 422);
    }
    $staff = pcvc_swl_load_staff($conn, $sid);
    if (!$staff) jsonResponse('Staff not found', false, 404);

    $ref = pcvc_swl_make_reference($sid);
    $html = pcvc_swl_build_html($staff, $subject, $content, $ref);
    $pdf  = pcvc_swl_render_pdf($html, $sid, $ref);
    $base = pcvc_swl_public_base_url();

    jsonResponse([
        'reference' => $ref,
        'pdf_url'   => $base . '/' . $pdf['path'],
        'pdf_path'  => $pdf['path'],
        'filename'  => $pdf['filename'],
    ]);
    exit;
}

/* =====================================================
   SEND
===================================================== */
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid     = (int) ($_POST['staff_id'] ?? 0);
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $content = (string) ($_POST['content_html'] ?? '');
    $overrideEmail = trim((string) ($_POST['override_email'] ?? ''));
    $overridePhone = trim((string) ($_POST['override_phone'] ?? ''));
    $sendEm  = !isset($_POST['send_email'])    || $_POST['send_email']    === '1' || $_POST['send_email']    === 'true';
    $sendWa  = !isset($_POST['send_whatsapp']) || $_POST['send_whatsapp'] === '1' || $_POST['send_whatsapp'] === 'true';

    if ($sid <= 0 || $subject === '' || trim(strip_tags($content)) === '') {
        jsonResponse('Staff, subject and message body are required.', false, 422);
    }
    if (!$sendEm && !$sendWa) {
        jsonResponse('Select email and/or WhatsApp.', false, 422);
    }

    $staff = pcvc_swl_load_staff($conn, $sid);
    if (!$staff) jsonResponse('Staff not found', false, 404);

    $staffName = trim((string) ($staff['full_name'] ?: ($staff['first_name'] . ' ' . $staff['last_name'])));
    $email     = $overrideEmail !== '' ? $overrideEmail : trim((string) ($staff['email'] ?? ''));
    $phone     = $overridePhone !== '' ? $overridePhone : trim((string) ($staff['phone_number'] ?? ''));

    $ref  = pcvc_swl_make_reference($sid);
    $html = pcvc_swl_build_html($staff, $subject, $content, $ref);
    $pdf  = pcvc_swl_render_pdf($html, $sid, $ref);
    $base = pcvc_swl_public_base_url();
    $pdfUrl = $base . '/' . $pdf['path'];

    $emOut = ['sent' => false, 'error' => ''];
    $waOut = ['sent' => false, 'method' => '', 'error' => '', 'not_on_whatsapp' => false];

    if ($sendEm) {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emOut['error'] = 'Staff has no valid email.';
        } else {
            $emOut = pcvc_swl_send_email($email, $staffName, $subject, $content, $pdf['abs'], $pdf['filename']);
        }
    }

    if ($sendWa) {
        if ($phone === '') {
            $waOut['error'] = 'Staff has no phone number on file.';
        } else {
            // Pass absolute path so the helper can upload the PDF to Meta media
            // (more reliable than fetching by public URL).
            $waOut = pcvc_swl_send_whatsapp($phone, $staffName, $subject, $pdfUrl, $ref, $pdf['abs']);
        }
    }

    $recordId = pcvc_swl_save_record($conn, [
        'staff_id'        => $sid,
        'staff_name'      => $staffName,
        'staff_email'     => $email,
        'staff_phone'     => $phone,
        'subject'         => $subject,
        'content_html'    => $content,
        'pdf_path'        => $pdf['path'],
        'email_sent'      => !empty($emOut['sent']) ? 1 : 0,
        'email_error'     => $emOut['error'] ?? '',
        'whatsapp_sent'   => !empty($waOut['sent']) ? 1 : 0,
        'whatsapp_method' => $waOut['method'] ?? '',
        'whatsapp_error'  => $waOut['error'] ?? '',
        'created_by'      => $adminId,
    ]);

    jsonResponse([
        'id'        => $recordId,
        'reference' => $ref,
        'pdf_url'   => $pdfUrl,
        'pdf_path'  => $pdf['path'],
        'email'     => $emOut,
        'whatsapp'  => $waOut,
    ]);
    exit;
}

/* =====================================================
   HISTORY
===================================================== */
if ($action === 'list') {
    $limit  = max(1, min(50, (int) ($_GET['limit']  ?? 20)));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    $sid    = (int) ($_GET['staff_id'] ?? 0);
    $q      = trim((string) ($_GET['q'] ?? ''));
    $like   = '%' . $q . '%';

    $where  = '1=1';
    $params = [];
    $types  = '';
    if ($sid > 0) {
        $where .= ' AND staff_id = ?';
        $params[] = $sid; $types .= 'i';
    }
    if ($q !== '') {
        $where .= ' AND (staff_name LIKE ? OR subject LIKE ?)';
        $params[] = $like; $params[] = $like; $types .= 'ss';
    }

    $sql = "SELECT id, staff_id, staff_name, staff_email, staff_phone, subject,
                   pdf_path, email_sent, email_error, whatsapp_sent, whatsapp_method, whatsapp_error,
                   created_at
            FROM staff_warning_letters
            WHERE $where ORDER BY id DESC LIMIT $offset, $limit";
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    $base = pcvc_swl_public_base_url();
    while ($r = $res->fetch_assoc()) {
        $r['pdf_url'] = $r['pdf_path'] ? ($base . '/' . $r['pdf_path']) : '';
        $rows[] = $r;
    }
    $stmt->close();
    jsonResponse(['items' => $rows]);
    exit;
}

/* =====================================================
   DELETE
===================================================== */
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse('Invalid id', false, 400);

    $stmt = $conn->prepare("SELECT pdf_path FROM staff_warning_letters WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && !empty($row['pdf_path'])) {
        $abs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $row['pdf_path']);
        if (is_file($abs)) @unlink($abs);
    }
    $del = $conn->prepare("DELETE FROM staff_warning_letters WHERE id = ? LIMIT 1");
    $del->bind_param('i', $id);
    $del->execute();
    $del->close();
    jsonResponse(['deleted' => $id]);
    exit;
}

jsonResponse('Unknown action', false, 400);
