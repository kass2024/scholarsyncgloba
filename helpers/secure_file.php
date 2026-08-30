<?php
declare(strict_types=1);

/**
 * Secure upload access — all files under uploads/ must be served via download.php
 * after authentication, never via direct /uploads/ URLs.
 */

function pcvc_secure_file_project_root(): string
{
    return dirname(__DIR__);
}

/** Normalize a stored path to a relative uploads/... path. */
function pcvc_norm_upload_rel_path(string $path): string
{
    $path = str_replace("\0", '', trim($path));
    $path = trim($path, "\"' \t\r\n");
    $path = str_replace('\\', '/', $path);

    $pos = stripos($path, 'uploads/');
    if ($pos !== false) {
        $path = substr($path, $pos);
    }

    return ltrim($path, '/');
}

/** Allowed realpath bases under the project uploads directory. */
function pcvc_secure_file_allowed_bases(): array
{
    static $bases = null;
    if ($bases !== null) {
        return $bases;
    }

    $root = realpath(pcvc_secure_file_project_root() . '/uploads');
    if ($root === false) {
        $bases = [];
        return $bases;
    }

    $bases = [$root];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir()) {
            $real = realpath($item->getPathname());
            if ($real !== false) {
                $bases[] = $real;
            }
        }
    }

    $bases = array_values(array_unique(array_filter($bases)));
    return $bases;
}

/** Resolve a relative uploads path to an absolute file path, or null if invalid. */
function pcvc_secure_file_resolve(string $relPath): ?string
{
    $relPath = pcvc_norm_upload_rel_path($relPath);
    if ($relPath === '' || stripos($relPath, 'uploads/') !== 0) {
        return null;
    }

    $full = realpath(pcvc_secure_file_project_root() . '/' . $relPath);
    if ($full === false || !is_file($full)) {
        return null;
    }

    foreach (pcvc_secure_file_allowed_bases() as $base) {
        if ($base && str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
            return $full;
        }
        if ($base && $full === $base) {
            return $full;
        }
    }

    return null;
}

/** True when an admin, student portal, or in-progress form session is active. */
function pcvc_secure_file_auth_ok(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!empty($_SESSION['admin_id']) || !empty($_SESSION['id'])) {
        return true;
    }
    if (!empty($_SESSION['student_account_id'])) {
        return true;
    }
    // Public form sessions (applicants filling/editing their own forms)
    foreach (['user_id', 'credit_user_id', 'loan_user_id'] as $key) {
        if (!empty($_SESSION[$key])) {
            return true;
        }
    }

    return false;
}

/** Profile photo img src — stored filenames live under uploads/. */
function pcvc_profile_photo_url(?string $filename): string
{
    $filename = trim(str_replace('\\', '/', (string) $filename));
    if ($filename === '' || $filename === 'default_avatar.png') {
        return 'assets/images/default-avatar.svg';
    }
    if (stripos($filename, 'uploads/') === 0) {
        return pcvc_secure_file_url($filename, ['inline' => true]);
    }

    return pcvc_secure_file_url('uploads/' . ltrim($filename, '/'), ['inline' => true]);
}

/** Require admin or student session; 401/redirect otherwise. */
function pcvc_secure_file_require_auth(bool $json = false): void
{
    if (pcvc_secure_file_auth_ok()) {
        return;
    }

    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    header('Location: admin-login.php');
    exit;
}

/** Build a download.php URL for a stored uploads/ path. */
function pcvc_secure_file_url(string $relPath, array $opts = []): string
{
    $relPath = pcvc_norm_upload_rel_path($relPath);
    if ($relPath === '') {
        return '#';
    }

    $url = 'download.php?f=' . rawurlencode(base64_encode($relPath));
    if (!empty($opts['inline'])) {
        $url .= '&inline=1';
    }
    if (!empty($opts['name'])) {
        $url .= '&name=' . rawurlencode((string) $opts['name']);
    }

    return $url;
}

/** View + download button group for admin detail pages. */
function pcvc_secure_file_links_html(string $relPath, string $label = 'Document'): string
{
    $relPath = pcvc_norm_upload_rel_path($relPath);
    if ($relPath === '') {
        return '<div class="text-muted small">' . htmlspecialchars($label) . ': not uploaded</div>';
    }

    if (pcvc_secure_file_resolve($relPath) === null) {
        return '<div class="text-warning small">' . htmlspecialchars($label) . ': file missing on server</div>';
    }

    $viewUrl = htmlspecialchars(pcvc_secure_file_url($relPath, ['inline' => true]), ENT_QUOTES, 'UTF-8');
    $dlUrl   = htmlspecialchars(pcvc_secure_file_url($relPath), ENT_QUOTES, 'UTF-8');

    return '<div class="doc-row border rounded p-2 mb-2 d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2">'
        . '<span class="text-break"><i class="fas fa-file me-2"></i>' . htmlspecialchars($label) . '</span>'
        . '<span class="d-flex flex-wrap gap-1">'
        . '<a href="' . $viewUrl . '" target="_blank" class="btn btn-sm btn-outline-primary flex-fill flex-sm-grow-0"><i class="fas fa-eye"></i> View</a>'
        . '<a href="' . $dlUrl . '" class="btn btn-sm btn-primary flex-fill flex-sm-grow-0"><i class="fas fa-download"></i> Download</a>'
        . '</span></div>';
}

/** Inline JS helper for pages that build document links in JavaScript. */
function pcvc_secure_file_js(): string
{
    return <<<'JS'
function pcvcSecureFileUrl(relPath, inline) {
  if (!relPath) return '#';
  try {
    const enc = btoa(unescape(encodeURIComponent(relPath)));
    let u = 'download.php?f=' + encodeURIComponent(enc);
    if (inline) u += '&inline=1';
    return u;
  } catch (e) {
    return '#';
  }
}
JS;
}
