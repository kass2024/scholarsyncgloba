<?php
session_start();
require_once __DIR__ . '/db.php';

// Check if user is logged in
$admin_id = $_SESSION['id'] ?? null;
if (!$admin_id) {
    header("Location: admin-login.php");
    exit;
}

// Get role
$role = 'standard'; // default role
$admin_id_safe = mysqli_real_escape_string($conn, $admin_id);
$result = mysqli_query($conn, "SELECT role FROM admins WHERE id = '$admin_id_safe'");
if ($result && mysqli_num_rows($result) > 0) {
    $admin = mysqli_fetch_assoc($result);
    $role = $admin['role'] ?? 'standard';
}

$isSuperAdmin = ($role === 'superadmin');

// Debug: Uncomment for testing
// echo "Role: " . htmlspecialchars($role) . " | IsSuperAdmin: " . ($isSuperAdmin ? 'true' : 'false');
/* ------------------------ SERIAL GENERATOR ------------------------ */
function generatePlatformSerial($conn) {
    $year = date("Y");
    $sql = $conn->query("SELECT COUNT(*) AS total FROM platforms WHERE YEAR(created_at) = $year");
    $row = $sql->fetch_assoc();
    $count = $row["total"] + 1;
    return "PLT-$year-" . str_pad($count, 4, "0", STR_PAD_LEFT);
}

