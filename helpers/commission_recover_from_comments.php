<?php
declare(strict_types=1);

require_once __DIR__ . '/commission_currency.php';

/**
 * Try to read a USD amount from free-text commission comments.
 */
function pcvc_parse_usd_from_comment_text(string $text): ?float
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }

    // $500 or $1,234.56
    if (preg_match('/\$\s*([\d]{1,3}(?:,\d{3})*(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?)/u', $text, $m)) {
        $n = (float) str_replace(',', '', $m[1]);
        if ($n > 0 && $n < 1e8) {
            return $n;
        }
    }

    // USD 500 or USD: 500
    if (preg_match('/USD\s*[:]?\s*([\d]{1,3}(?:,\d{3})*(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?)/iu', $text, $m)) {
        $n = (float) str_replace(',', '', $m[1]);
        if ($n > 0 && $n < 1e8) {
            return $n;
        }
    }

    // 500 USD
    if (preg_match('/([\d]{1,3}(?:,\d{3})*(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?)\s*USD\b/iu', $text, $m)) {
        $n = (float) str_replace(',', '', $m[1]);
        if ($n > 0 && $n < 1e8) {
            return $n;
        }
    }

    return null;
}

/**
 * If comments mention RWF only, derive USD using the current checkout rate.
 */
function pcvc_parse_usd_from_rwf_in_comment(string $text): ?float
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }

    if (!preg_match('/RWF|FRW/i', $text)) {
        return null;
    }

    if (preg_match('/(?:RWF|FRW)\s*[:]?\s*([\d][\d,\s]*)/iu', $text, $m)) {
        $rwf = (float) str_replace([',', ' ', "\u{00a0}"], '', $m[1]);
        if ($rwf < 500) {
            return null;
        }
        $conv = pcvc_usd_to_rwf_conversion(1.0);
        $rate = (float) ($conv['rate'] ?? 0);
        if ($rate <= 0) {
            return null;
        }

        return round($rwf / $rate, 2);
    }

    return null;
}

/**
 * @return array{usd: float, source: string}|null
 */
function pcvc_parse_commission_amount_from_comment(?string $comments): ?array
{
    if ($comments === null) {
        return null;
    }
    $comments = trim($comments);
    if ($comments === '') {
        return null;
    }

    $usd = pcvc_parse_usd_from_comment_text($comments);
    if ($usd !== null) {
        return ['usd' => $usd, 'source' => 'usd_in_text'];
    }

    $usd = pcvc_parse_usd_from_rwf_in_comment($comments);
    if ($usd !== null) {
        return ['usd' => $usd, 'source' => 'rwf_in_text'];
    }

    return null;
}

/**
 * Preview or apply: fill amount_usd / amount_rwf / fx_rate_used where USD is zero and comments parse.
 *
 * @return array{preview: list<array{id:int,parsed_usd:float,source:string,snippet:string}>, updated: int, skipped: int}
 */
function pcvc_recover_commission_amounts_from_comments(mysqli $conn, bool $dryRun): array
{
    $preview = [];
    $updated = 0;
    $skipped = 0;

    $sql = 'SELECT id, comments, amount_usd, amount_rwf FROM commission_requests
            WHERE (amount_usd IS NULL OR amount_usd <= 0)
            AND comments IS NOT NULL AND TRIM(comments) != ""';
    $res = $conn->query($sql);
    if (!$res) {
        return ['preview' => [], 'updated' => 0, 'skipped' => 0];
    }

    while ($row = $res->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        $parsed = pcvc_parse_commission_amount_from_comment((string) ($row['comments'] ?? ''));
        if ($parsed === null) {
            ++$skipped;
            continue;
        }
        $usd = (float) $parsed['usd'];
        $conv = pcvc_usd_to_rwf_conversion($usd);
        $rwf = (float) $conv['rwf'];
        $rate = (float) $conv['rate'];
        $snippet = mb_substr(preg_replace('/\s+/', ' ', (string) $row['comments']), 0, 120);

        $preview[] = [
            'id' => $id,
            'parsed_usd' => $usd,
            'source' => $parsed['source'],
            'snippet' => $snippet,
        ];

        if (!$dryRun) {
            $u = $conn->prepare(
                'UPDATE commission_requests SET amount_usd = ?, amount_rwf = ?, fx_rate_used = ? WHERE id = ? LIMIT 1'
            );
            if ($u) {
                $u->bind_param('dddi', $usd, $rwf, $rate, $id);
                if ($u->execute()) {
                    ++$updated;
                }
                $u->close();
            }
        }
    }
    $res->free();

    return ['preview' => $preview, 'updated' => $updated, 'skipped' => $skipped];
}
