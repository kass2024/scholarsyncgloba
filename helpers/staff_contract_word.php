<?php
declare(strict_types=1);

if (!defined('PHP_OS_FAMILY')) {
    define('PHP_OS_FAMILY', DIRECTORY_SEPARATOR === '\\' ? 'Windows' : 'Linux');
}

require_once __DIR__ . '/staff_contract_schema.php';
require_once __DIR__ . '/../includes/company_branding.php';

function pcvc_staff_contract_require_autoload(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $pcvcAutoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($pcvcAutoload)) {
        require_once $pcvcAutoload;
    }
    $loaded = true;
}

function pcvc_staff_contract_require_pdf_helpers(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    pcvc_staff_contract_require_autoload();
    require_once __DIR__ . '/staff_contract_pdf.php';
    require_once __DIR__ . '/contract_signature_image.php';
    $loaded = true;
}

/**
 * Whether LibreOffice/soffice is available for server-side DOCX→PDF.
 */
function pcvc_staff_contract_libreoffice_available(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        foreach ([
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ] as $bin) {
            if (is_file($bin)) {
                $cached = true;
                return true;
            }
        }
    }

    foreach (['/usr/bin/libreoffice', '/usr/bin/soffice', '/usr/local/bin/libreoffice'] as $bin) {
        if (is_file($bin)) {
            $cached = true;
            return true;
        }
    }

    $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
    if (!in_array('exec', $disabled, true)) {
        foreach (['libreoffice', 'soffice'] as $cmd) {
            @exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null', $out, $code);
            if ($code === 0 && !empty($out[0])) {
                $cached = true;
                return true;
            }
            $out = [];
        }
    }

    $cached = false;
    return false;
}

/**
 * Shared hosting (e.g. Namecheap): render filled Word in the browser instead of server PDF.
 */
function pcvc_staff_contract_use_docx_preview(): bool
{
    if (PHP_OS_FAMILY === 'Windows') {
        return false;
    }
    return !pcvc_staff_contract_libreoffice_available();
}

/**
 * Build inline Word drawing XML for an embedded PNG.
 */
function pcvc_staff_contract_inline_image_xml(string $rid, string $label, int $cx, int $cy): string
{
    return '<w:r>' . pcvc_staff_contract_inline_drawing_xml($rid, $label, $cx, $cy) . '</w:r>';
}

/**
 * Drawing block only (no w:r wrapper) for merging into an existing run.
 */
function pcvc_staff_contract_inline_drawing_xml(string $rid, string $label, int $cx, int $cy): string
{
    return '<w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
        . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
        . '<wp:docPr id="' . (9000 + crc32($label) % 1000) . '" name="' . htmlspecialchars($label, ENT_XML1) . '"/>'
        . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
        . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:nvPicPr><pic:cNvPr id="0" name="' . htmlspecialchars($label, ENT_XML1) . '"/><pic:cNvPicPr/></pic:nvPicPr>'
        . '<pic:blipFill><a:blip r:embed="' . $rid . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
        . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
        . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing>';
}

/**
 * Strip a single outer w:r wrapper when embedding into a preserved run prefix.
 */
function pcvc_staff_contract_run_inner_content(string $runXml): string
{
    if (preg_match('#^<w:r[^>]*>(.*)</w:r>$#s', trim($runXml), $match)) {
        return $match[1];
    }

    return $runXml;
}

/**
 * Find the start of the w:r element that contains a placeholder token.
 */
function pcvc_staff_contract_find_word_run_start(string $xml, int $tokenPos): ?int
{
    $before = substr($xml, 0, $tokenPos);
    $best = null;
    $offset = 0;
    while (($p = strpos($before, '<w:r', $offset)) !== false) {
        $next = $before[$p + 4] ?? '';
        if ($next === '>' || $next === ' ') {
            $best = $p;
        }
        $offset = $p + 4;
    }
    return $best;
}

/**
 * Regex for Word placeholders split as: [prefix${] + [key] + [}]
 */
function pcvc_staff_contract_fragmented_placeholder_pattern(string $key): string
{
    $keyEsc = preg_quote($key, '/');

    return '#(<w:r[^>]*>(?:<w:rPr>.*?<\/w:rPr>)?)<w:t(?:\s[^>]*)?>([^<]*?)\s*\$\{</w:t><\/w:r>'
        . '(?:<w:proofErr[^>]*\/>)?'
        . '<w:r[^>]*>(?:<w:rPr>.*?<\/w:rPr>)?<w:t(?:\s[^>]*)?>' . $keyEsc . '</w:t><\/w:r>'
        . '(?:<w:proofErr[^>]*\/>)?'
        . '<w:r[^>]*>(?:<w:rPr>.*?<\/w:rPr>)?<w:t(?:\s[^>]*)?>\}</w:t><\/w:r>#s';
}

/**
 * Return the w:p paragraph XML containing a byte offset.
 */
function pcvc_staff_contract_paragraph_for_offset(string $xml, int $offset): string
{
    $start = strrpos(substr($xml, 0, $offset), '<w:p');
    if ($start === false) {
        return '';
    }
    $end = strpos($xml, '</w:p>', $offset);
    if ($end === false) {
        return '';
    }

    return substr($xml, $start, $end - $start + strlen('</w:p>'));
}

/**
 * Replace ${key} when Word split it across runs (prefix${ + key + }).
 */
