<?php
require_once __DIR__ . '/db.php';
if (isset($_GET['id'])) {
    $userId = $_GET['id'];

    // List of all tables
    $formTables = [
        'student_applications',
        'master_loan_applications',
        'form_17_applications',
        'form_20_applications',
        'credit_transfer_applications',
        'commission_requests'
    ];

    // Fixed default forms
    $defaultFormUrls = [
        'master_loan_applications' => 'master-loan.php',
        'form_17_applications'     => 'visa.php',
        'form_20_applications'     => 'form-20.php',
        'credit_transfer_applications' => 'credit_transfer.php',
        'commission_requests'      => 'Commission-Request.php'
    ];

    $found = false;

    foreach ($formTables as $table) {

        // Special case: student_applications serves multiple forms
        if ($table === 'student_applications') {

            $stmt = $conn->prepare("SELECT form_url, region_id, university_id FROM $table WHERE user_id = ?");
            if ($stmt === false) continue;

            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                echo json_encode([
                    'status'        => 'success',
                    'form_url'      => $row['form_url'], // saved form URL — dynamic
                    'region_id'     => $row['region_id'],
                    'university_id' => $row['university_id']
                ]);
                $found = true;
                $stmt->close();
                break;
            }

            $stmt->close();

        // Special case: visa form
        } elseif ($table === 'form_17_applications') {

            $stmt = $conn->prepare("SELECT region_id, country_id FROM $table WHERE user_id = ?");
            if ($stmt === false) continue;

            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                echo json_encode([
                    'status'     => 'success',
                    'form_url'   => $defaultFormUrls[$table],
                    'region_id'  => $row['region_id'],
                    'country_id' => $row['country_id']
                ]);
                $found = true;
                $stmt->close();
                break;
            }

            $stmt->close();

        // Other tables
        } else {

            $stmt = $conn->prepare("SELECT user_id FROM $table WHERE user_id = ?");
            if ($stmt === false) continue;

            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                echo json_encode([
                    'status'   => 'success',
                    'form_url' => $defaultFormUrls[$table]
                ]);
                $found = true;
                $stmt->close();
                break;
            }

            $stmt->close();
        }
    }

    if (!$found) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Application ID not found in any table.'
        ]);
    }

    $conn->close();

} else {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing ID parameter.'
    ]);
}
