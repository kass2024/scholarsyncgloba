<?php
/**
 * Allowed pCloud folders for Marketing Materials (get + upload).
 * No other folder may be listed or written to by the materials pages.
 */
if (!defined('PCVC_MATERIALS_PCLOUD_TOKEN')) {
    define('PCVC_MATERIALS_PCLOUD_TOKEN', 'kqNT7Z8BpwhA0d4MFZVgju0kZbR12PpsX93VWhpTOL5i4jVefcDdX');
}

/** @return int[] */
function pcvc_materials_allowed_folder_ids(): array
{
    return [
        30447221155,
        29604175723,
    ];
}

function pcvc_materials_is_allowed_folder(int $folderId): bool
{
    return in_array($folderId, pcvc_materials_allowed_folder_ids(), true);
}

/**
 * Flatten files from a pCloud listfolder metadata tree.
 *
 * @param array $items
 * @param array $out
 * @param int|null $sourceFolderId
 */
function pcvc_materials_flatten_files(array $items, array &$out, ?int $sourceFolderId = null): void
{
    foreach ($items as $i) {
        if (empty($i['isfolder'])) {
            if ($sourceFolderId !== null) {
                $i['source_folderid'] = $sourceFolderId;
            }
            $out[] = $i;
            continue;
        }
        if (!empty($i['contents']) && is_array($i['contents'])) {
            pcvc_materials_flatten_files($i['contents'], $out, $sourceFolderId);
        }
    }
}

/**
 * List files from one allowed materials folder (recursive).
 *
 * @return array{ok:bool, files:array, name?:string, error?:string}
 */
function pcvc_materials_list_folder(int $folderId, bool $recursive = true): array
{
    if (!pcvc_materials_is_allowed_folder($folderId)) {
        return ['ok' => false, 'files' => [], 'error' => 'Folder not allowed'];
    }

    $token = PCVC_MATERIALS_PCLOUD_TOKEN;
    $rec = $recursive ? 1 : 0;
    $listUrl = "https://api.pcloud.com/listfolder?folderid={$folderId}&recursive={$rec}&access_token={$token}";
    $res = @file_get_contents($listUrl);
    $json = $res ? json_decode($res, true) : null;

    if (!$json || !isset($json['metadata'])) {
        return ['ok' => false, 'files' => [], 'error' => 'Failed to list folder'];
    }

    $files = [];
    $contents = $json['metadata']['contents'] ?? [];
    if (is_array($contents)) {
        pcvc_materials_flatten_files($contents, $files, $folderId);
    }

    return [
        'ok' => true,
        'files' => $files,
        'name' => (string)($json['metadata']['name'] ?? ('Folder ' . $folderId)),
    ];
}

/**
 * List and merge files from all allowed materials folders.
 *
 * @return array{images:array, videos:array, others:array, folders:array}
 */
function pcvc_materials_list_all(): array
{
    $images = [];
    $videos = [];
    $others = [];
    $folders = [];
    $seenFileIds = [];

    foreach (pcvc_materials_allowed_folder_ids() as $folderId) {
        $result = pcvc_materials_list_folder($folderId, true);
        $folders[] = [
            'folderid' => $folderId,
            'name' => $result['name'] ?? ('Folder ' . $folderId),
            'ok' => $result['ok'],
        ];

        if (!$result['ok']) {
            continue;
        }

        foreach ($result['files'] as $f) {
            $fileId = isset($f['fileid']) ? (int)$f['fileid'] : 0;
            if ($fileId > 0) {
                if (isset($seenFileIds[$fileId])) {
                    continue;
                }
                $seenFileIds[$fileId] = true;
            }

            $ext = strtolower(pathinfo((string)($f['name'] ?? ''), PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $images[] = $f;
            } elseif (in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'mkv'], true)) {
                $videos[] = $f;
            } else {
                $others[] = $f;
            }
        }
    }

    return [
        'images' => $images,
        'videos' => $videos,
        'others' => $others,
        'folders' => $folders,
    ];
}
