<?php
require_once __DIR__ . '/db.php';
// Generate Serial Number
function generateSerialNumber($conn) {
    $year = date("Y");
    $sql = $conn->query("SELECT COUNT(*) AS total FROM abroad_courses WHERE YEAR(created_at) = $year");
    $row = $sql->fetch_assoc();
    $count = $row["total"] + 1;
    return "SSA-$year-" . str_pad($count, 4, "0", STR_PAD_LEFT);
}

/* ------------------------ ADD COURSE ------------------------ */
if (isset($_POST["action"]) && $_POST["action"] == "add") {
    $serial = generateSerialNumber($conn);
    $country = $_POST["country"];
    $course = $_POST["course_name"];
    $link = $_POST["course_link"];
    $start = $_POST["start_date"];
    $end = $_POST["end_date"];

    $status = ($end >= date("Y-m-d")) ? "Open" : "Closed";

    $stmt = $conn->prepare("
        INSERT INTO abroad_courses (serial_no, country, course_name, course_link, start_date, end_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssss", $serial, $country, $course, $link, $start, $end, $status);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF'] . "?added=1");
    exit;
}

/* ------------------------ UPDATE COURSE ------------------------ */
if (isset($_POST["action"]) && $_POST["action"] == "edit") {
    $id = $_POST["id"];
    $country = $_POST["country"];
    $course = $_POST["course_name"];
    $link = $_POST["course_link"];
    $start = $_POST["start_date"];
    $end = $_POST["end_date"];

    $status = ($end >= date("Y-m-d")) ? "Open" : "Closed";

    $stmt = $conn->prepare("
        UPDATE abroad_courses 
        SET country=?, course_name=?, course_link=?, start_date=?, end_date=?, status=?
        WHERE id=?
    ");
    $stmt->bind_param("ssssssi", $country, $course, $link, $start, $end, $status, $id);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
    exit;
}

/* ------------------------ DELETE COURSE ------------------------ */
if (isset($_GET["delete"])) {
    $id = intval($_GET["delete"]);
    $conn->query("DELETE FROM abroad_courses WHERE id=$id");
    header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Short Study Abroad Manager</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <!-- Bootstrap Select -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">

    <style>
        body {
            background: #F5F9F3;
            font-family: 'Segoe UI';
        }
        .page-title {
            font-weight: 700;
            color: #1E64B7;
        }
        .card {
            border-radius: 14px;
            border: none;
        }

        /* Primary buttons using Logo Blue */
        .btn-primary {
            background: #1E64B7;
            border: none;
        }
        .btn-primary:hover {
            background: #144A82;
        }

        /* Country dropdown */
        .bootstrap-select .dropdown-toggle {
            border-radius: 10px;
            border: 1px solid #AACDA5;
            padding: 12px;
        }

        /* Table header */
        th {
            background: #2E6A2C !important;
            color: white;
        }

        /* Modal Header: Logo Green */
        .modal-header {
            background: #2E6A2C;
            color: white;
        }

        .modern-input,
        .bootstrap-select>.dropdown-toggle {
            border-radius: 10px !important;
            padding: 12px !important;
            border: 1px solid #A3C8A0;
        }
        .modern-input:focus {
            border-color: #2E6A2C !important;
            box-shadow: 0 0 0 0.15rem rgba(46, 106, 44, 0.3) !important;
        }

        .calendar-icon {
            background: #E3F1E0 !important;
            border: 1px solid #A3C8A0 !important;
            border-top-left-radius: 10px !important;
            border-bottom-left-radius: 10px !important;
            color: #2E6A2C;
        }
        .modern-date {
            border-radius: 10px !important;
            border: 1px solid #A3C8A0;
            padding: 10px !important;
        }

        .modern-date:focus {
            border-color: #2E6A2C !important;
            box-shadow: 0 0 0 0.15rem rgba(46, 106, 44, 0.3) !important;
        }

        /* Modal Animation */
        .modal.fade .modal-dialog {
            transform: translateY(-20px);
            transition: all 0.25s ease-in-out;
        }
        .modal.show .modal-dialog {
            transform: translateY(0);
        }
    </style>
</head>
<body class="p-4">

<div class="container">

    <div class="text-center mb-3">
        <h2 class="page-title">Short Study Abroad & Seasonal Academy</h2>
    </div>

    <div class="d-flex justify-content-end mb-4">
        <button class="btn btn-primary px-3" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> Add Course
        </button>
    </div>

    <div class="card shadow-sm p-3">
        <table id="courseTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Serial</th>
                    <th>Country</th>
                    <th>Course Name</th>
                    <th>Course Link</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $q = $conn->query("SELECT * FROM abroad_courses ORDER BY id DESC");
            while ($row = $q->fetch_assoc()) {

                $safeRow = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');

                $statusBadge =
                    $row["status"] == "Open"
                        ? "<span class='badge bg-success'>Open</span>"
                        : "<span class='badge bg-danger'>Closed</span>";

                echo "
                <tr>
                    <td>{$row['serial_no']}</td>
                    <td>{$row['country']}</td>
                    <td>{$row['course_name']}</td>

                    <td>
                        <div class='input-group input-group-sm'>
                            <input class='form-control link-{$row['id']}' value='{$row['course_link']}' readonly>
                            <button class='btn btn-outline-primary' onclick='copyLink({$row['id']})'>
                                <i class=\"bi bi-clipboard\"></i>
                            </button>
                        </div>
                    </td>

                    <td>{$row['start_date']}</td>
                    <td>{$row['end_date']}</td>
                    <td>$statusBadge</td>

                    <td class='text-center'>
                        <button class='btn btn-warning btn-sm me-1' onclick='editCourse($safeRow)'>
                            <i class='bi bi-pencil-square'></i>
                        </button>

                        <a href='?delete={$row['id']}' class='btn btn-danger btn-sm'
                           onclick='return confirm(\"Delete this course?\")'>
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
                <h5 class="modal-title fw-bold">Add Course</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="modal-body p-4">

                    <label class="fw-semibold">Country</label>
                    <select class="selectpicker w-100" name="country" data-live-search="true" required>
                        <?php
                        $countries = ["Afghanistan","Albania","Algeria","Andorra","Angola","Argentina","Armenia","Australia","Austria","Azerbaijan",
                        "Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia","Bosnia","Botswana",
                        "Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cambodia","Cameroon","Canada","Cape Verde","Central African Republic",
                        "Chad","Chile","China","Colombia","Comoros","Congo","Costa Rica","Croatia","Cuba","Cyprus","Czech Republic","Denmark",
                        "Djibouti","Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","Eritrea","Estonia","Eswatini","Ethiopia",
                        "Fiji","Finland","France","Gabon","Gambia","Georgia","Germany","Ghana","Greece","Grenada","Guatemala","Guinea",
                        "Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Israel","Italy",
                        "Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kiribati","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon",
                        "Lesotho","Liberia","Libya","Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives",
                        "Mali","Malta","Mauritania","Mauritius","Mexico","Micronesia","Moldova","Monaco","Mongolia","Montenegro","Morocco",
                        "Mozambique","Myanmar","Namibia","Nauru","Nepal","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Korea",
                        "North Macedonia","Norway","Oman","Pakistan","Palau","Panama","Papua New Guinea","Paraguay","Peru","Philippines",
                        "Poland","Portugal","Qatar","Romania","Russia","Rwanda","Samoa","San Marino","Saudi Arabia","Senegal","Serbia",
                        "Seychelles","Sierra Leone","Singapore","Slovakia","Slovenia","Somalia","South Africa","South Korea","South Sudan",
                        "Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand",
                        "Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey","Turkmenistan","Uganda","Ukraine","United Arab Emirates",
                        "United Kingdom","United States","Uruguay","Uzbekistan","Vanuatu","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"];

                        foreach ($countries as $c) {
                            echo "<option value='$c'>$c</option>";
                        }
                        ?>
                    </select>

                    <div class="form-floating mt-3 mb-3">
                        <input type="text" class="form-control modern-input" name="course_name" placeholder="Course Name" required>
                        <label>Short Course Name</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" class="form-control modern-input" name="course_link" placeholder="Course Link" required>
                        <label>Course Link</label>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Start Date</label>
                            <div class="input-group">
                                <span class="input-group-text calendar-icon"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" name="start_date" class="form-control modern-date" required>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">End Date</label>
                            <div class="input-group">
                                <span class="input-group-text calendar-icon"><i class="bi bi-calendar2-week"></i></span>
                                <input type="date" name="end_date" class="form-control modern-date" required>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary px-4" style="border-radius: 8px;">Save</button>
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
                <h5 class="modal-title fw-bold">Edit Course</h5>
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

                    <div class="form-floating mt-3 mb-3">
                        <input type="text" class="form-control modern-input" id="edit_course" name="course_name" placeholder="Course Name" required>
                        <label>Short Course Name</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" class="form-control modern-input" id="edit_link" name="course_link" placeholder="Course Link" required>
                        <label>Course Link</label>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Start Date</label>
                            <div class="input-group">
                                <span class="input-group-text calendar-icon"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" id="edit_start" name="start_date" class="form-control modern-date" required>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">End Date</label>
                            <div class="input-group">
                                <span class="input-group-text calendar-icon"><i class="bi bi-calendar2-week"></i></span>
                                <input type="date" id="edit_end" name="end_date" class="form-control modern-date" required>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary px-4" style="border-radius: 8px;">Update</button>
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

<!-- Bootstrap Select JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>

<script>
$(document).ready(function () {
    $('#courseTable').DataTable();
    $('.selectpicker').selectpicker();
});

function copyLink(id) {
    let input = document.querySelector('.link-' + id);
    input.select();
    document.execCommand("copy");
    alert("Link copied!");
}

// Load course for edit
function editCourse(row) {
    document.getElementById("edit_id").value = row.id;

    $("#edit_country").val(row.country);
    $("#edit_country").selectpicker("refresh");

    document.getElementById("edit_course").value = row.course_name;
    document.getElementById("edit_link").value = row.course_link;
    document.getElementById("edit_start").value = row.start_date;
    document.getElementById("edit_end").value = row.end_date;

    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

</body>
</html>
