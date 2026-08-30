<?php
session_start();
require_once __DIR__ . '/db.php';
/* ===========================================================
   AUTH CHECK
=========================================================== */
if (!isset($_SESSION['id'])) {
    http_response_code(403);
    exit("Access denied. Please log in.");
}

$admin_id = intval($_SESSION['id']);

/* ===========================================================
   GET ADMIN INFO
=========================================================== */
$stmt = $conn->prepare("SELECT full_name FROM admins WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($admin_name);
$stmt->fetch();
$stmt->close();

if (!$admin_name) {
    exit("Invalid admin session.");
}

/* ===========================================================
   CSRF TOKEN
=========================================================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Salary Request</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7fb;
            padding: 15px;
        }
        .card-custom {
            border-radius: 16px;
            padding: 28px;
            border: none;
            background: white;
        }
        .btn-dashboard {
            background: #6c63ff;
            color: white;
            font-weight: bold;
            border-radius: 10px;
            padding: 7px 16px;
        }
        .btn-dashboard:hover {
            background: #4c45ff;
        }
        .salary-box {
            background: #e8ffe8;
            border-left: 6px solid #28a745;
            padding: 15px;
            border-radius: 8px;
            font-size: 17px;
            display: none;
        }
    </style>
</head>

<body>

<div class="container">
    <div class="card shadow card-custom mx-auto" style="max-width: 700px;">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold m-0">💰 Salary Request</h3>
            <a href="admin-dashboard.php" class="btn btn-dashboard">⬅ Back</a>
        </div>

        <!-- ALERTS -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <p class="mb-3">
            <strong>Staff:</strong> <?= htmlspecialchars($admin_name); ?>
        </p>

        <!-- MONTH PICKER -->
        <label class="form-label fw-bold mt-3">Select Month:</label>
        <input type="month" id="monthPicker" class="form-control" value="<?= date('Y-m'); ?>">

        <!-- SALARY BOX -->
        <div id="salaryBox" class="salary-box mt-4">
            Salary for <span id="salaryMonth"></span>:
            <span class="text-success fw-bold" id="salaryAmount"></span>
        </div>

        <hr id="divider" class="mt-4" style="display:none;">

        <!-- SALARY REQUEST FORM -->
        <form method="POST" action="submit-salary-request.php" id="salaryForm" style="display:none;">

            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
            <input type="hidden" name="month" id="reqMonth">
            <input type="hidden" name="salary_rwf" id="reqSalary">

            <!-- PAYMENT METHOD -->
            <label class="form-label fw-bold">Payment Method:</label>
            <select name="payment_method" id="payment_method" class="form-select" required>
                <option value="">Choose option</option>
                <option value="bank">Bank Transfer</option>
                <option value="momo">Mobile Money</option>
            </select>

            <!-- BANK FIELDS -->
            <div id="bank_fields" class="mt-3" style="display:none;">
                <label class="form-label">Bank Name</label>
                <input type="text" name="bank_name" class="form-control">

                <label class="form-label mt-2">Account Number</label>
                <input type="text" name="bank_account" class="form-control">

                <label class="form-label mt-2">Registered Names</label>
                <input type="text" name="bank_registered_names" class="form-control">
            </div>

            <!-- MOMO FIELDS -->
            <div id="momo_fields" class="mt-3" style="display:none;">
                <label class="form-label">MoMo Number</label>
                <input type="text" name="momo_number" class="form-control">

                <label class="form-label mt-2">Registered Names</label>
                <input type="text" name="momo_registered_names" class="form-control">
            </div>

            <button type="submit" class="btn btn-success w-100 mt-4 py-2">
                Submit Salary Request
            </button>
        </form>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
/* ===========================================================
   AUTO FETCH SALARY WHEN MONTH CHANGES
=========================================================== */
$("#monthPicker").on("change", function () {
    let month = $(this).val();
    if (!month) return;

    $.post("calculate-salary.php", { month: month }, function(res) {

        if (res.error) {
            alert(res.error);
            return;
        }

        // Update UI
        $("#salaryMonth").text(month);
        $("#salaryAmount").text(res.salary + " RWF");

        $("#salaryBox").fadeIn();
        $("#divider").fadeIn();
        $("#salaryForm").fadeIn();

        $("#reqMonth").val(month);
        $("#reqSalary").val(res.raw_salary);

    }, "json").fail(function() {
        alert("Server error. Try again.");
    });
});

/* ===========================================================
   PAYMENT METHOD TOGGLER + REQUIRED FLAG
=========================================================== */
$("#payment_method").on("change", function () {
    let method = $(this).val();

    // Hide both first
    $("#bank_fields").hide();
    $("#momo_fields").hide();

    // Remove required first
    $("#bank_fields input").prop("required", false);
    $("#momo_fields input").prop("required", false);

    // Apply logic
    if (method === "bank") {
        $("#bank_fields").show();
        $("#bank_fields input").prop("required", true);
    }
    else if (method === "momo") {
        $("#momo_fields").show();
        $("#momo_fields input").prop("required", true);
    }
});
</script>

</body>
</html>
