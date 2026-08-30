<?php
declare(strict_types=1);

/**
 * MoPay wallet transaction log — see sql/migrate_mopay_wallet_transactions.sql
 *
 * @param array<string, mixed> $meta
 */
function pcvc_ensure_mopay_wallet_transactions_schema(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS mopay_wallet_transactions (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          direction ENUM(\'outbound\',\'inbound\') NOT NULL DEFAULT \'outbound\',
          context_type VARCHAR(64) NOT NULL DEFAULT \'unknown\',
          context_id VARCHAR(128) NULL,
          context_label VARCHAR(512) NULL,
          initiated_by_admin_id INT UNSIGNED NULL,
          recipient_msisdn VARCHAR(32) NULL,
          amount_rwf INT NOT NULL DEFAULT 0,
          currency VARCHAR(8) NOT NULL DEFAULT \'RWF\',
          status ENUM(\'success\',\'failed\',\'pending\') NOT NULL DEFAULT \'pending\',
          gateway_transaction_id VARCHAR(128) NULL,
          mopay_flow VARCHAR(32) NULL,
          http_status SMALLINT UNSIGNED NULL,
          error_message TEXT NULL,
          gateway_response_json MEDIUMTEXT NULL,
          meta_json TEXT NULL,
          retry_of_id BIGINT UNSIGNED NULL DEFAULT NULL,
          PRIMARY KEY (id),
          KEY idx_mwt_created (created_at),
          KEY idx_mwt_status (status),
          KEY idx_mwt_context (context_type, context_id(64)),
          KEY idx_mwt_initiator (initiated_by_admin_id),
          KEY idx_mwt_direction (direction, created_at),
          KEY idx_mwt_retry_of (retry_of_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/**
 * @param array{
 *   context_type:string,
 *   context_id?:string|null,
 *   context_label?:string|null,
 *   initiated_by_admin_id:int,
 *   recipient_msisdn?:string|null,
 *   amount_rwf:int,
 *   status:'success'|'failed'|'pending',
 *   gateway_transaction_id?:string|null,
 *   mopay_flow?:string|null,
 *   http_status?:int|null,
 *   error_message?:string|null,
 *   gateway_response_json?:string|null,
 *   meta?:array<string, mixed>|null,
 *   direction?:'outbound'|'inbound',
 *   retry_of_id?:int|null
 * } $row
 */
function pcvc_mopay_wallet_tx_insert(mysqli $conn, array $row): void
{
    pcvc_ensure_mopay_wallet_transactions_schema($conn);

    $direction = ($row['direction'] ?? 'outbound') === 'inbound' ? 'inbound' : 'outbound';
    $ctxType = (string) ($row['context_type'] ?? 'unknown');
    $ctxId = isset($row['context_id']) && $row['context_id'] !== null && $row['context_id'] !== ''
        ? (string) $row['context_id']
        : null;
    $ctxLabel = isset($row['context_label']) ? (string) $row['context_label'] : null;
    $initBy = (int) ($row['initiated_by_admin_id'] ?? 0);
    $initByParam = $initBy > 0 ? $initBy : null;
    $msisdn = isset($row['recipient_msisdn']) ? trim((string) $row['recipient_msisdn']) : '';
    $msisdnParam = $msisdn !== '' ? $msisdn : null;
    $amt = (int) ($row['amount_rwf'] ?? 0);
    $status = (string) ($row['status'] ?? 'pending');
    if (!in_array($status, ['success', 'failed', 'pending'], true)) {
        $status = 'pending';
    }
    $tid = isset($row['gateway_transaction_id']) ? trim((string) $row['gateway_transaction_id']) : '';
    $tidParam = $tid !== '' ? $tid : null;
    $flow = isset($row['mopay_flow']) ? trim((string) $row['mopay_flow']) : '';
    $flowParam = $flow !== '' ? $flow : null;
    $http = isset($row['http_status']) ? (int) $row['http_status'] : null;
    $httpParam = ($http !== null && $http > 0) ? $http : null;
    $err = isset($row['error_message']) ? (string) $row['error_message'] : '';
    $errParam = $err !== '' ? $err : null;
    $gw = isset($row['gateway_response_json']) ? (string) $row['gateway_response_json'] : '';
    if (strlen($gw) > 60000) {
        $gw = substr($gw, 0, 60000) . '…[truncated]';
    }
    $gwParam = $gw !== '' ? $gw : null;
    $meta = $row['meta'] ?? null;
    $metaJson = (is_array($meta) && $meta !== []) ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $retryOf = isset($row['retry_of_id']) ? (int) $row['retry_of_id'] : 0;
    $retryOfParam = $retryOf > 0 ? $retryOf : null;

    $sql = 'INSERT INTO mopay_wallet_transactions (
        direction, context_type, context_id, context_label, initiated_by_admin_id,
        recipient_msisdn, amount_rwf, currency, status, gateway_transaction_id,
        mopay_flow, http_status, error_message, gateway_response_json, meta_json, retry_of_id
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

    $st = $conn->prepare($sql);
    if (!$st) {
        error_log('mopay_wallet_tx_insert prepare failed: ' . $conn->error);
        return;
    }

    $currency = 'RWF';
    // 16 params: ssss + i + s + i + ssss + i + sss + i (must match column order exactly)
    $bindTypes = str_repeat('s', 4) . 'i' . 's' . 'i' . str_repeat('s', 4) . 'i' . str_repeat('s', 3) . 'i';
    $st->bind_param(
        $bindTypes,
        $direction,
        $ctxType,
        $ctxId,
        $ctxLabel,
        $initByParam,
        $msisdnParam,
        $amt,
        $currency,
        $status,
        $tidParam,
        $flowParam,
        $httpParam,
        $errParam,
        $gwParam,
        $metaJson,
        $retryOfParam
    );
    if (!$st->execute()) {
        error_log('mopay_wallet_tx_insert failed: ' . $st->error);
    }
    $st->close();
}

/**
 * Extract digits MSISDN from MoPay webhook `data` (field names vary by gateway version).
 *
 * @param array<string, mixed>|null $data
 */
function pcvc_mopay_webhook_payer_digits(?array $data): ?string
{
    if ($data === null || $data === []) {
        return null;
    }
    foreach (['account_no', 'phoneNumber', 'msisdn', 'phone', 'payerPhone', 'senderPhone', 'fromAccount', 'customerMsisdn'] as $k) {
        if (!isset($data[$k]) || !is_scalar($data[$k])) {
            continue;
        }
        $d = preg_replace('/\D+/', '', (string) $data[$k]);

        if ($d !== null && $d !== '') {
            return $d;
        }
    }

    return null;
}

/**
 * Log money entering the merchant wallet from MoPay checkout (customer MoMo payment).
 * Called from payments/mopay/webhook.php when JWT is valid and payment status is success.
 * Idempotent: skips if the same gateway transaction id is already logged as inbound.
 *
 * @param array<string, mixed>|null $feeMeta From debit_initiate line (student_id, package_id, …) or null
 */
function pcvc_mopay_wallet_tx_log_inbound_webhook(
    mysqli $conn,
    string $transactionId,
    ?array $data,
    $amount,
    $currency,
    ?array $feeMeta = null
): void {
    $tid = trim($transactionId);
    if ($tid === '') {
        return;
    }

    pcvc_ensure_mopay_wallet_transactions_schema($conn);

    $dup = $conn->prepare('SELECT id FROM mopay_wallet_transactions WHERE gateway_transaction_id = ? AND direction = ? LIMIT 1');
    if ($dup) {
        $dir = 'inbound';
        $dup->bind_param('ss', $tid, $dir);
        $dup->execute();
        $dr = $dup->get_result()->fetch_assoc();
        $dup->close();
        if (is_array($dr)) {
            return;
        }
    }

    $amtFloat = is_numeric($amount) ? (float) $amount : 0.0;
    $amountRwf = (int) round($amtFloat);
    if ($amountRwf < 0) {
        $amountRwf = 0;
    }

    $cur = is_string($currency) && $currency !== '' ? strtoupper(trim($currency)) : 'RWF';
    $payerDigits = pcvc_mopay_webhook_payer_digits($data);

    $ctxType = (is_array($feeMeta) && $feeMeta !== []) ? 'fee_checkout' : 'mopay_checkout';
    $label = 'MoPay checkout (wallet credit)';
    if ($ctxType === 'fee_checkout') {
        $sid = (int) ($feeMeta['student_id'] ?? 0);
        $pid = (int) ($feeMeta['package_id'] ?? 0);
        $label = 'Fee checkout — student app #' . $sid . ', package #' . $pid;
    }

    $meta = [
        'source' => 'mopay_webhook',
        'currency_reported' => $cur,
    ];
    if (is_array($feeMeta) && $feeMeta !== []) {
        $meta['fee_checkout'] = $feeMeta;
    }

    pcvc_mopay_wallet_tx_insert($conn, [
        'direction' => 'inbound',
        'context_type' => $ctxType,
        'context_id' => $tid,
        'context_label' => $label,
        'initiated_by_admin_id' => 0,
        'recipient_msisdn' => $payerDigits,
        'amount_rwf' => $amountRwf,
        'status' => 'success',
        'gateway_transaction_id' => $tid,
        'mopay_flow' => 'webhook',
        'gateway_response_json' => $data !== null && $data !== [] ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        'meta' => $meta,
    ]);
}

/** Last 4 digits for display */
function pcvc_mopay_mask_msisdn(?string $d): string
{
    $d = preg_replace('/\D+/', '', (string) $d);
    if ($d === null || strlen($d) < 4) {
        return '—';
    }

    return '…' . substr($d, -4);
}

/**
 * Full digits, grouped for readability (admin views).
 */
function pcvc_mopay_format_msisdn_full(?string $d): string
{
    $d = preg_replace('/\D+/', '', (string) $d);
    if ($d === '') {
        return '—';
    }

    return trim(chunk_split($d, 3, ' '));
}
