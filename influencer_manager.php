<?php
require_once __DIR__ . '/db.php';
/* -------------------- SERIAL GENERATOR -------------------- */
function generateInfluencerSerial($conn) {
    $year = date("Y");
    $sql = $conn->query("SELECT COUNT(*) AS total FROM influencers WHERE YEAR(created_at) = $year");
    $row = $sql->fetch_assoc();
    $count = $row["total"] + 1;
    return "INF-$year-" . str_pad($count, 4, "0", STR_PAD_LEFT);
}

/* -------------------- ADD INFLUENCER -------------------- */
if (isset($_POST["action"]) && $_POST["action"] == "add") {

    $serial   = generateInfluencerSerial($conn);
    $name     = $_POST["influencer_name"];
    $phone    = $_POST["phone"];
    $email    = $_POST["email"];
    $cost     = $_POST["cost_per_month"];
    $start    = $_POST["start_date"];
    $months   = $_POST["paid_months"];
    $nextPay  = $_POST["next_payment_date"];
    $status   = $_POST["status"];

    $stmt = $conn->prepare("
        INSERT INTO influencers 
        (serial_no, influencer_name, phone, email, cost_per_month, start_date, paid_months, next_payment_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("ssssdsiss", $serial, $name, $phone, $email, $cost, $start, $months, $nextPay, $status);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF'] . "?added=1");
    exit;
}

/* -------------------- UPDATE INFLUENCER -------------------- */
if (isset($_POST["action"]) && $_POST["action"] == "edit") {

    $id       = $_POST["id"];
    $name     = $_POST["influencer_name"];
    $phone    = $_POST["phone"];
    $email    = $_POST["email"];
    $cost     = $_POST["cost_per_month"];
    $start    = $_POST["start_date"];
    $months   = $_POST["paid_months"];
    $nextPay  = $_POST["next_payment_date"];
    $status   = $_POST["status"];

    $stmt = $conn->prepare("
        UPDATE influencers 
        SET influencer_name=?, phone=?, email=?, cost_per_month=?, start_date=?, paid_months=?, next_payment_date=?, status=?
        WHERE id=?
    ");

    $stmt->bind_param("sssdsissi", $name, $phone, $email, $cost, $start, $months, $nextPay, $status, $id);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
    exit;
}

/* -------------------- DELETE -------------------- */
if (isset($_POST["delete_id"])) {
    $id = intval($_POST["delete_id"]);
    $conn->query("DELETE FROM influencers WHERE id=$id");
    header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Influencer Management</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <style>
        body { background:#F6F8FA; font-family:'Segoe UI', sans-serif; }
        .page-title { font-weight:700; color:#1A5B9E; }
        th { background:#0E4C2E !important; color:white !important; font-size:14px; }
        .card { border-radius:18px; border:none; }
        .modal-content { border-radius:16px; }
        .form-floating label { color:#777; }
        table td { vertical-align: middle !important; white-space: nowrap !important; }
        table th { white-space: nowrap !important; }
        .btn-primary, .btn-warning, .btn-danger { border-radius:6px; }
    </style>
</head>

<body class="p-4">

<div class="container">

    <h2 class="page-title text-center mb-4">Influencer Management</h2>

    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary px-4 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#addModal">
            + Add Influencer
        </button>
    </div>

    <div class="card p-4 shadow-sm">
        <table id="infTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Serial</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Cost/Month</th>
                    <th>Start Date</th>
                    <th>Paid Months</th>
                    <th>Next Payment</th>
                    <th>Status</th>
                    <th>Total Paid</th>
                    <th width="130">Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php
            $q = $conn->query("SELECT * FROM influencers ORDER BY id DESC");

            while ($row = $q->fetch_assoc()) {
                $safe = htmlspecialchars(json_encode($row), ENT_QUOTES);

                $badge = $row['status'] == "Active"
                        ? "<span class='badge bg-success'>Active</span>"
                        : "<span class='badge bg-secondary'>Not Active</span>";

                $costFormatted = number_format($row['cost_per_month']) . " RWF";
                $totalPaidFormatted = number_format($row['total_paid_amount']) . " RWF";

                echo "
                <tr>
                    <td>{$row['serial_no']}</td>
                    <td>{$row['influencer_name']}</td>
                    <td>{$row['phone']}</td>
                    <td>{$row['email']}</td>
                    <td>$costFormatted</td>
                    <td>{$row['start_date']}</td>
                    <td>{$row['paid_months']}</td>
                    <td>{$row['next_payment_date']}</td>
                    <td>$badge</td>
                    <td class='fw-bold text-success'>$totalPaidFormatted</td>

                    <td class='text-center'>
                        <button class='btn btn-warning btn-sm me-1' onclick='editInf($safe)'>Edit</button>

                        <form method=\"POST\" class=\"d-inline\">
                            <input type=\"hidden\" name=\"delete_id\" value=\"{$row['id']}\">
                            <button class='btn btn-danger btn-sm' onclick=\"return confirm('Delete this influencer?')\">Delete</button>
                        </form>
                    </td>
                </tr>
                ";
            }
            ?>
            </tbody>
        </table>
    </div>

</div>


<!-- ADD MODAL -->
<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add Influencer</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="modal-body">

                    <div class="form-floating mb-3">
                        <input type="text" name="influencer_name" class="form-control" required>
                        <label>Name of Influencer</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" name="phone" class="form-control" required>
                        <label>Phone</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="email" name="email" class="form-control" required>
                        <label>Email</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="number" name="cost_per_month" step="0.01" class="form-control" required>
                        <label>Cost per Month</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="date" name="start_date" class="form-control" required>
                        <label>Start Date</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="number" name="paid_months" class="form-control" required>
                        <label>Paid Months</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="date" name="next_payment_date" class="form-control" required>
                        <label>Next Payment</label>
                    </div>

                    <select class="form-select mb-2" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Not Active">Not Active</option>
                    </select>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary px-4">Save</button>
                </div>
            </form>

        </div>
    </div>
</div>




<!-- EDIT MODAL -->
<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Influencer</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="modal-body">

                    <div class="form-floating mb-3">
                        <input type="text" name="influencer_name" id="edit_name" class="form-control" required>
                        <label>Name</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" name="phone" id="edit_phone" class="form-control" required>
                        <label>Phone</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                        <label>Email</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="number" name="cost_per_month" id="edit_cost" step="0.01" class="form-control" required>
                        <label>Cost per Month</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="date" name="start_date" id="edit_start" class="form-control" required>
                        <label>Start Date</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="number" name="paid_months" id="edit_months" class="form-control" required>
                        <label>Paid Months</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="date" name="next_payment_date" id="edit_next" class="form-control" required>
                        <label>Next Payment</label>
                    </div>

                    <select class="form-select" name="status" id="edit_status" required>
                        <option value="Active">Active</option>
                        <option value="Not Active">Not Active</option>
                    </select>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary px-4">Update</button>
                </div>

            </form>

        </div>
    </div>
</div>



<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    $('#infTable').DataTable({
        "pageLength": 10
    });
});

function editInf(r) {
    $("#edit_id").val(r.id);
    $("#edit_name").val(r.influencer_name);
    $("#edit_phone").val(r.phone);
    $("#edit_email").val(r.email);
    $("#edit_cost").val(r.cost_per_month);
    $("#edit_start").val(r.start_date);
    $("#edit_months").val(r.paid_months);
    $("#edit_next").val(r.next_payment_date);
    $("#edit_status").val(r.status);

    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

</body>
</html>