/* ------------------------ ADD PLATFORM ------------------------ */
if (isset($_POST["action"]) && $_POST["action"] == "add") {

    $serial   = generatePlatformSerial($conn);
    $name     = $_POST["platform_name"];
    $user     = $_POST["username"];
    $pass     = $_POST["password"];
    $person   = $_POST["person_in_charge"];
    $status   = $_POST["status"];
    $link     = $_POST["platform_link"];

    $stmt = $conn->prepare("
        INSERT INTO platforms 
        (serial_no, platform_name, username, password, person_in_charge, status, platform_link)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("ssssiss", $serial, $name, $user, $pass, $person, $status, $link);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF'] . "?added=1");
    exit;
}

/* ------------------------ UPDATE PLATFORM ------------------------ */
if (isset($_POST["action"]) && $_POST["action"] == "edit") {

    $id       = $_POST["id"];
    $name     = $_POST["platform_name"];
    $user     = $_POST["username"];
    $pass     = $_POST["password"];
    $person   = $_POST["person_in_charge"];
    $status   = $_POST["status"];
    $link     = $_POST["platform_link"];

    $stmt = $conn->prepare("
        UPDATE platforms 
        SET platform_name=?, username=?, password=?, person_in_charge=?, status=?, platform_link=?
        WHERE id=?
    ");

    $stmt->bind_param("sssissi", $name, $user, $pass, $person, $status, $link, $id);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
    exit;
}

/* ------------------------ DELETE PLATFORM ------------------------ */
if (isset($_GET["delete"])) {
    $id = intval($_GET["delete"]);
    $conn->query("DELETE FROM platforms WHERE id=$id");
    header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Platforms Management</title>

    <!-- Bootstrap + Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <!-- Bootstrap Select -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">

    <style>
        body { background: #F5F9F3; font-family: 'Segoe UI'; }
        .page-title { font-weight: 700; color: #1E64B7; }
        .card { border-radius: 14px; border: none; }
        th { background: #2E6A2C !important; color: white; }
        .modal-header { background: #2E6A2C; color: white; }
        .copy-btn { cursor: pointer; }

        /* Smart link preview */
        .link-preview {
            max-width: 180px;
            display: inline-block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }
        .nowrap { white-space: nowrap; }
    </style>
</head>

<body class="p-4">

<div class="container">

    <h2 class="page-title text-center mb-3">Platforms Access Manager</h2>

    <?php if ($isSuperAdmin): ?>
    <div class="d-flex justify-content-end mb-4">
        <button class="btn btn-primary px-3" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> Add Platform
        </button>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm p-3">
        <table id="pltTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Serial</th>
                    <th>Platform</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Person in Charge</th>
                    <th>Status</th>
                    <th>Link</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php
            function previewLink($url) {
                if (!$url) return "";
                $clean = preg_replace("(^https?://)", "", $url);
                return strlen($clean) > 18 ? substr($clean, 0, 18) . "…" : $clean;
            }

            $q = $conn->query("SELECT * FROM platforms ORDER BY id DESC");

            while ($row = $q->fetch_assoc()) {
                $safeRow = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');

                // Load admin name
                $admin = $conn->query("SELECT full_name FROM admins WHERE id={$row['person_in_charge']}")->fetch_assoc();
                $adminName = $admin ? $admin['full_name'] : "Unknown";

                ?>
                <tr>
                    <td><?= $row['serial_no'] ?></td>
                    <td><?= $row['platform_name'] ?></td>
                    <td><?= $row['username'] ?></td>

                    <td class='nowrap text-center'>
                        <span id='pwd_<?= $row['id'] ?>' style='letter-spacing:2px;'>•••••••</span>
                        <i class='bi bi-eye ms-2 text-primary' style='cursor:pointer;' onclick="togglePassword('<?= $row['password'] ?>', <?= $row['id'] ?>)"></i>
                    </td>

                    <td><?= $adminName ?></td>
                    <td><?= $row['status'] ?></td>

                    <td class='nowrap text-center'>
                        <?php if ($row['platform_link']): ?>
                            <a href='<?= $row['platform_link'] ?>' target='_blank' class='text-primary text-decoration-none' title='<?= $row['platform_link'] ?>'>
                                <i class='bi bi-link-45deg'></i>
                                <span class='link-preview'><?= previewLink($row['platform_link']) ?></span>
                            </a>
                            <i class='bi bi-clipboard text-success ms-2 copy-btn' onclick="copyText('<?= $row['platform_link'] ?>')"></i>
                        <?php endif; ?>
                    </td>

                    <td class='text-center'>
                        <?php if ($isSuperAdmin): ?>
                            <button class='btn btn-warning btn-sm me-1' onclick='editPlatform(<?= $safeRow ?>)'>
                                <i class='bi bi-pencil-square'></i>
                            </button>
                            <a href='?delete=<?= $row['id'] ?>' class='btn btn-danger btn-sm' onclick='return confirm("Delete this platform?")'>
                                <i class='bi bi-trash'></i>
                            </a>
                        <?php else: ?>
                            <span class='text-muted'>-</span>
                        <?php endif; ?>
                    </td>
                </tr>
<?php
            }
            ?>
            </tbody>
        </table>
    </div>

</div>

<?php if ($isSuperAdmin): ?>
<!-- ADD MODAL -->
<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg" style="border-radius:15px;">

            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Platform</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="modal-body p-4">

                    <div class="form-floating mb-3">
                        <input type="text" name="platform_name" class="form-control" required>
                        <label>Platform Name</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" name="username" class="form-control" required>
                        <label>Username</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" name="password" class="form-control" required>
                        <label>Password</label>
                    </div>

                    <label class="fw-semibold">Person in charge</label>
                    <select class="selectpicker w-100" name="person_in_charge" data-live-search="true" required>
                        <?php
                        $adminQ = $conn->query("SELECT id, full_name FROM admins WHERE role IN ('staff','superadmin','agent')");
                        while ($a = $adminQ->fetch_assoc()) {
                            echo "<option value='{$a['id']}'>{$a['full_name']}</option>";
                        }
                        ?>
                    </select>

                    <label class="fw-semibold mt-3">Status</label>
                    <select class="form-select" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Not Active">Not Active</option>
                    </select>

                    <div class="form-floating mt-3">
                        <input type="url" name="platform_link" class="form-control">
                        <label>Platform Link</label>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary px-4">Save</button>
                </div>

            </form>

        </div>
    </div>
</div>
<?php endif; ?>


<?php if ($isSuperAdmin): ?>
<!-- EDIT MODAL -->
<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg" style="border-radius:15px;">

            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Platform</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="modal-body p-4">

                    <div class="form-floating mb-3">
                        <input type="text" name="platform_name" id="edit_name" class="form-control" required>
                        <label>Platform Name</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" name="username" id="edit_user" class="form-control" required>
                        <label>Username</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" name="password" id="edit_pass" class="form-control" required>
                        <label>Password</label>
                    </div>

                    <label class="fw-semibold">Person in charge</label>
                    <select class="selectpicker w-100" id="edit_person" name="person_in_charge" data-live-search="true" required>
                        <?php
                        $adminQ = $conn->query("SELECT id, full_name FROM admins WHERE role IN ('staff','superadmin')");
                        while ($a = $adminQ->fetch_assoc()) {
                            echo "<option value='{$a['id']}'>{$a['full_name']}</option>";
                        }
                        ?>
                    </select>

                    <label class="fw-semibold mt-3">Status</label>
                    <select class="form-select" id="edit_status" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Not Active">Not Active</option>
                    </select>

                    <div class="form-floating mt-3">
                        <input type="url" name="platform_link" id="edit_link" class="form-control">
                        <label>Platform Link</label>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary px-4">Update</button>
                </div>

            </form>

        </div>
    </div>
</div>
<?php endif; ?>


<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>

<script>
$(document).ready(function () {
    $('#pltTable').DataTable();
    $('.selectpicker').selectpicker();
});

/* ----------- COPY BUTTON ----------- */
function copyText(text) {
    navigator.clipboard.writeText(text);
    alert("Copied ✓");
}

/* ----------- PASSWORD SHOW/HIDE ----------- */
function togglePassword(real, id) {
    var span = document.getElementById("pwd_" + id);

    if (span.innerHTML.includes("•")) {
        span.innerHTML = real;
    } else {
        span.innerHTML = "•••••••";
    }
}

/* ----------- LOAD EDIT MODAL ----------- */
function editPlatform(row) {
    $("#edit_id").val(row.id);
    $("#edit_name").val(row.platform_name);
    $("#edit_user").val(row.username);
    $("#edit_pass").val(row.password);
    $("#edit_person").val(row.person_in_charge).selectpicker("refresh");
    $("#edit_status").val(row.status);
    $("#edit_link").val(row.platform_link);

    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

</body>
</html>
