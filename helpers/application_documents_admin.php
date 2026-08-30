<?php
declare(strict_types=1);

/**
 * Student Application Report — admin document upload/replace + missing-doc notifications.
 */

require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/student_status_notify.php';
require_once __DIR__ . '/marketing_brochure_send.php';
require_once __DIR__ . '/secure_file.php';

/** @return array<string,array{label:string,column:string,multiple:bool}> */
function pcvc_app_document_types(): array
{
    return [
        'degree_transcripts'     => ['label' => 'Degree Transcripts',     'column' => 'degree_transcripts',     'multiple' => true],
        'high_school_degree'     => ['label' => 'High School Degree',     'column' => 'high_school_degree',     'multiple' => false],
        'passport'               => ['label' => 'Passport',               'column' => 'valid_passport',         'multiple' => false],
        'cv_resume'                => ['label' => 'CV / Resume',            'column' => 'cv_resume',              'multiple' => false],
        'personal_statement'       => ['label' => 'Personal Statement',     'column' => 'personal_statement',     'multiple' => false],
        'recommendation_letters'   => ['label' => 'Recommendation Letters', 'column' => 'recommendation_letters', 'multiple' => false],
        'english_certificate'      => ['label' => 'English Certificate',    'column' => 'english_certificate',    'multiple' => false],
        'birth_certificate'        => ['label' => 'Birth Certificate',      'column' => 'birth_certificate',      'multiple' => false],
        'payment_proof'            => ['label' => 'Payment Proof',          'column' => 'payment_proof',          'multiple' => false],
    ];
}

function pcvc_app_document_type(string $key): ?array
{
    $types = pcvc_app_document_types();
    return $types[$key] ?? null;
}

