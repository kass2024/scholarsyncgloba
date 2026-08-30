<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';

$admin_id = $_SESSION['admin_id'] ?? $_SESSION['id'] ?? null;
if (!$admin_id) {
    die('Access denied.');
}

// Fetch admin role
$stmt = $conn->prepare('SELECT role FROM admins WHERE id = ?');
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

$isSuperAdmin = pcvc_is_superadmin_role($role ?? '');
?>
<!DOCTYPE html>
<html>
<head>
<title>Attendance Report Dashboard - ScholarSync Global</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<style>
:root {
    --pcvc-navy: #427431;
    --pcvc-secondary-blue: #3661B9;
    --pcvc-dark-blue: #2f5a26;
    --pcvc-gold: #E21D1E;
    --pcvc-white: #FFFFFF;
}

body {
    background-color: #f8f9fa;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* PCVC color scheme */
.card {
    border: 1px solid rgba(1, 47, 107, 0.1);
    border-radius: 10px;
}

.card-header {
    background-color: var(--pcvc-navy);
    color: white;
    font-weight: 600;
    border-radius: 10px 10px 0 0 !important;
    padding: 15px 20px;
}

.btn-primary {
    background-color: var(--pcvc-navy);
    border-color: var(--pcvc-navy);
}

.btn-primary:hover {
    background-color: var(--pcvc-dark-blue);
    border-color: var(--pcvc-dark-blue);
}

.btn-danger {
    background-color: var(--pcvc-gold);
    border-color: var(--pcvc-gold);
    color: #333;
}

.btn-danger:hover {
    background-color: #e0964a;
    border-color: #e0964a;
    color: #333;
}

.table thead {
    background-color: var(--pcvc-secondary-blue);
    color: white;
}

.table thead th {
    border-bottom: none;
    font-weight: 600;
    padding: 15px 12px;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(37, 77, 129, 0.05);
}

.table-bordered {
    border: 1px solid rgba(1, 47, 107, 0.1);
}

.table-hover tbody tr:hover {
    background-color: rgba(242, 166, 90, 0.1);
}

.form-control:focus, .form-select:focus {
    border-color: var(--pcvc-gold);
    box-shadow: 0 0 0 0.25rem rgba(242, 166, 90, 0.25);
}

.form-label.fw-bold {
    color: var(--pcvc-navy);
}

/* Custom KPI Cards */
.kpi-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border-left: 5px solid var(--pcvc-gold);
    transition: transform 0.3s;
}

.kpi-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
}

.kpi-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--pcvc-navy);
    margin-bottom: 5px;
}

.kpi-label {
    font-size: 0.9rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.kpi-icon {
    font-size: 2.5rem;
    color: var(--pcvc-gold);
    margin-bottom: 10px;
}

/* Staff Search */
#staffList {
    position: absolute;
    background: white;
    width: 100%;
    max-height: 200px;
    border: 1px solid var(--pcvc-secondary-blue);
    border-radius: 6px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 4px 12px rgba(0, 39, 101, 0.1);
}

#staffList div {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
    transition: background-color 0.2s;
}

#staffList div:hover {
    background-color: rgba(242, 166, 90, 0.15);
}

/* Chart Styling */
#chartAttendance {
    max-height: 400px;
}

/* Responsive */
@media (max-width: 768px) {
    .kpi-card {
        margin-bottom: 15px;
    }
    
    .kpi-value {
        font-size: 1.5rem;
    }
}

/* Print Styles */
@media print {
    #filterForm, #pdfBtn, .kpi-card, #chartAttendance {
        display: none !important;
    }
    
    .card {
        border: none;
        box-shadow: none;
    }
    
    .table thead {
        background-color: #f1f1f1 !important;
        color: #000 !important;
    }
}
</style>

</head>
<body>

