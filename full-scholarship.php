<?php
require_once __DIR__ . '/db.php';
/* ------------------------ SERIAL GENERATOR ------------------------ */
function generateSerial($conn) {
    $year = date("Y");
    $sql = $conn->query("SELECT COUNT(*) AS total FROM full_scholarships WHERE YEAR(created_at) = $year");
    $row = $sql->fetch_assoc();
    $count = $row["total"] + 1;
    return "FSC-$year-" . str_pad($count, 4, "0", STR_PAD_LEFT);
}

/* ------------------------ ADD SCHOLARSHIP ------------------------ */
if (isset($_POST["action"]) && $_POST["action"] == "add") {
    $serial = generateSerial($conn);

    $country  = $_POST["country"];
    $school   = $_POST["school_name"];
    $study    = $_POST["study_level"];
    $super    = $_POST["supervisor_required"];
    $english  = $_POST["english_requirement"];
    $univlink = $_POST["university_link"];
    $applink  = $_POST["application_link"];

    $stmt = $conn->prepare("
        INSERT INTO full_scholarships 
        (serial_no, country, school_name, study_level, supervisor_required, english_requirement, university_link, application_link)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("ssssssss", $serial, $country, $school, $study, $super, $english, $univlink, $applink);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF'] . "?added=1");
    exit;
}

/* ------------------------ UPDATE SCHOLARSHIP ------------------------ */
if (isset($_POST["action"]) && $_POST["action"] == "edit") {

    $id       = $_POST["id"];
    $country  = $_POST["country"];
    $school   = $_POST["school_name"];
    $study    = $_POST["study_level"];
    $super    = $_POST["supervisor_required"];
    $english  = $_POST["english_requirement"];
    $univlink = $_POST["university_link"];
    $applink  = $_POST["application_link"];

    $stmt = $conn->prepare("
        UPDATE full_scholarships 
        SET country=?, school_name=?, study_level=?, supervisor_required=?, english_requirement=?, university_link=?, application_link=?
        WHERE id=?
    ");

    $stmt->bind_param("sssssssi", $country, $school, $study, $super, $english, $univlink, $applink, $id);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
    exit;
}

/* ------------------------ DELETE ------------------------ */
if (isset($_GET["delete"])) {
    $id = intval($_GET["delete"]);
    $conn->query("DELETE FROM full_scholarships WHERE id=$id");
    header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Masters and PhD full scholarship</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">

    <style>
        body { background: #F5F9F3; font-family: 'Segoe UI'; }
        .page-title { font-weight: 700; color: #1E64B7; }
        .card { border-radius: 14px; border: none; }
        .btn-primary { background: #1E64B7; border: none; }
        .btn-primary:hover { background: #144A82; }
        th { background: #2E6A2C !important; color: white; }
        .modal-header { background: #2E6A2C; color: white; }
        .modern-input, .bootstrap-select>.dropdown-toggle {
            border-radius: 10px !important;
            padding: 12px !important;
            border: 1px solid #A3C8A0;
        }
        .modern-input:focus {
            border-color: #2E6A2C !important;
            box-shadow: 0 0 0 0.15rem rgba(46, 106, 44, 0.3) !important;
        }
        .copy-btn { cursor: pointer; }
        .link-preview {
    max-width: 180px;          /* fixed width to prevent table breaking */
    display: inline-block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;   /* (...) */
    vertical-align: middle;
}
.nowrap {
    white-space: nowrap;
}

    </style>
</head>
<body class="p-4">

<div class="container">

    <div class="text-center mb-3">
        <h2 class="page-title">Masters and PhD full scholarship</h2>
    </div>

    <div class="d-flex justify-content-end mb-4">
        <button class="btn btn-primary px-3" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> Add Scholarship
        </button>
    </div>

    <div class="card shadow-sm p-3">
        <table id="schTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Serial</th>
                    <th>Country</th>
                    <th>School Name</th>
                    <th>Study Level</th>
                    <th>Supervisor</th>
                    <th>English</th>
                    <th>University</th>
                    <th>Application</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $q = $conn->query("SELECT * FROM full_scholarships ORDER BY id DESC");

            while ($row = $q->fetch_assoc()) {
                $safeRow = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');

                $u = $row['university_link'];
                $a = $row['application_link'];

                echo "
                <tr>
                    <td>{$row['serial_no']}</td>
                    <td>{$row['country']}</td>
                    <td>{$row['school_name']}</td>
                    <td>{$row['study_level']}</td>
                    <td>{$row['supervisor_required']}</td>
                    <td>{$row['english_requirement']}</td>
<td class='text-center nowrap'>
    " . ($u ? "
        <a href='$u' target='_blank' class='text-primary text-decoration-none' title='$u'>
            <i class='bi bi-link-45deg'></i>
            <span class='link-preview'>" . substr(preg_replace('(^https?://)', '', $u), 0, 18) . "…</span>
        </a>
        <i class='bi bi-clipboard copy-btn text-success ms-2' onclick=\"copyText('$u')\"></i>
    " : "") . "
</td>


<td class='text-center nowrap'>
    " . ($a ? "
        <a href='$a' target='_blank' class='text-primary text-decoration-none' title='$a'>
            <i class='bi bi-link-45deg'></i>
            <span class='link-preview'>" . substr(preg_replace('(^https?://)', '', $a), 0, 18) . "…</span>
        </a>
        <i class='bi bi-clipboard copy-btn text-success ms-2' onclick=\"copyText('$a')\"></i>
    " : "") . "
</td>


                    <td class='text-center'>
                        <button class='btn btn-warning btn-sm me-1' onclick='editScholarship($safeRow)'>
                            <i class='bi bi-pencil-square'></i>
                        </button>
                        <a href='?delete={$row['id']}' class='btn btn-danger btn-sm'
                           onclick='return confirm(\"Delete this scholarship?\")'>
                            <i class='bi bi-trash'></i>
                        </a>
                    </td>
                </tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg" style="border-radius: 16px;">

            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Scholarship</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="modal-body p-4">

                    <label class="fw-semibold">Country</label>
                    <select class="selectpicker w-100" name="country" data-live-search="true" required>
                        <?php
                        $countries = ["United States","United Kingdom","Canada","Germany","France","China","Japan","Australia","Belgium","Netherlands","Finland","Sweden","Norway","Italy","Spain","South Korea","Switzerland"];
                        foreach ($countries as $c) echo "<option value='$c'>$c</option>";
                        ?>
                    </select>

                    <div class="form-floating mt-3">
                        <input type="text" class="form-control modern-input" name="school_name" required>
                        <label>School / University Name</label>
                    </div>

                    <label class="fw-semibold mt-3">Study Level</label>
                    <select class="form-select modern-input" name="study_level" required>
                        <option value="Masters">Masters</option>
                        <option value="PhD">PhD</option>
                        <option value="Masters and PhD">Masters and PhD</option>
                    </select>

                    <label class="fw-semibold mt-3">Supervisor Requirement</label>
                    <select class="form-select modern-input" name="supervisor_required" required>
                        <option value="Require Supervisor">Require Supervisor</option>
                        <option value="Do Not Require Supervisor">Do Not Require Supervisor</option>
                    </select>

                    <label class="fw-semibold mt-3">English Proficiency</label>
                    <select class="form-select modern-input" name="english_requirement" required>
                        <option value="Accept Medium of Instruction">Accept Medium of Instruction</option>
                        <option value="Require English Certificate">Require English Certificate</option>
                    </select>

                    <div class="form-floating mt-3">
                        <input type="url" class="form-control modern-input" name="university_link">
                        <label>University Link</label>
                    </div>

                    <div class="form-floating mt-3">
                        <input type="url" class="form-control modern-input" name="application_link">
                        <label>Application Link</label>
                    </div>

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
        <div class="modal-content shadow-lg" style="border-radius: 16px;">

            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Scholarship</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="modal-body p-4">

                    <label class="fw-semibold">Country</label>
                    <select class="selectpicker w-100" id="edit_country" name="country" data-live-search="true" required>
                        <?php foreach ($countries as $c) echo "<option value='$c'>$c</option>"; ?>
                    </select>

                    <div class="form-floating mt-3">
                        <input type="text" class="form-control modern-input" id="edit_school" name="school_name" required>
                        <label>School / University Name</label>
                    </div>

                    <label class="fw-semibold mt-3">Study Level</label>
                    <select class="form-select modern-input" id="edit_study" name="study_level" required>
                        <option value="Masters">Masters</option>
                        <option value="PhD">PhD</option>
                        <option value="Masters and PhD">Masters and PhD</option>
                    </select>

                    <label class="fw-semibold mt-3">Supervisor Requirement</label>
                    <select class="form-select modern-input" id="edit_super" name="supervisor_required" required>
                        <option value="Require Supervisor">Require Supervisor</option>
                        <option value="Do Not Require Supervisor">Do Not Require Supervisor</option>
                    </select>

                    <label class="fw-semibold mt-3">English Proficiency</label>
                    <select class="form-select modern-input" id="edit_english" name="english_requirement" required>
                        <option value="Accept Medium of Instruction">Accept Medium of Instruction</option>
                        <option value="Require English Certificate">Require English Certificate</option>
                    </select>

                    <div class="form-floating mt-3">
                        <input type="url" class="form-control modern-input" id="edit_unilink" name="university_link">
                        <label>University Link</label>
                    </div>

                    <div class="form-floating mt-3">
                        <input type="url" class="form-control modern-input" id="edit_applink" name="application_link">
                        <label>Application Link</label>
                    </div>

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
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>

<script>
$(document).ready(function () {
    $('#schTable').DataTable();
    $('.selectpicker').selectpicker();
});

function copyText(text) {
    navigator.clipboard.writeText(text);
    alert("Copied ✓");
}

function editScholarship(row) {
    $("#edit_id").val(row.id);
    $("#edit_country").val(row.country);
    $("#edit_country").selectpicker("refresh");

    $("#edit_school").val(row.school_name);
    $("#edit_study").val(row.study_level);
    $("#edit_super").val(row.supervisor_required);
    $("#edit_english").val(row.english_requirement);

    $("#edit_unilink").val(row.university_link);
    $("#edit_applink").val(row.application_link);

    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

</body>
</html>