/** Whether a stored DB value counts as "uploaded". */
function pcvc_app_document_has_value(mixed $raw, bool $multiple): bool
{
    if ($multiple) {
        if (is_array($raw)) {
            foreach ($raw as $p) {
                if (is_string($p) && trim($p) !== '') {
                    return true;
                }
            }
            return false;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $p) {
                    if (is_string($p) && trim($p) !== '') {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    return is_string($raw) && trim($raw) !== '';
}

/**
 * Build documents payload for API (paths + metadata for UI).
 *
 * @return array{items:array<int,array>,definitions:array<string,array>}
 */
function pcvc_app_documents_for_view(array $appRow): array
{
    $items = [];
    foreach (pcvc_app_document_types() as $key => $def) {
        $col = $def['column'];
        $raw = $appRow[$col] ?? null;
        $paths = [];
        $urls  = [];
        if ($def['multiple']) {
            $arr = is_array($raw) ? $raw : json_decode((string) $raw, true);
            if (is_array($arr)) {
                foreach ($arr as $p) {
                    if (is_string($p) && trim($p) !== '') {
                        $paths[] = $p;
                        $urls[]  = pcvc_secure_file_url($p);
                    }
                }
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            $paths[] = $raw;
            $urls[]  = pcvc_secure_file_url($raw);
        }
        $items[] = [
            'key'      => $key,
            'label'    => $def['label'],
            'column'   => $col,
            'multiple' => $def['multiple'],
            'paths'    => $paths,
            'urls'     => $urls,
            'present'  => count($paths) > 0,
        ];
    }
    return ['items' => $items, 'definitions' => pcvc_app_document_types()];
}

/**
 * Upload or replace one document for an application (all logged-in admins).
 *
 * @return array{ok:bool,path?:string,documents?:array,error?:string}
 */
function pcvc_app_replace_document(mysqli $conn, int $applicationId, string $docKey, array $file): array
{
    $def = pcvc_app_document_type($docKey);
    if (!$def) {
        return ['ok' => false, 'error' => 'Unknown document type.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed or no file selected.'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }
    $orig = (string) ($file['name'] ?? 'document');
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
    if (!in_array($ext, $allowed, true)) {
        return ['ok' => false, 'error' => 'Allowed types: PDF, JPG, PNG, WEBP, DOC, DOCX.'];
    }
    if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'File must be 15 MB or smaller.'];
    }

    $stmt = $conn->prepare('SELECT id FROM student_applications WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$exists) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($orig, PATHINFO_FILENAME)) ?: 'document';
    $filename = time() . '_' . $applicationId . '_' . $docKey . '_' . $safeBase . '.' . $ext;
    $absPath  = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp, $absPath)) {
        return ['ok' => false, 'error' => 'Could not save file on server.'];
    }
    $relPath = 'uploads/' . $filename;

    $col = $def['column'];
    if ($def['multiple']) {
        $json = json_encode([$relPath], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sql  = "UPDATE student_applications SET `$col` = ? WHERE id = ? LIMIT 1";
        $u = $conn->prepare($sql);
        $u->bind_param('si', $json, $applicationId);
    } else {
        $sql = "UPDATE student_applications SET `$col` = ? WHERE id = ? LIMIT 1";
        $u = $conn->prepare($sql);
        $u->bind_param('si', $relPath, $applicationId);
    }
    if (!$u || !$u->execute()) {
        @unlink($absPath);
        return ['ok' => false, 'error' => 'Database update failed.'];
    }
    $u->close();

    $stmt2 = $conn->prepare('SELECT * FROM student_applications WHERE id = ? LIMIT 1');
    $stmt2->bind_param('i', $applicationId);
    $stmt2->execute();
    $row = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    $docPayload = $row ? pcvc_app_documents_for_view($row) : ['items' => [], 'definitions' => pcvc_app_document_types()];

    return [
        'ok'        => true,
        'path'      => $relPath,
        'documents' => $docPayload['items'],
    ];
}

/**
 * Notify student about missing documents via WhatsApp template + email.
 *
 * @param array<int,string> $missingKeys
 * @return array{ok:bool,whatsapp:array,email:array,error?:string}
 */
function pcvc_app_notify_missing_documents(
    mysqli $conn,
    int $applicationId,
    array $missingKeys,
    string $customNote = '',
    bool $sendWhatsapp = true,
    bool $sendEmail = true,
    string $overridePhone = '',
    string $overrideEmail = '',
    string $customMessage = ''
): array {
    if (!$sendWhatsapp && !$sendEmail) {
        return ['ok' => false, 'error' => 'Select WhatsApp and/or email.', 'whatsapp' => [], 'email' => []];
    }

    $stmt = $conn->prepare(
        'SELECT id, first_name, last_name, email, area_code, phone_number, emergency_area_code, emergency_phone_number
         FROM student_applications WHERE id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$app) {
        return ['ok' => false, 'error' => 'Application not found.', 'whatsapp' => [], 'email' => []];
    }

    $types = pcvc_app_document_types();
    $labels = [];
    foreach ($missingKeys as $key) {
        $key = (string) $key;
        if (isset($types[$key])) {
            $labels[] = $types[$key]['label'];
        }
    }
    if (!$labels) {
        return ['ok' => false, 'error' => 'Select at least one missing document.', 'whatsapp' => [], 'email' => []];
    }

    $name = trim((string) ($app['first_name'] ?? '') . ' ' . (string) ($app['last_name'] ?? ''));
    if ($name === '') {
        $name = 'Applicant';
    }

    // Resolve recipients (admin-overridden values win over DB values)
    $email = trim($overrideEmail);
    if ($email === '') {
        $email = trim((string) ($app['email'] ?? ''));
    }
    $phone = trim($overridePhone);
    if ($phone === '') {
        $phone = trim((string) ($app['area_code'] ?? '') . (string) ($app['phone_number'] ?? ''));
        if ($phone === '') {
            $phone = trim((string) ($app['emergency_area_code'] ?? '') . (string) ($app['emergency_phone_number'] ?? ''));
        }
    }

    $docList = implode(', ', $labels);
    $portalUrl = pcvc_app_student_portal_url();
    $note = trim($customNote);
    $customMessage = trim($customMessage);

    $waOut = ['sent' => false, 'method' => '', 'error' => '', 'not_on_whatsapp' => false, 'to' => ''];
    $emOut = ['sent' => false, 'error' => ''];

    if ($sendWhatsapp) {
        // Prefer the admin-edited message as the session-window body; fall back to a default.
        $waBody = $customMessage !== ''
            ? $customMessage
            : pcvc_app_missing_docs_whatsapp_session_body($name, $docList, $portalUrl, $note);
        $waOut  = pcvc_app_send_missing_docs_whatsapp($phone, $name, $docList, $portalUrl, $waBody);

        // If the recipient isn't on WhatsApp, do NOT deliver via WhatsApp.
        // Carry this clearly upstream so the UI can show "Skipped — not on WhatsApp".
        if (!empty($waOut['not_on_whatsapp'])) {
            $waOut['sent']   = false;
            $waOut['method'] = 'skipped';
            if (empty($waOut['error'])) {
                $waOut['error'] = 'This number is not on WhatsApp — message not delivered.';
            }
        }
    }

    if ($sendEmail) {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emOut = ['sent' => false, 'error' => 'Student has no valid email on file.'];
        } else {
            $emOut = [
                'sent'  => pcvc_app_send_missing_docs_email($email, $name, $labels, $portalUrl, $note, $customMessage),
                'error' => '',
            ];
            if (!$emOut['sent']) {
                $emOut['error'] = 'Email could not be sent.';
            }
        }
    }

    $anySent = (!empty($waOut['sent'])) || (!empty($emOut['sent']));
    $errs = [];
    if ($sendWhatsapp && empty($waOut['sent']) && !empty($waOut['error'])) {
        $errs[] = 'WhatsApp: ' . $waOut['error'];
    }
    if ($sendEmail && empty($emOut['sent']) && !empty($emOut['error'])) {
        $errs[] = $emOut['error'];
    }

    return [
        'ok'        => $anySent,
        'whatsapp'  => $waOut,
        'email'     => $emOut,
        'error'     => $anySent ? '' : (implode(' ', $errs) ?: 'Notification failed.'),
    ];
}

function pcvc_app_student_portal_url(): string
{
    $base = trim((string) (function_exists('xander_env_get') ? xander_env_get('APP_PUBLIC_URL') : ''));
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api/applications.php')), '/');
        $base   = $scheme . '://' . $host . preg_replace('#/api$#', '', $dir);
    }
    return rtrim($base, '/') . '/student/';
}

function pcvc_app_missing_docs_whatsapp_session_body(string $name, string $docList, string $portalUrl, string $note): string
{
    $hi = $name !== '' ? ('Hello ' . $name . ',') : 'Hello,';
    $msg  = $hi . "\n\n";
    $msg .= "We are reviewing your application and still need the following document(s):\n";
    $msg .= $docList . "\n\n";
    if ($note !== '') {
        $msg .= $note . "\n\n";
    }
    $msg .= "Please upload them here:\n" . $portalUrl . "\n\n";
    $msg .= "Reply to this message if you need help.\n\n";
    $msg .= "— ScholarSync Global";
    return $msg;
}

/**
 * Heuristic — does this WhatsApp error / status mean the number isn't registered on WhatsApp?
 */
function pcvc_app_whatsapp_response_means_not_registered(array $res): bool
{
    $err    = (string) ($res['error']  ?? '');
    $detail = (string) ($res['detail'] ?? '');
    $blob   = $err . ' ' . $detail;

    // Meta error codes that indicate "recipient is not on WhatsApp"
    foreach (['131026', '131045', '131051', '131047'] as $code) {
        if (strpos($detail, $code) !== false) {
            return true;
        }
    }
    if (stripos($blob, 'not on WhatsApp') !== false) {
        return true;
    }
    if (stripos($blob, 'not a WhatsApp user') !== false) {
        return true;
    }
    if (stripos($blob, 'recipient phone number not in allowed list') !== false) {
        return true;
    }
    return false;
}

/**
 * WhatsApp Cloud API — template first (outside 24h window).
 *
 * @return array{sent:bool,method:string,error:string,detail:string,not_on_whatsapp?:bool,to?:string}
 */
function pcvc_app_send_missing_docs_whatsapp(string $phoneRaw, string $name, string $docList, string $portalUrl, string $sessionBody): array
{
    $token = trim(xander_env_get('WHATSAPP_ACCESS_TOKEN'));
    if ($token === '') {
        $token = trim(xander_env_get('WHATSAPP_TOKEN'));
    }
    $phoneId = trim(xander_env_get('WHATSAPP_PHONE_NUMBER_ID'));
    $empty   = ['sent' => false, 'method' => '', 'error' => '', 'detail' => '', 'not_on_whatsapp' => false, 'to' => ''];
    if ($token === '' || $phoneId === '') {
        $empty['error'] = 'WhatsApp is not configured.';
        return $empty;
    }

    $dcc       = trim(xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE'));
    $defaultCc = $dcc !== '' ? $dcc : null;
    $to        = xander_format_phone_for_whatsapp_e164($phoneRaw, $defaultCc);
    if ($to === null || $to === '') {
        $empty['error'] = 'Student phone number is missing or invalid.';
        return $empty;
    }
    $empty['to'] = $to;

    $version = trim(xander_env_get('META_GRAPH_VERSION')) ?: 'v19.0';
    $url     = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneId) . '/messages';

    $tplName = trim(xander_env_get('WHATSAPP_MISSING_DOCS_TEMPLATE_NAME'));
    if ($tplName === '') {
        $tplName = 'pcvc_missing_documents';
    }
    $tplLang = trim(xander_env_get('WHATSAPP_MISSING_DOCS_TEMPLATE_LANG')) ?: 'en';

    $bodyTexts = [
        pcvc_brochure_wa_sanitize_param($name),
        pcvc_brochure_wa_sanitize_param($docList),
        pcvc_brochure_wa_sanitize_param($portalUrl),
    ];

    $res = xander_whatsapp_send_template_or_session(
        $to,
        $url,
        $token,
        $tplName,
        $tplLang,
        3,
        $bodyTexts,
        $sessionBody
    );

    $sent      = (bool) ($res['sent'] ?? false);
    $method    = (string) ($res['method'] ?? '');
    $errorMsg  = (string) ($res['error']  ?? '');
    $detail    = (string) ($res['detail'] ?? '');
    $notOnWa   = false;
    if (!$sent) {
        $notOnWa = pcvc_app_whatsapp_response_means_not_registered([
            'error'  => $errorMsg,
            'detail' => $detail,
        ]);
        if ($notOnWa && $errorMsg === '') {
            $errorMsg = 'This number is not on WhatsApp — message not delivered.';
        }
    }

    return [
        'sent'            => $sent,
        'method'          => $method,
        'error'           => $errorMsg,
        'detail'          => $detail,
        'not_on_whatsapp' => $notOnWa,
        'to'              => $to,
    ];
}

/**
 * @param array<int,string> $labels
 */
function pcvc_app_send_missing_docs_email(
    string $toEmail,
    string $name,
    array $labels,
    string $portalUrl,
    string $note,
    string $customMessage = ''
): bool {
    try {
        require_once __DIR__ . '/mailer.php';
        $mail = app_mailer();
        $mail->addAddress($toEmail, $name ?: $toEmail);
        $mail->Subject = 'Documents needed for your application — ScholarSync Global';
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        $customMessage = trim($customMessage);
        if ($customMessage !== '') {
            // Use admin-edited message as the main body; keep the upload CTA + link.
            $bodyHtml = nl2br(htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8'));
            $mail->Body = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:600px;margin:0 auto;color:#1e293b;line-height:1.6;">'
                . '<div>' . $bodyHtml . '</div>'
                . '<p style="margin-top:24px;"><a href="' . htmlspecialchars($portalUrl, ENT_QUOTES) . '" style="display:inline-block;background:#427431;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:700;">Upload documents</a></p>'
                . '<p style="color:#64748b;font-size:13px;">Or copy this link: ' . htmlspecialchars($portalUrl, ENT_QUOTES) . '</p>'
                . '</div>';
            $mail->AltBody = $customMessage . "\n\nUpload: {$portalUrl}\n";
        } else {
            $listHtml = '<ul style="margin:12px 0;padding-left:20px;line-height:1.6;">';
            foreach ($labels as $lb) {
                $listHtml .= '<li>' . htmlspecialchars($lb, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $listHtml .= '</ul>';
            $noteHtml = $note !== ''
                ? '<p style="margin:16px 0;padding:12px;background:#f0f9ff;border-left:4px solid #3661B9;border-radius:6px;">'
                    . nl2br(htmlspecialchars($note, ENT_QUOTES, 'UTF-8')) . '</p>'
                : '';
            $mail->Body = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:600px;margin:0 auto;color:#1e293b;">'
                . '<p>Dear ' . $safeName . ',</p>'
                . '<p>We are reviewing your application and still need the following document(s):</p>'
                . $listHtml
                . $noteHtml
                . '<p><a href="' . htmlspecialchars($portalUrl, ENT_QUOTES) . '" style="display:inline-block;background:#427431;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:700;">Upload documents</a></p>'
                . '<p style="color:#64748b;font-size:13px;">Or copy this link: ' . htmlspecialchars($portalUrl, ENT_QUOTES) . '</p>'
                . '<p>Thank you,<br><strong>ScholarSync Global</strong></p></div>';
            $mail->AltBody = "Dear {$name},\n\nWe need: " . implode(', ', $labels) . "\n\n{$note}\n\nUpload: {$portalUrl}\n";
        }

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('[missing_docs] email: ' . $e->getMessage());
        return false;
    }
}