function pcvc_staff_contract_replace_fragmented_placeholder(
    string $xml,
    string $key,
    string $replacement,
    bool $replacementIsXml = false
): string {
    if (strpos($xml, '${' . $key . '}') !== false) {
        return $xml;
    }

    $pattern = pcvc_staff_contract_fragmented_placeholder_pattern($key);
    $keyWt = '/<w:t(?:\s[^>]*)?>' . preg_quote($key, '/') . '<\/w:t>/';
    $offset = 0;

    while (preg_match($keyWt, $xml, $keyMatch, PREG_OFFSET_CAPTURE, $offset)) {
        $keyPos = (int) $keyMatch[0][1];
        $para = pcvc_staff_contract_paragraph_for_offset($xml, $keyPos);
        if ($para === '' || !preg_match($pattern, $para, $fragMatch, PREG_OFFSET_CAPTURE)) {
            $offset = $keyPos + 1;
            continue;
        }

        $paraStart = strrpos(substr($xml, 0, $keyPos), '<w:p');
        if ($paraStart === false) {
            $offset = $keyPos + 1;
            continue;
        }

        $absStart = $paraStart + (int) $fragMatch[0][1];
        $absLen = strlen($fragMatch[0][0]);

        if ($replacementIsXml) {
            $inner = pcvc_staff_contract_run_inner_content($replacement);
            $insert = $fragMatch[1][0] . $inner . '</w:r>';
        } else {
            $prefix = $fragMatch[2][0];
            $safe = htmlspecialchars($replacement, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $insert = $fragMatch[1][0] . '<w:t xml:space="preserve">' . $prefix . $safe . '</w:t></w:r>';
        }

        $xml = substr($xml, 0, $absStart) . $insert . substr($xml, $absStart + $absLen);
        $offset = $absStart + strlen($insert);
    }

    return $xml;
}

/**
 * Replace the single w:r run that contains a ${placeholder} token (safe — no cross-document regex).
 */
function pcvc_staff_contract_replace_placeholder_run_in_xml(string $xml, string $placeholderKey, string $replacementXml): string
{
    $token = '${' . $placeholderKey . '}';
    $needle = '<w:t>' . $token . '</w:t>';
    $pos = strpos($xml, $needle);
    if ($pos === false) {
        $pos = strpos($xml, $token);
        if ($pos === false) {
            return pcvc_staff_contract_replace_fragmented_placeholder($xml, $placeholderKey, $replacementXml, true);
        }

        return substr($xml, 0, $pos) . $replacementXml . substr($xml, $pos + strlen($token));
    }

    $rStart = pcvc_staff_contract_find_word_run_start($xml, $pos);
    $rEnd = strpos($xml, '</w:r>', $pos);
    if ($rStart === null || $rEnd === false) {
        return str_replace($needle, $replacementXml, $xml);
    }
    $rEnd += strlen('</w:r>');

    return substr($xml, 0, $rStart) . $replacementXml . substr($xml, $rEnd);
}

/**
 * Replace ${placeholder_key} in document.xml with an embedded PNG image.
 */
function pcvc_staff_contract_embed_image_at_placeholder(
    string $docxAbs,
    string $placeholderKey,
    string $mediaFileName,
    string $pngBytes,
    int $widthEmu = 1371600,
    int $heightEmu = 457200
): void {
    if ($pngBytes === '') {
        throw new RuntimeException('Signature image is empty.');
    }

    $zip = new ZipArchive();
    if ($zip->open($docxAbs) !== true) {
        throw new RuntimeException('Could not open contract document for image embed.');
    }

    $mediaPath = 'word/media/' . $mediaFileName;
    if ($zip->locateName($mediaPath) !== false) {
        $zip->deleteName($mediaPath);
    }
    $zip->addFromString($mediaPath, $pngBytes);

    $relsPath = 'word/_rels/document.xml.rels';
    $rels = (string) $zip->getFromName($relsPath);
    if ($rels === '') {
        $zip->close();
        throw new RuntimeException('Contract relationships file missing.');
    }

    $target = 'media/' . $mediaFileName;
    $newRid = '';
    if (preg_match('/Id="(rId\d+)"[^>]+Target="' . preg_quote($target, '/') . '"/', $rels, $existing)) {
        $newRid = $existing[1];
    } else {
        $nextId = 1;
        if (preg_match_all('/Id="rId(\d+)"/', $rels, $matches)) {
            $nextId = max(array_map('intval', $matches[1])) + 1;
        }
        $newRid = 'rId' . $nextId;
        $rels = str_replace(
            '</Relationships>',
            '<Relationship Id="' . $newRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="' . $target . '"/></Relationships>',
            $rels
        );
        $zip->deleteName($relsPath);
        $zip->addFromString($relsPath, $rels);
    }

    $xml = (string) $zip->getFromName('word/document.xml');
    if ($xml === '') {
        $zip->close();
        throw new RuntimeException('Contract document body missing.');
    }

    $drawing = pcvc_staff_contract_inline_image_xml(
        $newRid,
        ucfirst(str_replace('_', ' ', $placeholderKey)),
        $widthEmu,
        $heightEmu
    );
    $updated = pcvc_staff_contract_replace_placeholder_run_in_xml($xml, $placeholderKey, $drawing);

    $zip->deleteName('word/document.xml');
    $zip->addFromString('word/document.xml', $updated);
    $zip->close();
}

/**
 * Remove a text placeholder line (e.g. employee signature before signing).
 */
function pcvc_staff_contract_clear_text_placeholder(string $docxAbs, string $placeholderKey): void
{
    $zip = new ZipArchive();
    if ($zip->open($docxAbs) !== true) {
        return;
    }
    $xml = (string) $zip->getFromName('word/document.xml');
    if ($xml === '') {
        $zip->close();
        return;
    }
    $blank = '<w:r><w:t xml:space="preserve"> </w:t></w:r>';
    $updated = pcvc_staff_contract_replace_placeholder_run_in_xml($xml, $placeholderKey, $blank);
    if ($updated !== $xml) {
        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $updated);
    }
    $zip->close();
}

function pcvc_staff_contract_manager_signature_bytes(): ?string
{
    $path = pcvc_staff_contract_manager_signature_path();
    if (!is_file($path)) {
        return null;
    }
    $bytes = file_get_contents($path);
    return $bytes !== false && $bytes !== '' ? $bytes : null;
}

/**
 * Embed employer signature image; embed or clear employee signature placeholder.
 */
function pcvc_staff_contract_apply_signature_images(string $docxAbs, ?string $employeeSignatureDataUrl = null): void
{
    $employerPng = pcvc_staff_contract_manager_signature_bytes();
    if ($employerPng !== null) {
        pcvc_staff_contract_embed_image_at_placeholder(
            $docxAbs,
            'employer_signature',
            'employer_signature.png',
            $employerPng,
            1371600,
            457200
        );
    }

    if ($employeeSignatureDataUrl !== null && $employeeSignatureDataUrl !== '') {
        if (!function_exists('contract_signature_to_display_png')) {
            require_once __DIR__ . '/contract_signature_image.php';
        }
        $sigPng = contract_signature_to_display_png($employeeSignatureDataUrl);
        if ($sigPng === null) {
            $sigPng = contract_signature_raw_bytes($employeeSignatureDataUrl);
        }
        if ($sigPng !== null && $sigPng !== '') {
            pcvc_staff_contract_embed_image_at_placeholder(
                $docxAbs,
                'employee_signature',
                'employee_signature.png',
                $sigPng,
                1371600,
                457200
            );
            return;
        }
    }

    pcvc_staff_contract_clear_text_placeholder($docxAbs, 'employee_signature');
}

/**
 * @deprecated Use pcvc_staff_contract_embed_image_at_placeholder()
 */
function pcvc_staff_contract_embed_signature_in_docx(string $docxAbs, string $pngBytes): void
{
    pcvc_staff_contract_embed_image_at_placeholder(
        $docxAbs,
        'employee_signature',
        'employee_signature.png',
        $pngBytes,
        1371600,
        457200
    );
}

/**
 * Placeholders supported in Word templates (${placeholder_name}).
 *
 * @return list<string>
 */
function pcvc_staff_contract_placeholder_keys(): array
{
    return [
        'full_name', 'first_name', 'last_name', 'email', 'phone_number', 'username',
        'role', 'position', 'employment_type', 'employment_start_date',
        'national_id', 'date_of_birth', 'marital_status', 'nationality',
        'place_of_birth', 'address', 'monthly_salary', 'salary_currency',
        'company_name', 'signing_date', 'probation_end_date',
        'employer_name', 'employer_position', 'employer_date', 'employer_signature',
        'employee_signature',
    ];
}

function pcvc_staff_contract_employer_defaults(): array
{
    return [
        'name' => 'TWAJAMAHORO JEAN PIERRE',
        'position' => 'Managing director',
    ];
}

function pcvc_staff_contract_manager_signature_path(): string
{
    return dirname(__DIR__) . '/admin/signature-manager.png';
}

function pcvc_staff_contract_format_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return date('F j, Y', strtotime($value));
    }
    return $value;
}

/**
 * Resolve job title for contract merge (admins.position).
 */
function pcvc_staff_contract_resolve_position(array $admin): string
{
    return trim((string) ($admin['position'] ?? ''));
}

/**
 * Agreement reference suffix (PCVC-PROB-… in Word templates).
 */
function pcvc_staff_contract_agreement_reference(array $admin): string
{
    $nationalId = trim((string) ($admin['national_id'] ?? ''));
    if ($nationalId !== '') {
        return $nationalId;
    }
    $adminId = (int) ($admin['id'] ?? 0);
    return $adminId > 0 ? (string) $adminId : '';
}

function pcvc_staff_contract_canonical_template_path(): string
{
    return dirname(__DIR__) . '/admin/ScholarSync Contract for Mutware.docx';
}

/**
 * @return array{has_media:bool, media_count:int, num_pr_count:int}
 */
function pcvc_staff_contract_docx_stats(string $docxAbs): array
{
    $stats = ['has_media' => false, 'media_count' => 0, 'num_pr_count' => 0];
    if (!is_file($docxAbs)) {
        return $stats;
    }
    $zip = new ZipArchive();
    if ($zip->open($docxAbs) !== true) {
        return $stats;
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (strpos($name, 'word/media/') === 0) {
            $stats['has_media'] = true;
            $stats['media_count']++;
        }
    }
    $xml = (string) $zip->getFromName('word/document.xml');
    if ($xml !== '') {
        $stats['num_pr_count'] = substr_count($xml, 'w:numPr');
    }
    $zip->close();
    return $stats;
}

