<?php
/**
 * Salary disbursement from the MoPay merchant wallet (the account tied to your API
 * credentials) to an employee MSISDN.
 *
 * MoPay Gateway V1 documents:
 * - POST /api/v1/payment — debits `account_no` (payer) and optionally sends `transfers[]`
 *   (customer checkout → receiver). Not the right shape for “pay from merchant balance”.
 * - POST /api/v1/momo/transfer — body: amount, message, account_no (destination),
 *   payment_type, currency, transactionId. Deduction is from the authenticated merchant
 *   wallet; “We charge 100 per transfer” (per PDF).
 *
 * Default endpoint: /api/v1/momo/transfer. Override with MOPAY_SALARY_PAYOUT_ENDPOINT if needed.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mopay_http.php';
require_once __DIR__ . '/trace.php';
require_once dirname(__DIR__, 2) . '/includes/momo_phone.php';

/** Gateway SMS / message fields are often ASCII-only; em dashes and unicode break VALIDATION_ERROR. */
function mopay_salary_sanitize_message(string $s): string
{
    $s = str_replace(['—', '–', '«', '»', '’', '‘', '“', '”'], ['-', '-', '', '', "'", "'", '"', '"'], $s);
    $s = preg_replace('/[^\x20-\x7E]/', '', $s) ?? '';
    $s = trim(preg_replace('/\s+/', ' ', $s) ?? '');
    if ($s === '') {
        return 'MOPAY Salary';
    }

    return strlen($s) > 120 ? substr($s, 0, 120) : $s;
}

/** Some gateways cap length and allow only [A-Za-z0-9_]. */
function mopay_salary_sanitize_transaction_id(string $tid): string
{
    $tid = preg_replace('/[^A-Za-z0-9_]/', '_', $tid);
    $tid = trim($tid, '_');
    if (strlen($tid) > 48) {
        $tid = substr($tid, 0, 48);
    }
    if ($tid === '') {
        $tid = 'PAY_' . date('YmdHis') . '_' . random_int(1000, 9999);
    }

    return $tid;
}

/**
 * New id for POST /api/v1/payment after a failed /momo/transfer: the gateway reserves
 * transactionId across endpoints, so reusing the transfer id causes "Trx id already exists".
 */
function mopay_salary_new_payment_fallback_transaction_id(): string
{
    return mopay_salary_sanitize_transaction_id(
        'PAYROLL_P1_' . date('YmdHis') . '_' . random_int(100000, 999999)
    );
}

/**
 * Extract human-readable error from MoPay JSON body.
 *
 * @param mixed $json
 */
function mopay_salary_format_gateway_error($json, string $rawBody): string
{
    if (!is_array($json)) {
        return $rawBody !== '' ? substr($rawBody, 0, 400) : 'Unknown error';
    }
    $parts = [];
    if (isset($json['message'])) {
        $m = $json['message'];
        $parts[] = is_string($m) ? $m : json_encode($m);
    }
    if (isset($json['code'])) {
        $parts[] = 'code=' . (is_scalar($json['code']) ? (string) $json['code'] : json_encode($json['code']));
    }
    if (isset($json['error']) && is_string($json['error'])) {
        $parts[] = $json['error'];
    }
    if (isset($json['errors']) && is_array($json['errors'])) {
        $parts[] = json_encode($json['errors']);
    }

    return $parts !== [] ? implode(' | ', $parts) : substr($rawBody, 0, 400);
}

/**
 * Fallback: same wallet→recipient pattern as customer checkout, but payer is merchant MSISDN.
 *
 * @return array{ok:bool,http?:int,error?:string,json?:mixed,raw?:string}
 */
