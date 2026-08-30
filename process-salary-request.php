<?php
session_start();
require_once __DIR__ . '/db.php';
/* ===========================================================
   SECURITY CHECK
=========================================================== */
if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    exit("Unauthorized access.");
}

$isSuperAdmin = ($_SESSION['role'] === "superadmin" || $_SESSION['role'] === "hr");

if (!$isSuperAdmin) {
    http_response_code(403);
    exit("You do not have permission to create salary requests.");
}

/* ===========================================================
   COLLECT POST DATA SAFELY
=========================================================== */
function clean($v) {
    return trim($v ?? "");
}

$admin_id              = intval($_POST['admin_id'] ?? 0);
$month                 = clean($_POST['month']);
$salary_rwf            = clean($_POST['salary_rwf']);
$payment_method        = clean($_POST['payment_method']);

$bank_name             = clean($_POST['bank_name']);
$bank_account          = clean($_POST['bank_account']);
$bank_registered_names = clean($_POST['bank_registered_names']);

$momo_number           = clean($_POST['momo_number']);
$momo_registered_names = clean($_POST['momo_registered_names']);

/* ===========================================================
   CORE VALIDATION
=========================================================== */
if ($admin_id === 0 || $month === "" || $salary_rwf === "" || $payment_method === "") {
    $_SESSION['error'] = "Missing required fields.";
    header("Location: salary-report.php");
    exit;
}

if (!is_numeric($salary_rwf)) {
    $_SESSION['error'] = "Invalid salary amount.";
    header("Location: salary-report.php");
    exit;
}

$salary_rwf = floatval($salary_rwf);

/* ===========================================================
   PAYMENT METHOD VALIDATION
=========================================================== */
if ($payment_method === "bank") {

    if ($bank_name === "" || $bank_account === "" || $bank_registered_names === "") {
        $_SESSION['error'] = "Please fill all bank fields.";
        header("Location: salary-report.php");
        exit;
    }

    // Nullify MoMo fields
    $momo_number = null;
    $momo_registered_names = null;

} elseif ($payment_method === "momo") {

    if ($momo_number === "" || $momo_registered_names === "") {
        $_SESSION['error'] = "Please fill all MoMo fields.";
        header("Location: salary-report.php");
        exit;
    }

    // Nullify bank fields
    $bank_name = null;
    $bank_account = null;
    $bank_registered_names = null;

} else {
    $_SESSION['error'] = "Invalid payment method.";
    header("Location: salary-report.php");
    exit;
}

/* ===========================================================
   PREVENT DUPLICATE REQUEST FOR SAME MONTH
=========================================================== */
$check = $conn->prepare("
    SELECT id FROM salary_requests 
    WHERE admin_id = ? AND month = ?
");
$check->bind_param("is", $admin_id, $month);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $check->close();
    $_SESSION['error'] = "A salary request already exists for this staff member for the selected month.";
    header("Location: salary-report.php");
    exit;
}
$check->close();

/* ===========================================================
   INSERT NEW REQUEST (Status defaults to 'pending' in database)
=========================================================== */
$stmt = $conn->prepare("
    INSERT INTO salary_requests (
        admin_id,
        month,
        total_salary_rwf,
        payment_method,
        bank_name,
        bank_account,
        bank_registered_names,
        momo_number,
        momo_registered_names,
        requested_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param(
    "isdssssss",
    $admin_id,
    $month,
    $salary_rwf,
    $payment_method,
    $bank_name,
    $bank_account,
    $bank_registered_names,
    $momo_number,
    $momo_registered_names
);

if ($stmt->execute()) {
    $_SESSION['success'] = "Salary request submitted successfully! Status: Pending";
    header("Location: salary-report.php");
    exit;
} else {
    $_SESSION['error'] = "Database error: " . $stmt->error;
    header("Location: salary-report.php");
    exit;
}

$stmt->close();
$conn->close();
?>