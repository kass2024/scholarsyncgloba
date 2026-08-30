<?php
/**
 * Seed University of Saint Joseph Mbarara (USOJ) + static programmes into scholarsyncglobal.
 * Programmes are copied into MIS — not fetched from the e-learning platform.
 */
declare(strict_types=1);

require __DIR__ . '/db.php';
require __DIR__ . '/helpers/credit_transfer_programs.php';

$uniName = 'University of Saint Joseph Mbarara (USOJ)';
$regionId = 5;   // Africa
$countryId = 175; // Uganda

$levelMap = [
    "Bachelor's Programs" => 4, // Bachelor Degree
    "Master's Programs"   => 5, // Master Degree
    'PhD Programs'        => 6, // Doctorate Degree
    'Diploma Programs'    => 3, // Diploma
    'Short Courses'       => 1, // Short Course
];

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('SELECT id FROM universities WHERE name = ? LIMIT 1');
    $stmt->bind_param('s', $uniName);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($row) {
        $uniId = (int) $row['id'];
        echo "University already exists id={$uniId}\n";
    } else {
        $ins = $conn->prepare('INSERT INTO universities (name, region_id, country_id) VALUES (?, ?, ?)');
        $ins->bind_param('sii', $uniName, $regionId, $countryId);
        $ins->execute();
        $uniId = (int) $ins->insert_id;
        $ins->close();
        echo "Inserted university id={$uniId}\n";
    }

    $usoj = pcvc_credit_transfer_programs()['USOJ'] ?? [];
    $check = $conn->prepare(
        'SELECT id FROM programs WHERE university_id = ? AND program_level_id = ? AND program_name = ? LIMIT 1'
    );
    $insert = $conn->prepare(
        'INSERT INTO programs (university_id, program_level_id, program_name, is_active) VALUES (?, ?, ?, 1)'
    );

    $added = 0;
    $skipped = 0;
    foreach ($usoj as $group => $names) {
        $levelId = $levelMap[$group] ?? 4;
        foreach ($names as $name) {
            $check->bind_param('iis', $uniId, $levelId, $name);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            if ($exists) {
                $skipped++;
                continue;
            }
            $insert->bind_param('iis', $uniId, $levelId, $name);
            $insert->execute();
            $added++;
        }
    }
    $check->close();
    $insert->close();

    $conn->commit();
    echo "Programs added={$added}, skipped={$skipped}\n";
    echo "Done.\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
