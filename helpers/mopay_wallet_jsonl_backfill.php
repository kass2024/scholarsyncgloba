<?php
declare(strict_types=1);

require_once __DIR__ . '/mopay_wallet_transactions.php';

/**
 * @return array{imported:int, skipped:int, errors:list<string>}
 */
function pcvc_mopay_wallet_tx_exists(mysqli $conn, string $gatewayId, string $direction): bool
{
    $gatewayId = trim($gatewayId);
    if ($gatewayId === '') {
        return true;
    }
    $st = $conn->prepare('SELECT 1 FROM mopay_wallet_transactions WHERE gateway_transaction_id = ? AND direction = ? LIMIT 1');
    if (!$st) {
        return true;
    }
    $st->bind_param('ss', $gatewayId, $direction);
    $st->execute();
    $ok = $st->get_result()->fetch_row() !== null;
    $st->close();

    return $ok;
}

function pcvc_mopay_backfill_status_ok($http): bool
{
    if (!is_numeric($http)) {
        return false;
    }

    $c = (int) $http;

    return $c >= 200 && $c < 300;
}

function pcvc_mopay_backfill_webhook_success($status): bool
{
    if (is_int($status) || (is_string($status) && ctype_digit(trim((string) $status)))) {
        return (int) $status >= 200 && (int) $status < 300;
    }
    if (!is_string($status)) {
        return false;
    }
    $s = strtoupper(trim($status));

    return in_array($s, ['SUCCESS', 'SUCCESSFUL', 'SUCCEEDED', 'COMPLETED', 'PAID', 'APPROVED', 'OK'], true);
}

/**
 * Merge rows from local JSONL into mopay_wallet_transactions (idempotent).
 *
 * @return array{imported:int, skipped:int, errors:list<string>, detail:list<string>}
 */
