<?php
declare(strict_types=1);

/**
 * @return array<string, string>
 */
function pcvc_research_elearning_allowed_sources(): array
{
    return [
        'credit_transfer_applications' => 'Credit Transfer',
        'upafa_registrations' => 'UPAFA',
    ];
}

function pcvc_research_elearning_normalize_source(string $sourceTable): string
{
    $sourceTable = trim($sourceTable);
    $allowed = pcvc_research_elearning_allowed_sources();
    return array_key_exists($sourceTable, $allowed) ? $sourceTable : 'credit_transfer_applications';
}

/**
 * @return array<string, string>
 */
function pcvc_research_elearning_doc_fields(): array
{
    return [
        'research_proposal' => 'Research Proposal',
        'final_year_project_report' => 'Final Year Project Report',
        'scientific_paper' => 'Scientific Paper (Journal/Conference Paper)',
        'internship_report' => 'Internship Report',
        'internship_evaluation_form' => 'Internship Evaluation Form',
        'logbook' => 'Logbook',
    ];
}

function pcvc_ensure_research_elearning_schema(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS credit_transfer_research_elearning (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            credit_transfer_id INT UNSIGNED NULL,
            source_table VARCHAR(64) NOT NULL DEFAULT 'credit_transfer_applications',
            source_id INT UNSIGNED NOT NULL DEFAULT 0,
            user_id VARCHAR(64) NULL,
            student_name VARCHAR(255) NULL,
            student_email VARCHAR(190) NULL,
            overall_status ENUM('not_started','in_progress','submitted','completed','on_hold') NOT NULL DEFAULT 'not_started',
            research_proposal VARCHAR(500) NULL,
            final_year_project_report VARCHAR(500) NULL,
            scientific_paper VARCHAR(500) NULL,
            internship_report VARCHAR(500) NULL,
            internship_evaluation_form VARCHAR(500) NULL,
            logbook VARCHAR(500) NULL,
            admin_notes TEXT NULL,
            last_status_check_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ct_research_user_id (user_id),
            KEY idx_ct_research_status (overall_status),
            KEY idx_ct_research_source (source_table, source_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $requiredColumns = [
        'source_table' => "VARCHAR(64) NOT NULL DEFAULT 'credit_transfer_applications' AFTER credit_transfer_id",
        'source_id' => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER source_table",
        'user_id' => "VARCHAR(64) NULL AFTER source_id",
        'student_name' => "VARCHAR(255) NULL AFTER user_id",
        'student_email' => "VARCHAR(190) NULL AFTER student_name",
        'overall_status' => "ENUM('not_started','in_progress','submitted','completed','on_hold') NOT NULL DEFAULT 'not_started' AFTER student_email",
        'research_proposal' => "VARCHAR(500) NULL AFTER overall_status",
        'final_year_project_report' => "VARCHAR(500) NULL AFTER research_proposal",
        'scientific_paper' => "VARCHAR(500) NULL AFTER final_year_project_report",
        'internship_report' => "VARCHAR(500) NULL AFTER scientific_paper",
        'internship_evaluation_form' => "VARCHAR(500) NULL AFTER internship_report",
        'logbook' => "VARCHAR(500) NULL AFTER internship_evaluation_form",
        'admin_notes' => "TEXT NULL AFTER logbook",
        'last_status_check_at' => "DATETIME NULL AFTER admin_notes",
        'created_by' => "INT UNSIGNED NULL AFTER last_status_check_at",
        'updated_by' => "INT UNSIGNED NULL AFTER created_by",
    ];

    $existing = [];
    $res = $conn->query('SHOW COLUMNS FROM credit_transfer_research_elearning');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $existing[(string) ($row['Field'] ?? '')] = true;
        }
        $res->free();
    }

    foreach ($requiredColumns as $column => $definition) {
        if (!isset($existing[$column])) {
            $conn->query("ALTER TABLE credit_transfer_research_elearning ADD COLUMN {$column} {$definition}");
        }
    }

    $conn->query(
        "UPDATE credit_transfer_research_elearning
         SET source_table = 'credit_transfer_applications',
             source_id = credit_transfer_id
         WHERE source_id = 0 AND credit_transfer_id IS NOT NULL AND credit_transfer_id > 0"
    );

    $legacyIdx = $conn->query("SHOW INDEX FROM credit_transfer_research_elearning WHERE Key_name = 'uq_ct_research_credit_transfer_id'");
    if ($legacyIdx && $legacyIdx->num_rows > 0) {
        $conn->query('ALTER TABLE credit_transfer_research_elearning DROP INDEX uq_ct_research_credit_transfer_id');
    }
    if ($legacyIdx) {
        $legacyIdx->free();
    }

    $idxRes = $conn->query("SHOW INDEX FROM credit_transfer_research_elearning WHERE Key_name = 'uq_ct_research_source'");
    if ($idxRes && $idxRes->num_rows === 0) {
        $conn->query(
            'ALTER TABLE credit_transfer_research_elearning
             ADD UNIQUE KEY uq_ct_research_source (source_table, source_id)'
        );
    }
    if ($idxRes) {
        $idxRes->free();
    }
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function pcvc_research_elearning_compute_status(array $row): array
{
    $fields = pcvc_research_elearning_doc_fields();
    $uploaded = [];
    $missing = [];

    foreach ($fields as $key => $label) {
        $path = trim((string) ($row[$key] ?? ''));
        if ($path !== '') {
            $uploaded[] = ['key' => $key, 'label' => $label, 'path' => $path];
        } else {
            $missing[] = ['key' => $key, 'label' => $label];
        }
    }

    $count = count($uploaded);
    $total = count($fields);
    $autoStatus = 'not_started';
    if ($count === $total && $total > 0) {
        $autoStatus = 'completed';
    } elseif ($count > 0) {
        $autoStatus = 'in_progress';
    }

    return [
        'uploaded_count' => $count,
        'total_count' => $total,
        'missing' => $missing,
        'uploaded' => $uploaded,
        'auto_status' => $autoStatus,
        'completion_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
    ];
}

/**
 * @return array<string, mixed>|null
 */
function pcvc_research_elearning_fetch_student(mysqli $conn, string $sourceTable, int $sourceId): ?array
{
    $sourceTable = pcvc_research_elearning_normalize_source($sourceTable);
    if ($sourceId <= 0) {
        return null;
    }

    if ($sourceTable === 'upafa_registrations') {
        $stmt = $conn->prepare(
            'SELECT id, email, telephone AS phone, academic_year,
                TRIM(CONCAT_WS(" ", first_name, last_name)) AS full_name,
                field_of_study, department, school_name_address
             FROM upafa_registrations
             WHERE id = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $sourceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }

        return [
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'full_name' => (string) ($row['full_name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'ref' => 'UPAFA-' . $sourceId,
            'user_id' => 'upafa-' . $sourceId,
            'program' => 'UPAFA',
            'extra' => trim((string) (($row['field_of_study'] ?? '') ?: ($row['department'] ?? ''))),
        ];
    }

    $stmt = $conn->prepare(
        'SELECT id, user_id, email, mobile_number AS phone, university,
            TRIM(CONCAT_WS(" ", first_name, middle_name, last_name)) AS full_name
         FROM credit_transfer_applications
         WHERE id = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $sourceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    return [
        'source_table' => $sourceTable,
        'source_id' => $sourceId,
        'full_name' => (string) ($row['full_name'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'phone' => (string) ($row['phone'] ?? ''),
        'ref' => (string) ($row['user_id'] ?? ''),
        'user_id' => (string) ($row['user_id'] ?? ''),
        'program' => 'Credit Transfer',
        'extra' => (string) ($row['university'] ?? ''),
    ];
}

/**
 * @return array<string, mixed>|null
 */
function pcvc_research_elearning_get_or_create(
    mysqli $conn,
    string $sourceTable,
    int $sourceId,
    ?int $adminId = null
): ?array {
    pcvc_ensure_research_elearning_schema($conn);

    $student = pcvc_research_elearning_fetch_student($conn, $sourceTable, $sourceId);
    if (!$student) {
        return null;
    }

    $sourceTable = (string) $student['source_table'];
    $sourceId = (int) $student['source_id'];

    $stmt = $conn->prepare(
        'SELECT * FROM credit_transfer_research_elearning
         WHERE source_table = ? AND source_id = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('si', $sourceTable, $sourceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $name = (string) ($student['full_name'] ?? '');
        $email = (string) ($student['email'] ?? '');
        $userId = (string) ($student['user_id'] ?? '');
        $creditTransferId = $sourceTable === 'credit_transfer_applications' ? $sourceId : null;
        $createdBy = $adminId !== null && $adminId > 0 ? $adminId : 0;

        $ins = $conn->prepare(
            'INSERT INTO credit_transfer_research_elearning
                (credit_transfer_id, source_table, source_id, user_id, student_name, student_email,
                 overall_status, created_by, last_status_check_at)
             VALUES (?, ?, ?, ?, ?, ?, "not_started", NULLIF(?, 0), NOW())'
        );
        if (!$ins) {
            return null;
        }
        $ins->bind_param('isisssi', $creditTransferId, $sourceTable, $sourceId, $userId, $name, $email, $createdBy);
        $ins->execute();
        $ins->close();

        $stmt = $conn->prepare(
            'SELECT * FROM credit_transfer_research_elearning
             WHERE source_table = ? AND source_id = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('si', $sourceTable, $sourceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (is_array($row)) {
        $row['student_meta'] = $student;
        $row['ct_full_name'] = (string) ($student['full_name'] ?? '');
        $row['ct_email'] = (string) ($student['email'] ?? '');
        $row['ct_user_id'] = (string) ($student['user_id'] ?? '');
        $row['program'] = (string) ($student['program'] ?? '');
    }

    return is_array($row) ? $row : null;
}

/**
 * @param array<int, array<string, mixed>> $results
 * @return array<string, array<string, mixed>>
 */
function pcvc_research_elearning_status_map(mysqli $conn, array $results): array
{
    pcvc_ensure_research_elearning_schema($conn);
    if (!$results) {
        return [];
    }

    $ctIds = [];
    $upafaIds = [];
    foreach ($results as $row) {
        $table = (string) ($row['table'] ?? '');
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        if ($table === 'upafa_registrations') {
            $upafaIds[] = $id;
        } else {
            $ctIds[] = $id;
        }
    }

    $map = [];
    $docKeys = array_keys(pcvc_research_elearning_doc_fields());

    $load = static function (mysqli $conn, string $table, array $ids) use (&$map, $docKeys): void {
        if (!$ids) {
            return;
        }
        $ids = array_values(array_unique(array_filter($ids, static fn(int $v): bool => $v > 0)));
        if (!$ids) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT source_table, source_id, overall_status, {$table} AS _x,
                research_proposal, final_year_project_report, scientific_paper,
                internship_report, internship_evaluation_form, logbook, updated_at
                FROM credit_transfer_research_elearning
                WHERE source_table = ? AND source_id IN ({$placeholders})";
        // Fix SQL - remove bogus alias
        $sql = "SELECT source_table, source_id, overall_status,
                research_proposal, final_year_project_report, scientific_paper,
                internship_report, internship_evaluation_form, logbook, updated_at
                FROM credit_transfer_research_elearning
                WHERE source_table = ? AND source_id IN ({$placeholders})";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return;
        }
        $types = 's' . str_repeat('i', count($ids));
        $params = array_merge([$table], $ids);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $key = (string) ($row['source_table'] ?? '') . ':' . (int) ($row['source_id'] ?? 0);
            $uploaded = 0;
            foreach ($docKeys as $field) {
                if (trim((string) ($row[$field] ?? '')) !== '') {
                    $uploaded++;
                }
            }
            $total = count($docKeys);
            $map[$key] = [
                'overall_status' => (string) ($row['overall_status'] ?? 'not_started'),
                'uploaded_count' => $uploaded,
                'total_count' => $total,
                'completion_pct' => $total > 0 ? (int) round(($uploaded / $total) * 100) : 0,
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }
        $stmt->close();
    };

    $load($conn, 'credit_transfer_applications', $ctIds);
    $load($conn, 'upafa_registrations', $upafaIds);

    return $map;
}

/**
 * @param array<string, mixed>|null $status
 * @return array{label: string, badge: string, uploaded_count: int, total_count: int}
 */
function pcvc_research_elearning_list_status(?array $status): array
{
    $total = 6;
    if (!$status) {
        return [
            'label' => 'Not started',
            'badge' => 'secondary',
            'uploaded_count' => 0,
            'total_count' => $total,
        ];
    }

    $uploaded = (int) ($status['uploaded_count'] ?? 0);
    $overall = (string) ($status['overall_status'] ?? 'not_started');

    if ($uploaded >= $total) {
        return [
            'label' => "Completed ({$uploaded}/{$total})",
            'badge' => 'success',
            'uploaded_count' => $uploaded,
            'total_count' => $total,
        ];
    }
    if ($uploaded > 0) {
        return [
            'label' => "In progress ({$uploaded}/{$total})",
            'badge' => 'warning',
            'uploaded_count' => $uploaded,
            'total_count' => $total,
        ];
    }
    if ($overall === 'on_hold') {
        return [
            'label' => 'On hold',
            'badge' => 'danger',
            'uploaded_count' => 0,
            'total_count' => $total,
        ];
    }

    return [
        'label' => 'Not started',
        'badge' => 'secondary',
        'uploaded_count' => 0,
        'total_count' => $total,
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array{record: array<string, mixed>, documents: array<int, array<string, mixed>>, progress: array<string, mixed>}
 */
function pcvc_research_elearning_build_payload(array $row): array
{
    $progress = pcvc_research_elearning_compute_status($row);
    $documents = [];
    foreach (pcvc_research_elearning_doc_fields() as $key => $label) {
        $path = trim((string) ($row[$key] ?? ''));
        $documents[] = [
            'key' => $key,
            'label' => $label,
            'uploaded' => $path !== '',
            'path' => $path,
            'file_name' => $path !== '' ? basename($path) : '',
        ];
    }

    return [
        'record' => [
            'id' => (int) ($row['id'] ?? 0),
            'source_table' => (string) ($row['source_table'] ?? ''),
            'source_id' => (int) ($row['source_id'] ?? 0),
            'program' => (string) ($row['program'] ?? ''),
            'user_id' => (string) ($row['user_id'] ?? $row['ct_user_id'] ?? ''),
            'student_name' => (string) ($row['student_name'] ?? $row['ct_full_name'] ?? ''),
            'student_email' => (string) ($row['student_email'] ?? $row['ct_email'] ?? ''),
            'overall_status' => (string) ($row['overall_status'] ?? 'not_started'),
            'admin_notes' => (string) ($row['admin_notes'] ?? ''),
            'last_status_check_at' => (string) ($row['last_status_check_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ],
        'documents' => $documents,
        'progress' => $progress,
    ];
}