function mopay_salary_payout_via_payment_api(
    array $cfg,
    string $merchantAccountNo,
    string $employeeMsisdn12,
    int $amountRwf,
    string $transactionId,
    string $transferMessage
): array {
    $merchantAccountNo = trim($merchantAccountNo);
    $emp = pcvc_normalize_rw_momo_msisdn($employeeMsisdn12);
    if ($merchantAccountNo === '' || $emp === null || $amountRwf < 1) {
        return ['ok' => false, 'error' => 'Invalid merchant, employee, or amount for payment fallback.'];
    }

    $serverBase = rtrim((string) ($cfg['server_base_url'] ?? ''), '/');
    $url = $serverBase . '/api/v1/payment';
    $currency = (string) ($cfg['default_currency'] ?? 'RWF');
    $msg = mopay_salary_sanitize_message($transferMessage);
    $tid = mopay_salary_sanitize_transaction_id($transactionId);
    $tidTransfer = mopay_salary_sanitize_transaction_id($tid . '_T');

    $payload = [
        'transactionId' => $tid,
        'account_no' => $merchantAccountNo,
        'title' => mopay_salary_sanitize_message((string) ($cfg['payment_title'] ?? 'Salary payment')),
        'details' => mopay_salary_sanitize_message((string) ($cfg['payment_details'] ?? 'Payroll')),
        'payment_type' => 'momo',
        'amount' => $amountRwf,
        'currency' => $currency,
        'message' => $msg,
        'transfers' => [
            [
                'transactionId' => $tidTransfer,
                'account_no' => $emp,
                'payment_type' => 'momo',
                'amount' => $amountRwf,
                'currency' => $currency,
                'message' => $msg,
            ],
        ],
    ];

    try {
        $authValue = mopay_get_authorization_value($cfg);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Auth error: ' . $e->getMessage()];
    }

    $headers = [
        'Authorization: ' . $authValue,
        'Content-Type: application/json; charset=UTF-8',
        'Accept: application/json',
        'category: BIZAO',
    ];

    mopay_trace_log([
        'type' => 'salary_payout_payment_fallback',
        'transactionId' => $tid,
        'endpoint' => $url,
    ]);

    $res = mopay_http_request('POST', $url, $headers, $payload);
    $rawBody = is_string($res['body']) ? $res['body'] : '';
    $json = $rawBody !== '' ? json_decode($rawBody, true) : null;
    $code = (int) ($res['status_code'] ?? 0);

    if (!empty($res['curl_error'])) {
        return ['ok' => false, 'error' => 'Network: ' . (string) $res['curl_error'], 'http' => $code];
    }

    if ($code >= 200 && $code < 300) {
        return [
            'ok' => true,
            'http' => $code,
            'json' => $json,
            'raw' => $rawBody,
            'flow' => 'payment_api',
            'transactionId' => $tid,
        ];
    }

    $err = 'HTTP ' . $code . ': ' . mopay_salary_format_gateway_error(is_array($json) ? $json : [], $rawBody);

    return ['ok' => false, 'http' => $code, 'error' => $err, 'json' => $json, 'raw' => $rawBody];
}

/**
 * @return array{ok:bool,http?:int,error?:string,json?:mixed,raw?:string,flow?:string}
 */
