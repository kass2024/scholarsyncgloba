<?php
declare(strict_types=1);

/**
 * ============================================================
 * STAFF WARNING LETTER — helper
 * ------------------------------------------------------------
 *  - Auto schema for `staff_warning_letters`
 *  - PDF generator with header.png + footer.png from /cards
 *  - Email (PHPMailer via app_mailer()) and WhatsApp (Cloud API
 *    template + document) delivery.
 * ============================================================
 */

require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/student_status_notify.php'; // xander_whatsapp_* helpers
require_once __DIR__ . '/marketing_brochure_send.php'; // pcvc_brochure_wa_sanitize_param

use Dompdf\Dompdf;
use Dompdf\Options;

/* ============================================================
   SCHEMA BOOTSTRAP
============================================================ */
function pcvc_swl_ensure_schema(mysqli $conn): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS staff_warning_letters (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_id        INT UNSIGNED NOT NULL,
            staff_name      VARCHAR(255) DEFAULT NULL,
            staff_email     VARCHAR(255) DEFAULT NULL,
            staff_phone     VARCHAR(64)  DEFAULT NULL,
            subject         VARCHAR(255) NOT NULL,
            content_html    MEDIUMTEXT   NOT NULL,
            pdf_path        VARCHAR(500) DEFAULT NULL,
            email_sent      TINYINT(1)   NOT NULL DEFAULT 0,
            email_error     VARCHAR(500) DEFAULT NULL,
            whatsapp_sent   TINYINT(1)   NOT NULL DEFAULT 0,
            whatsapp_method VARCHAR(32)  DEFAULT NULL,
            whatsapp_error  VARCHAR(500) DEFAULT NULL,
            created_by      INT UNSIGNED DEFAULT NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_staff_id (staff_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    @$conn->query($sql);
}

/* ============================================================
   PUBLIC URL FOR THIS APP (used to make PDF link for WhatsApp)
============================================================ */
function pcvc_swl_public_base_url(): string
{
    $env = trim((string) (function_exists('xander_env_get') ? xander_env_get('APP_PUBLIC_URL') : ''));
    if ($env !== '') {
        return rtrim($env, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    // Strip /api or /helpers tail if present (we want the app root)
    $dir    = preg_replace('#/(api|helpers)$#', '', $dir);
    return rtrim($scheme . '://' . $host . $dir, '/');
}

/* ============================================================
   LOAD STAFF ROW
============================================================ */
function pcvc_swl_load_staff(mysqli $conn, int $staffId): ?array
{
    $stmt = $conn->prepare("
        SELECT id, full_name, first_name, last_name, email, phone_number, position
        FROM admins WHERE id = ? LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/* ============================================================
   IMG → DATA URI (so Dompdf inlines images, no remote fetch)
============================================================ */
function pcvc_swl_img_data_uri(string $absPath): string
{
    if (!is_file($absPath)) return '';
    $bin = @file_get_contents($absPath);
    if ($bin === false) return '';
    // Pick MIME from extension first (reliable + no dependency on mime_magic).
    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    $byExt = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
    ];
    $mime = $byExt[$ext] ?? null;
    if ($mime === null && function_exists('mime_content_type')) {
        $detected = @mime_content_type($absPath);
        if ($detected) $mime = $detected;
    }
    if ($mime === null) $mime = 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode($bin);
}

/* ============================================================
   BUILD HTML FOR PDF (letterhead + footer)
============================================================ */
function pcvc_swl_build_html(array $staff, string $subject, string $contentHtml, string $referenceCode): string
{
    $cardsDir  = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cards' . DIRECTORY_SEPARATOR;
    $headerUri = pcvc_swl_img_data_uri($cardsDir . 'header.png');
    $footerUri = pcvc_swl_img_data_uri($cardsDir . 'footer.png');
    $signatureUri = pcvc_swl_img_data_uri($cardsDir . 'signature-mac.jpeg');
    if ($signatureUri === '') {
        $signatureUri = pcvc_swl_img_data_uri($cardsDir . 'signature-mac.jpg');
    }
    if ($signatureUri === '') {
        $signatureUri = pcvc_swl_img_data_uri($cardsDir . 'signature-mac.png');
    }
    $name      = htmlspecialchars(trim(($staff['full_name'] ?? '') ?: trim(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? ''))), ENT_QUOTES, 'UTF-8');
    $position  = htmlspecialchars((string) ($staff['position'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email     = htmlspecialchars((string) ($staff['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $subjectH  = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $refH      = htmlspecialchars($referenceCode, ENT_QUOTES, 'UTF-8');
    $issuedOn  = date('F j, Y');

    // Allow only safe HTML the editor produces
    $bodyHtml  = $contentHtml;

    $css = <<<'CSS'
        @page { margin: 130px 35px 70px 35px; size: A4 portrait; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 11pt; color: #1f2937; line-height: 1.5; }
        header { position: fixed; top: -120px; left: 0; right: 0; height: 110px; text-align: center; }
        header img { width: 100%; max-height: 110px; }
        footer { position: fixed; bottom: -55px; left: 0; right: 0; height: 50px; text-align: center; }
        footer img { width: 100%; max-height: 45px; }
        .meta { margin-bottom: 14px; }
        .meta-row { margin: 1px 0; }
        .meta-label { color: #6b7280; }
        .ref { float: right; color: #6b7280; font-size: 9.5pt; }
        h1.title {
            font-size: 15pt; margin: 6px 0 12px 0; color: #b91c1c; text-transform: uppercase;
            letter-spacing: 0.04em; text-align: center; border-bottom: 2px solid #b91c1c; padding-bottom: 6px;
        }
        .recipient {
            background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;
            padding:8px 12px; margin-bottom:12px; font-size: 10.5pt;
        }
        .recipient .name { font-weight: 700; }
        .body { text-align: justify; }
        .body p { margin: 0 0 8px; }
        .body strong { color: #111827; }

        /* Signature stays as one unbreakable unit */
        .signature-block { margin-top: 22px; page-break-inside: avoid; }
        .signature-inner { display: inline-block; min-width: 240px; }
        .signature-img { display: block; height: 60px; margin: 4px 0 -4px 0; }
        .signature-line { border-bottom: 1px solid #111; padding-top: 4px; }
        .signature-name { font-weight: 700; font-size: 11.5pt; padding-top: 4px; }
        .signature-title { font-size: 10pt; color: #374151; margin-top: 2px; line-height: 1.4; }
        .signature-org { font-size: 9.5pt; color: #6b7280; margin-top: 1px; }
    CSS;

    ob_start();
    ?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><style><?= $css ?></style></head>
<body>

<?php if ($headerUri): ?>
<header><img src="<?= $headerUri ?>" alt="Letterhead"></header>
<?php endif; ?>

<?php if ($footerUri): ?>
<footer><img src="<?= $footerUri ?>" alt="Footer"></footer>
<?php endif; ?>

<div class="meta">
    <span class="ref">Ref: <?= $refH ?></span>
    <div class="meta-row"><span class="meta-label">Date:</span> <?= $issuedOn ?></div>
</div>

<h1 class="title">Warning Letter</h1>

<div class="recipient">
    <div class="meta-row"><span class="meta-label">To:</span> <span class="name"><?= $name ?: '[Staff]' ?></span></div>
    <?php if ($position !== ''): ?>
    <div class="meta-row"><span class="meta-label">Position:</span> <?= $position ?></div>
    <?php endif; ?>
    <?php if ($email !== ''): ?>
    <div class="meta-row"><span class="meta-label">Email:</span> <?= $email ?></div>
    <?php endif; ?>
    <div class="meta-row"><span class="meta-label">Subject:</span> <strong><?= $subjectH ?></strong></div>
</div>

<div class="body">
    <?= $bodyHtml ?>
</div>

<div class="signature-block">
    <p style="margin:0 0 4px 0;">Yours sincerely,</p>
    <div class="signature-inner">
        <?php if ($signatureUri !== ''): ?>
        <img src="<?= $signatureUri ?>" alt="Signature" class="signature-img">
        <?php endif; ?>
        <div class="signature-line"></div>
        <div class="signature-name">Prof. Marc-Logan</div>
        <div class="signature-title">Company Advisor and Shareholder</div>
        <div class="signature-org">ScholarSync Global Co. Ltd.</div>
    </div>
</div>

</body>
</html>
    <?php
    return (string) ob_get_clean();
}

/* ============================================================
   RENDER PDF — returns ['path' => relative, 'abs' => absolute, 'filename' => …]
============================================================ */
function pcvc_swl_render_pdf(string $html, int $staffId, string $referenceCode): array
{
    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'warnings';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    $safeRef  = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $referenceCode) ?: 'WL';
    $filename = 'warning_' . $staffId . '_' . $safeRef . '.pdf';
    $absPath  = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    $relPath  = 'uploads/warnings/' . $filename;

    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $options = new Options([
        'isRemoteEnabled' => true,
        'isHtml5ParserEnabled' => true,
        'defaultFont' => 'DejaVu Sans',
    ]);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    file_put_contents($absPath, $dompdf->output());

    return ['abs' => $absPath, 'path' => $relPath, 'filename' => $filename];
}

/* ============================================================
   CC DEFAULTS (override via .env)
============================================================ */
function pcvc_swl_cc_email(): string
{
    $v = trim((string) xander_env_get('STAFF_WARNING_LETTER_CC_EMAIL'));
    return $v !== '' ? $v : 'infos@scholarsyncglobal.ca';
}

function pcvc_swl_cc_whatsapp_raw(): string
{
    $v = trim((string) xander_env_get('STAFF_WARNING_LETTER_CC_WHATSAPP'));
    return $v !== '' ? $v : '+1 (438) 290-6688';
}

/* ============================================================
   EMAIL WITH PDF ATTACHMENT
============================================================ */
function pcvc_swl_send_email(string $toEmail, string $toName, string $subject, string $contentHtml, string $pdfAbsPath, string $pdfFilename): array
{
    try {
        require_once __DIR__ . '/mailer.php';
        $mail = app_mailer();
        $mail->addAddress($toEmail, $toName ?: $toEmail);
        $ccEmail = pcvc_swl_cc_email();
        if ($ccEmail !== '' && filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->addCC($ccEmail, 'ScholarSync Global');
        }
        $mail->Subject = 'Warning Letter — ' . $subject;
        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeSubj = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');

        $mail->Body = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:640px;margin:0 auto;color:#1e293b;line-height:1.6;">'
            . '<p>Dear ' . ($safeName ?: 'Colleague') . ',</p>'
            . '<p>Please find attached an official warning letter regarding: <strong>' . $safeSubj . '</strong>.</p>'
            . '<p>Kindly read the attached letter carefully. If you have any questions, you may reach out directly.</p>'
            . '<p>Regards,<br><strong>Prof. Marc-Logan</strong><br>Company Advisor and Shareholder<br>ScholarSync Global Co. Ltd.</p>'
            . '</div>';
        $mail->AltBody = "Dear {$toName},\n\nAttached is an official warning letter regarding: {$subject}.\n\nProf. Marc-Logan\nCompany Advisor and Shareholder\nScholarSync Global Co. Ltd.";

        if (is_file($pdfAbsPath)) {
            $mail->addAttachment($pdfAbsPath, $pdfFilename);
        }

        $mail->send();
        return ['sent' => true, 'error' => ''];
    } catch (Throwable $e) {
        error_log('[warning_letter] email: ' . $e->getMessage());
        return ['sent' => false, 'error' => $e->getMessage()];
    }
}

/* ============================================================
   UPLOAD PDF TO META MEDIA ENDPOINT — returns media_id or ''
   Most reliable way to attach a document; no need for the file
   to be publicly reachable.
============================================================ */
function pcvc_swl_whatsapp_upload_media(string $phoneId, string $token, string $version, string $pdfAbsPath, string $filename): array
{
    $out = ['id' => '', 'http' => 0, 'body' => ''];
    if (!is_file($pdfAbsPath)) {
        $out['body'] = 'PDF file not found: ' . $pdfAbsPath;
        error_log('[warning_letter] media upload: ' . $out['body']);
        return $out;
    }
    $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneId) . '/media';

    $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $filename) ?: 'warning_letter.pdf';
    $cfile = new CURLFile($pdfAbsPath, 'application/pdf', $safeName);
    $fields = [
        'messaging_product' => 'whatsapp',
        'type'              => 'application/pdf',
        'file'              => $cfile,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields,
    ]);
    $body  = (string) curl_exec($ch);
    $http  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err   = curl_error($ch);
    curl_close($ch);

    $out['http'] = $http;
    $out['body'] = $body !== '' ? $body : $err;
    error_log('[warning_letter] media upload HTTP ' . $http . ' body: ' . $body);

    if ($http >= 200 && $http < 300) {
        $j = json_decode($body, true);
        if (is_array($j) && !empty($j['id'])) {
            $out['id'] = (string) $j['id'];
        }
    }
    return $out;
}

function pcvc_swl_whatsapp_template_lang_codes(string $primary): array
{
    $primary = trim($primary) ?: 'en';
    $codes   = [$primary];
    foreach (['en', 'en_US', 'en_GB'] as $c) {
        if (!in_array($c, $codes, true)) {
            $codes[] = $c;
        }
    }
    return $codes;
}

function pcvc_swl_whatsapp_extract_error_message(array $res): string
{
    $err = xander_whatsapp_extract_error($res['json'] ?? null);
    return (string) ($err['message'] ?? ('HTTP ' . ($res['http'] ?? 0)));
}

function pcvc_swl_whatsapp_is_language_error(array $res): bool
{
    $msg = strtolower(pcvc_swl_whatsapp_extract_error_message($res));
    return str_contains($msg, 'language') || str_contains($msg, 'locale') || str_contains($msg, 'translation');
}

function pcvc_swl_whatsapp_is_template_structure_error(array $res): bool
{
    $errStruct = xander_whatsapp_extract_error($res['json'] ?? null);
    $errMsg    = strtolower((string) ($errStruct['message'] ?? ''));
    $errCode   = (int) ($errStruct['code'] ?? 0);
    return $errCode === 132000 || $errCode === 132001 || $errCode === 132012 || $errCode === 132015 ||
           str_contains($errMsg, 'parameter') || str_contains($errMsg, 'header') ||
           str_contains($errMsg, 'components') || str_contains($errMsg, 'template');
}

/* ============================================================
   WHATSAPP — standalone document message (fallback only)
============================================================ */
function pcvc_swl_whatsapp_send_document_message(
    string $to,
    string $messagesUrl,
    string $token,
    string $mediaId,
    string $docFilename,
    string $pdfPublicUrl,
    string $caption
): array {
    $doc = [];
    if ($mediaId !== '') {
        $doc['id'] = $mediaId;
    } elseif ($pdfPublicUrl !== '' && preg_match('#^https://#i', $pdfPublicUrl)) {
        $doc['link'] = $pdfPublicUrl;
    } else {
        return ['sent' => false, 'error' => 'No media id or public HTTPS PDF URL for document message.'];
    }
    $doc['filename'] = $docFilename;
    if ($caption !== '') {
        $doc['caption'] = pcvc_brochure_wa_sanitize_param($caption);
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
        'type'              => 'document',
        'document'          => $doc,
    ];
    $res = xander_whatsapp_graph_post($messagesUrl, $token, $payload);
    error_log('[warning_letter] wa document to=' . $to . ' HTTP ' . $res['http'] . ' body: ' . $res['body']);
    $ok = ($res['http'] >= 200 && $res['http'] < 300 && xander_whatsapp_response_has_message_id($res['json']));
    if ($ok) {
        return ['sent' => true, 'error' => ''];
    }
    $err = xander_whatsapp_extract_error($res['json']);
    return ['sent' => false, 'error' => (string) ($err['message'] ?? 'Document message failed (HTTP ' . $res['http'] . ')')];
}

/* ============================================================
   WHATSAPP — text template, then PDF document (one recipient)
============================================================ */
function pcvc_swl_whatsapp_deliver_template_to(
    string $to,
    string $staffName,
    string $subject,
    string $referenceCode,
    string $mediaId,
    string $docFilename,
    string $url,
    string $token,
    string $tplName,
    string $tplLang,
    array $bodyParams,
    string $pdfPublicUrl = ''
): array {
    $result = [
        'sent' => false, 'method' => '', 'error' => '', 'not_on_whatsapp' => false,
        'to' => $to, 'pdf_attached' => false, 'detail' => '',
    ];

    $pdfHttps = preg_match('#^https://#i', $pdfPublicUrl) === 1;
    $canSendPdf = $mediaId !== '' || $pdfHttps;

    $tplOk = static function (array $res): bool {
        return ($res['http'] >= 200 && $res['http'] < 300 && xander_whatsapp_response_has_message_id($res['json']));
    };

    $waNotRegistered = static function (array $res): bool {
        $errStruct = xander_whatsapp_extract_error($res['json']);
        $errCode   = (int) ($errStruct['code']    ?? 0);
        $errSub    = (int) ($errStruct['subcode'] ?? 0);
        return $errCode === 131026 || $errSub === 131026 ||
               $errCode === 131045 || $errSub === 131045 ||
               $errCode === 131051 || $errSub === 131051;
    };

    $sendTemplate = static function (array $components, string $lang) use (
        $url, $token, $to, $tplName
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'       => $tplName,
                'language'   => ['code' => $lang],
                'components' => $components,
            ],
        ];
        $res = xander_whatsapp_graph_post($url, $token, $payload);
        error_log('[warning_letter] wa tpl to=' . $to . ' lang=' . $lang . ' HTTP ' . $res['http'] . ' body: ' . $res['body']);
        return $res;
    };

    $attachPdfFollowUp = function () use (
        &$result, $to, $url, $token, $mediaId, $docFilename, $pdfPublicUrl, $canSendPdf, $subject, $referenceCode
    ): void {
        if (!$canSendPdf) {
            $result['error'] = 'Warning text sent. PDF could not be uploaded — set APP_PUBLIC_URL to your HTTPS site for PDF delivery.';
            return;
        }
        $caption = 'Warning letter — ' . $subject . ' (Ref: ' . $referenceCode . ')';
        usleep(600000);
        $docOut = pcvc_swl_whatsapp_send_document_message(
            $to, $url, $token, $mediaId, $docFilename, $pdfPublicUrl, $caption
        );
        if (!empty($docOut['sent'])) {
            $result['pdf_attached'] = true;
            $result['method']       = 'text_then_pdf';
            return;
        }
        $result['error'] = 'Warning text sent; PDF failed: ' . ($docOut['error'] ?? 'unknown');
    };

    $langs   = pcvc_swl_whatsapp_template_lang_codes($tplLang);
    $lastErr = '';
    $lastRes = null;
    $needsDocHeader = false;

    /* Step 1 — text-only template (always try first; never block on PDF upload) */
    foreach ($langs as $i => $lang) {
        if ($i > 0 && $lastRes !== null && !pcvc_swl_whatsapp_is_language_error($lastRes)) {
            break;
        }
        $resText = $sendTemplate([['type' => 'body', 'parameters' => $bodyParams]], $lang);
        $lastRes = $resText;
        $result['detail'] = substr((string) ($resText['body'] ?? ''), 0, 500);

        if ($tplOk($resText)) {
            $result['sent']   = true;
            $result['method'] = 'text_then_pdf';
            $attachPdfFollowUp();
            return $result;
        }

        $lastErr = pcvc_swl_whatsapp_extract_error_message($resText);
        if ($waNotRegistered($resText)) {
            $result['error'] = 'This number is not on WhatsApp — message not delivered.';
            $result['not_on_whatsapp'] = true;
            return $result;
        }
        if (pcvc_swl_whatsapp_is_template_structure_error($resText)) {
            $needsDocHeader = true;
            break;
        }
        if (!pcvc_swl_whatsapp_is_language_error($resText)) {
            break;
        }
    }

    /* Step 2 — Meta template still has Document header (uploaded PDF in template) */
    if ($needsDocHeader && $mediaId !== '') {
        foreach ($langs as $i => $lang) {
            if ($i > 0 && $lastRes !== null && !pcvc_swl_whatsapp_is_language_error($lastRes)) {
                break;
            }
            $components = [
                [
                    'type'       => 'header',
                    'parameters' => [[
                        'type'     => 'document',
                        'document' => ['id' => $mediaId, 'filename' => $docFilename],
                    ]],
                ],
                ['type' => 'body', 'parameters' => $bodyParams],
            ];
            $resDoc = $sendTemplate($components, $lang);
            $lastRes = $resDoc;
            $result['detail'] = substr((string) ($resDoc['body'] ?? ''), 0, 500);
            if ($tplOk($resDoc)) {
                $result['sent']         = true;
                $result['pdf_attached'] = true;
                $result['method']       = 'template_with_pdf_header';
                return $result;
            }
            $lastErr = pcvc_swl_whatsapp_extract_error_message($resDoc);
            if ($waNotRegistered($resDoc)) {
                $result['error'] = 'This number is not on WhatsApp — message not delivered.';
                $result['not_on_whatsapp'] = true;
                return $result;
            }
            if ($pdfHttps && pcvc_swl_whatsapp_is_template_structure_error($resDoc)) {
                $linkComponents = [
                    [
                        'type'       => 'header',
                        'parameters' => [[
                            'type'     => 'document',
                            'document' => ['link' => $pdfPublicUrl, 'filename' => $docFilename],
                        ]],
                    ],
                    ['type' => 'body', 'parameters' => $bodyParams],
                ];
                $resLink = $sendTemplate($linkComponents, $lang);
                if ($tplOk($resLink)) {
                    $result['sent']         = true;
                    $result['pdf_attached'] = true;
                    $result['method']       = 'template_with_pdf_header_link';
                    return $result;
                }
                $lastErr = pcvc_swl_whatsapp_extract_error_message($resLink);
            }
            if (!pcvc_swl_whatsapp_is_language_error($resDoc)) {
                break;
            }
        }
    }

    $result['error'] = 'WhatsApp failed: ' . ($lastErr ?: 'unknown error');
    if ($needsDocHeader && $mediaId === '') {
        $result['error'] .= ' (Template may require a PDF header but PDF upload to Meta failed.)';
    }
    return $result;
}

/* ============================================================
   WHATSAPP — text template + PDF document (+ CC copy)
   Uses text-only pcvc_warning_letter template, then sends the
   generated PDF as a second WhatsApp document message.
============================================================ */
function pcvc_swl_send_whatsapp(
    string $phoneRaw,
    string $staffName,
    string $subject,
    string $pdfPublicUrl,
    string $referenceCode,
    string $pdfAbsPath = ''
): array {
    $token   = trim((string) xander_env_get('WHATSAPP_ACCESS_TOKEN'));
    if ($token === '') $token = trim((string) xander_env_get('WHATSAPP_TOKEN'));
    $phoneId = trim((string) xander_env_get('WHATSAPP_PHONE_NUMBER_ID'));

    $result = ['sent' => false, 'method' => '', 'error' => '', 'not_on_whatsapp' => false, 'cc' => ['sent' => false, 'error' => '', 'to' => '']];
    if ($token === '' || $phoneId === '') {
        $result['error'] = 'WhatsApp is not configured (token/phone-id missing).';
        return $result;
    }

    $dcc       = trim((string) xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE'));
    $defaultCc = $dcc !== '' ? $dcc : null;
    $to        = xander_format_phone_for_whatsapp_e164($phoneRaw, $defaultCc);
    if ($to === null || $to === '') {
        $result['error'] = 'Staff phone number is missing or invalid.';
        return $result;
    }

    $version = trim((string) xander_env_get('META_GRAPH_VERSION')) ?: 'v19.0';
    $url     = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneId) . '/messages';

    $tplName = trim((string) xander_env_get('WHATSAPP_WARNING_LETTER_TEMPLATE_NAME')) ?: 'pcvc_warning_letter';
    $tplLang = trim((string) xander_env_get('WHATSAPP_WARNING_LETTER_TEMPLATE_LANG')) ?: 'en';
    $docFilename = 'Warning_Letter_' . $referenceCode . '.pdf';

    $mediaId = '';
    if ($pdfAbsPath !== '' && is_file($pdfAbsPath)) {
        for ($try = 0; $try < 2 && $mediaId === ''; $try++) {
            $upload = pcvc_swl_whatsapp_upload_media($phoneId, $token, $version, $pdfAbsPath, $docFilename);
            if ($upload['id'] !== '') {
                $mediaId = $upload['id'];
            } else {
                error_log('[warning_letter] PDF upload to Meta failed (try ' . ($try + 1) . ', HTTP ' . $upload['http'] . ').');
                if ($try === 0) {
                    usleep(300000);
                }
            }
        }
    } else {
        error_log('[warning_letter] PDF file missing at: ' . $pdfAbsPath);
    }

    $bodyParams = [
        ['type' => 'text', 'text' => pcvc_brochure_wa_sanitize_param($staffName ?: 'Colleague')],
        ['type' => 'text', 'text' => pcvc_brochure_wa_sanitize_param($subject)],
        ['type' => 'text', 'text' => pcvc_brochure_wa_sanitize_param($referenceCode)],
    ];

    $primary = pcvc_swl_whatsapp_deliver_template_to(
        $to, $staffName, $subject, $referenceCode, $mediaId, $docFilename,
        $url, $token, $tplName, $tplLang, $bodyParams, $pdfPublicUrl
    );
    $result['sent']             = !empty($primary['sent']);
    $result['method']           = (string) ($primary['method'] ?? '');
    $result['error']            = (string) ($primary['error'] ?? '');
    $result['not_on_whatsapp']  = !empty($primary['not_on_whatsapp']);
    $result['pdf_attached']     = !empty($primary['pdf_attached']);
    $result['detail']           = (string) ($primary['detail'] ?? '');

    $ccRaw = pcvc_swl_cc_whatsapp_raw();
    $ccTo  = xander_format_phone_for_whatsapp_e164($ccRaw, $defaultCc);
    $result['cc']['to'] = $ccTo ?? '';
    if ($ccTo === null || $ccTo === '') {
        $result['cc']['error'] = 'CC WhatsApp number is missing or invalid.';
        return $result;
    }
    if ($ccTo === $to) {
        $result['cc']['sent']         = $result['sent'];
        $result['cc']['pdf_attached'] = $result['pdf_attached'];
        $result['cc']['error']        = '';
        return $result;
    }

    $ccOut = pcvc_swl_whatsapp_deliver_template_to(
        $ccTo, $staffName, $subject, $referenceCode, $mediaId, $docFilename,
        $url, $token, $tplName, $tplLang, $bodyParams, $pdfPublicUrl
    );
    $result['cc']['sent']         = !empty($ccOut['sent']);
    $result['cc']['pdf_attached'] = !empty($ccOut['pdf_attached']);
    $result['cc']['error']        = (string) ($ccOut['error'] ?? '');
    $result['cc']['method']       = (string) ($ccOut['method'] ?? '');

    if ($result['sent'] && !$result['cc']['sent']) {
        $ccErr = $result['cc']['error'] !== '' ? $result['cc']['error'] : 'CC delivery failed';
        $result['error'] = trim($result['error'] . ' Staff notified; CC WhatsApp (' . $ccTo . ') failed: ' . $ccErr);
    }

    return $result;
}

/* ============================================================
   PERSIST RECORD
============================================================ */
function pcvc_swl_save_record(mysqli $conn, array $r): int
{
    pcvc_swl_ensure_schema($conn);
    $stmt = $conn->prepare("
        INSERT INTO staff_warning_letters
            (staff_id, staff_name, staff_email, staff_phone,
             subject, content_html, pdf_path,
             email_sent, email_error, whatsapp_sent, whatsapp_method, whatsapp_error,
             created_by, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())
    ");
    if (!$stmt) return 0;
    $staffId   = (int) $r['staff_id'];
    $staffName = (string) ($r['staff_name'] ?? '');
    $staffMail = (string) ($r['staff_email'] ?? '');
    $staffPhone= (string) ($r['staff_phone'] ?? '');
    $subj      = (string) ($r['subject'] ?? '');
    $html      = (string) ($r['content_html'] ?? '');
    $pdfPath   = (string) ($r['pdf_path'] ?? '');
    $emSent    = !empty($r['email_sent']) ? 1 : 0;
    $emErr     = (string) ($r['email_error'] ?? '');
    $waSent    = !empty($r['whatsapp_sent']) ? 1 : 0;
    $waMethod  = (string) ($r['whatsapp_method'] ?? '');
    $waErr     = (string) ($r['whatsapp_error'] ?? '');
    $createdBy = (int) ($r['created_by'] ?? 0);

    // Types: i,s,s,s,s,s,s,i,s,i,s,s,i  (13 params)
    $stmt->bind_param(
        'issssssisissi',
        $staffId, $staffName, $staffMail, $staffPhone,
        $subj, $html, $pdfPath,
        $emSent, $emErr, $waSent, $waMethod, $waErr,
        $createdBy
    );
    $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

/* ============================================================
   REFERENCE CODE
============================================================ */
function pcvc_swl_make_reference(int $staffId): string
{
    return 'WL-' . date('Ymd') . '-' . $staffId . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}