<div class="container-fluid mt-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: var(--pcvc-navy);">📊 Attendance Report Dashboard</h2>
            <p class="text-muted">Monitor staff attendance and work hours</p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark p-2" style="border: 1px solid #ddd;">
                <strong>Admin:</strong> <?php echo htmlspecialchars($role); ?>
            </span>
        </div>
    </div>

    <!-- FILTERS PANEL -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filters & Controls</h5>
        </div>
        <div class="card-body p-4">
            <form id="filterForm">
                <div class="row g-3">

                    <!-- Report Type -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Report Type</label>
                        <select class="form-select" name="filter" id="filter">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>

                    <!-- Daily -->
                    <div class="col-md-3 filter-date">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" class="form-control" name="date" id="dailyDate">
                    </div>

                    <!-- Weekly -->
                    <div class="col-md-3 filter-week" style="display:none;">
                        <label class="form-label fw-bold">Week</label>
                        <input type="week" class="form-control" name="week" id="weekPicker">
                    </div>

                    <!-- Monthly -->
                    <div class="col-md-3 filter-month" style="display:none;">
                        <label class="form-label fw-bold">Month</label>
                        <input type="month" class="form-control" name="month" id="monthPicker">
                    </div>

                    <!-- Staff Search -->
                    <?php if ($isSuperAdmin): ?>
                    <div class="col-md-3 position-relative">
                        <label class="form-label fw-bold">Search Staff</label>
                        <input type="text" class="form-control" name="staff" id="staff" autocomplete="off" placeholder="Type name...">
                        <div id="staffList"></div>
                    </div>
                    <?php endif; ?>

                    <!-- Apply Filter -->
                    <div class="col-md-3 mt-4">
                        <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                    </div>

                    <!-- PDF -->
                    <div class="col-md-3 mt-4">
                        <button class="btn btn-danger w-100" id="pdfBtn">📄 Export PDF</button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- KPI Cards Row -->
    <div class="row mb-4" id="kpiRow">
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon">👥</div>
                <div class="kpi-value" id="totalStaff">0</div>
                <div class="kpi-label">Total Staff</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon">⏱️</div>
                <div class="kpi-value" id="avgMinutes">0</div>
                <div class="kpi-label">Avg. Minutes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon">💰</div>
                <div class="kpi-value" id="totalSalary">0 RWF</div>
                <div class="kpi-label">Total Salary</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon">📅</div>
                <div class="kpi-value" id="reportDate">Today</div>
                <div class="kpi-label">Report Period</div>
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Attendance Details</h5>
        </div>
        <div class="card-body p-3">
            <table id="attendanceTable" class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Date</th>
                        <th>Check-In</th>
                        <th>Check-Out</th>
                        <th>Work Minutes</th>
                        <th>Salary (RWF)</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Attendance Trend Chart -->
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Attendance Trend</h5>
        </div>
        <div class="card-body">
            <canvas id="chartAttendance"></canvas>
        </div>
    </div>

</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="report.js"></script>

<script>
/* ================================
   AUTO WEEK & AUTO MONTH SETUP
=================================== */

// Auto-set today's date
document.getElementById("dailyDate").value = new Date().toISOString().substr(0, 10);

// Auto-set current month
let now = new Date();
document.getElementById("monthPicker").value = now.toISOString().substr(0, 7);

// Auto-set current ISO week
function getISOWeek(date) {
    let temp = new Date(date.getTime());
    temp.setHours(0, 0, 0, 0);
    temp.setDate(temp.getDate() + 4 - (temp.getDay() || 7));
    let yearStart = new Date(temp.getFullYear(), 0, 1);
    return Math.ceil((((temp - yearStart) / 86400000) + 1) / 7);
}

let week = getISOWeek(now);
let year = now.getFullYear();
document.getElementById("weekPicker").value = `${year}-W${week.toString().padStart(2, '0')}`;

/* ================================
   TOGGLE FILTER FIELDS
=================================== */
$("#filter").on("change", function () {
    let t = $(this).val();
    $(".filter-date, .filter-week, .filter-month").hide();

    if (t === "daily") {
        $(".filter-date").show();
        $("#reportDate").text($("#dailyDate").val());
    }
    if (t === "weekly") {
        $(".filter-week").show();
        $("#reportDate").text($("#weekPicker").val());
    }
    if (t === "monthly") {
        $(".filter-month").show();
        $("#reportDate").text($("#monthPicker").val());
    }
});

/* ================================
   STAFF AUTOCOMPLETE
=================================== */
$("#staff").keyup(function () {
    let q = $(this).val().trim();
    if (q.length === 0) {
        $("#staffList").hide();
        return;
    }

    $.post("search-staff.php", {query: q}, function (data) {
        $("#staffList").html(data).show();
    });
});

$(document).on("click", "#staffList div", function () {
    $("#staff").val($(this).text());
    $("#staffList").hide();
});

$(document).click(function (e) {
    if (!$(e.target).closest("#staff").length) {
        $("#staffList").hide();
    }
});

/* ================================
   UPDATE KPI CARDS
=================================== */
function updateKPICards(data) {
    if (data && data.length > 0) {
        // Calculate totals
        let totalMinutes = 0;
        let totalSalary = 0;
        
        data.forEach(row => {
            totalMinutes += parseInt(row.workMinutes) || 0;
            totalSalary += parseInt(row.salary.replace(/,/g, '')) || 0;
        });
        
        let avgMinutes = Math.round(totalMinutes / data.length);
        
        // Update KPI cards
        $("#totalStaff").text(data.length);
        $("#avgMinutes").text(avgMinutes);
        $("#totalSalary").text(totalSalary.toLocaleString() + " RWF");
    } else {
        $("#totalStaff").text("0");
        $("#avgMinutes").text("0");
        $("#totalSalary").text("0 RWF");
    }
}