function mopay_salary_payout_single(
    array $cfg,
    string $merchantAccountNo,
    string $employeeMsisdn12,
    int $amountRwf,
    string $transactionId,
    string $transferMessage
): array {
    $merchantAccountNo = trim($merchantAccountNo);
    $emp = pcvc_normalize_rw_momo_msisdn($employeeMsisdn12);
    // MOPAY_RECEIVER_ACCOUNT_NO proves payroll/MoPay merchant config is present; transfer API debits the wallet bound to your token, not this field in the JSON body.
    if ($merchantAccountNo === '' || $emp === null) {
        return ['ok' => false, 'error' => 'Invalid merchant account or employee phone.'];
    }
    if ($amountRwf < 1) {
        return ['ok' => false, 'error' => 'Amount must be at least 1 RWF.'];
    }

    $minTransfer = (int) ($cfg['min_transfer_amount'] ?? 1);
    if ($amountRwf < $minTransfer) {
        return ['ok' => false, 'error' => "Amount below gateway minimum ({$minTransfer} RWF)."];
    }

    $serverBase = rtrim((string) ($cfg['server_base_url'] ?? ''), '/');
    if ($serverBase === '') {
        return ['ok' => false, 'error' => 'MOPAY_SERVER_BASE_URL is not configured.'];
    }

    $endpointPath = getenv('MOPAY_SALARY_PAYOUT_ENDPOINT');
    $endpointPath = is_string($endpointPath) && $endpointPath !== ''
        ? $endpointPath
        : '/api/v1/momo/transfer';
    if ($endpointPath[0] !== '/') {
        $endpointPath = '/' . $endpointPath;
    }
    $url = $serverBase . $endpointPath;

    $currency = (string) ($cfg['default_currency'] ?? 'RWF');
    $countryCode = strtolower((string) ($cfg['default_country_code'] ?? 'rw'));
    $mno = (string) ($cfg['default_mno'] ?? 'mtn');
    $tid = mopay_salary_sanitize_transaction_id($transactionId);
    $msg = mopay_salary_sanitize_message($transferMessage);

    // PDF MoPay Gateway V1 — POST /api/v1/momo/transfer: destination account_no, amount deducted from merchant wallet (Bearer token).
    $payload = [
        'amount' => $amountRwf,
        'message' => $msg,
        'account_no' => $emp,
        'payment_type' => 'momo',
        'currency' => $currency,
        'transactionId' => $tid,
        'country_code' => $countryCode,
        'mno' => $mno,
    ];

    try {
        $authValue = mopay_get_authorization_value($cfg);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Auth error: ' . $e->getMessage()];
    }

    $headers = [
        'Authorization: ' . $authValue,
        'Content-Type: application/json; charset=UTF-8',
        'Accept: application/json',
        'category: BIZAO',
    ];

    mopay_trace_log([
        'type' => 'salary_payout_request',
        'transactionId' => $tid,
        'endpoint' => $url,
        'auth_mode' => mopay_trace_auth_mode($authValue),
        'merchant_configured' => $merchantAccountNo !== '',
        'employee_tail' => substr($emp, -4),
        'amount' => $amountRwf,
    ]);

    $res = mopay_http_request('POST', $url, $headers, $payload);
    $rawBody = is_string($res['body']) ? $res['body'] : '';
    $json = $rawBody !== '' ? json_decode($rawBody, true) : null;
    $code = (int) ($res['status_code'] ?? 0);

    $logPath = __DIR__ . '/storage/transactions.jsonl';
    file_put_contents(
        $logPath,
        json_encode([
            'type' => 'salary_payout',
            'time' => date('c'),
            'transactionId' => $tid,
            'http' => $code,
            'response' => $json ?? $rawBody,
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    if (!empty($res['curl_error'])) {
        return ['ok' => false, 'error' => 'Network: ' . (string) $res['curl_error'], 'http' => $code];
    }

    if ($code >= 200 && $code < 300) {
        return [
            'ok' => true,
            'http' => $code,
            'json' => $json,
            'raw' => $rawBody,
            'flow' => 'transfer',
            'transactionId' => $tid,
        ];
    }

    $err = 'HTTP ' . $code . ': ' . mopay_salary_format_gateway_error(is_array($json) ? $json : [], $rawBody);

    $disableFb = getenv('MOPAY_SALARY_DISABLE_PAYMENT_FALLBACK');
    if ($disableFb === '1' || $disableFb === 'true') {
        return ['ok' => false, 'http' => $code, 'error' => $err, 'json' => $json, 'raw' => $rawBody];
    }

    // Fresh id: MoPay rejects duplicate transactionId if the failed transfer already reserved it.
    $fbTid = mopay_salary_new_payment_fallback_transaction_id();
    mopay_trace_log([
        'type' => 'salary_payout_fallback_ids',
        'transfer_transactionId' => $tid,
        'payment_transactionId' => $fbTid,
    ]);

    $fb = mopay_salary_payout_via_payment_api(
        $cfg,
        $merchantAccountNo,
        $employeeMsisdn12,
        $amountRwf,
        $fbTid,
        $transferMessage
    );
    if (!empty($fb['ok'])) {
        return $fb;
    }

    $fbErr = isset($fb['error']) ? (string) $fb['error'] : 'payment fallback failed';

    return [
        'ok' => false,
        'http' => $code,
        'error' => $err . ' | fallback: ' . $fbErr,
        'json' => $json,
        'raw' => $rawBody,
        'fallback_http' => $fb['http'] ?? null,
        'fallback_json' => $fb['json'] ?? null,
    ];
}