/**
 * Pick the template used for merge (canonical ScholarSync file when upload lacks stamp/images).
 */
function pcvc_staff_contract_resolve_template_path(string $docxAbs): string
{
    $stats = pcvc_staff_contract_docx_stats($docxAbs);
    if ($stats['has_media']) {
        return $docxAbs;
    }

    $canonical = pcvc_staff_contract_canonical_template_path();
    return is_file($canonical) ? $canonical : $docxAbs;
}

/**
 * Warn when upload is a stripped export (stamp applied at merge time via canonical template).
 */
function pcvc_staff_contract_ensure_rich_template(string $docxAbs): string
{
    $stats = pcvc_staff_contract_docx_stats($docxAbs);
    if ($stats['has_media']) {
        return '';
    }

    $canonical = pcvc_staff_contract_canonical_template_path();
    if (!is_file($canonical)) {
        return 'Uploaded contract has no embedded images (company stamp may be missing). '
            . 'Save the contract from Word as .docx with images included, or add '
            . 'admin/ScholarSync Contract for Mutware.docx on the server.';
    }

    return 'Uploaded file had no company stamp/images. The standard ScholarSync contract template will be used when filling placeholders.';
}

/**
 * Extract visible text from a Word XML fragment (w:t nodes only).
 */
function pcvc_staff_contract_xml_fragment_text(string $fragment): string
{
    if (!preg_match_all('/<w:t(?:\s[^>]*)?>([^<]*)<\/w:t>/', $fragment, $matches)) {
        return '';
    }
    return implode('', $matches[1]);
}

/**
 * Strip spell-check tags only (safe on large documents).
 */
function pcvc_staff_contract_strip_proof_err(string $xml): string
{
    return preg_replace('/<w:proofErr[^>]*\/>/', '', $xml) ?? $xml;
}

/**
 * Replace ${placeholder} values in DOCX XML without destroying list formatting.
 *
 * @param array<string, string> $values
 * @param list<string> $imageKeys
 */