/* ============================================ 
   CUSTOM PDF PRINT (Clean report only)
============================================ */
$("#pdfBtn").click(function (e) {
    e.preventDefault();

    // Fetch selected filters
    let type = $("#filter").val();
    let staff = $("#staff").val() || "All Staff";
    let label = "";

    if (type === "daily") label = "Date: " + $("#dailyDate").val();
    if (type === "weekly") label = "Week: " + $("#weekPicker").val();
    if (type === "monthly") label = "Month: " + $("#monthPicker").val();

    /* =============================
       CALCULATE TOTAL SALARY
    ============================= */
    let totalSalary = 0;
    let totalMinutes = 0;
    let staffCount = 0;

    $("#attendanceTable tbody tr").each(function () {
        staffCount++;
        let salaryText = $(this).find("td:last").text().replace(/,/g, "");
        let salary = parseFloat(salaryText) || 0;
        totalSalary += salary;
        
        let minutesText = $(this).find("td:nth-child(5)").text();
        let minutes = parseInt(minutesText) || 0;
        totalMinutes += minutes;
    });

    let formattedTotalSalary = totalSalary.toLocaleString() + " RWF";
    let avgMinutes = staffCount > 0 ? Math.round(totalMinutes / staffCount) : 0;

    /* =============================
       BUILD TABLE HTML
    ============================= */
    let tableHtml = `
        <table border="1" cellspacing="0" cellpadding="8" 
               style="width:100%; border-collapse:collapse; font-size:14px; margin-top:15px;">
            <thead>
                <tr style="background:#427431; color:white;">
                    <th style="padding:10px; text-align:left;">Name</th>
                    <th style="padding:10px; text-align:left;">Date</th>
                    <th style="padding:10px; text-align:left;">Check-In</th>
                    <th style="padding:10px; text-align:left;">Check-Out</th>
                    <th style="padding:10px; text-align:left;">Work Minutes</th>
                    <th style="padding:10px; text-align:left;">Salary (RWF)</th>
                </tr>
            </thead>
            <tbody>
    `;

    $("#attendanceTable tbody tr").each(function () {
        tableHtml += "<tr>";
        $(this).find("td").each(function () {
            tableHtml += `<td style="padding:8px;">${$(this).text()}</td>`;
        });
        tableHtml += "</tr>";
    });

    tableHtml += `
            </tbody>
        </table>
    `;

    /* =============================
       OPEN PRINT WINDOW
    ============================= */
    let printWindow = window.open("", "", "width=900,height=700");

    printWindow.document.write(`
        <html>
        <head>
            <title>ScholarSync Global - Attendance Report</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    padding: 25px; 
                    color: #333;
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 20px;
                    border-bottom: 3px solid #E21D1E;
                    padding-bottom: 15px;
                }
                .logo {
                    font-size: 24px;
                    font-weight: bold;
                    color: #427431;
                    margin-bottom: 5px;
                }
                .subtitle {
                    color: #666;
                    font-size: 16px;
                }
                .report-info { 
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 20px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border-left: 4px solid #3661B9;
                }
                .info-box {
                    display: flex;
                    flex-direction: column;
                }
                .info-label {
                    font-size: 12px;
                    color: #666;
                    text-transform: uppercase;
                    margin-bottom: 3px;
                }
                .info-value {
                    font-size: 16px;
                    font-weight: bold;
                    color: #427431;
                }
                .summary-box {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 25px;
                }
                .summary-card {
                    background: #e8f4ff;
                    border: 1px solid #3661B9;
                    border-radius: 8px;
                    padding: 15px;
                    text-align: center;
                    width: 30%;
                }
                .summary-value {
                    font-size: 20px;
                    font-weight: bold;
                    color: #427431;
                    margin: 10px 0;
                }
                .summary-label {
                    font-size: 14px;
                    color: #666;
                }
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 12px;
                    color: #888;
                    border-top: 1px solid #eee;
                    padding-top: 15px;
                }
            </style>
        </head>
        <body>

            <div class="header">
                <div class="logo">SCHOLARSYNC GLOBAL</div>
                <div class="subtitle">Attendance Report</div>
            </div>

            <div class="report-info">
                <div class="info-box">
                    <span class="info-label">Report Type</span>
                    <span class="info-value">${type.charAt(0).toUpperCase() + type.slice(1)}</span>
                </div>
                <div class="info-box">
                    <span class="info-label">Period</span>
                    <span class="info-value">${label}</span>
                </div>
                <div class="info-box">
                    <span class="info-label">Staff</span>
                    <span class="info-value">${staff}</span>
                </div>
            </div>

            ${tableHtml}

            <div class="summary-box">
                <div class="summary-card">
                    <div class="summary-value">${staffCount}</div>
                    <div class="summary-label">Total Staff</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">${avgMinutes} min</div>
                    <div class="summary-label">Average Work Time</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">${formattedTotalSalary}</div>
                    <div class="summary-label">Total Salary</div>
                </div>
            </div>

            <div class="footer">
                Generated on ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()} | ScholarSync Global © ${new Date().getFullYear()}
            </div>

        </body>
        </html>
    `);

    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
});

// Initialize report date
$("#reportDate").text($("#dailyDate").val());

</script>

</body>
</html>