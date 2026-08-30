<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

function logError(string $message, array $data = []): void {
    $logFile = __DIR__ . '/logs/air_reservation_errors.log';
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if (!empty($data)) {
        $entry .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    file_put_contents($logFile, $entry . PHP_EOL, FILE_APPEND);
}

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method', 405);
    }

    // CSRF validation (uncomment if you have session started)
    // session_start();
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     throw new Exception('Invalid security token', 403);
    // }

    // Get and sanitize form data
    $user_id = trim($_POST['user_id'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_area_code = trim($_POST['phone_area_code'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $nationality = trim($_POST['nationality'] ?? '');
    $passport_number = strtoupper(trim($_POST['passport_number'] ?? ''));
    $passport_expiry = $_POST['passport_expiry'] ?? '';
    $trip_type = $_POST['trip_type'] ?? '';
    $departure_city = isset($_POST['departure_city']) ? (int)$_POST['departure_city'] : 0;
    $destination_city = isset($_POST['destination_city']) ? (int)$_POST['destination_city'] : 0;
    $departure_city_text = trim($_POST['departure_city_text'] ?? '');
    $destination_city_text = trim($_POST['destination_city_text'] ?? '');
    $departure_date = $_POST['departure_date'] ?? '';
    $return_date = !empty($_POST['return_date']) ? $_POST['return_date'] : null;
    $passengers = isset($_POST['passengers']) ? (int)$_POST['passengers'] : 1;
    $cabin_class = strtolower(trim($_POST['cabin_class'] ?? ''));
    $payment_method = $_POST['payment_method'] ?? '';
    $airline_preferences = $_POST['airline_preferences'] ?? '';
    $form_version = $_POST['form_version'] ?? '1.0';
    $submission_time = isset($_POST['submission_time']) ? (int)$_POST['submission_time'] : time();

    // Validate required fields
    $required_fields = [
        'user_id' => $user_id,
        'full_name' => $full_name,
        'gender' => $gender,
        'email' => $email,
        'phone_area_code' => $phone_area_code,
        'phone_number' => $phone_number,
        'date_of_birth' => $date_of_birth,
        'nationality' => $nationality,
        'passport_number' => $passport_number,
        'passport_expiry' => $passport_expiry,
        'trip_type' => $trip_type,
        'departure_date' => $departure_date,
        'cabin_class' => $cabin_class,
        'payment_method' => $payment_method,
        'departure_city' => $departure_city,
        'destination_city' => $destination_city
    ];

    $missing_fields = [];
    foreach ($required_fields as $field => $value) {
        if (empty($value) && $value !== 0) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
    }

    // Validate airport IDs are positive integers
    if ($departure_city <= 0 || $destination_city <= 0) {
        throw new Exception('Invalid airport selection. Please select valid airports.');
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Validate dates
    if (!strtotime($date_of_birth) || !strtotime($passport_expiry) || !strtotime($departure_date)) {
        throw new Exception('Invalid date format');
    }

    if ($return_date && !strtotime($return_date)) {
        throw new Exception('Invalid return date format');
    }

    // Validate trip type
    $allowed_trip_types = ['one_way', 'round_trip', 'multi_city'];
    if (!in_array($trip_type, $allowed_trip_types)) {
        throw new Exception('Invalid trip type');
    }

    // Validate cabin class
    $allowed_cabin_classes = ['economy', 'business', 'first'];
    if (!in_array($cabin_class, $allowed_cabin_classes)) {
        throw new Exception('Invalid cabin class');
    }

    // Validate payment method
    $allowed_payment_methods = ['mobile_money', 'bank_transfer', 'cash'];
    if (!in_array($payment_method, $allowed_payment_methods)) {
        throw new Exception('Invalid payment method');
    }

    // Validate passengers count
    if ($passengers < 1 || $passengers > 9) {
        throw new Exception('Passengers count must be between 1 and 9');
    }

    // Get nationality_id from countries table
    $nationality_id = null;
    $stmt = $conn->prepare("SELECT id FROM countries WHERE name = ? OR LOWER(name) = LOWER(?)");
    $stmt->bind_param("ss", $nationality, $nationality);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $nationality_id = (int)$row['id'];
    }
    $stmt->close();

    if (!$nationality_id) {
        // Try to find by partial match or use default
        $stmt = $conn->prepare("SELECT id FROM countries LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $nationality_id = (int)$row['id'];
        } else {
            throw new Exception('Nationality not found in database');
        }
        $stmt->close();
    }

    // Verify that the airport IDs exist in airports2 table
    $stmt = $conn->prepare("SELECT id FROM airports2 WHERE id = ?");
    $stmt->bind_param("i", $departure_city);
    $stmt->execute();
    if (!$stmt->get_result()->num_rows) {
        throw new Exception("Departure airport ID $departure_city does not exist");
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id FROM airports2 WHERE id = ?");
    $stmt->bind_param("i", $destination_city);
    $stmt->execute();
    if (!$stmt->get_result()->num_rows) {
        throw new Exception("Destination airport ID $destination_city does not exist");
    }
    $stmt->close();

    // Begin transaction
    $conn->begin_transaction();

    // Insert reservation - matching your exact table structure
    $stmt = $conn->prepare("
        INSERT INTO air_reservations (
            user_id, 
            full_name, 
            email, 
            phone_area_code, 
            phone_number,
            date_of_birth, 
            nationality_id, 
            passport_number, 
            passport_expiry,
            trip_type, 
            departure_city, 
            destination_city, 
            departure_date,
            return_date, 
            passengers, 
            cabin_class, 
            payment_method,
            emergency_full_name, 
            emergency_relationship, 
            emergency_phone, 
            emergency_email,
            seat_preference, 
            meal_preference, 
            special_requests,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    // Set default values for required fields
    $emergency_full_name = $full_name;
    $emergency_relationship = 'Self';
    $emergency_phone = $phone_area_code . $phone_number;
    $emergency_email = $email;
    $seat_preference = 'any';
    $meal_preference = 'none';
    $special_requests = '';

    $stmt->bind_param(
        "ssssssissssiissssssssss",
        $user_id,              // s
        $full_name,            // s
        $email,                // s
        $phone_area_code,      // s
        $phone_number,         // s
        $date_of_birth,        // s
        $nationality_id,       // i
        $passport_number,      // s
        $passport_expiry,      // s
        $trip_type,            // s
        $departure_city,       // i
        $destination_city,     // i
        $departure_date,       // s
        $return_date,          // s
        $passengers,           // i
        $cabin_class,          // s
        $payment_method,       // s
        $emergency_full_name,  // s
        $emergency_relationship, // s
        $emergency_phone,      // s
        $emergency_email,      // s
        $seat_preference,      // s
        $meal_preference,      // s
        $special_requests      // s
    );

    if (!$stmt->execute()) {
        throw new Exception('Insert failed: ' . $stmt->error);
    }

    $reservation_id = $stmt->insert_id;
    $stmt->close();

    // Handle airline preferences if provided (store in a separate table if you have one)
    if (!empty($airline_preferences) && $airline_preferences !== '[]') {
        $airlines = json_decode($airline_preferences, true);
        if (is_array($airlines) && !empty($airlines)) {
            // You can store these in a separate table if needed
            // For now, we'll just log them
            logError("Airline preferences for reservation $reservation_id", $airlines);
        }
    }

    // Commit transaction
    $conn->commit();

    // Log success
    logError("Reservation created successfully", [
        'reservation_id' => $reservation_id,
        'user_id' => $user_id
    ]);

    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Reservation submitted successfully',
        'reservation_id' => $reservation_id,
        'user_id' => $user_id
    ]);

} catch (Exception $e) {
    // Rollback transaction if started
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    // Log error
    logError('Submission error: ' . $e->getMessage(), [
        'post_data' => $_POST,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}