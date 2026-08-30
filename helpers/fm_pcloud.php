<?php
declare(strict_types=1);

/**
 * Francophonie Mobility — pCloud helpers for candidate intro videos.
 * Folder ID is fixed for this feature (do not change without ops approval).
 */

const FM_PCLOUD_FOLDER_ID = 32332888671;
const FM_PCLOUD_TOKEN = 'kqNT7Z8BpwhA0d4MFZVgju0kZbR12PpsX93VWhpTOL5i4jVefcDdX';

function fm_pcloud_token(): string
{
    return FM_PCLOUD_TOKEN;
}

function fm_pcloud_folder_id(): int
{
    return FM_PCLOUD_FOLDER_ID;
}

/**
 * @return array{ok:bool,response:?array,error?:string}
 */
function fm_pcloud_request(string $path, array $query = [], array $postFiles = []): array
{
    $query['access_token'] = fm_pcloud_token();
    $url = 'https://api.pcloud.com/' . ltrim($path, '/');
    if ($query !== [] && $postFiles === []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 600,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ];

    if ($postFiles !== []) {
        $fields = $query;
        foreach ($postFiles as $key => $file) {
            $fields[$key] = $file;
        }
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $fields;
    }

    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $raw === '') {
        return ['ok' => false, 'response' => null, 'error' => $err !== '' ? $err : 'Empty pCloud response'];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'response' => null, 'error' => 'Invalid JSON from pCloud'];
    }
    if ((int) ($json['result'] ?? -1) !== 0) {
        return [
            'ok' => false,
            'response' => $json,
            'error' => (string) ($json['error'] ?? ('pCloud error #' . ($json['result'] ?? '?'))),
        ];
    }

    return ['ok' => true, 'response' => $json];
}

/**
 * Upload a local file into the Francophonie Mobility pCloud folder.
 *
 * @return array{ok:bool,fileid?:string,name?:string,error?:string,response?:array}
 */
function fm_pcloud_upload_file(string $absPath, string $remoteName): array
{
    if (!is_file($absPath)) {
        return ['ok' => false, 'error' => 'Local video file missing'];
    }

    $mime = mime_content_type($absPath) ?: 'application/octet-stream';
    $cfile = curl_file_create($absPath, $mime, $remoteName);

    $result = fm_pcloud_request('uploadfile', [
        'folderid' => fm_pcloud_folder_id(),
        'nopartial' => 1,
        'renameifexists' => 1,
    ], ['file' => $cfile]);

    if (!$result['ok']) {
        return [
            'ok' => false,
            'error' => $result['error'] ?? 'Upload failed',
            'response' => $result['response'] ?? null,
        ];
    }

    $meta = $result['response']['metadata'][0] ?? $result['response']['metadata'] ?? null;
    if (!is_array($meta)) {
        return ['ok' => false, 'error' => 'pCloud upload succeeded but metadata missing', 'response' => $result['response']];
    }

    $fileId = (string) ($meta['fileid'] ?? '');
    if ($fileId === '') {
        return ['ok' => false, 'error' => 'pCloud file id missing', 'response' => $result['response']];
    }

    return [
        'ok' => true,
        'fileid' => $fileId,
        'name' => (string) ($meta['name'] ?? $remoteName),
        'response' => $result['response'],
    ];
}

/**
 * Create / fetch a public link for a pCloud file.
 *
 * @return array{ok:bool,link?:string,code?:string,error?:string}
 */
function fm_pcloud_public_link(string $fileId): array
{
    $fileId = trim($fileId);
    if ($fileId === '') {
        return ['ok' => false, 'error' => 'Missing file id'];
    }

    $result = fm_pcloud_request('getfilepublink', ['fileid' => $fileId]);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'] ?? 'Could not create public link'];
    }

    $link = (string) ($result['response']['link'] ?? '');
    $code = (string) ($result['response']['code'] ?? '');
    if ($link === '' && $code !== '') {
        $link = 'https://u.pcloud.link/publink/show?code=' . rawurlencode($code);
    }
    if ($link === '') {
        return ['ok' => false, 'error' => 'Public link empty'];
    }

    return ['ok' => true, 'link' => $link, 'code' => $code];
}

/**
 * Best-effort direct download URL for a public link code.
 */
function fm_pcloud_direct_download(string $code): string
{
    $code = trim($code);
    if ($code === '') {
        return '';
    }
    $result = fm_pcloud_request('getpublinkdownload', ['code' => $code]);
    if (!$result['ok']) {
        return '';
    }
    $hosts = $result['response']['hosts'] ?? [];
    $path = (string) ($result['response']['path'] ?? '');
    if (!is_array($hosts) || $hosts === [] || $path === '') {
        return '';
    }
    $host = (string) $hosts[0];

    return 'https://' . $host . $path;
}
