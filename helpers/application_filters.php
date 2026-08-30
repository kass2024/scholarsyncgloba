<?php
declare(strict_types=1);

/**
 * Effective application status: first matching flag in priority order (same logic as students-manage.php).
 *
 * @return list<string>
 */
function pcvc_application_status_priority(): array
{
    return [
        'deny',
        'enrolled',
        'visa_approved',
        'visa_scheduled',
        'sevis_paid',
        'i20_sent',
        'caq',
        'admit',
        'app_paid',
        'sent_to_platform',
        'submitted',
        'addn_doc',
        'incomplete_app',
        'app_start',
    ];
}

/**
 * Human-readable labels for admin filters (aligned with students-manage status dropdown).
 *
 * @return array<string, string>
 */
function pcvc_application_status_labels(): array
{
    return [
        'incomplete_app' => 'Incomplete App',
        'submitted' => 'Submitted',
        'sent_to_platform' => 'Sent to Platform',
        'app_paid' => 'App Paid',
        'admit' => 'Admit',
        'caq' => 'CAQ',
        'i20_sent' => 'I-20 Sent',
        'sevis_paid' => 'Sevis Paid',
        'visa_scheduled' => 'Visa Sch.',
        'visa_approved' => 'Visa OK',
        'enrolled' => 'Enrolled',
        'addn_doc' => 'Add Doc',
        'deny' => 'Rejected',
        'app_start' => 'App Start',
    ];
}

/**
 * Effective status key for one application row (assoc from DB).
 */
function pcvc_application_effective_status(array $row): ?string
{
    foreach (pcvc_application_status_priority() as $key) {
        if (!empty($row[$key]) && (int) $row[$key] === 1) {
            return $key;
        }
    }

    return null;
}

/**
 * SQL CASE expression for one row (use in WHERE before GROUP BY).
 */
function pcvc_sql_case_effective_status(string $tableAlias = 'sa'): string
{
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', $tableAlias);
    if ($a === '') {
        $a = 'sa';
    }

    $parts = [];
    foreach (pcvc_application_status_priority() as $key) {
        $parts[] = "WHEN IFNULL(`{$a}`.`{$key}`,0)=1 THEN '{$key}'";
    }

    return 'CASE ' . implode(' ', $parts) . ' ELSE NULL END';
}

/**
 * MAX(CASE…) for SELECT … GROUP BY sa.id (MySQL ONLY_FULL_GROUP_BY safe).
 */
function pcvc_sql_max_effective_status(string $tableAlias = 'sa'): string
{
    return 'MAX(' . pcvc_sql_case_effective_status($tableAlias) . ')';
}