function pcvc_mopay_backfill_from_local_logs(mysqli $conn): array
{
    pcvc_ensure_mopay_wallet_transactions_schema($conn);

    $imported = 0;
    $skipped = 0;
    $errors = [];
    $detail = [];

    $root = dirname(__DIR__) . '/payments/mopay';
    $txFile = $root . '/storage/transactions.jsonl';
    $whFile = $root . '/logs/webhook.log.jsonl';

    // 1) Webhook log (best amounts for inbound completions)
    if (is_readable($whFile)) {
        $lines = @file($whFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $lineNum => $line) {
                $j = json_decode($line, true);
                if (!is_array($j) || ($j['type'] ?? '') !== 'webhook_received') {
                    continue;
                }
                $tid = trim((string) ($j['transactionId'] ?? ''));
                if ($tid === '') {
                    $skipped++;
                    continue;
                }
                if (pcvc_mopay_wallet_tx_exists($conn, $tid, 'inbound')) {
                    $skipped++;
                    continue;
                }
                $st = $j['status'] ?? null;
                if (!pcvc_mopay_backfill_webhook_success($st)) {
                    $skipped++;
                    continue;
                }
                $payload = $j['decoded_payload'] ?? null;
                $data = null;
                if (is_array($payload) && array_key_exists('data', $payload)) {
                    $data = $payload['data'];
                    if (is_string($data) && $data !== '' && (strpos(ltrim($data), '{') === 0 || strpos(ltrim($data), '[') === 0)) {
                        $decoded = json_decode($data, true);
                        if (is_array($decoded)) {
                            $data = $decoded;
                        }
                    }
                }
                if (!is_array($data)) {
                    $data = [];
                }
                $amt = isset($j['amount']) ? (float) $j['amount'] : (isset($data['amount']) ? (float) $data['amount'] : 0.0);
                $amountRwf = (int) round($amt);
                $payer = pcvc_mopay_webhook_payer_digits($data);
                $cur = isset($j['currency']) ? (string) $j['currency'] : 'RWF';

                pcvc_mopay_wallet_tx_insert($conn, [
                    'direction' => 'inbound',
                    'context_type' => 'mopay_checkout',
                    'context_id' => $tid,
                    'context_label' => 'Imported from webhook.log.jsonl',
                    'initiated_by_admin_id' => 0,
                    'recipient_msisdn' => $payer,
                    'amount_rwf' => $amountRwf,
                    'status' => 'success',
                    'gateway_transaction_id' => $tid,
                    'mopay_flow' => 'webhook_import',
                    'gateway_response_json' => strlen($line) > 12000 ? substr($line, 0, 12000) . '…' : $line,
                    'meta' => ['source' => 'backfill_webhook_log', 'currency_reported' => strtoupper($cur)],
                ]);
                $imported++;
                $detail[] = 'webhook log: inbound ' . $tid;
            }
        }
    }

    // 2) transactions.jsonl (initiate + salary attempts)
    if (is_readable($txFile)) {
        $lines = @file($txFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $j = json_decode($line, true);
                if (!is_array($j)) {
                    continue;
                }
                $type = (string) ($j['type'] ?? '');

                if ($type === 'debit_initiate') {
                    $tid = trim((string) ($j['transactionId'] ?? ''));
                    if ($tid === '' || pcvc_mopay_wallet_tx_exists($conn, $tid, 'inbound')) {
                        $skipped++;
                        continue;
                    }
                    $http = $j['response_http'] ?? 0;
                    $req = isset($j['request']) && is_array($j['request']) ? $j['request'] : [];
                    $amt = (int) round((float) ($req['amount'] ?? 0));
                    $payerDigits = null;
                    if (isset($req['account_no'])) {
                        $payerDigits = pcvc_mopay_webhook_payer_digits(['account_no' => $req['account_no']]);
                    }
                    $feeMeta = isset($j['fee_checkout_meta']) && is_array($j['fee_checkout_meta']) ? $j['fee_checkout_meta'] : null;
                    $ctxType = $feeMeta ? 'fee_checkout' : 'mopay_checkout';
                    $label = $feeMeta
                        ? 'Imported debit_initiate (fee checkout)'
                        : 'Imported debit_initiate (checkout)';
                    $ok = pcvc_mopay_backfill_status_ok($http);
                    pcvc_mopay_wallet_tx_insert($conn, [
                        'direction' => 'inbound',
                        'context_type' => $ctxType,
                        'context_id' => $tid,
                        'context_label' => $label,
                        'initiated_by_admin_id' => 0,
                        'recipient_msisdn' => $payerDigits,
                        'amount_rwf' => $amt,
                        'status' => $ok ? 'success' : 'failed',
                        'gateway_transaction_id' => $tid,
                        'mopay_flow' => 'jsonl_debit_initiate',
                        'http_status' => is_numeric($http) ? (int) $http : null,
                        'error_message' => $ok ? null : ('HTTP ' . (string) $http),
                        'gateway_response_json' => strlen($line) > 12000 ? substr($line, 0, 12000) . '…' : $line,
                        'meta' => array_merge(
                            ['source' => 'backfill_transactions_jsonl', 'response_http' => $http],
                            $feeMeta ? ['fee_checkout_meta' => $feeMeta] : []
                        ),
                    ]);
                    $imported++;
                    $detail[] = 'transactions.jsonl: debit_initiate ' . $tid;
                    continue;
                }

                if ($type === 'salary_payout') {
                    $tid = trim((string) ($j['transactionId'] ?? ''));
                    if ($tid === '' || pcvc_mopay_wallet_tx_exists($conn, $tid, 'outbound')) {
                        $skipped++;
                        continue;
                    }
                    $http = $j['http'] ?? 0;
                    $ok = pcvc_mopay_backfill_status_ok($http);
                    $empId = 0;
                    $month = '';
                    if (preg_match('/^PAYROLL_(\d+)_(\d{6})_/i', $tid, $m)) {
                        $empId = (int) $m[1];
                        $month = $m[2];
                    }
                    $amt = 0;
                    if (isset($j['amount_rwf'])) {
                        $amt = (int) $j['amount_rwf'];
                    }

                    pcvc_mopay_wallet_tx_insert($conn, [
                        'direction' => 'outbound',
                        'context_type' => 'payroll_staff',
                        'context_id' => $month !== '' ? ($month . '_emp_' . $empId) : $tid,
                        'context_label' => 'Imported salary_payout' . ($empId > 0 ? ' — staff #' . $empId : ''),
                        'initiated_by_admin_id' => 0,
                        'recipient_msisdn' => null,
                        'amount_rwf' => $amt,
                        'status' => $ok ? 'success' : 'failed',
                        'gateway_transaction_id' => $tid,
                        'mopay_flow' => 'jsonl_salary_payout',
                        'http_status' => is_numeric($http) ? (int) $http : null,
                        'error_message' => $ok ? null : ('HTTP ' . (string) $http),
                        'gateway_response_json' => strlen($line) > 12000 ? substr($line, 0, 12000) . '…' : $line,
                        'meta' => [
                            'source' => 'backfill_transactions_jsonl',
                            'transaction_id' => $tid,
                            'employee_admin_id' => $empId,
                            'note' => $amt === 0 ? 'Amount was not stored in legacy JSONL; see gateway or payroll UI.' : '',
                        ],
                    ]);
                    $imported++;
                    $detail[] = 'transactions.jsonl: salary_payout ' . $tid;
                }
            }
        }
    }

    if (!is_readable($whFile) && !is_readable($txFile)) {
        $errors[] = 'No readable files at payments/mopay/logs/webhook.log.jsonl or payments/mopay/storage/transactions.jsonl';
    }

    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
        'detail' => $detail,
    ];
}
