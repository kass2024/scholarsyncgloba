<?php
/**
 * Staff / admin profile photo upload helpers.
 * Accepts common image formats (and any MIME image/*) with a practical size limit.
 */

/** Max profile photo size in bytes (15MB). */
function pcvc_profile_photo_max_bytes(): int
{
    return 15 * 1024 * 1024;
}

/** @return string[] */
function pcvc_profile_photo_allowed_extensions(): array
{
    return [
        'jpg', 'jpeg', 'jpe', 'jfif', 'pjpeg', 'pjp',
        'png', 'gif', 'webp', 'bmp', 'dib',
        'tif', 'tiff',
        'heic', 'heif',
        'avif', 'apng',
        'ico', 'svg',
    ];
}

function pcvc_profile_photo_upload_error_message(int $errorCode): string
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Image too large for server limits. Try a smaller file (max 15MB).';
        case UPLOAD_ERR_PARTIAL:
            return 'Image upload was incomplete. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No image was selected.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Server temp folder is missing.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Server could not write the uploaded image.';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload blocked by a PHP extension.';
        default:
            return 'Image upload failed.';
    }
}

/**
 * Detect whether an uploaded file is an acceptable profile picture.
 */
function pcvc_profile_photo_is_allowed(string $originalName, string $tmpPath, string &$extOut = ''): bool
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    // Normalize JPEG variants
    if (in_array($ext, ['jpe', 'jfif', 'pjpeg', 'pjp'], true)) {
        $ext = 'jpg';
    }
    $extOut = $ext;

    if ($ext !== '' && in_array($ext, pcvc_profile_photo_allowed_extensions(), true)) {
        return true;
    }

    // Accept any real image/* MIME even if extension is unusual/missing
    $mime = '';
    if (is_file($tmpPath) && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
        }
    }
    if ($mime === '' && function_exists('mime_content_type') && is_file($tmpPath)) {
        $mime = (string) @mime_content_type($tmpPath);
    }
    if ($mime !== '' && stripos($mime, 'image/') === 0) {
        if ($extOut === '') {
            $map = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/bmp' => 'bmp',
                'image/tiff' => 'tif',
                'image/heic' => 'heic',
                'image/heif' => 'heif',
                'image/avif' => 'avif',
                'image/svg+xml' => 'svg',
            ];
            $extOut = $map[strtolower($mime)] ?? 'img';
        }
        return true;
    }

    // Last resort: getimagesize recognizes many formats
    if (is_file($tmpPath)) {
        $info = @getimagesize($tmpPath);
        if (is_array($info) && !empty($info[0]) && !empty($info[1])) {
            if ($extOut === '') {
                $extOut = 'jpg';
            }
            return true;
        }
    }

    return false;
}

/**
 * Save an uploaded profile photo into /uploads and return the stored filename.
 *
 * @param array $file One entry from $_FILES (e.g. $_FILES['profile_photo'])
 * @return array{ok:bool, filename?:string, error?:string}
 */
function pcvc_profile_photo_store(array $file, ?string $uploadDir = null): array
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => pcvc_profile_photo_upload_error_message($errorCode)];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $name = (string) ($file['name'] ?? '');
    $size = (int) ($file['size'] ?? 0);

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Invalid upload. Please try again.'];
    }

    if ($size <= 0 || $size > pcvc_profile_photo_max_bytes()) {
        return ['ok' => false, 'error' => 'Image too large (max 15MB).'];
    }

    $ext = '';
    if (!pcvc_profile_photo_is_allowed($name, $tmp, $ext)) {
        return ['ok' => false, 'error' => 'Invalid image type. Please upload a picture file.'];
    }

    $ext = preg_replace('/[^a-z0-9]/', '', strtolower($ext)) ?: 'jpg';

    $uploadDir = $uploadDir ?: (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['ok' => false, 'error' => 'Upload folder is missing or not writable.'];
    }
    if (!is_writable($uploadDir)) {
        @chmod($uploadDir, 0775);
        if (!is_writable($uploadDir)) {
            return ['ok' => false, 'error' => 'Upload folder is not writable.'];
        }
    }

    $photoName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $target = rtrim($uploadDir, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . $photoName;

    $saved = @move_uploaded_file($tmp, $target);
    if (!$saved) {
        // Fallback for some Windows/XAMPP setups where move_uploaded_file fails
        $saved = @copy($tmp, $target);
        if ($saved) {
            @unlink($tmp);
        }
    }

    if (!$saved || !is_file($target)) {
        return ['ok' => false, 'error' => 'Failed to upload image. Check uploads folder permissions.'];
    }

    @chmod($target, 0644);

    return ['ok' => true, 'filename' => $photoName];
}
