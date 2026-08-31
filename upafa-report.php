<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers/secure_file.php';
/* ===============================
   SEARCH
================================ */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = '';

if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where = "WHERE
        r.first_name LIKE '%$s%' OR
        r.last_name LIKE '%$s%' OR
        r.email LIKE '%$s%' OR
        r.telephone LIKE '%$s%' OR
        r.academic_year LIKE '%$s%'
    ";
}

/* ===============================
   FETCH APPLICANTS
================================ */
$sql = "
SELECT r.*
FROM upafa_registrations r
$where
ORDER BY r.created_at DESC
";

$res = $conn->query($sql);
$applicants = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $applicants[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Applicants Report</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f4f6f9;
}
.card {
    border-radius: 16px;
}
.app-no {
    font-size: 1.2rem;
    font-weight: 700;
    color: #0d6efd;
}
.file-box {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 10px;
    margin-bottom: 10px;
}
.file-box small {
    color: #6c757d;
}
.badge {
    font-size: 0.75rem;
}
</style>
</head>

<body>
<div class="container-fluid p-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold">📋 Applicants Report</h3>
        <span class="text-muted">Total Applicants: <?= count($applicants) ?></span>
    </div>

    <!-- SEARCH -->
    <form method="get" class="mb-4">
        <div class="input-group">
            <input type="text"
                   name="search"
                   class="form-control"
                   placeholder="Search by name, email, phone, academic year..."
                   value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary">Search</button>
            <?php if ($search): ?>
                <a href="applicants_report.php" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$applicants): ?>
        <div class="alert alert-warning">No applicants found.</div>
    <?php endif; ?>

    <!-- APPLICANTS -->
    <?php $i = 1; foreach ($applicants as $r): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">

                <!-- HEADER -->
                <div class="row align-items-center mb-3">
                    <div class="col-md-8">
                        <div class="app-no">Applicant #<?= $i++ ?></div>
                        <h5 class="fw-bold mb-0">
                            <?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?>
                        </h5>
                        <div class="text-muted small">
                            <?= htmlspecialchars($r['email']) ?> |
                            <?= htmlspecialchars($r['telephone']) ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <span class="badge bg-info"><?= htmlspecialchars($r['academic_year']) ?></span>
                        <span class="badge bg-secondary"><?= ucfirst($r['application_status']) ?></span>
                    </div>
                </div>

                <!-- PERSONAL -->
                <div class="row small mb-2">
                    <div class="col-md-3"><strong>Nationality:</strong> <?= $r['nationality'] ?></div>
                    <div class="col-md-3"><strong>Birth:</strong> <?= $r['birth_place'] ?> (<?= $r['birth_date'] ?>)</div>
                    <div class="col-md-3"><strong>Education:</strong> <?= $r['highest_education'] ?></div>
                    <div class="col-md-3"><strong>Department:</strong> <?= $r['department'] ?></div>
                </div>

                <hr>

                <!-- ACADEMIC -->
                <div class="row small mb-2">
                    <div class="col-md-4"><strong>School:</strong> <?= $r['school_name_address'] ?></div>
                    <div class="col-md-4"><strong>Intended Degree:</strong> <?= $r['intended_degree'] ?></div>
                    <div class="col-md-4"><strong>Field of Study:</strong> <?= $r['field_of_study'] ?></div>
                </div>

                <!-- FINANCIAL (NO TUITION) -->
                <div class="row small mb-3">
                    <div class="col-md-4"><strong>Registration Fee:</strong> $<?= $r['registration_fees'] ?></div>
                    <div class="col-md-4"><strong>Scholarship:</strong> <?= $r['scholarship'] ?></div>
                    <div class="col-md-4"><strong>Referred:</strong> <?= $r['referred_by_scholarsync'] ?></div>
                </div>

                <!-- FILES -->
                <h6 class="fw-bold mt-3">📎 Uploaded Documents</h6>

                <?php
                $files = [];
                $rid = (int)$r['id'];
                $fres = $conn->query("
                    SELECT *
                    FROM upafa_registration_files
                    WHERE registration_id = $rid
                    ORDER BY uploaded_at ASC
                ");
                if ($fres) {
                    while ($f = $fres->fetch_assoc()) {
                        $files[] = $f;
                    }
                }
                ?>

                <?php if (!$files): ?>
                    <div class="text-muted small">No files uploaded.</div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($files as $f): ?>
                            <div class="col-md-4">
                                <div class="file-box">
                                    <strong><?= ucfirst(str_replace('_',' ',$f['file_type'])) ?></strong><br>
                                    <small><?= htmlspecialchars($f['original_name']) ?></small><br>
                                    <a href="download_file.php?id=<?= (int) $f['id'] ?>"
                                       class="btn btn-sm btn-outline-primary mt-2"
                                       download>
                                        ⬇ Download
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endforeach; ?>

</div>
</body>
</html>