function pcvc_staff_contract_apply_placeholder_values(string $xml, array $values, array $imageKeys): string
{
    $xml = pcvc_staff_contract_strip_proof_err($xml);

    foreach ($values as $key => $value) {
        if (in_array($key, $imageKeys, true)) {
            continue;
        }
        $safe = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = str_replace('${' . $key . '}', $safe, $xml);
    }

    if (strpos($xml, '${') !== false) {
        foreach (pcvc_staff_contract_placeholder_keys() as $key) {
            if (!isset($values[$key]) || in_array($key, $imageKeys, true)) {
                continue;
            }
            if (!pcvc_staff_contract_xml_has_unresolved_placeholder($xml, $key)) {
                continue;
            }
            $safe = (string) $values[$key];
            $safeXml = htmlspecialchars($safe, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml = str_replace('${' . $key . '}', $safeXml, $xml);
            if (!pcvc_staff_contract_xml_has_unresolved_placeholder($xml, $key)) {
                continue;
            }
            for ($pass = 0; $pass < 4; $pass++) {
                $prev = $xml;
                $xml = pcvc_staff_contract_replace_fragmented_placeholder($xml, $key, $safe);
                $xml = pcvc_staff_contract_replace_key_split_placeholder($xml, $key, $safe);
                $xml = pcvc_staff_contract_replace_split_placeholder($xml, $key, $safe);
                if ($xml === $prev || !pcvc_staff_contract_xml_has_unresolved_placeholder($xml, $key)) {
                    break;
                }
            }
            if (strpos($xml, '${') === false) {
                break;
            }
        }
    }

    return $xml;
}

/**
 * Replace placeholders Word split across runs without collapsing XML structure.
 */
function pcvc_staff_contract_replace_split_placeholder(string $xml, string $key, string $safe): string
{
    if (strpos($xml, '${' . $key . '}') !== false) {
        return $xml;
    }

    $keyWt = '/<w:t(?:\s[^>]*)?>' . preg_quote($key, '/') . '<\/w:t>/';
    $offset = 0;

    while (preg_match($keyWt, $xml, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $keyPos = (int) $match[0][1];
        $para = pcvc_staff_contract_paragraph_for_offset($xml, $keyPos);
        if ($para === '') {
            $offset = $keyPos + 1;
            continue;
        }

        $paraStart = strrpos(substr($xml, 0, $keyPos), '<w:p');
        if ($paraStart === false) {
            $offset = $keyPos + 1;
            continue;
        }

        $before = substr($para, 0, $keyPos - $paraStart);
        if (strpos($before, '${') === false) {
            $offset = $keyPos + 1;
            continue;
        }

        $safeXml = htmlspecialchars($safe, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $chunk = $para;
        $chunk = preg_replace(
            '/<w:t(?:\s[^>]*)?>([^<]*?)\s*\$\{<\/w:t>/',
            '<w:t xml:space="preserve">$1</w:t>',
            $chunk,
            1
        ) ?? $chunk;
        $chunk = preg_replace(
            '/<w:t(?:\s[^>]*)?>\s*\$\{<\/w:t>/',
            '',
            $chunk,
            1
        ) ?? $chunk;
        $chunk = preg_replace(
            $keyWt,
            '<w:t xml:space="preserve">' . $safeXml . '</w:t>',
            $chunk,
            1
        ) ?? $chunk;
        $chunk = preg_replace(
            '/<w:t(?:\s[^>]*)?>\}<\/w:t>/',
            '',
            $chunk,
            1
        ) ?? $chunk;

        if ($chunk === $para) {
            $offset = $keyPos + 1;
            continue;
        }

        $paraEnd = strpos($xml, '</w:p>', $keyPos);
        if ($paraEnd === false) {
            $offset = $keyPos + 1;
            continue;
        }
        $paraEnd += strlen('</w:p>');

        $xml = substr($xml, 0, $paraStart) . $chunk . substr($xml, $paraEnd);
        $offset = $paraStart + strlen($chunk);
    }

    return $xml;
}

/**
 * True when document.xml still has an unresolved placeholder for this key.
 */
function pcvc_staff_contract_xml_has_unresolved_placeholder(string $xml, string $key): bool
{
    if (strpos($xml, '${' . $key . '}') !== false) {
        return true;
    }
    if (strpos($xml, '${') === false) {
        return false;
    }

    $keyLen = strlen($key);
    for ($splitAt = 1; $splitAt < $keyLen; $splitAt++) {
        $part1 = substr($key, 0, $splitAt);
        if (strpos($xml, '${' . $part1) !== false) {
            return true;
        }
    }

    return (bool) preg_match(
        '/<w:t(?:\s[^>]*)?>' . preg_quote($key, '/') . '<\/w:t>/',
        $xml
    );
}

/**
 * Replace placeholders Word split inside the key (e.g. ${employer_ + name}).
 */
function pcvc_staff_contract_replace_key_split_placeholder(string $xml, string $key, string $safe): string
{
    if (strpos($xml, '${' . $key . '}') !== false || strpos($xml, '${') === false) {
        return $xml;
    }

    $safeXml = htmlspecialchars($safe, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $keyLen = strlen($key);

    for ($splitAt = 1; $splitAt < $keyLen; $splitAt++) {
        $part1 = substr($key, 0, $splitAt);
        if (strpos($xml, '${' . $part1) === false) {
            continue;
        }
        $part2 = substr($key, $splitAt);
        $pattern = '#(<w:r[^>]*>(?:<w:rPr>.*?<\/w:rPr>)?)<w:t(?:\s[^>]*)?>\s*\$\{'
            . preg_quote($part1, '/')
            . '</w:t></w:r>(?:(?!</w:p>).)*<w:r[^>]*>(?:<w:rPr>.*?<\/w:rPr>)?<w:t(?:\s[^>]*)?>'
            . preg_quote($part2, '/')
            . '\}</w:t></w:r>#s';

        $offset = 0;
        while (($pos = strpos($xml, '${' . $part1, $offset)) !== false) {
            $paraStart = strrpos(substr($xml, 0, $pos), '<w:p');
            $paraEnd = strpos($xml, '</w:p>', $pos);
            if ($paraStart === false || $paraEnd === false) {
                $offset = $pos + 1;
                continue;
            }
            $paraEnd += strlen('</w:p>');
            $para = substr($xml, $paraStart, $paraEnd - $paraStart);
            if (!preg_match($pattern, $para, $km, PREG_OFFSET_CAPTURE)) {
                $offset = $pos + 1;
                continue;
            }

            $replacement = $km[1][0] . '<w:t xml:space="preserve"> ' . $safeXml . '</w:t></w:r>';
            $newPara = substr($para, 0, $km[0][1]) . $replacement . substr($para, $km[0][1] + strlen($km[0][0]));
            $xml = substr($xml, 0, $paraStart) . $newPara . substr($xml, $paraEnd);
            $offset = $paraStart + strlen($newPara);
        }
    }

    return $xml;
}

/**
 * Remove hard/soft page breaks before canonical layout is applied (idempotent merge).
 */
function pcvc_staff_contract_strip_canonical_page_breaks(string $xml): string
{
    $xml = preg_replace(
        '/<w:p\b[^>]*>\s*<w:r[^>]*>\s*<w:br\s+w:type="page"\s*\/>\s*<\/w:r>\s*<\/w:p>/',
        '',
        $xml
    ) ?? $xml;
    $xml = preg_replace('/<w:lastRenderedPageBreak\s*\/>/', '', $xml) ?? $xml;

    return $xml;
}

/**
 * Remove Word soft page hints (they create extra blank pages in docx-preview).
 */
function pcvc_staff_contract_strip_soft_page_hints(string $xml): string
{
    return preg_replace('/<w:lastRenderedPageBreak\s*\/>/', '', $xml) ?? $xml;
}

/**
 * Restore hard page breaks from legacy pageBreakBefore markers (docx-preview needs w:type="page").
 */
function pcvc_staff_contract_restore_hard_page_breaks(string $xml): string
{
    if (strpos($xml, 'w:pageBreakBefore') === false) {
        return $xml;
    }

    $breakPara = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

    return preg_replace_callback(
        '/<w:p\b([^>]*)>(\s*<w:pPr>)(.*?)(<\/w:pPr>)/s',
        static function (array $m) use ($breakPara): string {
            if (strpos($m[3], 'w:pageBreakBefore') === false) {
                return $m[0];
            }
            $pPrInner = preg_replace('/<w:pageBreakBefore\s*\/>/', '', $m[3]) ?? $m[3];
            if (trim($pPrInner) === '') {
                return $breakPara . '<w:p' . $m[1] . '>';
            }

            return $breakPara . '<w:p' . $m[1] . '>' . $m[2] . $pPrInner . $m[4];
        },
        $xml
    ) ?? $xml;
}

/**
 * Count hard page breaks that docx-preview and Word both honour.
 */
function pcvc_staff_contract_hard_page_break_count(string $xml): int
{
    $hard = substr_count($xml, 'w:type="page"');
    if ($hard > 0) {
        return $hard;
    }

    return substr_count($xml, 'w:pageBreakBefore');
}

/**
 * Canonical ScholarSync contract uses eight hard page breaks (nine pages).
 */
function pcvc_staff_contract_canonical_hard_page_break_count(): int
{
    return 8;
}

/**
 * Copy template to a temp file (no heavy XML rewriting).
 */
function pcvc_staff_contract_prepare_template(string $templateAbs): string
{
    pcvc_staff_contract_ensure_dirs();
    $work = pcvc_staff_contract_upload_dir() . '/tmp_tpl_' . bin2hex(random_bytes(8)) . '.docx';
    if (!copy($templateAbs, $work)) {
        throw new RuntimeException('Could not copy contract template.');
    }
    return $work;
}

/**
 * @param array<string, mixed> $admin
 * @return array<string, string>
 */
function pcvc_staff_contract_merge_values(
    array $admin,
    ?string $signingDate = null,
    ?string $signatureDataUrl = null
): array {
    $hasEmployeeSign = trim((string) ($signatureDataUrl ?? '')) !== '';
    $signingDateRaw = $signingDate !== null ? trim((string) $signingDate) : '';
    if ($hasEmployeeSign) {
        if ($signingDateRaw === '') {
            $signingDateRaw = date('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $signingDateRaw)) {
            $signingDateDisplay = date('F j, Y', strtotime($signingDateRaw));
        } else {
            $signingDateDisplay = $signingDateRaw;
        }
    } else {
        $signingDateDisplay = '';
    }

    $monthly = $admin['monthly_salary'] ?? '';
    $monthlyDisplay = ($monthly !== '' && $monthly !== null) ? number_format((float) $monthly, 2) : '';

    $startDateRaw = trim((string) ($admin['employment_start_date'] ?? ''));
    $startDate = pcvc_staff_contract_format_date($startDateRaw);
    $probationEnd = '';
    if ($startDateRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateRaw)) {
        $probationEnd = date('F j, Y', strtotime($startDateRaw . ' +3 months'));
    }

    $employer = pcvc_staff_contract_employer_defaults();

    return [
        'full_name' => trim((string) ($admin['full_name'] ?? '')),
        'first_name' => trim((string) ($admin['first_name'] ?? '')),
        'last_name' => trim((string) ($admin['last_name'] ?? '')),
        'email' => trim((string) ($admin['email'] ?? '')),
        'phone_number' => trim((string) ($admin['phone_number'] ?? '')),
        'username' => trim((string) ($admin['username'] ?? '')),
        'role' => trim((string) ($admin['role'] ?? '')),
        'position' => pcvc_staff_contract_resolve_position($admin),
        'employment_type' => trim((string) ($admin['employment_type'] ?? '')),
        'employment_start_date' => $startDate,
        'probation_end_date' => $probationEnd,
        'national_id' => pcvc_staff_contract_agreement_reference($admin) !== ''
            ? pcvc_staff_contract_agreement_reference($admin)
            : trim((string) ($admin['national_id'] ?? '')),
        'date_of_birth' => pcvc_staff_contract_format_date((string) ($admin['date_of_birth'] ?? '')),
        'marital_status' => trim((string) ($admin['marital_status'] ?? '')),
        'nationality' => trim((string) ($admin['nationality'] ?? '')),
        'place_of_birth' => trim((string) ($admin['place_of_birth'] ?? '')),
        'address' => trim((string) ($admin['address'] ?? '')),
        'monthly_salary' => $monthlyDisplay,
        'salary_currency' => trim((string) ($admin['salary_currency'] ?? '')),
        'company_name' => PCVC_COMPANY_DISPLAY_NAME,
        'signing_date' => $signingDateDisplay,
        'employer_name' => $employer['name'],
        'employer_position' => $employer['position'],
        'employer_date' => date('F j, Y'),
        'employer_signature' => '',
        'employee_signature' => '',
    ];
}

/**
 * Text anchors where the canonical 9-page Word contract starts a new page.
 *
 * @return list<string>
 */
function pcvc_staff_contract_page_break_anchor_texts(): array
{
    return [
        'The Company reserves the right to extend the probation period',
        'Support the Country Coordinator in achieving recruitment',
        'Help organize student information sessions and institutional presentations',
        '7. TRAINING AND LEARNING REQUIREMENTS',
        'The Employee shall not use Company property, systems, databases, software, documents, equipment',
        'Daily Check-In and Check-Out records in SCHOLARSYNC MIS shall serve as the official attendance',
        '14. CONFIRMATION OF EMPLOYMENT',
    ];
}

/**
 * Insert a hard page break immediately after the first paragraph matching anchor text.
 */
function pcvc_staff_contract_inject_page_break_after_anchor(string $xml, string $anchorText): string
{
    $breakPara = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

    if (!preg_match_all('/<w:p\b[^>]*>.*?<\/w:p>/s', $xml, $matches, PREG_OFFSET_CAPTURE)) {
        return $xml;
    }

    foreach ($matches[0] as $paragraph) {
        $text = pcvc_staff_contract_paragraph_text($paragraph[0]);
        if ($text === '' || strpos($text, $anchorText) === false) {
            continue;
        }
        $offset = $paragraph[1] + strlen($paragraph[0]);
        $after = substr($xml, $offset, 160);
        if (strpos($after, 'w:type="page"') !== false) {
            return $xml;
        }

        return substr($xml, 0, $offset) . $breakPara . substr($xml, $offset);
    }

    return $xml;
}

/**
 * True when a paragraph contains only a hard page break (optional pPr), no visible text.
 */
function pcvc_staff_contract_is_break_only_paragraph(string $paragraphXml): bool
{
    if (strpos($paragraphXml, 'w:type="page"') === false) {
        return false;
    }
    if (pcvc_staff_contract_paragraph_text($paragraphXml) !== '') {
        return false;
    }

    $body = preg_replace('/<w:pPr\b[^>]*>.*?<\/w:pPr>/s', '', $paragraphXml) ?? $paragraphXml;
    $body = preg_replace('/^<w:p\b[^>]*>/', '', $body) ?? $body;
    $body = preg_replace('/<\/w:p>$/', '', $body) ?? $body;

    return (bool) preg_match(
        '/^(?:\s*<w:r[^>]*>\s*(?:<w:lastRenderedPageBreak\s*\/>)?\s*<w:br\s+w:type="page"\s*\/>\s*<\/w:r>\s*)+$/s',
        trim($body)
    );
}

/**
 * Move standalone page-break paragraphs onto the end of the previous paragraph.
 * Word no longer inserts a blank page; docx-preview still sees w:type="page".
 */
function pcvc_staff_contract_compact_page_break_paragraphs(string $xml): string
{
    $prev = '';
    while ($prev !== $xml) {
        $prev = $xml;
        if (!preg_match_all('/<w:p\b[^>]*>.*?<\/w:p>/s', $xml, $matches, PREG_OFFSET_CAPTURE)) {
            break;
        }

        $merged = false;
        foreach ($matches[0] as $paragraph) {
            $paraXml = $paragraph[0];
            $offset = $paragraph[1];
            if (!pcvc_staff_contract_is_break_only_paragraph($paraXml)) {
                continue;
            }

            $prevClose = strrpos(substr($xml, 0, $offset), '</w:p>');
            if ($prevClose === false) {
                continue;
            }

            $brRun = '<w:r><w:br w:type="page"/></w:r>';
            $xml = substr($xml, 0, $prevClose)
                . $brRun
                . substr($xml, $prevClose, $offset - $prevClose)
                . substr($xml, $offset + strlen($paraXml));
            $merged = true;
            break;
        }

        if (!$merged) {
            break;
        }
    }

    return $xml;
}

/**
 * Finalize document.xml for filled/signed DOCX (layout + compact breaks).
 */
function pcvc_staff_contract_finalize_docx_xml(string $xml): string
{
    $xml = pcvc_staff_contract_apply_page_break_layout($xml);

    return pcvc_staff_contract_compact_page_break_paragraphs($xml);
}

/**
 * Patch an on-disk DOCX so Word download/preview matches the uploaded template pages.
 */
function pcvc_staff_contract_finalize_docx_on_disk(string $docxAbs): void
{
    $zip = new ZipArchive();
    if ($zip->open($docxAbs) !== true) {
        return;
    }
    $xml = (string) $zip->getFromName('word/document.xml');
    if ($xml === '') {
        $zip->close();
        return;
    }
    $fixed = pcvc_staff_contract_finalize_docx_xml($xml);
    if ($fixed !== $xml) {
        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $fixed);
    }
    $zip->close();
}

/**
 * Apply canonical page-break layout from ScholarSync Contract for Mutware.docx.
 *
 * Preserves the uploaded template's hard page breaks (nine pages in docx-preview).
 * Only removes soft hints and ghost breaks inside numbered lists.
 */
function pcvc_staff_contract_apply_page_break_layout(string $xml): string
{
    $xml = pcvc_staff_contract_restore_hard_page_breaks($xml);
    $xml = pcvc_staff_contract_strip_soft_page_hints($xml);
    $xml = pcvc_staff_contract_clean_docx_layout_in_xml($xml);

    if (substr_count($xml, 'w:type="page"') >= pcvc_staff_contract_canonical_hard_page_break_count()) {
        return $xml;
    }

    $xml = pcvc_staff_contract_strip_canonical_page_breaks($xml);
    $xml = pcvc_staff_contract_inject_page_breaks_in_xml($xml);

    return pcvc_staff_contract_inject_page_break_after_anchor($xml, 'Quality of work');
}

function pcvc_staff_contract_paragraph_text(string $paragraphXml): string
{
    if (!preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $paragraphXml, $matches)) {
        return '';
    }

    return trim(html_entity_decode(implode('', $matches[1])));
}

/**
 * Count pages implied by explicit page-break markers in document.xml.
 */
function pcvc_staff_contract_expected_page_count_from_xml(string $xml): int
{
    return max(1, pcvc_staff_contract_hard_page_break_count($xml) + 1);
}

/**
 * Count expected pages for a DOCX file on disk.
 */
function pcvc_staff_contract_expected_page_count(string $docxAbs): int
{
    $zip = new ZipArchive();
    if ($zip->open($docxAbs) !== true) {
        return 1;
    }
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    return $xml === '' ? 1 : pcvc_staff_contract_expected_page_count_from_xml($xml);
}

/**
 * Insert hard page breaks before canonical anchor paragraphs (matches ScholarSync Contract for Mutware.docx).
 */
function pcvc_staff_contract_inject_page_breaks_in_xml(string $xml): string
{
    $breakPara = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
    $anchors = pcvc_staff_contract_page_break_anchor_texts();

    if (!preg_match_all('/<w:p\b[^>]*>.*?<\/w:p>/s', $xml, $matches, PREG_OFFSET_CAPTURE)) {
        return $xml;
    }

    $usedAnchors = [];
    $shift = 0;
    foreach ($matches[0] as $paragraph) {
        $offset = $paragraph[1] + $shift;
        $text = pcvc_staff_contract_paragraph_text($paragraph[0]);
        if ($text === '') {
            continue;
        }

        foreach ($anchors as $anchor) {
            if (isset($usedAnchors[$anchor])) {
                continue;
            }
            if (strpos($text, $anchor) === false) {
                continue;
            }
            $before = substr($xml, max(0, $offset - 160), 160);
            if (strpos($before, 'w:type="page"') !== false) {
                $usedAnchors[$anchor] = true;
                break;
            }
            $xml = substr($xml, 0, $offset) . $breakPara . substr($xml, $offset);
            $shift += strlen($breakPara);
            $usedAnchors[$anchor] = true;
            break;
        }
    }

    return $xml;
}

/**
 * Remove hard page-break markers inside numbered list items (prevents empty bullet ghosts).
 * Keeps lastRenderedPageBreak hints on normal list rows so Word pagination matches the template.
 */
function pcvc_staff_contract_clean_docx_layout_in_xml(string $xml): string
{
    return preg_replace_callback(
        '/<w:p\b[^>]*>.*?<\/w:p>/s',
        static function (array $m): string {
            $p = $m[0];
            if (strpos($p, '<w:numPr>') === false || strpos($p, 'w:type="page"') === false) {
                return $p;
            }
            $p = preg_replace('/<w:br\s+w:type="page"\s*\/>/', '', $p) ?? $p;
            $p = preg_replace('/<w:lastRenderedPageBreak\s*\/>/', '', $p) ?? $p;

            return $p;
        },
        $xml
    );
}

/**
 * @deprecated Use pcvc_staff_contract_clean_docx_layout_in_xml()
 */
function pcvc_staff_contract_clean_list_page_breaks_in_xml(string $xml): string
{
    return pcvc_staff_contract_clean_docx_layout_in_xml($xml);
}

/**
 * Ensure canonical page breaks and list layout on an existing DOCX (no full rebuild).
 */
function pcvc_staff_contract_patch_docx_layout(string $docxAbs): void
{
    pcvc_staff_contract_finalize_docx_on_disk($docxAbs);
}

/**
 * Fill text placeholders by editing DOCX XML directly (preserves bullets, stamp, layout).
 *
 * @param array<string, string> $values
 */
function pcvc_staff_contract_fill_docx_text(string $docxAbs, array $values): void
{
    $zip = new ZipArchive();
    if ($zip->open($docxAbs) !== true) {
        throw new RuntimeException('Could not open contract document for text merge.');
    }

    $imageKeys = ['employer_signature', 'employee_signature'];
    $parts = ['word/document.xml'];

    foreach ($parts as $name) {
        $xml = $zip->getFromName($name);
        if ($xml === false || $xml === '') {
            continue;
        }
        $xml = pcvc_staff_contract_apply_placeholder_values($xml, $values, $imageKeys);
        $xml = pcvc_staff_contract_finalize_docx_xml($xml);
        $zip->deleteName($name);
        $zip->addFromString($name, $xml);
        unset($xml);
    }

    $zip->close();
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}

/**
 * @param array<string, mixed> $admin
 */
function pcvc_staff_contract_fill_docx(
    string $templateAbs,
    string $outputDocxAbs,
    array $admin,
    ?string $signingDate = null,
    ?string $signatureDataUrl = null
): void {
    if (!is_file($templateAbs)) {
        throw new RuntimeException('Contract Word template not found.');
    }

    pcvc_staff_contract_ensure_dirs();
    $outDir = dirname($outputDocxAbs);
    if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
        throw new RuntimeException('Could not create generated contract directory.');
    }

    $effectiveTemplate = pcvc_staff_contract_resolve_template_path($templateAbs);
    $preparedTemplate = pcvc_staff_contract_prepare_template($effectiveTemplate);
    try {
        if (!copy($preparedTemplate, $outputDocxAbs)) {
            throw new RuntimeException('Could not copy prepared contract template.');
        }

        $values = pcvc_staff_contract_merge_values($admin, $signingDate, $signatureDataUrl);
        pcvc_staff_contract_fill_docx_text($outputDocxAbs, $values);
        pcvc_staff_contract_apply_signature_images($outputDocxAbs, $signatureDataUrl);
    } finally {
        if (is_file($preparedTemplate)) {
            @unlink($preparedTemplate);
        }
    }
}

function pcvc_staff_contract_docx_to_pdf(string $docxAbs, string $pdfAbs): string
{
    if (!is_file($docxAbs)) {
        throw new RuntimeException('Generated contract document not found.');
    }

    $outDir = dirname($pdfAbs);
    if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
        throw new RuntimeException('Could not create PDF output directory.');
    }

    $errors = [];
    $converters = PHP_OS_FAMILY === 'Windows'
        ? [
            'pcvc_staff_contract_docx_to_pdf_msword',
            'pcvc_staff_contract_docx_to_pdf_vbscript',
            'pcvc_staff_contract_docx_to_pdf_libreoffice',
            'pcvc_staff_contract_docx_to_pdf_phpword',
        ]
        : [
            'pcvc_staff_contract_docx_to_pdf_libreoffice',
        ];

    $docxSize = filesize($docxAbs) ?: 0;
    $minPdfSize = max(400, (int) ($docxSize * 0.35));

    foreach ($converters as $converter) {
        if (!is_callable($converter)) {
            continue;
        }
        try {
            $converter($docxAbs, $pdfAbs);
            if (!is_file($pdfAbs)) {
                continue;
            }
            $pdfSize = filesize($pdfAbs) ?: 0;
            if ($pdfSize < 400) {
                $errors[] = $converter . ': PDF too small';
                @unlink($pdfAbs);
                continue;
            }
            if ($converter === 'pcvc_staff_contract_docx_to_pdf_phpword' && $pdfSize < $minPdfSize) {
                @unlink($pdfAbs);
                $errors[] = 'DomPDF output is too small — bullets/stamp may be missing.';
                continue;
            }
            return $converter;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    // Last resort on Windows only: basic DomPDF (shared hosting without Word/LibreOffice).
    if (PHP_OS_FAMILY === 'Windows') {
        try {
            pcvc_staff_contract_docx_to_pdf_phpword($docxAbs, $pdfAbs);
            if (is_file($pdfAbs) && filesize($pdfAbs) > 400) {
                return 'pcvc_staff_contract_docx_to_pdf_phpword_fallback';
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    throw new RuntimeException(
        'Could not convert contract to PDF. ' .
        (implode(' | ', $errors) ?: 'Install LibreOffice on the server (recommended for cPanel/Linux), or enable PHP exec().')
    );
}

function pcvc_staff_contract_pdf_engine_warning(string $engine): string
{
    if ($engine === 'pcvc_staff_contract_docx_to_pdf_phpword'
        || $engine === 'pcvc_staff_contract_docx_to_pdf_phpword_fallback') {
        return ' PDF was generated in basic mode (bullets/stamp may be simplified). '
            . 'For full Word layout, install LibreOffice on the server or regenerate from a Windows machine with Microsoft Word.';
    }
    return '';
}

function pcvc_staff_contract_docx_to_pdf_vbscript(string $docxAbs, string $pdfAbs): void
{
    if (PHP_OS_FAMILY !== 'Windows') {
        throw new RuntimeException('VBScript Word conversion is only available on Windows.');
    }

    $script = dirname(__DIR__) . '/tools/convert-docx-to-pdf.vbs';
    if (!is_file($script)) {
        throw new RuntimeException('Word VBScript converter missing.');
    }

    if (is_file($pdfAbs)) {
        @unlink($pdfAbs);
    }

    $cmd = 'cscript //nologo ' . escapeshellarg($script)
        . ' ' . escapeshellarg($docxAbs)
        . ' ' . escapeshellarg($pdfAbs);

    @exec($cmd . ' 2>&1', $output, $code);
    if (!is_file($pdfAbs) || filesize($pdfAbs) < 400) {
        throw new RuntimeException(
            'VBScript Word conversion failed' . ($output ? ': ' . implode(' ', $output) : '.')
        );
    }
}

function pcvc_staff_contract_docx_to_pdf_msword(string $docxAbs, string $pdfAbs): void
{
    if (PHP_OS_FAMILY !== 'Windows') {
        throw new RuntimeException('Microsoft Word conversion is only available on Windows.');
    }

    $script = dirname(__DIR__) . '/tools/convert-docx-to-pdf.ps1';
    if (!is_file($script)) {
        throw new RuntimeException('Word conversion script missing.');
    }

    $outDir = dirname($pdfAbs);
    if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
        throw new RuntimeException('Could not create PDF output directory.');
    }
    if (is_file($pdfAbs)) {
        @unlink($pdfAbs);
    }

    $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File '
        . escapeshellarg($script)
        . ' -DocxPath ' . escapeshellarg($docxAbs)
        . ' -PdfPath ' . escapeshellarg($pdfAbs);

    @exec($cmd . ' 2>&1', $output, $code);
    if (!is_file($pdfAbs) || filesize($pdfAbs) < 400) {
        throw new RuntimeException(
            'Microsoft Word PDF conversion failed' . ($output ? ': ' . implode(' ', $output) : '.')
        );
    }
}

function pcvc_staff_contract_docx_to_pdf_phpword(string $docxAbs, string $pdfAbs): void
{
    pcvc_staff_contract_require_autoload();
    if (!class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
        throw new RuntimeException('PhpWord library missing.');
    }

    $dompdfPath = dirname(__DIR__) . '/vendor/dompdf/dompdf';
    if (!is_dir($dompdfPath)) {
        throw new RuntimeException('DomPDF library missing.');
    }

    \PhpOffice\PhpWord\Settings::setPdfRendererName(\PhpOffice\PhpWord\Settings::PDF_RENDERER_DOMPDF);
    \PhpOffice\PhpWord\Settings::setPdfRendererPath($dompdfPath);

    $phpWord = \PhpOffice\PhpWord\IOFactory::load($docxAbs);
    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'PDF');
    $writer->save($pdfAbs);
    unset($phpWord, $writer);
}

function pcvc_staff_contract_docx_to_pdf_libreoffice(string $docxAbs, string $pdfAbs): void
{
    $candidates = [
        'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        '/usr/bin/libreoffice',
        '/usr/bin/soffice',
        '/usr/local/bin/libreoffice',
        '/usr/local/bin/soffice',
        '/snap/bin/libreoffice',
        'soffice',
        'libreoffice',
    ];

    $outDir = dirname($pdfAbs);
    $escapedOut = escapeshellarg($outDir);
    $escapedDocx = escapeshellarg($docxAbs);

    foreach ($candidates as $bin) {
        if ($bin !== 'soffice' && $bin !== 'libreoffice' && !is_file($bin)) {
            continue;
        }
        $cmd = escapeshellarg($bin) . ' --headless --convert-to pdf --outdir ' . $escapedOut . ' ' . $escapedDocx;
        @exec($cmd . ' 2>&1', $output, $code);
        $base = pathinfo($docxAbs, PATHINFO_FILENAME);
        $generated = $outDir . '/' . $base . '.pdf';
        if (is_file($generated)) {
            if ($generated !== $pdfAbs) {
                @rename($generated, $pdfAbs);
            }
            if (is_file($pdfAbs)) {
                return;
            }
        }
    }

    throw new RuntimeException('LibreOffice conversion unavailable.');
}

/**
 * Fetch full admin row for merge.
 *
 * @return array<string, mixed>|null
 */
function pcvc_staff_contract_admin_row(mysqli $conn, int $adminId): ?array
{
    $stmt = $conn->prepare(
        'SELECT id, username, first_name, last_name, full_name, email, phone_number, role,
                position, employment_type, employment_start_date, national_id, date_of_birth,
                marital_status, nationality, place_of_birth, address, monthly_salary, salary_currency
         FROM admins WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Resolve a downloadable contract file (rebuilds missing signed/filled DOCX when needed).
 *
 * @return array{rel:string, mime:string, ext:string}
 */
function pcvc_staff_contract_resolve_download(
    mysqli $conn,
    int $staffId,
    array $contract,
    string $type,
    string $format
): array {
    $type = $type === 'source' ? 'source' : 'signed';
    $format = $format === 'docx' ? 'docx' : 'pdf';
    $useDocxPreview = pcvc_staff_contract_use_docx_preview();

    if ($type === 'signed' && $format === 'pdf' && $useDocxPreview) {
        $format = 'docx';
    }

    if ($format === 'docx') {
        $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $ext = 'docx';
        try {
            $rel = pcvc_staff_contract_ensure_valid_docx($conn, $staffId, $contract, $type);
        } catch (Throwable $e) {
            throw new RuntimeException('Contract not ready: ' . $e->getMessage(), 0, $e);
        }
        if ($rel === '') {
            throw new RuntimeException('Word contract not available');
        }
        return ['rel' => $rel, 'mime' => $mime, 'ext' => $ext];
    }

    $rel = $type === 'signed'
        ? pcvc_staff_contract_signed_path($contract)
        : trim((string) ($contract['source_pdf_path'] ?? ''));

    if ($rel === '' && $type === 'signed') {
        try {
            $docxRel = pcvc_staff_contract_ensure_valid_docx($conn, $staffId, $contract, 'signed');
        } catch (Throwable $e) {
            throw new RuntimeException('Signed contract not available: ' . $e->getMessage(), 0, $e);
        }
        if ($docxRel !== '') {
            return [
                'rel' => $docxRel,
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'ext' => 'docx',
            ];
        }
    }

    if ($rel === '') {
        throw new RuntimeException('File not available');
    }

    $abs = pcvc_staff_contract_abs_path($rel);
    if (!is_file($abs)) {
        throw new RuntimeException('File missing on server');
    }

    return ['rel' => $rel, 'mime' => 'application/pdf', 'ext' => 'pdf'];
}

/**
 * Return a valid filled or signed DOCX path, rebuilding truncated/corrupt files automatically.
 */
function pcvc_staff_contract_ensure_valid_docx(
    mysqli $conn,
    int $staffId,
    array $contract,
    string $type = 'source'
): string {
    $type = $type === 'signed' ? 'signed' : 'source';
    if ($type === 'source' && pcvc_staff_contract_row_status($contract)['code'] === 'signed') {
        $type = 'signed';
    }
    $rel = $type === 'signed'
        ? pcvc_staff_contract_signed_docx_path($contract)
        : pcvc_staff_contract_preview_docx_path($contract);

    $needsRebuild = static function (string $pathRel): bool {
        if ($pathRel === '') {
            return true;
        }
        $abs = pcvc_staff_contract_abs_path($pathRel);
        return !is_file($abs) || pcvc_staff_contract_docx_is_corrupt($abs);
    };

    if (!$needsRebuild($rel)) {
        return $rel;
    }

    @set_time_limit(120);

    if ($type === 'signed' && pcvc_staff_contract_row_status($contract)['code'] === 'signed') {
        pcvc_staff_contract_regenerate($conn, $staffId, $contract, 'signed');
    } else {
        pcvc_staff_contract_generate_preview($conn, $staffId, $contract, null, false);
    }

    $contract = pcvc_staff_contract_for_admin($conn, $staffId);
    if (!$contract) {
        return '';
    }

    return $type === 'signed'
        ? pcvc_staff_contract_signed_docx_path($contract)
        : pcvc_staff_contract_preview_docx_path($contract);
}

/**
 * Generate prefilled DOCX and optionally preview PDF from stored Word template.
 *
 * @return array{filled_docx:string, preview_pdf:?string, position_warning?:string, pdf_warning?:string}
 */
function pcvc_staff_contract_generate_preview(
    mysqli $conn,
    int $adminId,
    array $contract,
    ?string $signingDate = null,
    bool $makePdf = true
): array {
    $admin = pcvc_staff_contract_admin_row($conn, $adminId);
    if (!$admin) {
        throw new RuntimeException('Staff account not found.');
    }

    $docxRel = trim((string) ($contract['source_docx_path'] ?? ''));
    if ($docxRel === '') {
        throw new RuntimeException('No Word contract template uploaded.');
    }

    $docxAbs = pcvc_staff_contract_abs_path($docxRel);
    $stamp = time();
    $filledDocxRel = 'uploads/staff_contracts/generated/filled_' . $adminId . '_' . $stamp . '.docx';
    $previewPdfRel = 'uploads/staff_contracts/generated/preview_' . $adminId . '_' . $stamp . '.pdf';
    $filledDocxAbs = pcvc_staff_contract_abs_path($filledDocxRel);
    $previewPdfAbs = pcvc_staff_contract_abs_path($previewPdfRel);

    pcvc_staff_contract_fill_docx($docxAbs, $filledDocxAbs, $admin, $signingDate, null);

    $engine = '';
    $pdfWarning = '';
    if ($makePdf && !pcvc_staff_contract_use_docx_preview()) {
        $engine = pcvc_staff_contract_docx_to_pdf($filledDocxAbs, $previewPdfAbs);
        $pdfWarning = pcvc_staff_contract_pdf_engine_warning($engine);
    } else {
        $previewPdfRel = null;
    }

    $positionWarning = '';
    if (pcvc_staff_contract_resolve_position($admin) === '') {
        $positionWarning = ' Note: staff Position is empty in Staff Management — fill Position and save, then regenerate the contract.';
    }

    $oldFilled = trim((string) ($contract['filled_docx_path'] ?? ''));
    $oldPreview = trim((string) ($contract['source_pdf_path'] ?? ''));
    if ($oldFilled !== '' && $oldFilled !== $filledDocxRel) {
        $oldAbs = pcvc_staff_contract_abs_path($oldFilled);
        if (is_file($oldAbs)) {
            @unlink($oldAbs);
        }
    }
    if ($makePdf && $oldPreview !== '' && $oldPreview !== $previewPdfRel && ($contract['status'] ?? '') !== 'signed') {
        $oldAbs = pcvc_staff_contract_abs_path($oldPreview);
        if (is_file($oldAbs)) {
            @unlink($oldAbs);
        }
    }

    $contractId = (int) ($contract['id'] ?? 0);
    if ($makePdf) {
        $stmt = $conn->prepare(
            'UPDATE employment_contracts
             SET filled_docx_path = ?, source_pdf_path = ?
             WHERE admin_id = ? AND id = ?'
        );
        if (!$stmt) {
            throw new RuntimeException('Database error');
        }
        $stmt->bind_param('ssii', $filledDocxRel, $previewPdfRel, $adminId, $contractId);
    } else {
        $stmt = $conn->prepare(
            'UPDATE employment_contracts
             SET filled_docx_path = ?
             WHERE admin_id = ? AND id = ?'
        );
        if (!$stmt) {
            throw new RuntimeException('Database error');
        }
        $stmt->bind_param('sii', $filledDocxRel, $adminId, $contractId);
    }
    $stmt->execute();
    $stmt->close();

    return [
        'filled_docx' => $filledDocxRel,
        'preview_pdf' => $previewPdfRel,
        'position_warning' => $positionWarning,
        'pdf_warning' => $pdfWarning,
    ];
}

/**
 * Generate final signed contract from Word template + signature.
 *
 * @return array{docx:string, pdf:?string}
 */
function pcvc_staff_contract_generate_signed(
    mysqli $conn,
    int $adminId,
    array $contract,
    string $signatureDataUrl,
    string $typedName,
    string $signedDate
): array {
    $admin = pcvc_staff_contract_admin_row($conn, $adminId);
    if (!$admin) {
        throw new RuntimeException('Staff account not found.');
    }
    if ($typedName !== '') {
        $admin['full_name'] = $typedName;
    }

    $docxRel = trim((string) ($contract['source_docx_path'] ?? ''));
    if ($docxRel === '') {
        throw new RuntimeException('No Word contract template uploaded.');
    }

    if (!pcvc_staff_contract_use_docx_preview()) {
        pcvc_staff_contract_require_pdf_helpers();
    } else {
        require_once __DIR__ . '/contract_signature_image.php';
    }
    pcvc_staff_contract_ensure_dirs();
    $docxAbs = pcvc_staff_contract_abs_path($docxRel);
    $stamp = time();
    $signedDocxRel = 'uploads/staff_contracts/signed/signed_staff_' . $adminId . '_' . $stamp . '.docx';
    $signedPdfRel = 'uploads/staff_contracts/signed/signed_staff_' . $adminId . '_' . $stamp . '.pdf';
    $signedDocxAbs = pcvc_staff_contract_abs_path($signedDocxRel);
    $signedPdfAbs = pcvc_staff_contract_abs_path($signedPdfRel);

    pcvc_staff_contract_fill_docx($docxAbs, $signedDocxAbs, $admin, $signedDate, $signatureDataUrl);

    $signedPdfOut = null;
    if (!pcvc_staff_contract_use_docx_preview()) {
        $previewPdfAbs = pcvc_staff_contract_abs_path(
            'uploads/staff_contracts/generated/tmp_sign_' . $adminId . '_' . $stamp . '.pdf'
        );
        pcvc_staff_contract_docx_to_pdf($signedDocxAbs, $previewPdfAbs);
        try {
            pcvc_staff_contract_stamp_employee_signature_pdf($previewPdfAbs, $signatureDataUrl, $signedPdfAbs);
            $signedPdfOut = $signedPdfRel;
        } catch (Throwable $e) {
            if (is_file($previewPdfAbs)) {
                @copy($previewPdfAbs, $signedPdfAbs);
                if (is_file($signedPdfAbs)) {
                    $signedPdfOut = $signedPdfRel;
                }
            }
            if ($signedPdfOut === null) {
                throw new RuntimeException('Could not stamp signature on contract PDF: ' . $e->getMessage());
            }
        }
        if (is_file($previewPdfAbs)) {
            @unlink($previewPdfAbs);
        }
    }

    return [
        'docx' => $signedDocxRel,
        'pdf' => $signedPdfOut,
    ];
}

/**
 * Rebuild preview or signed PDF from stored template + current profile data.
 *
 * @return array{message:string, preview_pdf?:string, signed_pdf?:string}
 */
function pcvc_staff_contract_regenerate(
    mysqli $conn,
    int $adminId,
    array $contract,
    string $mode = 'preview'
): array {
    $mode = $mode === 'signed' ? 'signed' : 'preview';

    if ($mode === 'signed') {
        if (($contract['status'] ?? '') !== 'signed') {
            throw new RuntimeException('Contract is not signed yet.');
        }
        $sigRel = trim((string) ($contract['signature_file'] ?? ''));
        $sigAbs = $sigRel !== '' ? pcvc_staff_contract_abs_path($sigRel) : '';
        if ($sigAbs === '' || !is_file($sigAbs)) {
            throw new RuntimeException('Stored signature image not found — cannot rebuild signed PDF.');
        }
        $signatureDataUrl = 'data:image/png;base64,' . base64_encode((string) file_get_contents($sigAbs));
        $signed = pcvc_staff_contract_generate_signed(
            $conn,
            $adminId,
            $contract,
            $signatureDataUrl,
            trim((string) ($contract['staff_typed_name'] ?? '')),
            !empty($contract['signed_at']) ? date('Y-m-d', strtotime((string) $contract['signed_at'])) : date('Y-m-d')
        );

        $oldSignedPdf = trim((string) ($contract['signed_pdf_path'] ?? ''));
        if ($oldSignedPdf !== '' && $signed['pdf'] !== null && $oldSignedPdf !== $signed['pdf']) {
            $oldAbs = pcvc_staff_contract_abs_path($oldSignedPdf);
            if (is_file($oldAbs)) {
                @unlink($oldAbs);
            }
        }
        $oldSignedDocx = trim((string) ($contract['signed_docx_path'] ?? ''));
        if ($oldSignedDocx !== '' && $oldSignedDocx !== $signed['docx']) {
            $oldAbs = pcvc_staff_contract_abs_path($oldSignedDocx);
            if (is_file($oldAbs)) {
                @unlink($oldAbs);
            }
        }

        $contractId = (int) ($contract['id'] ?? 0);
        $signedPdf = $signed['pdf'] ?? '';
        $stmt = $conn->prepare(
            'UPDATE employment_contracts SET signed_docx_path = ?, signed_pdf_path = ?, pdf_path = ? WHERE admin_id = ? AND id = ?'
        );
        if (!$stmt) {
            throw new RuntimeException('Database error');
        }
        $stmt->bind_param('sssii', $signed['docx'], $signedPdf, $signedPdf, $adminId, $contractId);
        $stmt->execute();
        $stmt->close();

        $message = pcvc_staff_contract_use_docx_preview()
            ? 'Signed contract Word file regenerated with current staff details.'
            : 'Signed contract PDF regenerated with current staff details.';

        return [
            'message' => $message,
            'signed_pdf' => $signedPdf !== '' ? $signedPdf : null,
            'signed_docx' => $signed['docx'],
        ];
    }

    $makePdf = !pcvc_staff_contract_use_docx_preview();
    $preview = pcvc_staff_contract_generate_preview($conn, $adminId, $contract, null, $makePdf);
    $message = $makePdf
        ? 'Contract preview PDF regenerated.'
        : 'Contract preview Word file regenerated.';
    if (!empty($preview['position_warning'])) {
        $message .= $preview['position_warning'];
    }
    if (!empty($preview['pdf_warning'])) {
        $message .= $preview['pdf_warning'];
    }

    return [
        'message' => $message,
        'preview_pdf' => $preview['preview_pdf'],
        'position_warning' => $preview['position_warning'] ?? '',
        'pdf_warning' => $preview['pdf_warning'] ?? '',
    ];
}
