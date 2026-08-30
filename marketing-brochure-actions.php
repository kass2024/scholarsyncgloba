<?php
declare(strict_types=1);

session_start();

// Always answer JSON — even on a fatal PHP error / uncaught exception.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=UTF-8');

set_exception_handler(static function (Throwable $e): void {
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode([
        'ok'    => false,
        'error' => 'Server error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
        }
        echo json_encode([
            'ok'    => false,
            'error' => 'Server fatal: ' . ($err['message'] ?? 'unknown'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
});

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/csrf.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/phone_whatsapp_normalize.php';
require_once __DIR__ . '/helpers/student_status_notify.php';
require_once __DIR__ . '/helpers/marketing_brochure_schema.php';
require_once __DIR__ . '/helpers/marketing_brochure_extract.php';
require_once __DIR__ . '/helpers/marketing_brochure_ai.php';
require_once __DIR__ . '/helpers/marketing_brochure_send.php';

pcvc_marketing_brochure_ensure_schema($conn);

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated. Please log in again.']);
    exit;
}

$adminId = (int) $_SESSION['id'];
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * Respond as JSON and stop.
 */
function pcvc_brochure_respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Slugify a piece of text for filenames / URLs.
 */
function pcvc_brochure_slugify(string $text): string
{
    $text = trim($text);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? $text;
    $text = trim($text, '-');
    if (function_exists('iconv')) {
        $tr = @iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        if ($tr !== false && $tr !== '') {
            $text = $tr;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text) ?? $text;
    $text = preg_replace('~-+~', '-', $text) ?? $text;

    return $text !== '' ? $text : ('brochure-' . bin2hex(random_bytes(4)));
}

/**
 * Ensure the brochures uploads folder exists and return its absolute path.
 * Delegates to the schema helper so deployment safeguards stay in one place.
 */
function pcvc_brochure_uploads_dir(): string
{
    return pcvc_marketing_brochure_ensure_uploads_dir();
}

/**
 * Build the absolute public share URL for a given brochure slug.
 */
function pcvc_brochure_public_url(string $slug): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base   = rtrim(str_replace('\\', '/', dirname($script)), '/');

    return $scheme . '://' . $host . $base . '/brochure-view.php?slug=' . urlencode($slug);
}

switch ($action) {
    // -------------------------------------------------------------
    case 'list_regions':
        $rows = [];
        if ($r = $conn->query('SELECT id, name FROM regions ORDER BY name')) {
            while ($row = $r->fetch_assoc()) {
                $rows[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
            }
        }
        pcvc_brochure_respond(['ok' => true, 'regions' => $rows]);
        break;

    // -------------------------------------------------------------
    case 'add_region':
        if (!pcvc_csrf_validate_post()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid security token.'], 403);
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Region name is required.'], 400);
        }
        $stmt = $conn->prepare('SELECT id FROM regions WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            pcvc_brochure_respond([
                'ok'     => true,
                'region' => ['id' => (int) $existing['id'], 'name' => $name],
                'note'   => 'Region already existed; reusing it.',
            ]);
        }
        $ins = $conn->prepare('INSERT INTO regions (name) VALUES (?)');
        $ins->bind_param('s', $name);
        if (!$ins->execute()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Could not create region: ' . $conn->error], 500);
        }
        $newId = (int) $conn->insert_id;
        $ins->close();
        pcvc_brochure_respond([
            'ok'     => true,
            'region' => ['id' => $newId, 'name' => $name],
        ]);
        break;

    // -------------------------------------------------------------
    case 'add_university':
        if (!pcvc_csrf_validate_post()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid security token.'], 403);
        }
        $uname    = trim((string) ($_POST['name'] ?? ''));
        $uregion  = isset($_POST['region_id']) ? (int) $_POST['region_id'] : 0;
        if ($uname === '') {
            pcvc_brochure_respond(['ok' => false, 'error' => 'University name is required.'], 400);
        }

        $stmt = $conn->prepare('SELECT id, name, region_id FROM universities WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->bind_param('s', $uname);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            if ($uregion > 0 && (int) $existing['region_id'] !== $uregion) {
                $upd = $conn->prepare('UPDATE universities SET region_id = ? WHERE id = ?');
                $existingId = (int) $existing['id'];
                $upd->bind_param('ii', $uregion, $existingId);
                $upd->execute();
                $upd->close();
                $existing['region_id'] = $uregion;
            }
            pcvc_brochure_respond([
                'ok'         => true,
                'university' => [
                    'id'        => (int) $existing['id'],
                    'name'      => (string) $existing['name'],
                    'region_id' => (int) $existing['region_id'],
                ],
                'note' => 'University already existed; reusing it.',
            ]);
        }

        if ($uregion > 0) {
            $ins = $conn->prepare('INSERT INTO universities (name, region_id) VALUES (?, ?)');
            $ins->bind_param('si', $uname, $uregion);
        } else {
            $ins = $conn->prepare('INSERT INTO universities (name) VALUES (?)');
            $ins->bind_param('s', $uname);
        }
        if (!$ins->execute()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Could not create university: ' . $conn->error], 500);
        }
        $newUniId = (int) $conn->insert_id;
        $ins->close();

        pcvc_brochure_respond([
            'ok'         => true,
            'university' => [
                'id'        => $newUniId,
                'name'      => $uname,
                'region_id' => $uregion,
            ],
        ]);
        break;

    // -------------------------------------------------------------
    case 'list_brochures':
        $regionId = isset($_GET['region_id']) ? (int) $_GET['region_id'] : 0;
        $q = trim((string) ($_GET['q'] ?? ''));

        $where  = ['b.is_active = 1'];
        $types  = '';
        $params = [];
        if ($regionId > 0) {
            $where[]  = 'b.region_id = ?';
            $types   .= 'i';
            $params[] = $regionId;
        }
        if ($q !== '') {
            $where[]  = '(b.title LIKE ? OR r.name LIKE ?)';
            $types   .= 'ss';
            $like     = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT b.id, b.title, b.slug, b.description, b.pdf_filename, b.pdf_path,
                       b.pdf_size_bytes, b.attach_pdf, b.extraction_status,
                       b.university_id, u.name AS university_name,
                       b.view_count, b.share_count, b.created_at,
                       b.region_id, r.name AS region_name
                FROM marketing_brochures b
                LEFT JOIN regions r ON r.id = b.region_id
                LEFT JOIN universities u ON u.id = b.university_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY b.created_at DESC
                LIMIT 200';
        $stmt = $conn->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res  = $stmt->get_result();
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $row['id']             = (int) $row['id'];
            $row['region_id']      = (int) ($row['region_id'] ?? 0);
            $row['view_count']     = (int) $row['view_count'];
            $row['share_count']    = (int) $row['share_count'];
            $row['pdf_size_bytes'] = (int) $row['pdf_size_bytes'];
            $row['attach_pdf']     = (int) ($row['attach_pdf'] ?? 1);
            $row['university_id']  = (int) ($row['university_id'] ?? 0);
            $row['university_name']= (string) ($row['university_name'] ?? '');
            $row['share_url']      = pcvc_brochure_public_url((string) $row['slug']);
            $list[]                = $row;
        }
        $stmt->close();
        pcvc_brochure_respond(['ok' => true, 'brochures' => $list]);
        break;

    // -------------------------------------------------------------
    case 'upload_brochure':
        if (!pcvc_csrf_validate_post()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid security token.'], 403);
        }

        $title        = trim((string) ($_POST['title'] ?? ''));
        $description  = trim((string) ($_POST['description'] ?? ''));
        $regionId     = isset($_POST['region_id'])     ? (int) $_POST['region_id']     : 0;
        $universityId = isset($_POST['university_id']) ? (int) $_POST['university_id'] : 0;
        $attachPdf    = !empty($_POST['attach_pdf']) ? 1 : 0;

        if ($title === '') {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Title is required.'], 400);
        }
        if ($regionId <= 0 && $universityId <= 0) {
            pcvc_brochure_respond([
                'ok'    => false,
                'error' => 'Please choose at least a region or a university (you can create either inline).',
            ], 400);
        }
        if (!isset($_FILES['pdf']) || ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'A PDF file is required.'], 400);
        }

        $f = $_FILES['pdf'];
        $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Only PDF files are allowed.'], 400);
        }
        if ((int) $f['size'] > 25 * 1024 * 1024) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'PDF must be 25 MB or less.'], 400);
        }

        $region = null;
        if ($regionId > 0) {
            $stmt = $conn->prepare('SELECT id, name FROM regions WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $regionId);
            $stmt->execute();
            $region = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$region) {
                pcvc_brochure_respond(['ok' => false, 'error' => 'Selected region no longer exists.'], 400);
            }
        } else {
            $regionId = 0;
        }

        $universityName = '';
        if ($universityId > 0) {
            $stmt = $conn->prepare('SELECT id, name FROM universities WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $universityId);
            $stmt->execute();
            $uni = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$uni) {
                pcvc_brochure_respond(['ok' => false, 'error' => 'Selected university no longer exists.'], 400);
            }
            $universityName = (string) $uni['name'];
        } else {
            $universityId = 0;
        }

        $baseSlug = pcvc_brochure_slugify($title);
        $slug     = $baseSlug;
        $suffix   = 1;
        while (true) {
            $check = $conn->prepare('SELECT id FROM marketing_brochures WHERE slug = ? LIMIT 1');
            $check->bind_param('s', $slug);
            $check->execute();
            $taken = $check->get_result()->fetch_assoc();
            $check->close();
            if (!$taken) {
                break;
            }
            $slug = $baseSlug . '-' . (++$suffix);
        }

        $dir       = pcvc_brochure_uploads_dir();
        $safeName  = $slug . '-' . time() . '.pdf';
        $absPath   = $dir . DIRECTORY_SEPARATOR . $safeName;
        $relPath   = 'uploads/brochures/' . $safeName;

        if (!@move_uploaded_file($f['tmp_name'], $absPath)) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Failed to move uploaded file.'], 500);
        }

        $size = (int) @filesize($absPath);

        // PDF → HTML extraction (best-effort; failure is non-fatal).
        $extract = pcvc_brochure_extract_pdf($absPath, $title, (string) $region['name']);
        $extractedText = (string) ($extract['text'] ?? '');
        $htmlContent   = (string) ($extract['html'] ?? '');
        $extEngine     = (string) ($extract['engine'] ?? 'none');
        $extStatus     = $htmlContent !== '' ? 'ok' : 'failed';

        $sql = 'INSERT INTO marketing_brochures
                  (region_id, university_id, title, slug, description, pdf_filename, pdf_path, pdf_size_bytes,
                   attach_pdf, extracted_text, html_content, extraction_status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $ins  = $conn->prepare($sql);
        $orig = (string) $f['name'];
        $regionIdParam     = $regionId     > 0 ? $regionId     : null;
        $universityIdParam = $universityId > 0 ? $universityId : null;
        // i i s s s s s i i s s s i -> 13 placeholders
        $ins->bind_param(
            'iisssssiisssi',
            $regionIdParam, $universityIdParam, $title, $slug, $description, $orig, $relPath, $size,
            $attachPdf, $extractedText, $htmlContent, $extStatus, $adminId
        );
        if (!$ins->execute()) {
            @unlink($absPath);
            pcvc_brochure_respond(['ok' => false, 'error' => 'Database insert failed: ' . $conn->error], 500);
        }
        $newId = (int) $conn->insert_id;
        $ins->close();

        pcvc_brochure_respond([
            'ok'        => true,
            'brochure'  => [
                'id'                => $newId,
                'title'             => $title,
                'slug'              => $slug,
                'description'       => $description,
                'region_id'         => (int) ($region['id'] ?? 0),
                'region_name'       => (string) ($region['name'] ?? ''),
                'university_id'     => $universityId,
                'university_name'   => $universityName,
                'pdf_filename'      => $orig,
                'pdf_path'          => $relPath,
                'pdf_size_bytes'    => $size,
                'attach_pdf'        => $attachPdf,
                'extraction_status' => $extStatus,
                'extraction_engine' => $extEngine,
                'view_count'        => 0,
                'share_count'       => 0,
                'share_url'         => pcvc_brochure_public_url($slug),
                'created_at'        => date('Y-m-d H:i:s'),
            ],
        ]);
        break;

    // -------------------------------------------------------------
    case 'update_brochure':
        if (!pcvc_csrf_validate_post()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid security token.'], 403);
        }

        $id           = (int) ($_POST['id'] ?? 0);
        $title        = trim((string) ($_POST['title'] ?? ''));
        $description  = trim((string) ($_POST['description'] ?? ''));
        $regionId     = isset($_POST['region_id'])     ? (int) $_POST['region_id']     : 0;
        $universityId = isset($_POST['university_id']) ? (int) $_POST['university_id'] : 0;

        if ($id <= 0) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid brochure ID.'], 400);
        }
        if ($title === '') {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Title is required.'], 400);
        }
        if ($regionId <= 0 && $universityId <= 0) {
            pcvc_brochure_respond([
                'ok'    => false,
                'error' => 'Please choose at least a region or a university.',
            ], 400);
        }

        $stmt = $conn->prepare('SELECT id, slug FROM marketing_brochures WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$existing) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Brochure not found.'], 404);
        }

        if ($regionId > 0) {
            $r = $conn->prepare('SELECT id, name FROM regions WHERE id = ? LIMIT 1');
            $r->bind_param('i', $regionId);
            $r->execute();
            $rRow = $r->get_result()->fetch_assoc();
            $r->close();
            if (!$rRow) {
                pcvc_brochure_respond(['ok' => false, 'error' => 'Selected region no longer exists.'], 400);
            }
        }

        if ($universityId > 0) {
            $u = $conn->prepare('SELECT id, name FROM universities WHERE id = ? LIMIT 1');
            $u->bind_param('i', $universityId);
            $u->execute();
            $uRow = $u->get_result()->fetch_assoc();
            $u->close();
            if (!$uRow) {
                pcvc_brochure_respond(['ok' => false, 'error' => 'Selected university no longer exists.'], 400);
            }
        }

        // mysqli needs typed vars; use 0 + SQL NULLIF for optional FK columns.
        $regionIdVal     = $regionId     > 0 ? $regionId     : 0;
        $universityIdVal = $universityId > 0 ? $universityId : 0;

        $hasUpdatedAt = false;
        if ($colRes = $conn->query("SHOW COLUMNS FROM marketing_brochures LIKE 'updated_at'")) {
            $hasUpdatedAt = (bool) $colRes->num_rows;
            $colRes->close();
        }

        $sqlUpd = $hasUpdatedAt
            ? 'UPDATE marketing_brochures
                  SET title = ?, description = ?,
                      region_id = NULLIF(?, 0),
                      university_id = NULLIF(?, 0),
                      updated_at = NOW()
                WHERE id = ?'
            : 'UPDATE marketing_brochures
                  SET title = ?, description = ?,
                      region_id = NULLIF(?, 0),
                      university_id = NULLIF(?, 0)
                WHERE id = ?';

        $upd = $conn->prepare($sqlUpd);
        $upd->bind_param('ssiii', $title, $description, $regionIdVal, $universityIdVal, $id);
        if (!$upd->execute()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Update failed: ' . $conn->error], 500);
        }
        $upd->close();

        $sel = $conn->prepare(
            'SELECT b.id, b.title, b.slug, b.description, b.region_id, b.university_id,
                    r.name AS region_name, u.name AS university_name
               FROM marketing_brochures b
               LEFT JOIN regions r      ON r.id = b.region_id
               LEFT JOIN universities u ON u.id = b.university_id
              WHERE b.id = ? LIMIT 1'
        );
        $sel->bind_param('i', $id);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc() ?: [];
        $sel->close();

        pcvc_brochure_respond([
            'ok'       => true,
            'brochure' => [
                'id'              => (int) ($row['id'] ?? $id),
                'title'           => (string) ($row['title'] ?? $title),
                'slug'            => (string) ($row['slug'] ?? ($existing['slug'] ?? '')),
                'description'    => (string) ($row['description'] ?? $description),
                'region_id'       => (int) ($row['region_id'] ?? 0),
                'region_name'     => (string) ($row['region_name'] ?? ''),
                'university_id'   => (int) ($row['university_id'] ?? 0),
                'university_name' => (string) ($row['university_name'] ?? ''),
            ],
        ]);
        break;

    // -------------------------------------------------------------
    case 'set_attach_pdf':
        if (!pcvc_csrf_validate_post()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid security token.'], 403);
        }
        $id    = (int) ($_POST['id'] ?? 0);
        $value = !empty($_POST['attach_pdf']) ? 1 : 0;
        if ($id <= 0) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid brochure ID.'], 400);
        }
        $u = $conn->prepare('UPDATE marketing_brochures SET attach_pdf = ? WHERE id = ?');
        $u->bind_param('ii', $value, $id);
        $u->execute();
        $u->close();
        pcvc_brochure_respond(['ok' => true, 'attach_pdf' => $value]);
        break;

    // -------------------------------------------------------------
    case 'regenerate_html':
        if (!pcvc_csrf_validate_post()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid security token.'], 403);
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid brochure ID.'], 400);
        }
        $stmt = $conn->prepare('SELECT b.pdf_path, b.title, COALESCE(r.name, "Global") AS region_name
                                FROM marketing_brochures b
                                LEFT JOIN regions r ON r.id = b.region_id
                                WHERE b.id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Brochure not found.'], 404);
        }
        $abs = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $row['pdf_path']);
        $extract = pcvc_brochure_extract_pdf($abs, (string) $row['title'], (string) $row['region_name']);
        $status  = !empty($extract['html']) ? 'ok' : 'failed';
        $u = $conn->prepare('UPDATE marketing_brochures
                              SET extracted_text = ?, html_content = ?, extraction_status = ?
                              WHERE id = ?');
        $u->bind_param('sssi', $extract['text'], $extract['html'], $status, $id);
        $u->execute();
        $u->close();
        pcvc_brochure_respond([
            'ok'                => true,
            'extraction_status' => $status,
            'extraction_engine' => $extract['engine'] ?? 'none',
            'ai_used'           => !empty($extract['ai_used']),
        ]);
        break;

    // -------------------------------------------------------------
    case 'reextract_all':
        if (!pcvc_csrf_validate_post()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid security token.'], 403);
        }
        $onlyMissing = !empty($_POST['only_missing']);
        $maxRun      = max(1, min((int) ($_POST['limit'] ?? 8), 25));
        $sqlList = $onlyMissing
            ? "SELECT b.id, b.pdf_path, b.title, COALESCE(r.name,'Global') AS region_name
                 FROM marketing_brochures b LEFT JOIN regions r ON r.id = b.region_id
                 WHERE b.is_active=1 AND (b.html_content IS NULL OR b.html_content='' OR b.extraction_status<>'ok')
                 ORDER BY b.id ASC LIMIT $maxRun"
            : "SELECT b.id, b.pdf_path, b.title, COALESCE(r.name,'Global') AS region_name
                 FROM marketing_brochures b LEFT JOIN regions r ON r.id = b.region_id
                 WHERE b.is_active=1
                 ORDER BY b.id ASC LIMIT $maxRun";
        $rs = $conn->query($sqlList);
        $done      = [];
        $failed    = [];
        $aiCnt     = 0;
        $regexCnt  = 0;
        $failNotes = [];
        while ($rs && ($row = $rs->fetch_assoc())) {
            $bid = (int) $row['id'];
            $abs = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $row['pdf_path']);
            if (!is_file($abs)) {
                $failed[] = $bid;
                $failNotes[] = ['id' => $bid, 'reason' => 'pdf_missing', 'path' => (string) $row['pdf_path']];
                continue;
            }
            $ext = pcvc_brochure_extract_pdf($abs, (string) $row['title'], (string) $row['region_name']);
            $ok  = trim((string) ($ext['html'] ?? '')) !== '';
            $st  = $ok ? 'ok' : 'failed';
            $txt = (string) ($ext['text'] ?? '');
            $htm = (string) ($ext['html'] ?? '');
            $u = $conn->prepare('UPDATE marketing_brochures SET extracted_text=?, html_content=?, extraction_status=? WHERE id=?');
            $u->bind_param('sssi', $txt, $htm, $st, $bid);
            if (!$u->execute()) {
                $failed[] = $bid;
                $failNotes[] = ['id' => $bid, 'reason' => 'db_update', 'error' => $conn->error];
                $u->close();
                continue;
            }
            $u->close();
            if ($ok) {
                $done[] = $bid;
                if (!empty($ext['ai_used'])) {
                    $aiCnt++;
                } elseif (pcvc_brochure_ai_enabled()) {
                    $regexCnt++;
                }
            } else {
                $failed[] = $bid;
                $failNotes[] = ['id' => $bid, 'reason' => 'extract_empty', 'engine' => (string) ($ext['engine'] ?? 'none')];
            }
        }
        pcvc_brochure_respond([
            'ok'         => true,
            'processed'  => count($done) + count($failed),
            'succeeded'  => count($done),
            'failed'     => count($failed),
            'ai_used'    => $aiCnt,
            'regex_used' => $regexCnt,
            'done_ids'   => $done,
            'failed_ids' => $failed,
            'failures'   => $failNotes,
            'ai_enabled' => pcvc_brochure_ai_enabled(),
            'ai_paused'  => function_exists('pcvc_brochure_ai_quota_paused') && pcvc_brochure_ai_quota_paused(),
        ]);
        break;

    // -------------------------------------------------------------
    case 'delete_brochure':
        if (!pcvc_csrf_validate_post()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid security token.'], 403);
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid brochure ID.'], 400);
        }
        $stmt = $conn->prepare('SELECT pdf_path FROM marketing_brochures WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Brochure not found.'], 404);
        }
        $del = $conn->prepare('DELETE FROM marketing_brochures WHERE id = ?');
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
        $full = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $row['pdf_path']);
        if (is_file($full)) {
            @unlink($full);
        }
        pcvc_brochure_respond(['ok' => true]);
        break;

    // -------------------------------------------------------------
    case 'lookup_phone':
    case 'search_applicants':
        $q = trim((string) ($_GET['q'] ?? $_POST['q'] ?? $_GET['phone'] ?? $_POST['phone'] ?? ''));
        if ($q === '') {
            pcvc_brochure_respond(['ok' => true, 'matches' => [], 'count' => 0]);
        }
        $isPhone   = (bool) preg_match('/^[\d\+\-\(\)\s]+$/', $q);
        $digits    = preg_replace('/\D+/', '', $q) ?? '';
        $shortDig  = strlen($digits) >= 6 ? ltrim(substr($digits, -10), '0') : '';
        $likeDig   = $shortDig !== '' ? ('%' . $shortDig . '%') : null;
        $likeText  = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $matches = pcvc_brochure_search_applicants($conn, $likeDig, $likeText, $isPhone, $q);

        $dcc       = trim(xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE'));
        $defaultCc = $dcc !== '' ? $dcc : null;
        $waE164    = null;
        if ($isPhone && $digits !== '') {
            $waE164 = xander_format_phone_for_whatsapp_e164($q, $defaultCc);
        }

        pcvc_brochure_respond([
            'ok'              => true,
            'normalized_e164' => $waE164,
            'whatsapp_url'    => $waE164 ? 'https://wa.me/' . rawurlencode($waE164) : null,
            'matches'         => $matches,
            'count'           => count($matches),
        ]);
        break;

    // -------------------------------------------------------------
    case 'send_whatsapp':
        if (!pcvc_csrf_validate_post()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid security token.'], 403);
        }
        $brochureId = (int) ($_POST['brochure_id'] ?? 0);
        $name       = trim((string) ($_POST['name'] ?? ''));
        $phone      = trim((string) ($_POST['phone'] ?? ''));
        $matchTbl   = trim((string) ($_POST['matched_table'] ?? ''));
        $matchId    = (int) ($_POST['matched_row_id'] ?? 0);
        $isNew      = (int) (!empty($_POST['is_new_contact']) ? 1 : 0);
        $customMsg  = trim((string) ($_POST['message'] ?? ''));

        if ($brochureId <= 0 || $phone === '') {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Phone is required.'], 400);
        }

        $stmt = $conn->prepare('SELECT id, slug, title FROM marketing_brochures WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $brochureId);
        $stmt->execute();
        $brochure = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$brochure) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Brochure not found.'], 404);
        }

        $shareUrl = pcvc_brochure_public_url((string) $brochure['slug']);
        $defaultMsg = pcvc_brochure_default_message($name, (string) $brochure['title'], $shareUrl);
        $body = $customMsg !== '' ? $customMsg : $defaultMsg;

        if ($isNew && $phone !== '') {
            pcvc_brochure_save_contact($conn, $name, $phone);
        }

        $result = pcvc_brochure_send_whatsapp($phone, $body, [
            'name'  => $name,
            'title' => (string) $brochure['title'],
            'url'   => $shareUrl,
        ]);

        $shareToken = bin2hex(random_bytes(8));
        $channel    = 'whatsapp';
        $notes      = $result['sent'] ? ('sent via ' . $result['method']) : ('failed: ' . substr($result['error'], 0, 200));
        $email      = '';
        $sql = 'INSERT INTO marketing_brochure_shares
                  (brochure_id, share_token, recipient_name, recipient_phone, recipient_email,
                   channel, matched_table, matched_row_id, is_new_contact, shared_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $ins = $conn->prepare($sql);
        $ins->bind_param('issssssiiis', $brochureId, $shareToken, $name, $phone, $email, $channel, $matchTbl, $matchId, $isNew, $adminId, $notes);
        $ins->execute();
        $ins->close();
        if ($result['sent']) {
            $conn->query("UPDATE marketing_brochures SET share_count = share_count + 1 WHERE id = $brochureId");
        }

        pcvc_brochure_respond([
            'ok'      => (bool) $result['sent'],
            'sent'    => (bool) $result['sent'],
            'method'  => $result['method'] ?? '',
            'error'   => $result['error'] ?? '',
            'detail'  => $result['detail'] ?? '',
            'share_url' => $shareUrl,
            'message' => $body,
        ]);
        break;

    // -------------------------------------------------------------
    case 'send_email':
        if (!pcvc_csrf_validate_post()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid security token.'], 403);
        }
        $brochureId = (int) ($_POST['brochure_id'] ?? 0);
        $name       = trim((string) ($_POST['name'] ?? ''));
        $email      = trim((string) ($_POST['email'] ?? ''));
        $matchTbl   = trim((string) ($_POST['matched_table'] ?? ''));
        $matchId    = (int) ($_POST['matched_row_id'] ?? 0);
        $isNew      = (int) (!empty($_POST['is_new_contact']) ? 1 : 0);
        $attachPdfReq = !empty($_POST['attach_pdf']);

        if ($brochureId <= 0 || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Valid email is required.'], 400);
        }

        $stmt = $conn->prepare('SELECT id, slug, title, description, pdf_path, pdf_filename, attach_pdf, html_content FROM marketing_brochures WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $brochureId);
        $stmt->execute();
        $brochure = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$brochure) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Brochure not found.'], 404);
        }

        $shareUrl   = pcvc_brochure_public_url((string) $brochure['slug']);
        $pdfAbs     = $attachPdfReq && (int) $brochure['attach_pdf'] === 1
            ? __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $brochure['pdf_path'])
            : '';
        $result = pcvc_brochure_send_email([
            'to_email'     => $email,
            'to_name'      => $name,
            'title'        => (string) $brochure['title'],
            'description'  => (string) ($brochure['description'] ?? ''),
            'html_content' => (string) ($brochure['html_content'] ?? ''),
            'share_url'    => $shareUrl,
            'pdf_path'     => $pdfAbs,
            'pdf_filename' => (string) ($brochure['pdf_filename'] ?? ''),
        ]);

        $shareToken = bin2hex(random_bytes(8));
        $channel    = 'email';
        $notes      = $result['sent'] ? 'sent via SMTP' : ('failed: ' . substr((string) $result['error'], 0, 200));
        $phone      = '';
        $sql = 'INSERT INTO marketing_brochure_shares
                  (brochure_id, share_token, recipient_name, recipient_phone, recipient_email,
                   channel, matched_table, matched_row_id, is_new_contact, shared_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $ins = $conn->prepare($sql);
        $ins->bind_param('issssssiiis', $brochureId, $shareToken, $name, $phone, $email, $channel, $matchTbl, $matchId, $isNew, $adminId, $notes);
        $ins->execute();
        $ins->close();
        if ($result['sent']) {
            $conn->query("UPDATE marketing_brochures SET share_count = share_count + 1 WHERE id = $brochureId");
        }

        pcvc_brochure_respond([
            'ok'        => (bool) $result['sent'],
            'sent'      => (bool) $result['sent'],
            'error'     => $result['error'] ?? '',
            'share_url' => $shareUrl,
        ]);
        break;

    // -------------------------------------------------------------
    case 'share_brochure':
        if (!pcvc_csrf_validate_post()) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid security token.'], 403);
        }
        $brochureId = (int) ($_POST['brochure_id'] ?? 0);
        $channel    = strtolower(trim((string) ($_POST['channel'] ?? 'copy')));
        $name       = trim((string) ($_POST['name'] ?? ''));
        $phone      = trim((string) ($_POST['phone'] ?? ''));
        $email      = trim((string) ($_POST['email'] ?? ''));
        $notes      = trim((string) ($_POST['notes'] ?? ''));
        $matchTable = trim((string) ($_POST['matched_table'] ?? ''));
        $matchId    = (int) ($_POST['matched_row_id'] ?? 0);
        $isNew      = (int) (!empty($_POST['is_new_contact']) ? 1 : 0);

        if ($brochureId <= 0) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Invalid brochure.'], 400);
        }
        if (!in_array($channel, ['copy', 'whatsapp', 'email', 'sms', 'other'], true)) {
            $channel = 'copy';
        }
        // Quick share from the brochure card (no specific recipient) is allowed:
        // phone/email are only required when a "Send to customer" payload is built.
        $quickShare = ($notes === 'quick-share');
        if (!$quickShare) {
            if ($channel === 'whatsapp' && $phone === '') {
                pcvc_brochure_respond(['ok' => false, 'error' => 'Phone is required for WhatsApp.'], 400);
            }
            if ($channel === 'email' && $email === '') {
                pcvc_brochure_respond(['ok' => false, 'error' => 'Email is required for email channel.'], 400);
            }
        }

        $stmt = $conn->prepare('SELECT id, slug, title FROM marketing_brochures WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $brochureId);
        $stmt->execute();
        $brochure = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$brochure) {
            pcvc_brochure_respond(['ok' => false, 'error' => 'Brochure not found.'], 404);
        }

        $shareUrl = pcvc_brochure_public_url((string) $brochure['slug']);
        $token    = bin2hex(random_bytes(8));

        if ($isNew && $phone !== '') {
            $digits  = preg_replace('/\D+/', '', $phone) ?? '';
            $segment = 'customer';
            $check   = $conn->prepare('SELECT id FROM contacts WHERE phone = ? LIMIT 1');
            $check->bind_param('s', $digits);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                $ins = $conn->prepare('INSERT INTO contacts (name, phone, segment, opted_in, status) VALUES (?, ?, ?, 1, "active")');
                $cleanName = $name !== '' ? $name : 'Brochure Contact';
                $ins->bind_param('sss', $cleanName, $digits, $segment);
                @$ins->execute();
                $ins->close();
            }
            $check->close();
        }

        $sql = 'INSERT INTO marketing_brochure_shares
                  (brochure_id, share_token, recipient_name, recipient_phone, recipient_email,
                   channel, matched_table, matched_row_id, is_new_contact, shared_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $ins  = $conn->prepare($sql);
        $ins->bind_param(
            'issssssiiis',
            $brochureId,
            $token,
            $name,
            $phone,
            $email,
            $channel,
            $matchTable,
            $matchId,
            $isNew,
            $adminId,
            $notes
        );
        $ins->execute();
        $shareId = (int) $conn->insert_id;
        $ins->close();

        $conn->query("UPDATE marketing_brochures SET share_count = share_count + 1 WHERE id = $brochureId");

        $dcc       = trim(xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE'));
        $defaultCc = $dcc !== '' ? $dcc : null;
        $waE164    = $phone !== '' ? xander_format_phone_for_whatsapp_e164($phone, $defaultCc) : null;

        $msgLines  = [];
        $greeting  = $name !== '' ? ('Hello ' . $name . ',') : 'Hello,';
        $msgLines[] = $greeting;
        $msgLines[] = '';
        $msgLines[] = 'Please find our brochure: ' . (string) $brochure['title'];
        $msgLines[] = $shareUrl;
        $msgLines[] = '';
        $msgLines[] = 'Reach out any time if you have questions.';
        $msgLines[] = '— ScholarSync Global';
        $waMessage  = implode("\n", $msgLines);

        $whatsappLink = $waE164 ? ('https://wa.me/' . rawurlencode($waE164) . '?text=' . rawurlencode($waMessage)) : null;
        $emailLink    = $email !== ''
            ? 'mailto:' . rawurlencode($email)
                . '?subject=' . rawurlencode((string) $brochure['title'])
                . '&body=' . rawurlencode($waMessage)
            : null;

        pcvc_brochure_respond([
            'ok'            => true,
            'share_id'      => $shareId,
            'share_url'     => $shareUrl,
            'whatsapp_url'  => $whatsappLink,
            'email_url'     => $emailLink,
            'message'       => $waMessage,
        ]);
        break;

    // -------------------------------------------------------------
    default:
        pcvc_brochure_respond(['ok' => false, 'error' => 'Unknown action.'], 400);
}
