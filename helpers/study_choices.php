<?php
declare(strict_types=1);

function pcvc_normalize_study_choices(array $choices): array
{
    $normalized = [];
    $seen = [];

    foreach ($choices as $choice) {
        if (!is_array($choice)) {
            continue;
        }

        $regionId = (int)($choice['region_id'] ?? 0);
        $universityId = (int)($choice['university_id'] ?? 0);
        $levelId = (int)($choice['program_level_id'] ?? 0);
        $programId = (int)($choice['program_id'] ?? 0);

        if ($regionId <= 0 || $universityId <= 0 || $levelId <= 0 || $programId <= 0) {
            continue;
        }

        $key = $regionId . '|' . $universityId . '|' . $levelId . '|' . $programId;
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $normalized[] = [
            'region_id' => $regionId,
            'university_id' => $universityId,
            'program_level_id' => $levelId,
            'program_id' => $programId,
        ];
    }

    return $normalized;
}

function pcvc_ensure_study_choice_schema(mysqli $conn): void
{
    $legacyIndex = null;
    $stmt = $conn->prepare(
        "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'application_study_choices'
           AND INDEX_NAME = 'uniq_application_university'"
    );

    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($legacyColumns);
        if ($stmt->fetch() && is_string($legacyColumns) && $legacyColumns !== '') {
            $legacyIndex = $legacyColumns;
        }
        $stmt->close();
    }

    $desiredIndexExists = false;
    $stmt = $conn->prepare(
        "SELECT 1
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'application_study_choices'
           AND INDEX_NAME = 'uniq_application_program_choice'
         LIMIT 1"
    );

    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($dummy);
        $desiredIndexExists = (bool)$stmt->fetch();
        $stmt->close();
    }

    if (!$desiredIndexExists) {
        if (
            !$conn->query(
                "ALTER TABLE application_study_choices
                 ADD UNIQUE KEY uniq_application_program_choice
                 (application_id, region_id, university_id, program_level_id, program_id)"
            )
        ) {
            throw new RuntimeException('Failed creating study choice program unique index: ' . $conn->error);
        }
    }

    if ($legacyIndex === 'application_id,university_id') {
        if (!$conn->query("ALTER TABLE application_study_choices DROP INDEX uniq_application_university")) {
            throw new RuntimeException('Failed dropping legacy study choice unique index: ' . $conn->error);
        }
    }
}
