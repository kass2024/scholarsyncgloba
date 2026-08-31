<?php
session_start();
// Main database (e.g. student_applications)
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/secure_file.php';
require_once __DIR__ . '/helpers/university_admins_schema.php';
require_once __DIR__ . '/helpers/korea_invitation_contract_schema.php';
require_once __DIR__ . '/helpers/korea_event_schema.php';
// Secondary database (e.g. applications from Cyprus system)
require_once 'database.php';  // This connects to scholarsyncglobal
pcvc_ensure_university_admins_schema($conn);
kic_contract_ensure_schema($conn);
kep_ensure_schema($conn);

$admin_id = $_SESSION['id'] ?? null;
if (!$admin_id || !isset($_SESSION['role'])) {
  header("Location: admin-login.php");
  exit;
}

$admin_id_safe = mysqli_real_escape_string($conn, $admin_id);
$result = mysqli_query($conn, "SELECT * FROM admins WHERE id = '$admin_id_safe'");
if (!$result || mysqli_num_rows($result) === 0) {
  die("Admin not found.");
}
$admin = mysqli_fetch_assoc($result);
$role = pcvc_normalize_role_string($admin['role'] ?? 'standard');
$displayName = trim(
  ($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')
);

if ($displayName === '') {
  $displayName = $admin['full_name'] ?? 'Administrator';
}

require_once __DIR__ . '/includes/staff_dashboard_stats.php';
require_once __DIR__ . '/helpers/application_filters.php';
require_once __DIR__ . '/helpers/caq_status.php';
pcvc_ensure_caq_column($conn);
$isSuperExecutive = pcvc_current_user_is_superadmin($conn);
$isCatholicPortal = (strtolower(trim((string) $role)) === 'catholic university of america');
$showStaffPersonalDashboard = !$isSuperExecutive && !$isCatholicPortal;
$staffDashStats = [];
if ($showStaffPersonalDashboard) {
    $staffDashStats = pcvc_staff_dashboard_stats($conn, (int) $admin_id);
}

// ===============================
// FLAG KEYS AND TITLES
// ===============================
$flagMap = [
  'incomplete_app' => 'Incomplete App',
  'submitted' => 'Submitted',
  'admit' => 'Admit',
  'caq' => 'CAQ',
  'i20_sent' => 'I-20 Sent',
  'sevis_paid' => 'Sevis Paid',
  'visa_scheduled' => 'Attended Visa Interview',
  'visa_approved' => 'Visa Approved',
  'enrolled' => 'Enrolled',
  'addn_doc' => 'Addn Doc',
  'deny' => 'Visa Denied',
  'app_start' => 'App Start'
];

// ===============================
// TOTAL APPLICATIONS COUNT
// (Only rows with valid email)
// ===============================
$totalApplications = 0;

$totalQuery = "
    SELECT COUNT(*) AS total
    FROM student_applications
    WHERE email IS NOT NULL
    AND email != ''
";

$totalRes = mysqli_query($conn, $totalQuery);

if ($totalRes) {
    $row = mysqli_fetch_assoc($totalRes);
    $totalApplications = (int)$row['total'];
}

// ===============================
// COUNT ALL STATUS FLAGS
// ===============================
$flagCounts = [];

$flagQuery = "
    SELECT
        COUNT(CASE WHEN incomplete_app = 1 THEN 1 END) AS incomplete_app,
        COUNT(CASE WHEN submitted = 1 THEN 1 END) AS submitted,
        COUNT(CASE WHEN admit = 1 THEN 1 END) AS admit,
        COUNT(CASE WHEN caq = 1 THEN 1 END) AS caq,
        COUNT(CASE WHEN i20_sent = 1 THEN 1 END) AS i20_sent,
        COUNT(CASE WHEN sevis_paid = 1 THEN 1 END) AS sevis_paid,
        COUNT(CASE WHEN visa_scheduled = 1 THEN 1 END) AS visa_scheduled,
        COUNT(CASE WHEN visa_approved = 1 THEN 1 END) AS visa_approved,
        COUNT(CASE WHEN enrolled = 1 THEN 1 END) AS enrolled,
        COUNT(CASE WHEN addn_doc = 1 THEN 1 END) AS addn_doc,
        COUNT(CASE WHEN deny = 1 THEN 1 END) AS deny,
        COUNT(CASE WHEN app_start = 1 THEN 1 END) AS app_start
    FROM student_applications
";

$flagRes = mysqli_query($conn, $flagQuery);

if ($flagRes) {
    $flagCounts = mysqli_fetch_assoc($flagRes);
}
$totalRes = mysqli_query($conn, $totalQuery);
$totalApplications = 0;

if ($totalRes) {
    $row = mysqli_fetch_assoc($totalRes);
    $totalApplications = (int)$row['total'];
}

$flagQuery = "
  SELECT
    SUM(incomplete_app) AS incomplete_app,
    SUM(submitted) AS submitted,
    SUM(admit) AS admit,
    SUM(caq) AS caq,
    SUM(i20_sent) AS i20_sent,
    SUM(sevis_paid) AS sevis_paid,
    SUM(visa_scheduled) AS visa_scheduled,
    SUM(visa_approved) AS visa_approved,
    SUM(enrolled) AS enrolled,
    SUM(addn_doc) AS addn_doc,
    SUM(deny) AS deny,
    SUM(app_start) AS app_start
  FROM (
    SELECT incomplete_app, submitted, admit, IFNULL(caq,0) AS caq, i20_sent, sevis_paid,
           visa_scheduled, visa_approved, enrolled, addn_doc, deny, app_start
    FROM student_applications

    UNION ALL

    SELECT incomplete_app, submitted, admit, 0 AS caq, i20_sent, sevis_paid,
           visa_scheduled, visa_approved, enrolled, addn_doc, deny, app_start
    FROM malta_applications

    UNION ALL

    SELECT incomplete_app, submitted, admit, 0 AS caq, i20_sent, sevis_paid,
           visa_scheduled, visa_approved, enrolled, addn_doc, deny, app_start
    FROM turkey_applications
  ) AS all_flags
";

$countRes = mysqli_query($conn, $flagQuery);
if ($countRes) {
    $flagCounts = mysqli_fetch_assoc($countRes);
} else {
    error_log("Flag count query failed: " . mysqli_error($conn));
    $flagCounts = array_fill_keys([
        'incomplete_app', 'submitted', 'admit', 'caq', 'i20_sent', 'sevis_paid',
        'visa_scheduled', 'visa_approved', 'enrolled', 'addn_doc', 'deny', 'app_start'
    ], 0);
}

// Catholic-only flag counts (student_applications only)
$catholicFlagCounts = [];

$catholicQuery = "
  SELECT
    SUM(incomplete_app) AS incomplete_app,
    SUM(submitted) AS submitted,
    SUM(admit) AS admit,
    SUM(i20_sent) AS i20_sent,
    SUM(sevis_paid) AS sevis_paid,
    SUM(visa_scheduled) AS visa_scheduled,
    SUM(visa_approved) AS visa_approved,
    SUM(enrolled) AS enrolled,
    SUM(addn_doc) AS addn_doc,
    SUM(deny) AS deny,
    SUM(app_start) AS app_start
  FROM student_applications
  WHERE university_id = 1 AND region_id = 1
";

$catholicRes = mysqli_query($conn, $catholicQuery);
if ($catholicRes) {
    $catholicFlagCounts = mysqli_fetch_assoc($catholicRes);
} else {
    error_log("Catholic flag query failed: " . mysqli_error($conn));
    $catholicFlagCounts = array_fill_keys(array_keys($flagMap), 0);
}

// Dashboard cards - COMPLETE LIST FROM ORIGINAL
$cards = [
  'all_admissions' => [
    'title' => 'All university admissions',
    'icon' => 'bi-mortarboard',
    'links' => [
      'application-list.php' => 'Student application Report',
      'students-manage.php' => 'Applicants Management',
      'receipt_viewer.php' => 'Check payment Receipt',
      'applyboard-dashboard.php' => 'ApplyBoard Applications',
      'task-assignment-monitoring.php' => 'Task assignment monitoring',
    ]
  ],
  'loan_applications' => [
    'title' => 'Study Loan Applications',
    'icon' => 'bi-bank',
    'links' => [
      'loan-applicants-report.php' => 'Loan Application list',
      'loan_search.php' => 'User-iD',
    ]
  ],
  'I-20_applications' => [
    'title' => 'I-20 Applications',
    'icon' => 'bi-file-earmark-text',
    'links' => [
      'form-20-report.php' => 'I-20 Applicant List',
    ]
  ],
  'staff_reporting' => [
    'title' => 'Staff Management',
    'icon' => 'bi-people',
    'links' => [
      'staff-management.php'=> 'Manage staff ',
      // 'tasks.php'=> 'Task Allocation',
      'admin/contracts-admin.php'=> 'Staff employment contracts',
      'salary-report.php'=> 'View Requested Salaries ',
      'leave-approvals.php'=> 'Manage Permissions ',
      'overtime-approvals.php'=> 'Overtime Management ',
      'jobs_report.php'=> 'Check job report ',
      'admin-payroll.php'=> 'Payroll ',
      'cards/generate_staff_card.php'=> 'Generate staff cards',
      'staff-warning-letters.php' => 'Warning letters',
    ]
  ],
  'commission_request' => [
    'title' => 'Commission Request',
    'icon' => 'bi-cash-coin',
    'links' => [
      'Commission-Request.php' => 'Request commission',
      'commission-requests-report.php' => 'All Requests'
    ]
  ],
  'credit_transfer' => [
    'title' => 'Credit Transfer Applications',
    'icon' => 'bi-arrow-left-right',
    'links' => [
      'Credit-Transfer-report.php' => 'Transfer Requests list',
      'transfer-status.php' => 'Review Status',
      'credit-search.php' => 'credit userID',
      'research-project-elearning.php' => 'RESEARCH PROJECT FOR ELEARNING',
    ]
  ],
  'visit_study_visa' => [
    'title' => 'Visit And Study Visa',
    'icon' => 'bi-globe2',
    'links' => [
      'visa-report.php' => 'Applicant List',
      'visa-report.php?process_status=rejected' => 'Rejected',
    ]
  ],
  'staff_attendance' => [
    'title' => 'Staff Attendance',
    'icon' => 'bi-calendar-check',
    'links' => [
      'attendance-ui.php' => 'Take attendance',
      'job_todo_list.php' => 'Job Do List',
      'salary.php' => 'Salary Request',
      'admin/contract.php'=> 'Sign your contract',
      'leave-request.php'=> 'Permission Request ',
      'staff_overtime_request.php'=> 'Overtime request ',
      'my-leaves.php' =>'Check permission status ',
      'attendance-report.php' => 'Attendance Report',
      'jobs_report.php'=> 'Check job report ',
      'cards/generate_staff_card.php'=> 'Generate your service card',
    ]
  ],
  'university_portal' => [
    'title' => 'Apply for Student',
    'icon' => 'bi-person-plus',
    'links' => [
      'student-application.php' => 'Apply Now',
      'agent-student-manage.php' => 'Manage Students',
      'userid-search.php' => 'User-id',
    ]
  ],
  // 'marketing' => [
  //   'title' => 'Marketing Materials',
  //   'icon' => 'bi-megaphone',
  //   'links' => [
  //     'upload-materials.php' => 'Upload Marketing materials',
  //     // 'get-materials.php' => 'Get Marketing materials',
  //   ]
  // ],
  'jobsabrod' => [
    'title' => 'Jobs Application',
    'icon' => 'bi-briefcase',
    'links' => [
      'job-applicant.php' => 'Check job Applicants',
      'job-applicant.php?process_status=rejected' => 'Rejected',
    ]
  ],
  'canada_medical' => [
    'title' => 'Canada Medical Exams',
    'icon' => 'bi-hospital',
    'links' => [
      'canada-medical-exams-applications.php' => 'View Applications',
    ]
  ],
  'francophonie_mobility' => [
    'title' => 'Francophonie Mobility',
    'icon' => 'bi-map',
    'links' => [
      'francophonie-mobility-applications.php' => 'View Applications',
      'admin-generate-fm-contract.php' => 'Issue E-Sign Contract',
      'admin-fm-contracts.php' => 'Signed Contracts',
    ]
  ],
  'employment_opportunities' => [
    'title' => 'Russian Employment Opportunities',
    'icon' => 'bi-briefcase',
    'links' => [
      'employment-opportunities-applications.php' => 'View Applications',
      'admin-generate-eo-contract.php' => 'Issue E-Sign Contract',
      'admin-eo-contracts.php' => 'Signed Contracts',
    ]
  ],
  'meeting_invitation' => [
    'title' => 'Meeting Invitation',
    'icon' => 'bi-camera-video',
    'links' => [
      'francophonie-meeting-invitation.php' => 'Meeting Invitation',
      'francophonie-meeting-recordings.php' => 'Meeting Recordings',
    ]
  ],
  'refund_requests' => [
    'title' => 'Refund Requests',
    'icon' => 'bi-cash-stack',
    'links' => [
      'refund-requests-report.php' => 'All Refund Requests',
    ]
  ],
  'platform' => [
    'title' => 'Platforms management',
    'icon' => 'bi-diagram-3',
    'links' => [
      'platforms.php' => 'Platforms management',
    ]
  ],
  'website_management' => [
    'title' => 'Website management',
    'icon' => 'bi-globe2',
    'links' => [
      'websites.php' => 'List all websites',
      'websites.php?open=add' => 'Add new website',
    ]
  ],
  'contracts' => [
    'title' => 'Student contract',
    'icon' => 'bi-file-earmark-lock',
    'links' => [
      'admin-generate-student-contract.php' => 'Issue contract link ',
      'admin-contracts.php' => 'View students Contracts',
      'admin-contracts-special.php' => 'Special students Contracts',
      'admin-generate-student-contract-special.php' => 'Issue special contract link',
      'admin/contract_esign.php' => 'Signed PhD Contracts',
    ]
  ],
  'partner_contracts' => [
    'title' => 'Partner Contract',
    'icon' => 'bi-handshake',
    'links' => [
      'admin-generate-partner-contract.php' => 'Issue partner contract link (English)',
      'admin-generate-partner-contract-french.php' => 'Issue partner contract link (French)',
      'admin-partner-contracts.php' => 'View partner contracts',
    ]
  ],
  'korea_invitation_contracts' => [
    'title' => 'Korea Invitation Contract',
    'icon' => 'bi-file-earmark-text',
    'links' => [
      'admin-generate-korea-invitation-contract.php' => 'Issue Korea invitation contract link',
      'admin-korea-invitation-contracts.php' => 'View Korea invitation contracts',
    ]
  ],
  'korea_event_participation' => [
    'title' => 'South Korea Event Participation',
    'icon' => 'bi-flag',
    'links' => [
      'korea-event-applications.php' => 'View Applications',
      'korea-event-applications.php?register=1' => 'Register applicant',
    ]
  ],
  'agent_contracts' => [
    'title' => 'Agent Contract',
    'icon' => 'bi-person-badge',
    'links' => [
      'admin-generate-agent-contract.php' => 'Issue agent contract link',
      'admin-agent-contracts.php' => 'View agent contracts',
    ]
  ],
];

$allowedCardsByRole = [
  'superadmin' => array_merge(array_keys($cards), ['application_flag_summary','agent_report','university_portal','admin_chat','start_fish','schools','marketing','abroad']),
  'standard' => ['university_admissions', 'loan_applications', 'I-20_applications', 'all_admissions','agent_report','university_portal','commission_request','staff_attendance','schools','marketing'],
  'Catholic university of America' => ['university_admissions', 'application_flag_summary','schools','marketing','abroad'],
  'staff' => ['staff_attendance','agent_report','university_portal','commission_request','all_admissions','loan_applications','schools','marketing','contracts','meeting_invitation'],
  'agent' => ['staff_attendance','agent_report','university_portal','commission_request','all_admissions','schools','marketing']
];

// Define sidebar menu access based on roles
$sidebarAccess = [
  'superadmin' => [
    'all_admissions', 'loan_applications', 'I-20_applications', 'staff_reporting',
    'commission_request', 'credit_transfer', 'visit_study_visa', 'staff_attendance',
    'university_portal', 'marketing', 'smart_brochure', 'jobsabrod', 'canada_medical', 'francophonie_mobility', 'employment_opportunities', 'meeting_invitation', 'refund_requests', 'platform', 'website_management', 'contracts', 'partner_contracts', 'korea_invitation_contracts', 'korea_event_participation', 'agent_contracts',
  ],
  'agent' => [
    'staff_attendance', 'agent_report', 'university_portal', 'commission_request',
    'all_admissions', 'schools', 'marketing', 'smart_brochure', 'visit_study_visa', 'platform'
  ],
  'staff' => [
    'staff_attendance', 'agent_report', 'university_portal', 'commission_request',
    'all_admissions', 'loan_applications', 'schools', 'marketing', 'smart_brochure', 'contracts','jobsabrod','credit_transfer', 'visit_study_visa', 'francophonie_mobility', 'employment_opportunities', 'meeting_invitation', 'korea_event_participation', 'platform'
  ],
  'standard' => [
    'university_admissions', 'loan_applications', 'I-20_applications', 'all_admissions',
    'agent_report', 'university_portal', 'commission_request', 'staff_attendance',
    'schools', 'marketing', 'smart_brochure', 'visit_study_visa', 'platform'
  ],
  'Catholic university of America' => [
    'university_admissions', 'application_flag_summary', 'schools', 'marketing', 'smart_brochure', 'abroad', 'visit_study_visa', 'platform'
  ]
];

// Get current role's allowed sidebar items (normalized — DB role may contain stray newlines)
$dashboardRoleKey = pcvc_resolve_dashboard_role_key($role, $sidebarAccess, 'standard');
$allowedSidebarItems = $sidebarAccess[$dashboardRoleKey] ?? $sidebarAccess['standard'];
if (pcvc_current_user_is_staff_or_superadmin($conn) && !in_array('meeting_invitation', $allowedSidebarItems, true)) {
    $allowedSidebarItems[] = 'meeting_invitation';
}
$allowedDashboardCards = $allowedCardsByRole[$dashboardRoleKey] ?? ($allowedCardsByRole['standard'] ?? []);

// Get agent data for chart
$agentsCombined = [];
$chart_labels = [];
$chart_data = [];

if (strtolower($role) !== 'catholic university of america') {
    $agentsQuery = "SELECT email, CONCAT(first_name, ' ', last_name) AS full_name FROM admins";
    $resAgents = mysqli_query($conn, $agentsQuery);
    while ($agent = mysqli_fetch_assoc($resAgents)) {
        $email = strtolower(trim($agent['email'] ?? ''));
        $agentsCombined[$email] = [
            'email' => $email,
            'name' => $agent['full_name'] ?: $email,
            'total' => 0,
            'submitted' => 0,
            'admit' => 0,
            'visa_approved' => 0,
            'enrolled' => 0,
        ];
    }

    // Pipeline columns are mutually exclusive (update-flag sets one flag = 1, others 0).
    // "Submitted" in the chart means at or past submission in the workflow, not only submitted=1.
    $mainAgentsQuery = "
      SELECT 
        LOWER(TRIM(sa.agent_email)) AS agent_email_norm,
        COUNT(*) AS total_students,
        SUM(
          IFNULL(sa.submitted, 0) = 1 OR IFNULL(sa.app_paid, 0) = 1 OR IFNULL(sa.admit, 0) = 1
          OR IFNULL(sa.i20_sent, 0) = 1 OR IFNULL(sa.sevis_paid, 0) = 1 OR IFNULL(sa.visa_scheduled, 0) = 1
          OR IFNULL(sa.visa_approved, 0) = 1 OR IFNULL(sa.enrolled, 0) = 1 OR IFNULL(sa.addn_doc, 0) = 1
          OR IFNULL(sa.deny, 0) = 1
        ) AS submitted,
        SUM(
          IFNULL(sa.admit, 0) = 1 OR IFNULL(sa.i20_sent, 0) = 1 OR IFNULL(sa.sevis_paid, 0) = 1
          OR IFNULL(sa.visa_scheduled, 0) = 1 OR IFNULL(sa.visa_approved, 0) = 1 OR IFNULL(sa.enrolled, 0) = 1
          OR IFNULL(sa.addn_doc, 0) = 1
        ) AS admit,
        SUM(IFNULL(sa.visa_approved, 0) = 1 OR IFNULL(sa.enrolled, 0) = 1) AS visa_approved,
        SUM(IFNULL(sa.enrolled, 0) = 1) AS enrolled
      FROM student_applications sa
      WHERE sa.agent_email IS NOT NULL AND TRIM(sa.agent_email) != ''
      GROUP BY LOWER(TRIM(sa.agent_email))
    ";
    $res1 = mysqli_query($conn, $mainAgentsQuery);
    while ($r = mysqli_fetch_assoc($res1)) {
        $email = strtolower(trim((string) ($r['agent_email_norm'] ?? '')));
        if (!isset($agentsCombined[$email])) continue;
        $agentsCombined[$email]['total'] += (int)$r['total_students'];
        $agentsCombined[$email]['submitted'] += (int)$r['submitted'];
        $agentsCombined[$email]['admit'] += (int)$r['admit'];
        $agentsCombined[$email]['visa_approved'] += (int)$r['visa_approved'];
        $agentsCombined[$email]['enrolled'] += (int)$r['enrolled'];
    }

    $cyprusQuery = "
      SELECT 
        agent_email,
        agent_first_name,
        agent_last_name,
        COUNT(*) AS total_students,
        SUM(status = 'verified') AS submitted,
        SUM(is_admitted = 1) AS admit
      FROM applications
      WHERE agent_email IS NOT NULL AND agent_email != ''
      GROUP BY agent_email
    ";
    $applicationsTable = mysqli_query($conn2, "SHOW TABLES LIKE 'applications'");
    if ($applicationsTable && mysqli_num_rows($applicationsTable) > 0) {
        $res2 = mysqli_query($conn2, $cyprusQuery);
        if ($res2) {
            while ($r = mysqli_fetch_assoc($res2)) {
                $email = strtolower(trim($r['agent_email']));
                $name = trim(($r['agent_first_name'] ?? '') . ' ' . ($r['agent_last_name'] ?? ''));
                if (!isset($agentsCombined[$email])) {
                    $agentsCombined[$email] = [
                        'email' => $email,
                        'name' => $name ?: $email,
                        'total' => 0,
                        'submitted' => 0,
                        'admit' => 0,
                        'visa_approved' => 0,
                        'enrolled' => 0,
                    ];
                }
                $agentsCombined[$email]['total'] += (int)$r['total_students'];
                $agentsCombined[$email]['submitted'] += (int)$r['submitted'];
                $agentsCombined[$email]['admit'] += (int)$r['admit'];
            }
        }
    }

    foreach ($agentsCombined as $agent) {
        if ($role !== 'superadmin' && strtolower(trim($agent['email'] ?? '')) !== strtolower(trim($admin['email'] ?? ''))) continue;
        $chart_labels[] = $agent['name'];
        $chart_data[] = (int)$agent['total'];
    }
}

// Personal agent pipeline (staff / agent / standard home): only this user's allocated students
$myAgentStats = null;
$staffAgentChartLabels = ['Allocated', 'Submitted', 'Admitted', 'Visa approved', 'Enrolled'];
$staffAgentChartData = [0, 0, 0, 0, 0];
if (!empty($showStaffPersonalDashboard) && strtolower($role) !== 'catholic university of america') {
    $myEmailLower = strtolower(trim((string) ($admin['email'] ?? '')));
    if ($myEmailLower !== '') {
        if (isset($agentsCombined[$myEmailLower])) {
            $myAgentStats = $agentsCombined[$myEmailLower];
        } else {
            $myAgentStats = [
                'email' => $myEmailLower,
                'name' => $displayName,
                'total' => 0,
                'submitted' => 0,
                'admit' => 0,
                'visa_approved' => 0,
                'enrolled' => 0,
            ];
        }
        $staffAgentChartData = [
            (int) $myAgentStats['total'],
            (int) $myAgentStats['submitted'],
            (int) $myAgentStats['admit'],
            (int) $myAgentStats['visa_approved'],
            (int) $myAgentStats['enrolled'],
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | ScholarSync Global</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- ScholarSync Global Color Scheme -->
  <style>
    :root {
      --navy: #427431;
      --navy-secondary: #3661B9;
      --navy-dark: #2f5a26;
      --gold: #E21D1E;
      --white: #FFFFFF;
      --page-bg: #eef2f7;
      --surface: #f8fafc;
      --surface-2: #f1f5f9;
      --border: #e2e8f0;
      --text: #1e3a2f;
      --text-brand: #427431;
      --muted: #64748b;
      --focus: rgba(66, 116, 49, .22);
      --success: #0d9f6e;
      --danger: #dc3545;
      --warning: #f59e0b;
      --info: #0ea5e9;
      --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.06);
      --shadow-md: 0 8px 24px -4px rgba(15, 23, 42, 0.1), 0 0 0 1px rgba(15, 23, 42, 0.04);
      --shadow-lg: 0 20px 50px -12px rgba(15, 23, 42, 0.15);
      --radius: 16px;
    }
  </style>
  
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="assets/css/system-settings.css">
  
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
      background:
        radial-gradient(ellipse 90% 60% at 100% 0%, rgba(66, 116, 49, 0.08) 0%, transparent 50%),
        radial-gradient(ellipse 70% 50% at 0% 100%, rgba(54, 97, 185, 0.06) 0%, transparent 45%),
        var(--page-bg);
      color: var(--text);
      min-height: 100vh;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    /* ========== SIDEBAR ========== */
    .sidebar {
      width: 288px;
      background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.06) 0%, transparent 28%),
        linear-gradient(165deg, #1a3214 0%, var(--navy-dark) 38%, var(--navy) 100%);
      color: white;
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      z-index: 1000;
      overflow-y: auto;
      overflow-x: hidden;
      transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.35s ease;
      box-shadow: 8px 0 40px rgba(15, 23, 42, 0.18), inset -1px 0 0 rgba(255, 255, 255, 0.06);
    }

    .sidebar::-webkit-scrollbar { width: 6px; }
    .sidebar::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.18);
      border-radius: 6px;
    }

    .sidebar-header {
      padding: 22px 18px 20px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      display: flex;
      flex-direction: row;
      align-items: center;
      gap: 14px;
      text-align: left;
      background: linear-gradient(180deg, rgba(0, 0, 0, 0.2) 0%, transparent 100%);
    }

    /* Circular seal — transparent PNG, no white rectangle */
    .logo-container {
      width: 54px;
      height: 54px;
      min-width: 54px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      overflow: hidden;
      background: rgba(255, 255, 255, 0.12);
      border: 2px solid rgba(255, 255, 255, 0.35);
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);
    }

    .logo-container img {
      display: block;
      width: 82%;
      height: 82%;
      object-fit: contain;
      object-position: center;
    }

    .logo-container img.logo-fallback-svg {
      width: 70%;
      height: 70%;
    }

    .sidebar-header h3 {
      font-size: 1.05rem;
      font-weight: 700;
      margin: 0;
      line-height: 1.35;
      color: white;
      letter-spacing: -0.02em;
    }

    .sidebar-header h3 small {
      display: block;
      font-size: 0.78rem;
      opacity: 0.88;
      font-weight: 500;
      letter-spacing: 0.01em;
    }

    .sidebar-section {
      padding: 20px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-section:last-child {
      border-bottom: none;
    }

    .sidebar-section-title {
      padding: 0 20px 12px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.5);
      letter-spacing: 0.5px;
    }

    .sidebar-link {
      display: flex;
      align-items: center;
      padding: 13px 20px;
      border-radius: 0 12px 12px 0;
      color: rgba(255, 255, 255, 0.88);
      text-decoration: none;
      transition: background 0.2s, color 0.2s, transform 0.2s;
      border-left: 3px solid transparent;
      font-size: 0.92rem;
      font-weight: 500;
      cursor: pointer;
    }

    .sidebar-link:hover,
    .sidebar-link.active {
      background: rgba(255, 255, 255, 0.12);
      color: white;
      border-left-color: var(--gold);
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06);
    }

    .sidebar-link i {
      font-size: 1.1rem;
      margin-right: 12px;
      width: 24px;
      text-align: center;
      opacity: 0.9;
    }

    .sidebar-link .arrow {
      margin-left: auto;
      transition: transform 0.3s;
      font-size: 0.9rem;
      opacity: 0.7;
    }

    .sidebar-link.active .arrow {
      transform: rotate(180deg);
    }

    .sidebar-submenu {
      background: rgba(0, 0, 0, 0.15);
      display: none;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .sidebar-link.active + .sidebar-submenu {
      display: block;
    }

    .sidebar-submenu a {
      display: flex;
      align-items: center;
      padding: 12px 20px 12px 56px;
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      font-size: 0.9rem;
      transition: all 0.2s;
      border-left: 4px solid transparent;
      position: relative;
      cursor: pointer;
    }

    .sidebar-submenu a:hover {
      background: rgba(255, 255, 255, 0.05);
      color: white;
      border-left-color: var(--gold);
      padding-left: 60px;
    }

    .sidebar-submenu a::before {
      content: '';
      position: absolute;
      left: 40px;
      top: 50%;
      width: 6px;
      height: 6px;
      background: var(--gold);
      border-radius: 50%;
      transform: translateY(-50%);
      opacity: 0.5;
    }

    .sidebar-submenu a i {
      font-size: 0.8rem;
      margin-right: 8px;
      width: 16px;
    }

    /* ========== MAIN CONTENT ========== */
    .main-content {
      margin-left: 288px;
      min-height: 100vh;
      transition: margin-left 0.3s ease;
      display: flex;
      flex-direction: column;
    }

    /* ========== TOPBAR ========== */
    .topbar {
      background: rgba(255, 255, 255, 0.86);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      padding: 16px 32px;
      border-bottom: 1px solid rgba(226, 232, 240, 0.9);
      box-shadow: 0 4px 24px -4px rgba(15, 23, 42, 0.07);
      position: sticky;
      top: 0;
      z-index: 999;
    }

    .topbar-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .page-title h1 {
      font-size: 1.5rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: var(--text-brand);
      margin: 0;
    }

    .page-title p {
      color: var(--muted);
      margin: 0;
      font-size: 0.9rem;
    }

    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .btn.btn-create-reminder {
      background: linear-gradient(135deg, #ef4444 0%, var(--gold) 100%) !important;
      color: #fff !important;
      border: none !important;
      padding: 10px 18px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.9rem;
      transition: transform 0.2s, box-shadow 0.2s;
      box-shadow: 0 4px 16px rgba(226, 29, 30, 0.35);
    }

    .btn.btn-create-reminder:hover {
      color: #fff !important;
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(226, 29, 30, 0.4);
    }

    .notification-bell {
      position: relative;
      background: none;
      border: 1px solid var(--border);
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--muted);
      transition: all 0.2s;
      cursor: pointer;
    }

    .notification-bell:hover {
      background: var(--surface);
      color: var(--navy);
    }

    .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background: var(--danger);
      color: white;
      font-size: 0.7rem;
      padding: 2px 6px;
      border-radius: 50%;
      min-width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
    }

    .user-profile-dropdown {
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      padding: 5px 10px;
      border-radius: 8px;
      transition: all 0.2s;
    }

    .user-profile-dropdown:hover {
      background: var(--surface);
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--gold);
    }

    .user-info {
      display: flex;
      flex-direction: column;
    }

    .user-name {
      font-weight: 600;
      font-size: 0.9rem;
      color: var(--navy);
    }

    .user-role {
      font-size: 0.8rem;
      color: var(--muted);
    }

    .dropdown-menu {
      border: none;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
      border-radius: 10px;
      overflow: hidden;
      margin-top: 10px;
    }

    .dropdown-item {
      padding: 10px 15px;
      font-size: 0.9rem;
      color: var(--text);
      transition: all 0.2s;
    }

    .dropdown-item:hover {
      background: var(--surface);
      color: var(--navy);
    }

    .dropdown-item i {
      margin-right: 10px;
      width: 20px;
      text-align: center;
    }

    /* ========== CONTENT AREA ========== */
    .content-wrapper {
      flex: 1;
      padding: 28px 32px 40px;
      background: transparent;
    }

    /* Dashboard view styling */
    #dashboard-view {
      display: block;
    }

    #content-frame {
      width: 100%;
      height: calc(100vh - 100px);
      border: none;
      border-radius: 12px;
      background: white;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    /* ========== DASHBOARD HERO ========== */
    .dashboard-hero {
      margin-bottom: 30px;
    }

    .hero-card {
      background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
      border-radius: var(--radius);
      padding: 22px 22px 20px;
      position: relative;
      overflow: hidden;
      box-shadow: var(--shadow-md);
      height: 100%;
      border: 1px solid rgba(255, 255, 255, 0.9);
      transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .hero-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-lg);
    }

    .hero-meta {
      font-size: .85rem;
      color: var(--muted);
      font-weight: 600;
      text-transform: uppercase;
    }

    .hero-value {
      font-size: 2.15rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: var(--text-brand);
      margin-top: 6px;
    }

    .hero-icon {
      position: absolute;
      right: 18px;
      bottom: 12px;
      font-size: 3.5rem;
      opacity: .12;
    }

    .hero-card.success { border-left: 5px solid var(--success); }
    .hero-card.info { border-left: 5px solid var(--info); }
    .hero-card.warning { border-left: 5px solid var(--warning); }

    /* ========== STAFF PERSONAL HUB ========== */
    .staff-hub-shell {
      margin-bottom: 2rem;
      padding: 1.5rem 1.5rem 1.75rem;
      border-radius: var(--radius);
      background: linear-gradient(145deg, #ffffff 0%, #f8fafc 55%, #f1f5f9 100%);
      border: 1px solid var(--border);
      box-shadow: var(--shadow-md);
    }
    .staff-hub-welcome {
      margin-bottom: 1.35rem;
      padding: 1.1rem 1.25rem;
      border-radius: 14px;
      background: linear-gradient(125deg, var(--navy-dark) 0%, var(--navy) 42%, var(--navy-secondary) 100%);
      color: #fff;
      box-shadow: 0 12px 32px rgba(47, 90, 38, 0.22);
      position: relative;
      overflow: hidden;
    }
    .staff-hub-welcome::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(105deg, transparent 40%, rgba(255,255,255,0.07) 50%, transparent 60%);
      pointer-events: none;
    }
    .staff-hub-welcome h2 {
      font-size: 1.35rem;
      font-weight: 800;
      color: #fff;
      margin: 0 0 6px 0;
      position: relative;
      z-index: 1;
      letter-spacing: -0.02em;
    }
    .staff-hub-welcome p {
      margin: 0;
      color: rgba(255, 255, 255, 0.88);
      font-size: 0.92rem;
      line-height: 1.45;
      position: relative;
      z-index: 1;
      max-width: 52rem;
    }
    .staff-hub-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(228px, 1fr));
      gap: 14px;
      margin-bottom: 1.25rem;
    }
    .staff-hub-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 16px 16px 14px;
      box-shadow: var(--shadow-sm);
      transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .staff-hub-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-lg);
      border-color: rgba(66, 116, 49, 0.25);
    }
    .staff-hub-card .hub-icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.28rem;
      margin-bottom: 10px;
      background: linear-gradient(135deg, rgba(66, 116, 49, 0.14) 0%, rgba(54, 97, 185, 0.12) 100%);
      color: var(--navy);
    }
    .staff-hub-card .hub-label {
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--muted);
      font-weight: 700;
      margin-bottom: 4px;
    }
    .staff-hub-card .hub-value {
      font-size: 1.85rem;
      font-weight: 800;
      color: var(--text-brand);
      line-height: 1.1;
      letter-spacing: -0.03em;
    }
    .staff-hub-card .hub-sub {
      font-size: 0.82rem;
      color: var(--muted);
      margin-top: 6px;
      line-height: 1.35;
    }
    .staff-hub-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    .staff-hub-actions .btn-hub {
      border-radius: 11px;
      font-weight: 600;
      font-size: 0.88rem;
      padding: 10px 16px;
      box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
    }
    .staff-agent-panel {
      margin-top: 0.5rem;
      margin-bottom: 2rem;
      padding: 0;
      border-radius: var(--radius);
      background: #fff;
      border: 1px solid var(--border);
      box-shadow: var(--shadow-md);
      overflow: hidden;
    }
    .staff-agent-panel-head {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      padding: 1.25rem 1.35rem;
      background: linear-gradient(180deg, #fafbfc 0%, #fff 100%);
      border-bottom: 1px solid var(--border);
    }
    .staff-agent-panel-head h3 {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--navy);
      margin: 0 0 6px 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .staff-agent-panel-head h3 i {
      color: var(--gold);
    }
    .staff-agent-panel-sub {
      margin: 0;
      font-size: 0.88rem;
      color: var(--muted);
      max-width: 36rem;
      line-height: 1.45;
    }
    .staff-agent-panel-body {
      padding: 1.35rem;
    }
    .staff-agent-chart-wrap {
      background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 1rem 0.75rem 0.5rem;
      height: 100%;
      min-height: 260px;
    }
    .staff-agent-table-wrap {
      border: 1px solid var(--border);
      border-radius: 14px;
      overflow: hidden;
      background: #fff;
    }
    .staff-agent-table-wrap thead th {
      background: linear-gradient(180deg, #f1f5f9 0%, #e8eef4 100%);
      color: var(--navy);
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      font-weight: 700;
      border-bottom: 2px solid rgba(66, 116, 49, 0.2);
      white-space: nowrap;
    }
    .staff-agent-table-wrap tbody td {
      font-size: 0.92rem;
      vertical-align: middle;
    }
    .staff-agent-email-badge {
      font-family: ui-monospace, monospace;
      font-size: 0.8rem;
      color: var(--muted);
    }

    /* ========== SUMMARY CARDS ========== */
    .summary-card {
      background: linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
      border-radius: var(--radius);
      box-shadow: var(--shadow-md);
      margin-bottom: 28px;
      overflow: hidden;
      border: 1px solid rgba(66, 116, 49, 0.2);
    }

    .summary-header {
      background: linear-gradient(125deg, var(--navy-dark) 0%, var(--navy) 45%, var(--navy-secondary) 100%);
      color: white;
      padding: 22px 22px;
      display: flex;
      align-items: center;
      gap: 15px;
      position: relative;
    }

    .summary-header::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.08) 50%, transparent 100%);
      pointer-events: none;
    }

    .summary-title {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 600;
      font-size: 1.1rem;
      margin: 0;
      position: relative;
      z-index: 1;
    }

    .summary-icon {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      background: rgba(242, 166, 90, 0.2);
      border: 1px solid rgba(242, 166, 90, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      color: white;
    }

    .summary-body {
      padding: 25px;
    }

    .flag-buttons {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 12px;
      margin-top: 15px;
    }

    .flag-btn {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 12px 15px;
      text-decoration: none;
      color: var(--text);
      transition: all 0.2s;
      font-size: 0.95rem;
    }

    .flag-btn:hover {
      background: var(--navy);
      color: white;
      border-color: var(--navy);
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(66, 116, 49, 0.25);
    }

    .flag-count {
      background: var(--gold);
      color: var(--navy);
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      min-width: 32px;
      text-align: center;
    }

    .flag-btn:hover .flag-count {
      background: white;
      color: var(--navy);
    }

    /* ========== PAYMENT DASHBOARD ========== */
    .payment-dashboard {
      background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
      border-radius: var(--radius);
      padding: 26px;
      box-shadow: var(--shadow-md);
      border: 1px solid rgba(226, 232, 240, 0.95);
      margin-bottom: 28px;
    }

    .payment-dashboard {
      background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
      border-radius: 16px;
      padding: 24px 26px 28px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 8px 28px rgba(15, 23, 42, 0.06);
    }

    .pay-dash-header {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 18px;
    }

    .pay-dash-header h3 {
      margin: 0;
      font-size: 1.35rem;
      font-weight: 800;
      color: #0b3c5d;
    }

    .pay-dash-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
    }

    .pay-dash-badge {
      font-size: 0.72rem;
      font-weight: 700;
      padding: 5px 10px;
      border-radius: 999px;
      letter-spacing: 0.02em;
    }

    .pay-dash-badge.ok {
      background: #dcfce7;
      color: #166534;
    }

    .pay-dash-badge.muted {
      background: #f1f5f9;
      color: #64748b;
    }

    .pay-currency-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 14px;
      margin-bottom: 18px;
    }

    .pay-currency-card {
      background: #fff;
      border-radius: 12px;
      padding: 16px 18px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
      position: relative;
      overflow: hidden;
    }

    .pay-currency-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #427431, #0b3c5d);
    }

    .pay-currency-code {
      font-size: 0.75rem;
      font-weight: 800;
      letter-spacing: 0.12em;
      color: #64748b;
      margin-bottom: 8px;
    }

    .pay-currency-row {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      font-size: 0.82rem;
      padding: 3px 0;
      color: #475569;
    }

    .pay-currency-row strong {
      color: #0f172a;
      font-weight: 700;
      font-size: 0.9rem;
    }

    .pay-currency-row.out strong {
      color: #b45309;
    }

    .payment-kpis {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin-bottom: 20px;
    }

    .payment-kpi-card {
      background: #fff;
      border-radius: 12px;
      padding: 14px 12px;
      text-align: center;
      transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
      cursor: pointer;
      border: 1px solid #e2e8f0;
      box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    }

    .payment-kpi-card:hover {
      transform: translateY(-2px);
      border-color: #94a3b8;
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
    }

    .payment-kpi-card.is-money {
      cursor: default;
    }

    .payment-kpi-card.is-money:hover {
      transform: none;
      box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
      border-color: #e2e8f0;
    }

    .payment-kpi-icon {
      font-size: 1.5rem;
      margin-bottom: 6px;
      line-height: 1;
    }

    .payment-kpi-value {
      font-size: 1.45rem;
      font-weight: 800;
      color: #0b3c5d;
      margin: 4px 0;
      line-height: 1.2;
    }

    .payment-kpi-value.small {
      font-size: 1.1rem;
    }

    .payment-kpi-title {
      font-size: 0.78rem;
      color: #64748b;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .chart-container {
      background: #fff;
      border-radius: 12px;
      padding: 18px 20px;
      margin-bottom: 16px;
      box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
      border: 1px solid #e2e8f0;
      height: 100%;
    }

    .chart-container h6 {
      color: #0b3c5d;
      font-size: 0.95rem;
      margin-bottom: 12px !important;
    }

    .pay-recent-wrap {
      background: #fff;
      border-radius: 12px;
      padding: 18px 20px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
    }

    .pay-recent-wrap h6 {
      color: #0b3c5d;
      font-size: 0.95rem;
    }

    .pay-recent-wrap .table thead th {
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #64748b;
      border-bottom-width: 2px;
      border-color: #e2e8f0;
      background: #f8fafc !important;
    }

    .pay-recent-wrap .table tbody td {
      font-size: 0.88rem;
      vertical-align: middle;
      border-color: #f1f5f9;
    }

    .pay-currency-pill {
      display: inline-block;
      font-size: 0.68rem;
      font-weight: 800;
      padding: 2px 6px;
      border-radius: 4px;
      background: #e8eef9;
      color: #0b3c5d;
      margin-right: 6px;
      vertical-align: middle;
    }

    /* ========== AGENT TRACKING ========== */
    .agent-tracking-card {
      background: white;
      border-radius: 12px;
      padding: 25px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      margin-bottom: 30px;
    }

    .agent-tracking-card h3 {
      font-size: 1.3rem;
      font-weight: 600;
      color: var(--navy);
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .agent-tracking-card h3 i {
      color: var(--gold);
    }

    /* ========== MODALS ========== */
    .modal-content {
      border: none;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
      background: linear-gradient(135deg, var(--navy), var(--navy-secondary));
      color: white;
      border-bottom: none;
      padding: 20px;
    }

    .modal-title {
      font-weight: 600;
      font-size: 1.1rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .modal-header .btn-close {
      filter: invert(1);
      opacity: 0.8;
      transition: opacity 0.2s;
    }

    .modal-header .btn-close:hover {
      opacity: 1;
    }

    .modal-body {
      padding: 25px;
      background: var(--surface);
    }

    .modal-footer {
      border-top: 1px solid var(--border);
      padding: 20px;
      background: white;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--navy), var(--navy-secondary));
      border: none;
      padding: 10px 25px;
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.2s;
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, var(--navy-dark), var(--navy));
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(1, 47, 107, 0.2);
    }

    /* ========== TOASTS ========== */
    .toast-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1090;
    }

    .toast {
      border: none;
      border-radius: 10px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      overflow: hidden;
    }

    .toast-body {
      font-size: 0.9rem;
      line-height: 1.4;
    }

    /* ========== RESPONSIVE ========== */
    @media (max-width: 1200px) {
      .flag-buttons {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      }
    }

    @media (max-width: 992px) {
      .sidebar {
        transform: translateX(-100%);
        width: 288px;
      }
      
      .sidebar.show {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0 !important;
      }
      
      .mobile-toggle {
        display: block !important;
      }
      
      .topbar-content {
        flex-wrap: wrap;
        gap: 15px;
      }
      
      .payment-kpis {
        grid-template-columns: repeat(2, 1fr);
      }

      .pay-currency-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 768px) {
      .content-wrapper {
        padding: 15px;
      }
      
      .flag-buttons {
        grid-template-columns: 1fr;
      }
      
      .payment-kpis {
        grid-template-columns: 1fr;
      }
      
      .topbar {
        padding: 15px;
      }
      
      .user-info {
        display: none;
      }
      
      .dashboard-hero .row {
        margin: 0 -8px;
      }
      
      .dashboard-hero [class*="col-"] {
        padding: 8px;
      }
    }

    @media (max-width: 576px) {
      .topbar-actions {
        flex-wrap: wrap;
        justify-content: flex-end;
      }
      
      .btn-create-reminder {
        order: 2;
      }
      
      .notification-bell {
        order: 3;
      }
      
      .user-profile-dropdown {
        order: 1;
        margin-left: auto;
      }
    }

    .mobile-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 1.5rem;
      color: var(--navy);
      cursor: pointer;
    }

    /* Loading spinner */
    .loading-spinner {
      display: none;
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      z-index: 9999;
      background: rgba(255, 255, 255, 0.9);
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    /* Table styling */
    .table-responsive {
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .table th {
      background: var(--surface);
      color: var(--navy);
      font-weight: 600;
      border-bottom: 2px solid var(--border);
    }

    .table td {
      vertical-align: middle;
      border-color: var(--border);
    }

    /* Form styling */
    .form-label {
      font-weight: 500;
      color: var(--navy);
      font-size: 0.9rem;
    }

    .form-control,
    .form-select {
      border-radius: 8px;
      border: 1px solid var(--border);
      padding: 10px 15px;
      font-size: 0.9rem;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: var(--navy);
      box-shadow: 0 0 0 0.2rem rgba(1, 47, 107, 0.25);
    }
  </style>
</head>
<body>
  <!-- Loading Spinner -->
  <div class="loading-spinner" id="loadingSpinner">
    <div class="text-center">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-2 text-muted">Loading content...</p>
    </div>
  </div>

  <!-- SIDEBAR -->
  <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="logo-container">
        <img src="scholarsync-global-logo.jpg" alt="" decoding="async" onerror="this.onerror=null;this.src='assets/brand/scholarsync-mark.svg';this.classList.add('logo-fallback-svg');">
      </div>
      <h3>Partners of ScholarSync<br><small>Canada Visa Consultant</small></h3>
    </div>
    
    <!-- Dashboard Section -->
    <div class="sidebar-section">
      <div class="sidebar-section-title">Main Menu</div>
      <a href="#" class="sidebar-link active" onclick="showDashboard()">
        <i class="bi bi-speedometer2"></i>
        <span>Dashboard</span>
      </a>
    </div>
    
    <!-- Applications Section -->
    <div class="sidebar-section">
      <div class="sidebar-section-title">Applications</div>
      
      <?php if (in_array('all_admissions', $allowedSidebarItems)): ?>
      <!-- All university admissions -->
      <a href="#all_admissions" class="sidebar-link" onclick="toggleSidebarMenu('all_admissions')">
        <i class="bi bi-mortarboard"></i>
        <span>All university admissions</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_all_admissions">
        <a href="#" onclick="loadInFrame('application-list.php', 'Student Application Report')">
          <i class="bi bi-file-text"></i>
          Student application Report
        </a>
        <a href="#" onclick="loadInFrame('students-manage.php', 'Applicants Management')">
          <i class="bi bi-gear"></i>
          Applicants Management
        </a>
        <a href="#" onclick="loadInFrame('receipt_viewer.php', 'Check Payment Receipt')">
          <i class="bi bi-receipt"></i>
          Check payment Receipt
        </a>
        <a href="#" onclick="loadInFrame('applyboard-dashboard.php', 'ApplyBoard Applications')">
          <i class="bi bi-grid-1x2"></i>
          ApplyBoard Applications
        </a>
        <a href="#" onclick="loadInFrame('task-assignment-monitoring.php', 'Task assignment monitoring')">
          <i class="bi bi-kanban"></i>
          Task assignment monitoring
        </a>
      </div>
      <?php endif; ?>
      
      <?php if (in_array('loan_applications', $allowedSidebarItems)): ?>
      <!-- Study Loan Applications -->
      <a href="#loan_applications" class="sidebar-link" onclick="toggleSidebarMenu('loan_applications')">
        <i class="bi bi-bank"></i>
        <span>Study Loan Applications</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_loan_applications">
        <a href="#" onclick="loadInFrame('loan-applicants-report.php', 'Loan Application List')">
          <i class="bi bi-list-check"></i>
          Loan Application list
        </a>
        <a href="#" onclick="loadInFrame('loan_search.php', 'Search by User ID')">
          <i class="bi bi-search"></i>
          User-iD
        </a>
      </div>
      <?php endif; ?>
      
      <?php if (in_array('I-20_applications', $allowedSidebarItems)): ?>
      <!-- I-20 Applications -->
      <a href="#I-20_applications" class="sidebar-link" onclick="toggleSidebarMenu('I-20_applications')">
        <i class="bi bi-file-earmark-text"></i>
        <span>I-20 Applications</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_I-20_applications">
        <a href="#" onclick="loadInFrame('form-20-report.php', 'I-20 Applicant List')">
          <i class="bi bi-file-earmark"></i>
          I-20 Applicant List
        </a>
      </div>
      <?php endif; ?>
      
      <?php if (in_array('staff_reporting', $allowedSidebarItems) && $isSuperExecutive): ?>
      <!-- Staff Management - Superadmin only -->
      <a href="#staff_reporting" class="sidebar-link" onclick="toggleSidebarMenu('staff_reporting')">
        <i class="bi bi-people"></i>
        <span>Staff Management</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_staff_reporting">
        <a href="#" onclick="loadInFrame('staff-management.php', 'Manage Staff')">
          <i class="bi bi-person-gear"></i>
          Staff
        </a>
        <!-- <a href="#" onclick="loadInFrame('tasks.php', 'Task Allocation')">
          <i class="bi bi-list-task"></i>
          Task Allocation
        </a> -->
        <a href="#" onclick="loadInFrame('admin/contracts-admin.php', 'Staff Employment Contracts')">
          <i class="bi bi-file-earmark-text"></i>
          Staff employment contracts
        </a>
        <a href="#" onclick="loadInFrame('salary-report.php', 'Requested Salaries')">
          <i class="bi bi-cash-stack"></i>
          Salaries
        </a>
        <a href="#" onclick="loadInFrame('leave-approvals.php', 'Manage Permissions')">
          <i class="bi bi-calendar-check"></i>
          Permissions
        </a>
        <a href="#" onclick="loadInFrame('overtime-approvals.php', 'Overtime Management')">
          <i class="bi bi-clock-history"></i>
          Overtime
        </a>
        <a href="#" onclick="loadInFrame('jobs_report.php', 'Job Report')">
          <i class="bi bi-clipboard-check"></i>
          Job reports
        </a>
        <a href="#" onclick="loadInFrame('admin-payroll.php', 'Payroll')">
          <i class="bi bi-credit-card"></i>
          Payroll
        </a>
        <a href="#" onclick="loadInFrame('cards/generate_staff_card.php', 'Generate Staff Cards')">
          <i class="bi bi-card-text"></i>
          Staff cards
        </a>
        <a href="#" onclick="loadInFrame('staff-warning-letters.php', 'Staff Warning Letters')">
          <i class="bi bi-exclamation-octagon"></i>
          Warning letters
        </a>
        <a href="#" onclick="loadInFrame('mopay-payment-transactions.php', 'MoPay Transactions')">
          <i class="bi bi-arrow-left-right"></i>
          MoPay transactions
        </a>
      </div>
      <?php endif; ?>
      
      <?php if (in_array('commission_request', $allowedSidebarItems)): ?>
      <!-- Commission Request -->
      <a href="#commission_request" class="sidebar-link" onclick="toggleSidebarMenu('commission_request')">
        <i class="bi bi-cash-coin"></i>
        <span>Commission Request</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_commission_request">
        <a href="#" onclick="loadInFrame('Commission-Request.php', 'Request Commission')">
          <i class="bi bi-plus-circle"></i>
          Request commission
        </a>
        <a href="#" onclick="loadInFrame('commission-my-requests.php', 'My Commission Requests')">
          <i class="bi bi-person-check"></i>
          My requests
        </a>
        <?php if (!empty($isSuperExecutive)): ?>
        <a href="#" onclick="loadInFrame('commission-requests-report.php', 'All Commission Requests')">
          <i class="bi bi-receipt"></i>
          All Requests
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      
   <?php if (in_array('credit_transfer', $allowedSidebarItems)): ?>
      <!-- Credit Transfer Applications - Superadmin only -->
      <a href="#credit_transfer" class="sidebar-link" onclick="toggleSidebarMenu('credit_transfer')">
        <i class="bi bi-arrow-left-right"></i>
        <span>Credit Transfer Applications</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_credit_transfer">
        <a href="#" onclick="loadInFrame('Credit-Transfer-report.php', 'Transfer Requests List')">
          <i class="bi bi-list-check"></i>
          Transfer Requests list
        </a>
        <!-- <a href="#" onclick="loadInFrame('transfer-status.php', 'Review Transfer Status')">
          <i class="bi bi-eye"></i>
          Review Status
        </a> -->
        <a href="#" onclick="loadInFrame('credit-search.php', 'Search Credit User ID')">
          <i class="bi bi-search"></i>
          credit userID
        </a>
        <a href="#" onclick="loadInFrame('research-project-elearning.php', 'Research Project for E-Learning')">
          <i class="bi bi-journal-bookmark"></i>
          RESEARCH PROJECT FOR ELEARNING
        </a>
        <?php if ($isSuperExecutive): ?>
        <a href="#" onclick="loadInFrame('special-student-payments.php', 'Record Credit Transfer / UPAFA Payment')">
          <i class="bi bi-cash-stack"></i>
          Record Payment (Credit Transfer / UPAFA)
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      
      <?php if (in_array('visit_study_visa', $allowedSidebarItems)): ?>
      <!-- Visit And Study Visa -->
      <a href="#visit_study_visa" class="sidebar-link" onclick="toggleSidebarMenu('visit_study_visa')">
        <i class="bi bi-globe2"></i>
        <span>Visit And Study Visa</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_visit_study_visa">
        <a href="#" onclick="loadInFrame('visa-report.php', 'Visa Applicant List')">
          <i class="bi bi-people"></i>
          Applicant List
        </a>
        <a href="#" onclick="loadInFrame('visa-report.php?process_status=rejected', 'Visa — Rejected')">
          <i class="bi bi-x-circle"></i>
          Rejected
        </a>
      </div>
      <?php endif; ?>
      
      <?php if (in_array('staff_attendance', $allowedSidebarItems)): ?>
      <!-- Staff Attendance -->
      <a href="#staff_attendance" class="sidebar-link" onclick="toggleSidebarMenu('staff_attendance')">
        <i class="bi bi-calendar-check"></i>
        <span>Staff Attendance</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_staff_attendance">
        <a href="#" onclick="loadInFrame('attendance-ui.php', 'Take Attendance')">
          <i class="bi bi-calendar-plus"></i>
          Take attendance
        </a>
        <a href="#" onclick="loadInFrame('job_todo_list.php', 'Job To-Do List')">
          <i class="bi bi-list-task"></i>
          Job Do List
        </a>
        <a href="#" onclick="loadInFrame('salary.php', 'Salary Request')">
          <i class="bi bi-wallet2"></i>
          Salary Request
        </a>
        <a href="#" onclick="loadInFrame('admin/contract.php', 'Sign Contract')">
          <i class="bi bi-pen"></i>
          Sign your contract
        </a>
        <a href="#" onclick="loadInFrame('leave-request.php', 'Permission Request')">
          <i class="bi bi-calendar-x"></i>
          Permission Request
        </a>
        <a href="#" onclick="loadInFrame('staff_overtime_request.php', 'Overtime Request')">
          <i class="bi bi-clock-history"></i>
          Overtime request
        </a>
        <a href="#" onclick="loadInFrame('my-leaves.php', 'Permission Status')">
          <i class="bi bi-calendar-check2"></i>
          Check permission status
        </a>
        <a href="#" onclick="loadInFrame('attendance-report.php', 'Attendance Report')">
          <i class="bi bi-graph-up"></i>
          Attendance Report
        </a>
        <a href="#" onclick="loadInFrame('jobs_report.php', 'Job Report')">
          <i class="bi bi-bar-chart"></i>
          Check job report
        </a>
        <a href="#" onclick="loadInFrame('cards/generate_staff_card.php', 'Generate Service Card')">
          <i class="bi bi-award"></i>
          Generate your service card
        </a>
      </div>
      <?php endif; ?>
      
      <?php if (in_array('university_portal', $allowedSidebarItems)): ?>
      <!-- Apply for Student -->
      <a href="#university_portal" class="sidebar-link" onclick="toggleSidebarMenu('university_portal')">
        <i class="bi bi-person-plus"></i>
        <span>Apply for Student</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_university_portal">
        <a href="#" onclick="loadInFrame('student-application.php', 'Apply Now')">
          <i class="bi bi-pencil-square"></i>
          Apply Now
        </a>
        <a href="#" onclick="loadInFrame('agent-student-manage.php', 'Manage Students')">
          <i class="bi bi-people"></i>
          Manage Students
        </a>
        <a href="#" onclick="loadInFrame('userid-search.php', 'User ID Search')">
          <i class="bi bi-search"></i>
          User-id
        </a>
      </div>
      <?php endif; ?>

      <?php if (in_array('marketing', $allowedSidebarItems)): ?>
      <!-- Marketing Materials -->
      <a href="#marketing" class="sidebar-link" onclick="toggleSidebarMenu('marketing')">
        <i class="bi bi-megaphone"></i>
        <span>Marketing Materials</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_marketing">
        <a href="#" onclick="loadInFrame('upload-materials.php', 'Upload Marketing Materials')">
          <i class="bi bi-upload"></i>
          Upload Marketing materials
        </a>
        <a href="#" onclick="loadInFrame('get-materials.php', 'Get Marketing Materials')">
          <i class="bi bi-download"></i>
          Get Marketing materials
        </a>
      </div>
      <?php endif; ?>

      <?php if (in_array('smart_brochure', $allowedSidebarItems)): ?>
      <!-- Smart Brochure Sharing -->
      <a href="#smart_brochure" class="sidebar-link" onclick="toggleSidebarMenu('smart_brochure')">
        <i class="bi bi-megaphone-fill"></i>
        <span>Smart Brochure Sharing</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_smart_brochure">
        <a href="#" onclick="loadInFrame('marketing-brochures.php', 'Smart Brochure Sharing')">
          <i class="bi bi-collection"></i>
          Brochures &amp; Share Center
        </a>
      </div>
      <?php endif; ?>

      <?php if (in_array('jobsabrod', $allowedSidebarItems)): ?>
      <!-- Jobs Application - Superadmin only -->
      <a href="#jobsabrod" class="sidebar-link" onclick="toggleSidebarMenu('jobsabrod')">
        <i class="bi bi-briefcase"></i>
        <span>Jobs Application</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_jobsabrod">
        <a href="#" onclick="loadInFrame('job-applicant.php', 'Check Job Applicants')">
          <i class="bi bi-person-check"></i>
          Check job Applicants
        </a>
        <a href="#" onclick="loadInFrame('job-applicant.php?process_status=rejected', 'Job Applicants — Rejected')">
          <i class="bi bi-x-circle"></i>
          Rejected
        </a>
      </div>
      <?php endif; ?>
      
      <?php if (in_array('canada_medical', $allowedSidebarItems)): ?>
      <!-- Canada Medical Exams -->
      <a href="#canada_medical" class="sidebar-link" onclick="toggleSidebarMenu('canada_medical')">
        <i class="bi bi-hospital"></i>
        <span>Canada Medical Exams</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_canada_medical">
        <a href="#" onclick="loadInFrame('canada-medical-exams-applications.php', 'View Applications')">
          <i class="bi bi-list-ul"></i>
          View Applications
        </a>
        <a href="#" onclick="loadInFrame('canada-medical-exams-applications.php?status=pending', 'Pending')">
          <i class="bi bi-clock"></i>
          Pending
        </a>
        <a href="#" onclick="loadInFrame('canada-medical-exams-applications.php?status=under_review', 'Under Review')">
          <i class="bi bi-eye"></i>
          Under Review
        </a>
        <a href="#" onclick="loadInFrame('canada-medical-exams-applications.php?status=approved', 'Approved')">
          <i class="bi bi-check-circle"></i>
          Approved
        </a>
        <a href="#" onclick="loadInFrame('canada-medical-exams-applications.php?status=rejected', 'Rejected')">
          <i class="bi bi-x-circle"></i>
          Rejected
        </a>
      </div>
      <?php endif; ?>

      <?php if (in_array('francophonie_mobility', $allowedSidebarItems)): ?>
      <a href="#francophonie_mobility" class="sidebar-link" onclick="toggleSidebarMenu('francophonie_mobility')">
        <i class="bi bi-map"></i>
        <span>Francophonie Mobility</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_francophonie_mobility">
        <a href="#" onclick="loadInFrame('francophonie-mobility-applications.php', 'Francophonie Mobility')">
          <i class="bi bi-list-ul"></i>
          View Applications
        </a>
        <a href="#" onclick="loadInFrame('francophonie-mobility-applications.php?status=pending', 'Pending')">
          <i class="bi bi-clock"></i>
          Pending
        </a>
        <a href="#" onclick="loadInFrame('francophonie-mobility-applications.php?status=under_review', 'Under Review')">
          <i class="bi bi-eye"></i>
          Under Review
        </a>
        <a href="#" onclick="loadInFrame('francophonie-mobility-applications.php?status=approved', 'Approved')">
          <i class="bi bi-check-circle"></i>
          Approved
        </a>
        <a href="#" onclick="loadInFrame('francophonie-mobility-applications.php?status=rejected', 'Rejected')">
          <i class="bi bi-x-circle"></i>
          Rejected
        </a>
        <a href="#" onclick="loadInFrame('admin-generate-fm-contract.php', 'Issue E-Sign Contract')">
          <i class="bi bi-file-earmark-text"></i>
          Issue E-Sign Contract
        </a>
        <a href="#" onclick="loadInFrame('admin-fm-contracts.php', 'Signed Mobility Contracts')">
          <i class="bi bi-file-earmark-check"></i>
          Signed Contracts
        </a>
      </div>
      <?php endif; ?>

      <?php if (in_array('employment_opportunities', $allowedSidebarItems)): ?>
      <a href="#employment_opportunities" class="sidebar-link" onclick="toggleSidebarMenu('employment_opportunities')">
        <i class="bi bi-briefcase"></i>
        <span>Russian Employment Opportunities</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_employment_opportunities">
        <a href="#" onclick="loadInFrame('employment-opportunities-applications.php', 'Russian Employment Opportunities')">
          <i class="bi bi-list-ul"></i>
          View Applications
        </a>
        <a href="#" onclick="loadInFrame('employment-opportunities-applications.php?status=pending', 'Pending')">
          <i class="bi bi-clock"></i>
          Pending
        </a>
        <a href="#" onclick="loadInFrame('employment-opportunities-applications.php?status=under_review', 'Under Review')">
          <i class="bi bi-eye"></i>
          Under Review
        </a>
        <a href="#" onclick="loadInFrame('employment-opportunities-applications.php?status=approved', 'Approved')">
          <i class="bi bi-check-circle"></i>
          Approved
        </a>
        <a href="#" onclick="loadInFrame('employment-opportunities-applications.php?status=rejected', 'Rejected')">
          <i class="bi bi-x-circle"></i>
          Rejected
        </a>
        <a href="#" onclick="loadInFrame('admin-generate-eo-contract.php', 'Issue E-Sign Contract')">
          <i class="bi bi-file-earmark-text"></i>
          Issue E-Sign Contract
        </a>
        <a href="#" onclick="loadInFrame('admin-eo-contracts.php', 'Signed Employment Contracts')">
          <i class="bi bi-file-earmark-check"></i>
          Signed Contracts
        </a>
      </div>
      <?php endif; ?>

      <?php if (in_array('meeting_invitation', $allowedSidebarItems)): ?>
      <a href="#meeting_invitation" class="sidebar-link" onclick="toggleSidebarMenu('meeting_invitation')">
        <i class="bi bi-camera-video"></i>
        <span>Meeting Invitation</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_meeting_invitation">
        <a href="#" onclick="loadInFrame('francophonie-meeting-invitation.php', 'Meeting Invitation')">
          <i class="bi bi-envelope-open"></i>
          Meeting Invitation
        </a>
        <a href="#" onclick="loadInFrame('francophonie-meeting-recordings.php', 'Meeting Recordings')">
          <i class="bi bi-cloud-download"></i>
          Meeting Recordings
        </a>
      </div>
      <?php endif; ?>
      
      <?php if (in_array('refund_requests', $allowedSidebarItems)): ?>
      <!-- Refund Requests (superadmin) -->
      <a href="#refund_requests" class="sidebar-link" onclick="toggleSidebarMenu('refund_requests')">
        <i class="bi bi-cash-stack"></i>
        <span>Refund Requests</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_refund_requests">
        <a href="#" onclick="loadInFrame('refund-requests-report.php', 'Refund Requests')">
          <i class="bi bi-list-ul"></i>
          All Refund Requests
        </a>
        <a href="#" onclick="loadInFrame('refund-requests-report.php?status=pending', 'Pending Refunds')">
          <i class="bi bi-clock"></i>
          Pending
        </a>
        <a href="#" onclick="loadInFrame('refund-requests-report.php?status=under_review', 'Under Review')">
          <i class="bi bi-eye"></i>
          Under Review
        </a>
        <a href="#" onclick="loadInFrame('refund-requests-report.php?status=approved', 'Approved Refunds')">
          <i class="bi bi-check-circle"></i>
          Approved
        </a>
        <a href="#" onclick="loadInFrame('refund-requests-report.php?status=rejected', 'Rejected Refunds')">
          <i class="bi bi-x-circle"></i>
          Rejected
        </a>
      </div>
      <?php endif; ?>
      
      <?php if (in_array('platform', $allowedSidebarItems)): ?>
      <!-- Platforms management - All admins -->
      <a href="#platform" class="sidebar-link" onclick="toggleSidebarMenu('platform')">
        <i class="bi bi-diagram-3"></i>
        <span>Platforms management</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_platform">
        <a href="#" onclick="loadInFrame('platforms.php', 'Platforms Management')">
          <i class="bi bi-gear"></i>
          Platforms management
        </a>
      </div>
      <?php endif; ?>

      <?php if ($isSuperExecutive): ?>
      <a href="#" class="sidebar-link" onclick="loadInFrame('websites.php', 'Website Management')">
        <i class="bi bi-globe2"></i>
        <span>Website management</span>
      </a>
      <?php endif; ?>
      
      <?php if (in_array('contracts', $allowedSidebarItems)): ?>
      <!-- Student contract -->
      <a href="#contracts" class="sidebar-link" onclick="toggleSidebarMenu('contracts')">
        <i class="bi bi-file-earmark-lock"></i>
        <span>Student contract</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_contracts">
        <a href="#" onclick="loadInFrame('admin-generate-student-contract.php', 'Issue Contract Link')">
          <i class="bi bi-link"></i>
          Issue contract link
        </a>
        <a href="#" onclick="loadInFrame('admin-contracts.php', 'View Student Contracts')">
          <i class="bi bi-files"></i>
          View students Contracts
        </a>
        <a href="#" onclick="loadInFrame('admin-contracts-special.php', 'Special Student Contracts')">
          <i class="bi bi-star"></i>
          Special students Contracts
        </a>
        <a href="#" onclick="loadInFrame('admin-generate-student-contract-special.php', 'Issue Special Contract Link')">
          <i class="bi bi-link-45deg"></i>
          Issue special contract link
        </a>
        <a href="#" onclick="loadInFrame('admin/contract_esign.php', 'Signed PhD Contracts')">
          <i class="bi bi-file-earmark-arrow-down"></i>
          Signed PhD Contracts
        </a>
      </div>
      <?php endif; ?>
      
      <?php if (in_array('partner_contracts', $allowedSidebarItems)): ?>
      <!-- Partner contract -->
      <a href="#partner_contracts" class="sidebar-link" onclick="toggleSidebarMenu('partner_contracts')">
        <i class="bi bi-handshake"></i>
        <span>Partner Contract</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_partner_contracts">
        <a href="#" onclick="loadInFrame('admin-generate-partner-contract.php', 'Issue Partner Contract Link (English)')">
          <i class="bi bi-link"></i>
          Issue partner contract link (English)
        </a>
        <a href="#" onclick="loadInFrame('admin-generate-partner-contract-french.php', 'Issue Partner Contract Link (French)')">
          <i class="bi bi-link"></i>
          Issue partner contract link (French)
        </a>
        <a href="#" onclick="loadInFrame('admin-partner-contracts.php', 'View Partner Contracts')">
          <i class="bi bi-files"></i>
          View partner contracts
        </a>
      </div>
      <?php endif; ?>

      <?php if (in_array('korea_event_participation', $allowedSidebarItems)): ?>
      <a href="#korea_event_participation" class="sidebar-link" onclick="toggleSidebarMenu('korea_event_participation')">
        <i class="bi bi-flag"></i>
        <span>South Korea Event Participation</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_korea_event_participation">
        <a href="#" onclick="loadInFrame('korea-event-applications.php', 'South Korea Event Participation')">
          <i class="bi bi-list-ul"></i>
          View Applications
        </a>
        <a href="#" onclick="loadInFrame('korea-event-applications.php?register=1', 'Register applicant')">
          <i class="bi bi-person-plus"></i>
          Register applicant
        </a>
        <a href="#" onclick="loadInFrame('korea-event-applications.php?status=pending', 'Pending')">
          <i class="bi bi-clock"></i>
          Pending
        </a>
        <a href="#" onclick="loadInFrame('korea-event-applications.php?status=under_review', 'Under Review')">
          <i class="bi bi-eye"></i>
          Under Review
        </a>
        <a href="#" onclick="loadInFrame('korea-event-applications.php?status=approved', 'Approved')">
          <i class="bi bi-check-circle"></i>
          Approved
        </a>
        <a href="#" onclick="loadInFrame('korea-event-applications.php?status=rejected', 'Rejected')">
          <i class="bi bi-x-circle"></i>
          Rejected
        </a>
        <a href="#" onclick="window.open('korea-event-participation-request.php', '_blank')">
          <i class="bi bi-box-arrow-up-right"></i>
          Open public form
        </a>
      </div>
      <?php endif; ?>

      <?php if (in_array('korea_invitation_contracts', $allowedSidebarItems)): ?>
      <!-- Korea invitation contract -->
      <a href="#korea_invitation_contracts" class="sidebar-link" onclick="toggleSidebarMenu('korea_invitation_contracts')">
        <i class="bi bi-file-earmark-text"></i>
        <span>Korea Invitation Contract</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_korea_invitation_contracts">
        <a href="#" onclick="loadInFrame('admin-generate-korea-invitation-contract.php', 'Issue Korea Invitation Contract Link')">
          <i class="bi bi-link"></i>
          Issue Korea invitation contract link
        </a>
        <a href="#" onclick="loadInFrame('admin-korea-invitation-contracts.php', 'View Korea Invitation Contracts')">
          <i class="bi bi-files"></i>
          View Korea invitation contracts
        </a>
      </div>
      <?php endif; ?>

      <?php if (in_array('agent_contracts', $allowedSidebarItems)): ?>
      <!-- Agent referral contract -->
      <a href="#agent_contracts" class="sidebar-link" onclick="toggleSidebarMenu('agent_contracts')">
        <i class="bi bi-person-badge"></i>
        <span>Agent Contract</span>
        <i class="bi bi-chevron-down arrow"></i>
      </a>
      <div class="sidebar-submenu" id="submenu_agent_contracts">
        <a href="#" onclick="loadInFrame('admin-generate-agent-contract.php', 'Issue Agent Contract Link')">
          <i class="bi bi-link"></i>
          Issue agent contract link
        </a>
        <a href="#" onclick="loadInFrame('admin-agent-contracts.php', 'View Agent Contracts')">
          <i class="bi bi-files"></i>
          View agent contracts
        </a>
      </div>
      <?php endif; ?>
    </div>
    
    <!-- Account Section -->
    <div class="sidebar-section">
      <div class="sidebar-section-title">Account</div>
      <a href="#" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#profileModal">
        <i class="bi bi-person"></i>
        <span>My Profile</span>
      </a>
      <a href="#" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#changePassModal">
        <i class="bi bi-key"></i>
        <span>Change Password</span>
      </a>
      <?php if ($isSuperExecutive): ?>
        <a href="#" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#adminSettingsModal">
          <i class="bi bi-gear"></i>
          <span>System Settings</span>
        </a>
        <a href="admin-testimonials.php" class="sidebar-link">
          <i class="bi bi-chat-quote"></i>
          <span>Website testimonials</span>
        </a>
      <?php endif; ?>
      <form action="admin-logout.php" method="POST" class="d-inline">
        <button type="submit" class="sidebar-link w-100 text-start border-0 bg-transparent text-white" style="outline: none;">
          <i class="bi bi-box-arrow-right"></i>
          <span>Logout</span>
        </button>
      </form>
    </div>
  </div>
  
  <!-- MAIN CONTENT -->
  <div class="main-content">
    <!-- TOPBAR -->
    <div class="topbar">
      <div class="topbar-content">
        <button class="mobile-toggle" onclick="toggleMobileSidebar()">
          <i class="bi bi-list"></i>
        </button>
        
        <div class="page-title">
          <h1 id="pageTitle">Dashboard</h1>
          <p>Welcome back, <?= htmlspecialchars($displayName) ?>!</p>
        </div>
        
        <div class="topbar-actions">
          <!-- Create Reminder Button -->
          <button class="btn btn-create-reminder" data-bs-toggle="modal" data-bs-target="#createReminderModal">
            <i class="bi bi-plus-circle me-1"></i> Create Reminder
          </button>
          
          <!-- Notifications Bell -->
          <div class="dropdown">
            <button class="notification-bell" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-bell"></i>
              <span id="notifBadge" class="notification-badge d-none">0</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end p-0" style="min-width: 360px; max-height: 420px; overflow:auto;" id="notifMenu">
              <li class="px-3 py-2 fw-semibold border-bottom">Notifications</li>
              <div id="notifItems"></div>
              <li class="text-center p-2"><a class="small" href="notifications.php">View all</a></li>
            </ul>
          </div>
          
          <!-- User Profile Dropdown -->
          <div class="dropdown">
            <div class="user-profile-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
              <img src="<?= htmlspecialchars(pcvc_profile_photo_url($admin['profile_photo'] ?? '')) ?>" 
                   class="user-avatar" alt="Profile">
              <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></div>
                <div class="user-role"><?= ucfirst($role) ?></div>
              </div>
              <i class="bi bi-chevron-down"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
              <li>
                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                  <i class="bi bi-person me-2"></i> My Profile
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePassModal">
                  <i class="bi bi-key me-2"></i> Change Password
                </a>
              </li>
              <?php if ($isSuperExecutive): ?>
                <li>
                  <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#adminSettingsModal">
                    <i class="bi bi-gear me-2"></i> System Settings
                  </a>
                </li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form action="admin-logout.php" method="POST" style="margin: 0;">
                  <button type="submit" class="dropdown-item text-danger">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                  </button>
                </form>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    
    <!-- CONTENT WRAPPER -->
    <div class="content-wrapper">
      <!-- Dashboard View -->
      <div id="dashboard-view">
        <?php if ($showStaffPersonalDashboard):
            $sd = $staffDashStats;
            $attMin = (int) ($sd['attendance_minutes_month'] ?? 0);
            $attH = intdiv($attMin, 60);
            $attM = $attMin % 60;
            $attHm = $attH > 0 ? $attH . 'h ' . $attM . 'm' : $attM . ' min';
        ?>
        <div class="staff-hub-shell">
        <section class="staff-hub-welcome mb-3">
          <h2><i class="bi bi-speedometer2 me-2"></i>Your workspace</h2>
        </section>
        <div class="staff-hub-grid">
          <div class="staff-hub-card">
            <div class="hub-icon"><i class="bi bi-calendar-check"></i></div>
            <div class="hub-label">Attendance · <?= htmlspecialchars((string) ($sd['month_label'] ?? '')) ?></div>
            <div class="hub-value"><?= (int) ($sd['attendance_days_month'] ?? 0) ?></div>
            <div class="hub-sub">days logged · <?= htmlspecialchars($attHm) ?> total</div>
          </div>
          <div class="staff-hub-card">
            <div class="hub-icon"><i class="bi bi-door-open"></i></div>
            <div class="hub-label">Permission requests</div>
            <div class="hub-value"><?= (int) ($sd['leave_pending'] ?? 0) ?></div>
            <div class="hub-sub">pending now · <?= (int) ($sd['leave_last_30d'] ?? 0) ?> in last 30 days</div>
          </div>
          <div class="staff-hub-card">
            <div class="hub-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="hub-label">Salary requests</div>
            <div class="hub-value"><?= (int) ($sd['salary_pending'] ?? 0) ?></div>
            <div class="hub-sub">pending · <?= (int) ($sd['salary_total'] ?? 0) ?> total requests</div>
          </div>
          <div class="staff-hub-card">
            <div class="hub-icon"><i class="bi bi-percent"></i></div>
            <div class="hub-label">Commission requests</div>
            <div class="hub-value"><?= (int) ($sd['commission_last_90d'] ?? 0) ?></div>
            <div class="hub-sub">last 90 days · <?= (int) ($sd['commission_total'] ?? 0) ?> all time</div>
          </div>
          <div class="staff-hub-card">
            <div class="hub-icon"><i class="bi bi-clock-history"></i></div>
            <div class="hub-label">Overtime</div>
            <div class="hub-value"><?= (int) ($sd['overtime_pending'] ?? 0) ?></div>
            <div class="hub-sub">pending · <?= (int) ($sd['overtime_open_approved'] ?? 0) ?> approved (history)</div>
          </div>
        </div>
        <div class="staff-hub-actions mb-4">
          <button type="button" class="btn btn-primary btn-hub" onclick="loadInFrame('attendance-ui.php', 'Attendance')"><i class="bi bi-calendar-plus me-1"></i> Check in</button>
          <button type="button" class="btn btn-outline-primary btn-hub" onclick="loadInFrame('leave-request.php', 'Permission request')"><i class="bi bi-send me-1"></i> Request permission</button>
          <button type="button" class="btn btn-outline-primary btn-hub" onclick="loadInFrame('my-leaves.php', 'My permissions')"><i class="bi bi-list-check me-1"></i> My permissions</button>
          <button type="button" class="btn btn-outline-primary btn-hub" onclick="loadInFrame('salary.php', 'Salary')"><i class="bi bi-wallet2 me-1"></i> Salary</button>
          <button type="button" class="btn btn-outline-primary btn-hub" onclick="loadInFrame('Commission-Request.php', 'Commission')"><i class="bi bi-currency-exchange me-1"></i> Commission</button>
          <button type="button" class="btn btn-outline-primary btn-hub" onclick="loadInFrame('staff_overtime_request.php', 'Overtime')"><i class="bi bi-hourglass-split me-1"></i> Overtime</button>
        </div>
        </div>

        <?php if ($myAgentStats !== null): ?>
        <section class="staff-agent-panel" aria-labelledby="staffAgentPanelTitle">
          <div class="staff-agent-panel-head">
            <div>
              <h3 id="staffAgentPanelTitle"><i class="bi bi-diagram-3 me-1"></i> My allocated students</h3>
            </div>
            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" onclick="loadInFrame('agent-student-manage.php', 'My students')">
              <i class="bi bi-people me-1"></i> Manage students
            </button>
          </div>
          <div class="staff-agent-panel-body">
            <div class="row g-4 align-items-stretch">
              <div class="col-lg-5">
                <div class="staff-agent-chart-wrap">
                  <canvas id="staffAgentChart" aria-label="Your student pipeline chart"></canvas>
                </div>
              </div>
              <div class="col-lg-7">
                <div class="table-responsive staff-agent-table-wrap">
                  <table id="staffAgentTable" class="table table-hover align-middle mb-0">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Your name</th>
                        <th>Agent email</th>
                        <th>Allocated</th>
                        <th>Submitted</th>
                        <th>Admit</th>
                        <th>Visa OK</th>
                        <th>Enrolled</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>1</td>
                        <td><strong><?= htmlspecialchars((string) ($myAgentStats['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><span class="staff-agent-email-badge"><?= htmlspecialchars((string) ($myAgentStats['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= (int) ($myAgentStats['total'] ?? 0) ?></td>
                        <td><?= (int) ($myAgentStats['submitted'] ?? 0) ?></td>
                        <td><?= (int) ($myAgentStats['admit'] ?? 0) ?></td>
                        <td><?= (int) ($myAgentStats['visa_approved'] ?? 0) ?></td>
                        <td><?= (int) ($myAgentStats['enrolled'] ?? 0) ?></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </section>
        <?php else: ?>
        <section class="staff-agent-panel mb-4" aria-label="Allocated students unavailable">
          <div class="staff-agent-panel-head">
            <div>
              <h3><i class="bi bi-person-exclamation me-1"></i> Allocated students</h3>
            </div>
          </div>
        </section>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($isSuperExecutive): ?>
        <!-- Hero Stats (superadmin only) -->
        <section class="dashboard-hero mb-4">
          <div class="row g-3">
            <div class="col-xl-3 col-md-6">
              <div class="hero-card">
                <div class="hero-meta">Total Applications</div>
                <div class="hero-value"><?= $totalApplications ?></div>
                <i class="bi bi-stack hero-icon"></i>
              </div>
            </div>

            <div class="col-xl-3 col-md-6">
              <div class="hero-card success">
                <div class="hero-meta">Submitted</div>
                <div class="hero-value"><?= $flagCounts['submitted'] ?? 0 ?></div>
                <i class="bi bi-check2-circle hero-icon"></i>
              </div>
            </div>

            <div class="col-xl-3 col-md-6">
              <div class="hero-card info">
                <div class="hero-meta">Admitted</div>
                <div class="hero-value"><?= $flagCounts['admit'] ?? 0 ?></div>
                <i class="bi bi-mortarboard hero-icon"></i>
              </div>
            </div>

            <div class="col-xl-3 col-md-6">
              <div class="hero-card warning">
                <div class="hero-meta">Visa Approved</div>
                <div class="hero-value"><?= $flagCounts['visa_approved'] ?? 0 ?></div>
                <i class="bi bi-passport hero-icon"></i>
              </div>
            </div>
          </div>
        </section>
        <?php endif; ?>
        
        <!-- Application Flag Summary + Payments (superadmin only) -->
        <?php if ($isSuperExecutive): ?>
          <div class="summary-card">
            <div class="summary-header">
              <div class="summary-title">
                <span class="summary-icon"><i class="bi bi-bar-chart"></i></span>
                <span>Students Applications Summary</span>
              </div>
            </div>
            <div class="summary-body">
              <div class="flag-buttons">
                <?php foreach ($flagMap as $key => $label): ?>
                  <a class="flag-btn" href="#" onclick="loadInFrame('view-applicants.php?flag=<?= $key ?>', '<?= $label ?> Applicants')">
                    <?= $label ?>
                    <span class="flag-count"><?= (int)($flagCounts[$key] ?? 0) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          
          <!-- Payment Dashboard -->
          <div class="payment-dashboard mt-4" id="paymentDashboard">
            <div class="pay-dash-header">
              <h3><i class="bi bi-credit-card-2-front me-2"></i>Payments Overview</h3>
              <div class="pay-dash-meta" id="pay-dash-meta">
                <span class="pay-dash-badge ok">Active receipts only</span>
                <span class="pay-dash-badge muted">Loading…</span>
              </div>
            </div>

            <div class="pay-currency-grid" id="payment-currency-cards">
              <div class="text-center py-4 text-muted col-span-all">Loading financial summary…</div>
            </div>

            <div class="payment-kpis" id="payment-kpis">
              <div class="text-center py-4 text-muted">Loading student status…</div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-lg-5">
                <div class="chart-container">
                  <h6 class="fw-bold mb-2">Students by payment status</h6>
                  <canvas id="paymentStatusChart" height="200"></canvas>
                </div>
              </div>
              <div class="col-lg-7">
                <div class="chart-container">
                  <h6 class="fw-bold mb-2">Collections by method &amp; currency</h6>
                  <canvas id="paymentMethodChart" height="200"></canvas>
                </div>
              </div>
            </div>

            <div class="pay-recent-wrap">
              <h6 class="fw-bold mb-3">Recent payments <span class="text-muted fw-normal">(latest active receipts)</span></h6>
              <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Amount</th>
                      <th>Method</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody id="recent-payments">
                    <tr>
                      <td colspan="4" class="text-center text-muted py-4">Loading payments…</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php endif; ?>
        
        <!-- Catholic University Summary -->
        <?php if (strtolower($role) === 'catholic university of america'): ?>
          <div class="summary-card mt-4">
            <div class="summary-header">
              <div class="summary-title">
                <span class="summary-icon"><i class="bi bi-mortarboard"></i></span>
                <span>Catholic University Flag Summary</span>
              </div>
            </div>
            <div class="summary-body">
              <div class="flag-buttons">
                <?php foreach ($flagMap as $key => $label): ?>
                  <a class="flag-btn" href="#" onclick="loadInFrame('view-applicants.php?flag=<?= $key ?>&university=1&region=1', 'Catholic University <?= $label ?>')">
                    <?= $label ?>
                    <span class="flag-count"><?= (int)($catholicFlagCounts[$key] ?? 0) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
        
        <!-- Agent Tracking (superadmin only) -->
        <?php if ($isSuperExecutive): ?>
          <div class="agent-tracking-card">
            <h3><i class="bi bi-person-lines-fill me-2"></i> Agent Tracking Summary</h3>
            <div class="border rounded-3 p-3 mb-3 bg-white shadow-sm">
              <div class="fw-semibold text-dark mb-1"><i class="bi bi-arrow-left-right me-1"></i> Change recruiting agent</div>
              <p class="small text-muted mb-2 mb-0">Search an applicant by name or email, pick a new agent from the roster (admin accounts), then save. Superadmin only.</p>
              <label for="pcvcStudentAgentSearch" class="form-label small mb-1 mt-2">Search student</label>
              <input type="search" id="pcvcStudentAgentSearch" class="form-control form-control-sm" maxlength="140" placeholder="Type at least 2 characters…" autocomplete="off">
              <div id="pcvcStudentAgentSearchResults" class="mt-2"></div>
            </div>
            <canvas id="agentChart" height="120" style="margin-bottom:20px;"></canvas>
            <div style="overflow-x: auto;">
              <table id="agentTable" class="display compact stripe" style="width: 100%;">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Agent Name</th>
                    <th>Email</th>
                    <th>Total Students</th>
                    <th>Submitted</th>
                    <th>Admit</th>
                    <th>Visa Approved</th>
                    <th>Enrolled</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $i = 1;
                  foreach ($agentsCombined as $agent) {
                      $agentName = htmlspecialchars((string) ($agent['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                      $agentEmailRaw = (string) ($agent['email'] ?? '');
                      $agentEmailEsc = htmlspecialchars($agentEmailRaw, ENT_QUOTES, 'UTF-8');
                      $agentNameAttr = htmlspecialchars((string) ($agent['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                      echo '<tr>
                      <td>' . (int) $i . '</td>
                      <td><button type="button" class="btn btn-link p-0 m-0 align-baseline text-start text-decoration-none fw-semibold js-agent-students" title="View students allocated to this agent" data-agent-email="' . $agentEmailEsc . '" data-agent-name="' . $agentNameAttr . '">' . $agentName . '</button></td>
                      <td>' . $agentEmailEsc . '</td>
                      <td>' . (int) ($agent['total'] ?? 0) . '</td>
                      <td>' . (int) ($agent['submitted'] ?? 0) . '</td>
                      <td>' . (int) ($agent['admit'] ?? 0) . '</td>
                      <td>' . (int) ($agent['visa_approved'] ?? 0) . '</td>
                      <td>' . (int) ($agent['enrolled'] ?? 0) . '</td>
                    </tr>';
                      $i++;
                  }
                  ?>
                </tbody>
              </table>
            </div>
            <script>
            window.pcvcAgentPickerOptions = <?= json_encode(array_values(array_map(static function ($row) {
                return [
                    'email' => (string) ($row['email'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                ];
            }, $agentsCombined)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
            </script>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- Content Frame for loaded pages -->
      <iframe name="content-frame" id="content-frame" title="Embedded page" scrolling="yes" style="display: none;"></iframe>
    </div>
  </div>
  
  <!-- MODALS SECTION -->
  
  <!-- Create Reminder Modal -->
  <div class="modal fade" id="createReminderModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i> Create Reminder</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="createReminderForm">
            <div class="mb-3">
              <label class="form-label">Title</label>
              <input type="text" class="form-control" name="title" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="3"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Remind Date</label>
              <input type="datetime-local" class="form-control" name="remind_date" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Audience</label>
              <select class="form-select" id="audienceSelect" name="audience">
                <option value="me">Only me</option>
                <option value="all">All users</option>
                <option value="role">By role</option>
                <option value="specific_admin">Specific admin</option>
                <option value="specific_email">Specific email</option>
              </select>
            </div>
            <div class="mb-3 d-none" id="audienceValueWrapper">
              <input type="text" class="form-control" id="audienceValue" name="audience_value" placeholder="Enter value">
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="saveReminder()">Save Reminder</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Agent allocated students (Agent Tracking Summary — superadmin) -->
  <div class="modal fade" id="agentStudentsModal" tabindex="-1" aria-labelledby="agentStudentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="agentStudentsModalLabel">Students by agent</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-2" id="agentStudentsModalSub"></p>
          <div id="agentStudentsModalAlert" class="alert alert-danger d-none py-2" role="alert"></div>
          <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Student</th>
                  <th>Email</th>
                  <th>Study</th>
                  <th>Status</th>
                  <th>Applied</th>
                </tr>
              </thead>
              <tbody id="agentStudentsModalBody">
                <tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-primary" id="agentStudentsOpenReportBtn">Open full report</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- System Settings Modal (for superadmin) -->
  <?php if ($isSuperExecutive): ?>
  <div class="modal fade" id="adminSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header ss-header">
          <h5 class="modal-title fw-bold">
            <i class="bi bi-gear-fill me-2"></i> System Settings
          </h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <ul class="nav nav-pills ss-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-universities" type="button">
                <i class="bi bi-bank me-1"></i> Universities
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-levels" type="button">
                <i class="bi bi-layers me-1"></i> Program Levels
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-programs" type="button">
                <i class="bi bi-journal-text me-1"></i> Programs
              </button>
            </li>
          </ul>
          <div class="tab-content">
            <!-- UNIVERSITIES TAB -->
            <div class="tab-pane fade show active" id="tab-universities">
              <?php
              $uniRows = [];
              $uniRegions = [];
              $q = "
                SELECT 
                  u.id,
                  u.name,
                  u.region_id,
                  u.country_id,
                  r.name AS region,
                  c.name AS country,
                  GROUP_CONCAT(
                    DISTINCT p.platform_name
                    ORDER BY p.platform_name
                    SEPARATOR ', '
                  ) AS platforms,
                  GROUP_CONCAT(
                    DISTINCT a.full_name
                    ORDER BY a.full_name
                    SEPARATOR ', '
                  ) AS admins_in_charge
                FROM universities u
                LEFT JOIN regions r ON r.id = u.region_id
                LEFT JOIN countries c ON c.id = u.country_id
                LEFT JOIN university_platforms up ON up.university_id = u.id
                LEFT JOIN platforms p ON p.id = up.platform_id AND p.status = 'Active'
                LEFT JOIN university_admins ua ON ua.university_id = u.id
                LEFT JOIN admins a ON a.id = ua.admin_id
                GROUP BY u.id, u.name, u.region_id, u.country_id, r.name, c.name
                ORDER BY u.name
              ";
              $res = mysqli_query($conn, $q);
              while ($row = mysqli_fetch_assoc($res)) {
                $uniRows[] = $row;
                $reg = trim((string) ($row['region'] ?? ''));
                if ($reg !== '') {
                  $uniRegions[$reg] = true;
                }
              }
              ksort($uniRegions);
              ?>
              <div class="ss-toolbar">
                <div class="ss-toolbar-title">
                  <h6>Universities</h6>
                  <small class="text-muted">Manage partner universities &amp; admins in charge</small>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center flex-grow-1 justify-content-end">
                  <div class="ss-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="search" id="uniSmartSearch" class="ss-search" placeholder="Search university, region, country, platform, admin…" autocomplete="off">
                    <button type="button" class="ss-search-clear" title="Clear">&times;</button>
                  </div>
                  <button class="btn btn-sm btn-success ss-btn-add" onclick="openUniversityModal()">
                    <i class="bi bi-plus-circle me-1"></i> Add University
                  </button>
                </div>
              </div>
              <div class="ss-filters">
                <button type="button" class="ss-chip active" data-filter="all">All</button>
                <button type="button" class="ss-chip" data-filter="has-admin">Has admin</button>
                <button type="button" class="ss-chip" data-filter="no-admin">No admin</button>
                <button type="button" class="ss-chip" data-filter="no-platform">No platform</button>
                <?php foreach (array_keys($uniRegions) as $regName): ?>
                  <button type="button" class="ss-chip" data-filter="region:<?= htmlspecialchars($regName) ?>"><?= htmlspecialchars($regName) ?></button>
                <?php endforeach; ?>
              </div>
              <div class="ss-meta" id="uniSmartMeta"></div>
              <div class="ss-table-wrap">
                <table class="table table-hover align-middle ss-table mb-0">
                  <thead>
                    <tr>
                      <th style="width:44px">#</th>
                      <th>University</th>
                      <th>Region / Country</th>
                      <th>Platform(s)</th>
                      <th>Admin(s) in charge</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $i = 1;
                    foreach ($uniRows as $row):
                      $platforms = trim((string) ($row['platforms'] ?? ''));
                      $admins = trim((string) ($row['admins_in_charge'] ?? ''));
                      $searchBlob = strtolower(implode(' ', [
                        $row['name'] ?? '',
                        $row['region'] ?? '',
                        $row['country'] ?? '',
                        $platforms,
                        $admins,
                      ]));
                      $hasAdmin = $admins !== '' ? '1' : '0';
                      $hasPlatform = $platforms !== '' ? '1' : '0';
                    ?>
                    <tr
                      data-uni-row
                      data-search="<?= htmlspecialchars($searchBlob) ?>"
                      data-region="<?= htmlspecialchars($row['region'] ?? '') ?>"
                      data-has-admin="<?= $hasAdmin ?>"
                      data-has-platform="<?= $hasPlatform ?>"
                    >
                      <td data-row-num><?= $i++ ?></td>
                      <td><div class="ss-uni-name"><?= htmlspecialchars($row['name']) ?></div></td>
                      <td>
                        <?php if (!empty($row['region'])): ?>
                          <span class="ss-badge ss-badge-region"><?= htmlspecialchars($row['region']) ?></span>
                        <?php endif; ?>
                        <div class="small text-muted mt-1"><?= htmlspecialchars($row['country'] ?? '—') ?></div>
                      </td>
                      <td>
                        <?php if ($platforms !== ''): ?>
                          <?php foreach (array_filter(array_map('trim', explode(',', $platforms))) as $plat): ?>
                            <span class="ss-badge ss-badge-platform"><?= htmlspecialchars($plat) ?></span>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <span class="ss-badge ss-badge-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($admins !== ''): ?>
                          <?php foreach (array_filter(array_map('trim', explode(',', $admins))) as $adm): ?>
                            <span class="ss-badge ss-badge-admin"><i class="bi bi-person-badge"></i> <?= htmlspecialchars($adm) ?></span>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <span class="ss-badge ss-badge-muted">Unassigned</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary ss-edit-btn" onclick='openUniversityModal({
                          id: <?= (int)$row["id"] ?>,
                          name: <?= json_encode($row["name"]) ?>,
                          region_id: <?= (int)$row["region_id"] ?>,
                          country_id: <?= (int)$row["country_id"] ?>
                        })' title="Edit">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-danger ss-edit-btn ms-1"
                          title="Delete university"
                          onclick='deleteUniversity(<?= (int)$row["id"] ?>, <?= json_encode($row["name"]) ?>)'
                        >
                          <i class="bi bi-trash"></i>
                        </button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <div class="ss-empty" id="uniSmartEmpty">
                  <i class="bi bi-search fs-3 d-block mb-2"></i>
                  No universities match your search
                </div>
              </div>
            </div>
            
            <!-- PROGRAM LEVELS TAB -->
            <div class="tab-pane fade" id="tab-levels">
              <div class="ss-toolbar">
                <div class="ss-toolbar-title">
                  <h6>Program Levels</h6>
                  <small class="text-muted">International study levels (e.g. BSc, MSc, PhD)</small>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center flex-grow-1 justify-content-end">
                  <div class="ss-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="search" id="levelSmartSearch" class="ss-search" placeholder="Search level code or name…" autocomplete="off">
                    <button type="button" class="ss-search-clear" title="Clear">&times;</button>
                  </div>
                  <button class="btn btn-sm btn-success ss-btn-add" onclick="openLevelModal()">
                    <i class="bi bi-plus-circle me-1"></i> Add Level
                  </button>
                </div>
              </div>
              <div class="ss-meta" id="levelSmartMeta"></div>
              <div class="ss-table-wrap">
                <table class="table table-hover align-middle ss-table mb-0">
                  <thead>
                    <tr>
                      <th style="width:44px">#</th>
                      <th>Code</th>
                      <th>Level Name</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $res = mysqli_query($conn, "SELECT * FROM program_levels ORDER BY id");
                    $i = 1;
                    while ($l = mysqli_fetch_assoc($res)):
                      $searchBlob = strtolower(($l['abbreviation'] ?? '') . ' ' . ($l['name'] ?? ''));
                    ?>
                    <tr data-level-row data-search="<?= htmlspecialchars($searchBlob) ?>">
                      <td data-row-num><?= $i++ ?></td>
                      <td><span class="ss-badge ss-badge-region"><?= htmlspecialchars($l['abbreviation']) ?></span></td>
                      <td class="fw-semibold"><?= htmlspecialchars($l['name']) ?></td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary ss-edit-btn" onclick='openLevelModal(<?= json_encode($l) ?>)'>
                          <i class="bi bi-pencil"></i>
                        </button>
                      </td>
                    </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
                <div class="ss-empty" id="levelSmartEmpty">
                  <i class="bi bi-search fs-3 d-block mb-2"></i>
                  No levels match your search
                </div>
              </div>
            </div>
            
            <!-- PROGRAMS TAB -->
            <div class="tab-pane fade" id="tab-programs">
              <div class="ss-toolbar">
                <div class="ss-toolbar-title">
                  <h6>Programs</h6>
                  <small class="text-muted">Programs linked to a University and Study Level</small>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center flex-grow-1 justify-content-end">
                  <div class="ss-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="search" id="programSearch" class="ss-search" placeholder="Search university, program, or level…" autocomplete="off">
                    <button type="button" class="ss-search-clear" title="Clear">&times;</button>
                  </div>
                  <button class="btn btn-sm btn-success ss-btn-add" onclick="openProgramModal()">
                    <i class="bi bi-plus-circle me-1"></i> Add Program
                  </button>
                </div>
              </div>
              <div class="ss-meta" id="programSmartMeta"></div>
              <?php
              $q = "
                SELECT
                  p.id,
                  p.program_name,
                  p.university_id,
                  p.program_level_id,
                  u.name AS university,
                  l.name AS level,
                  l.abbreviation AS level_code
                FROM programs p
                INNER JOIN universities u ON u.id = p.university_id
                INNER JOIN program_levels l ON l.id = p.program_level_id
                ORDER BY u.name ASC, l.name ASC, p.program_name ASC
              ";
              $res = mysqli_query($conn, $q);
              $tree = [];
              while ($row = mysqli_fetch_assoc($res)) {
                $u = $row['university'];
                $c = $row['level_code'];
                if (!isset($tree[$u][$c])) {
                  $tree[$u][$c] = [
                    'level_name' => $row['level'],
                    'programs'   => []
                  ];
                }
                $tree[$u][$c]['programs'][] = $row;
              }
              ?>
              <div class="container-fluid px-0">
                <?php if (!empty($tree)): ?>
                  <?php foreach ($tree as $university => $levels): ?>
                    <div class="card ss-prog-card mb-3" data-uni-card>
                      <div class="card-header">
                        <i class="bi bi-bank me-2"></i>
                        <?= htmlspecialchars($university) ?>
                      </div>
                      <div class="card-body py-3">
                        <?php foreach ($levels as $levelCode => $levelData): ?>
                          <div class="d-flex align-items-center mb-2 mt-3">
                            <span class="badge bg-dark me-2"><?= htmlspecialchars($levelCode) ?></span>
                            <span class="fw-semibold"><?= htmlspecialchars($levelData['level_name']) ?></span>
                            <span class="ms-2 text-muted small">(<?= count($levelData['programs']) ?> programs)</span>
                          </div>
                          <div class="list-group list-group-flush mb-2">
                            <?php foreach ($levelData['programs'] as $p): ?>
                              <div class="list-group-item d-flex justify-content-between align-items-center border-0 border-bottom py-2"
                                   data-program
                                   data-name="<?= htmlspecialchars(strtolower($p['program_name'])) ?>"
                                   data-university="<?= htmlspecialchars(strtolower($university)) ?>"
                                   data-level="<?= htmlspecialchars(strtolower($levelCode . ' ' . $levelData['level_name'])) ?>">
                                <div class="d-flex align-items-center">
                                  <i class="bi bi-dot fs-4 text-secondary me-2"></i>
                                  <div>
                                    <div class="fw-medium"><?= htmlspecialchars($p['program_name']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($university) ?></div>
                                  </div>
                                </div>
                                <div class="btn-group">
                                  <button class="btn btn-sm btn-outline-secondary" onclick='openProgramModal(<?= json_encode($p) ?>)' title="Edit">
                                    <i class="bi bi-pencil"></i>
                                  </button>
                                  <button class="btn btn-sm btn-outline-danger" onclick="deleteProgram(<?= (int)$p['id'] ?>)" title="Delete">
                                    <i class="bi bi-trash"></i>
                                  </button>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                  <div class="ss-empty" id="programSmartEmpty">
                    <i class="bi bi-search fs-3 d-block mb-2"></i>
                    No programs match your search
                  </div>
                <?php else: ?>
                  <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No programs added yet
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <small class="text-muted me-auto">
            <i class="bi bi-shield-lock me-1"></i> Superadmin only
          </small>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- University Modal -->
  <div class="modal fade" id="universityModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="uniModalTitle">Add University</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="universityForm">
          <input type="hidden" name="action" value="save_university">
          <input type="hidden" name="id" id="uni_id">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">University Name</label>
              <input type="text" class="form-control" name="name" id="uni_name" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Region</label>
              <select class="form-select" name="region_id" id="uni_region" required>
                <option value="">Select region</option>
                <?php
                $r = mysqli_query($conn, "SELECT id, name FROM regions ORDER BY name");
                while ($row = mysqli_fetch_assoc($r)) {
                  echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['name']).'</option>';
                }
                ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Country</label>
              <select class="form-select" name="country_id" id="uni_country" required>
                <option value="">Select country</option>
                <?php
                $c = mysqli_query($conn, "SELECT id, name FROM countries ORDER BY name");
                while ($row = mysqli_fetch_assoc($c)) {
                  echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['name']).'</option>';
                }
                ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Platforms <small class="text-muted">(multiple allowed)</small></label>
              <select class="form-select" name="platform_ids[]" id="uni_platforms" multiple>
                <?php
                $p = mysqli_query($conn, "SELECT id, platform_name FROM platforms WHERE status = 'Active' ORDER BY platform_name");
                while ($row = mysqli_fetch_assoc($p)) {
                  echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['platform_name']).'</option>';
                }
                ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Admin(s) in charge <small class="text-muted">(search &amp; multi-select)</small></label>
              <select class="form-select" name="admin_ids[]" id="uni_admins" multiple>
                <?php
                $adminsQ = mysqli_query(
                  $conn,
                  "SELECT id, full_name, email, role
                   FROM admins
                   WHERE LOWER(TRIM(COALESCE(role,''))) IN ('staff','superadmin','admin')
                   ORDER BY full_name"
                );
                while ($row = mysqli_fetch_assoc($adminsQ)) {
                  $label = trim((string) ($row['full_name'] ?? ''));
                  if ($label === '') {
                    $label = (string) ($row['email'] ?? 'Admin #' . $row['id']);
                  }
                  echo '<option value="'.(int)$row['id'].'">'
                    . htmlspecialchars($label . ' (' . ($row['email'] ?? '') . ')')
                    . '</option>';
                }
                ?>
              </select>
              <div class="form-text">Type to search staff, then tick names to assign.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" type="submit">Save University</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Level Modal -->
  <div class="modal fade" id="levelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="levelModalTitle">Add Level</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="levelForm">
          <input type="hidden" name="action" value="save_level">
          <input type="hidden" name="id" id="level_id">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Level Code</label>
              <input type="text" class="form-control" id="level_abbreviation" name="abbreviation" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Level Name</label>
              <input type="text" class="form-control" id="level_name" name="name" required>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Level</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Program Modal -->
  <div class="modal fade" id="programModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="programModalTitle"><i class="bi bi-journal-plus me-2"></i> Add Program(s)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">University</label>
            <select class="form-select" id="program_university" name="university_id" required>
              <option value="">Select university…</option>
              <?php
              $u = mysqli_query($conn, "SELECT id, name FROM universities ORDER BY name");
              while ($row = mysqli_fetch_assoc($u)) {
                echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['name']).'</option>';
              }
              ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Program Level</label>
            <select class="form-select" id="program_level" name="level_id" required>
              <option value="">Select level…</option>
              <?php
              $l = mysqli_query($conn, "SELECT id, name FROM program_levels ORDER BY name");
              while ($row = mysqli_fetch_assoc($l)) {
                echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['name']).'</option>';
              }
              ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Program Name(s) <small class="text-muted">(Press Enter to add multiple)</small></label>
            <input type="text" class="form-control" id="program_input" placeholder="e.g. Computer Science" autocomplete="off">
            <div class="mb-3">
              <label class="form-label">Input Mode</label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="program_mode" id="mode_manual" value="manual" checked>
                  <label class="form-check-label" for="mode_manual">Manual Entry</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="program_mode" id="mode_ai" value="ai">
                  <label class="form-check-label" for="mode_ai">Smart Paste (AI)</label>
                </div>
              </div>
            </div>
            <div id="program_list" class="d-flex flex-wrap gap-2 mt-2"></div>
          </div>
          <div class="mt-4">
            <label class="form-label">Smart Paste (AI) <small class="text-muted">Paste multiple programs (Word / PDF / website)</small></label>
            <textarea id="ai_program_text" class="form-control" rows="6" placeholder="Paste program list here…"></textarea>
            <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="analyzeProgramsBtn">🧠 Analyze & Prepare Programs</button>
            <div class="form-text">AI will extract and clean program names. You can review before saving.</div>
          </div>
          <div class="alert alert-light small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Programs will be created for the selected <strong>University</strong>.
            <span class="text-muted">(Smart Paste saves vocational/college programs as <strong>Professional Course in …</strong> automatically)</span>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="saveProgramsBtn">
            <i class="bi bi-save me-1"></i> Save Program(s)
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Payment List Modal -->
  <div class="modal fade" id="paymentListModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="paymentModalTitle">Payment Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Student Name</th>
                  <th>Email</th>
                  <th>Total Paid</th>
                  <th>Status</th>
                  <th>Last Payment</th>
                </tr>
              </thead>
              <tbody id="paymentModalBody">
                <!-- Data will be loaded here -->
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Toast Container -->
  <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

  <!-- JavaScript Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <!-- Settings JS -->
  <script src="settings.js"></script>
  <script src="assets/js/system-settings-search.js"></script>
  
  <script>
    // Main JavaScript functionality
    
    // Sidebar functionality
    function toggleMobileSidebar() {
      document.getElementById('sidebar').classList.toggle('show');
    }
    
    function toggleSidebarMenu(menuId) {
      const link = document.querySelector(`[href="#${menuId}"]`);
      const submenu = document.getElementById(`submenu_${menuId}`);
      
      // Close other submenus
      document.querySelectorAll('.sidebar-submenu').forEach(menu => {
        if (menu.id !== `submenu_${menuId}`) {
          menu.style.display = 'none';
          const otherLink = menu.previousElementSibling;
          if (otherLink && otherLink.classList.contains('sidebar-link')) {
            otherLink.classList.remove('active');
            const arrow = otherLink.querySelector('.arrow');
            if (arrow) arrow.style.transform = 'rotate(0deg)';
          }
        }
      });
      
      // Toggle current submenu
      if (submenu.style.display === 'block') {
        submenu.style.display = 'none';
        link.classList.remove('active');
        const arrow = link.querySelector('.arrow');
        if (arrow) arrow.style.transform = 'rotate(0deg)';
      } else {
        submenu.style.display = 'block';
        link.classList.add('active');
        const arrow = link.querySelector('.arrow');
        if (arrow) arrow.style.transform = 'rotate(180deg)';
      }
    }
    
    window.showDashboard = function showDashboard() {
      document.getElementById('dashboard-view').style.display = 'block';
      document.getElementById('content-frame').style.display = 'none';
      document.getElementById('pageTitle').textContent = 'Dashboard';
      
      // Update active state in sidebar
      document.querySelectorAll('.sidebar-link').forEach(link => {
        link.classList.remove('active');
        if (link.querySelector('.bi-speedometer2')) {
          link.classList.add('active');
        }
      });
      
      // Close all submenus
      document.querySelectorAll('.sidebar-submenu').forEach(menu => {
        menu.style.display = 'none';
        const link = menu.previousElementSibling;
        if (link && link.classList.contains('sidebar-link')) {
          link.classList.remove('active');
          const arrow = link.querySelector('.arrow');
          if (arrow) arrow.style.transform = 'rotate(0deg)';
        }
      });
      
      // Close sidebar on mobile
      if (window.innerWidth < 992) {
        document.getElementById('sidebar').classList.remove('show');
      }
    };
    
    window.loadInFrame = function loadInFrame(url, title) {
      const contentFrame = document.getElementById('content-frame');
      const dashboardView = document.getElementById('dashboard-view');
      
      // Show loading spinner
      document.getElementById('loadingSpinner').style.display = 'block';
      
      // Update iframe source
      contentFrame.src = url;
      
      // Show iframe and hide dashboard
      setTimeout(() => {
        contentFrame.style.display = 'block';
        dashboardView.style.display = 'none';
        document.getElementById('loadingSpinner').style.display = 'none';
        
        // Update page title
        document.getElementById('pageTitle').textContent = title || 'Content';
        
        // Update active state in sidebar
        document.querySelectorAll('.sidebar-submenu a').forEach(link => link.classList.remove('active'));
        
        // Close sidebar on mobile
        if (window.innerWidth < 992) {
          document.getElementById('sidebar').classList.remove('show');
        }
      }, 300);
      
      return false;
    };
    
    // Handle iframe load
    document.getElementById('content-frame').addEventListener('load', function() {
      // Add custom styles to loaded content if needed
      try {
        const iframeDoc = this.contentDocument || this.contentWindow.document;
        const style = iframeDoc.createElement('style');
        style.textContent = `
          body { font-family: 'Segoe UI', system-ui, sans-serif; }
          .container { max-width: 1200px; }
          .card { border-radius: 10px; }
          .btn-primary { background: linear-gradient(135deg, #427431, #3661B9); }
        `;
        iframeDoc.head.appendChild(style);
      } catch (e) {
        // Cross-origin restriction, ignore
      }
    });
    
    // Save reminder
    async function saveReminder() {
      const form = document.getElementById('createReminderForm');
      const formData = new FormData(form);
      
      try {
        const response = await fetch('reminders/save_reminder.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        
        if (data.ok) {
          showToast('Reminder saved', 'Your reminder has been scheduled.', 'success');
          bootstrap.Modal.getInstance(document.getElementById('createReminderModal')).hide();
          form.reset();
          document.getElementById('audienceValueWrapper').classList.add('d-none');
        } else {
          showToast('Failed', data.msg || 'Could not save reminder', 'error');
        }
      } catch (error) {
        showToast('Error', 'Network/server error', 'error');
      }
    }
    
    // Toast notification
    function showToast(title, message, type = 'info') {
      const container = document.getElementById('toastContainer');
      const toast = document.createElement('div');
      
      toast.className = `toast align-items-center text-white bg-${type} border-0`;
      toast.setAttribute('role', 'alert');
      toast.setAttribute('aria-live', 'assertive');
      toast.setAttribute('aria-atomic', 'true');
      
      toast.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">
            <strong>${title}</strong><br>
            ${message}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      `;
      
      container.appendChild(toast);
      const bsToast = new bootstrap.Toast(toast, {
        delay: 5000
      });
      bsToast.show();
      
      toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
      });
    }
    
    // Notifications functionality
    async function loadNotifications() {
      try {
        const response = await fetch('reminders/fetch_notifications.php');
        const data = await response.json();
        
        const badge = document.getElementById('notifBadge');
        const list = document.getElementById('notifItems');
        
        if (data.unread > 0) {
          badge.textContent = data.unread;
          badge.classList.remove('d-none');
        } else {
          badge.classList.add('d-none');
        }
        
        list.innerHTML = '';
        (data.items||[]).forEach(n=>{
          const li = document.createElement('div');
          li.className = 'dropdown-item ' + (n.is_read ? 'notif-item-read' : 'notif-item-unread');
          li.innerHTML = `
            <div class="d-flex flex-column">
              <div class="fw-semibold">${n.title}</div>
              <div class="small text-muted">${n.created_at ?? ''}</div>
              <div class="small">${n.body || ''}</div>
              <div class="text-end mt-1">
                <button class="btn btn-sm btn-link p-0 markRead" data-id="${n.id}">Mark read</button>
                ${n.link_url?`<a class="btn btn-sm btn-link p-0 ms-2" href="${n.link_url}">Open</a>`:''}
              </div>
            </div>`;
          list.appendChild(li);
        });
      } catch(e){ /* silent */ }
    }
    
    // Mark notification as read
    document.addEventListener('click', async (e) => {
      if (e.target.classList.contains('markRead')) {
        const id = e.target.dataset.id;
        try {
          await fetch('reminders/mark_read.php', {
            method: 'POST',
            body: new URLSearchParams({id}),
            credentials: 'same-origin'
          });
          loadNotifications();
        } catch (error) {
          console.error('Failed to mark notification as read:', error);
        }
      }
    });
    
    // Payment dashboard functionality
    document.addEventListener('DOMContentLoaded', async () => {
      const dashboard = document.getElementById('paymentDashboard');
      if (!dashboard) return;

      const fmtMoney = (amount, currency) => {
        const n = Number(amount || 0).toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
        return currency ? `${currency} ${n}` : n;
      };

      const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

      try {
        const res = await fetch('payment_dashboard_stats.php', { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (data.error) throw new Error(data.message || 'Stats unavailable');

        const metaEl = document.getElementById('pay-dash-meta');
        if (metaEl) {
          const active = Number(data.active_receipts || 0);
          const cancelled = Number(data.cancelled_receipts || 0);
          metaEl.innerHTML = `
            <span class="pay-dash-badge ok">${active} active receipt${active === 1 ? '' : 's'}</span>
            ${cancelled > 0 ? `<span class="pay-dash-badge muted">${cancelled} cancelled hidden</span>` : ''}
          `;
        }

        const currencyWrap = document.getElementById('payment-currency-cards');
        const byCur = Array.isArray(data.by_currency) ? data.by_currency : [];
        if (currencyWrap) {
          currencyWrap.innerHTML = byCur.length
            ? byCur.map(c => `
              <div class="pay-currency-card">
                <div class="pay-currency-code">${esc(c.currency)}</div>
                <div class="pay-currency-row"><span>Expected</span><strong>${fmtMoney(c.expected, '')}</strong></div>
                <div class="pay-currency-row"><span>Collected</span><strong>${fmtMoney(c.collected, '')}</strong></div>
                <div class="pay-currency-row out"><span>Outstanding</span><strong>${fmtMoney(c.outstanding, '')}</strong></div>
              </div>
            `).join('')
            : `<div class="text-muted py-3">No package revenue data yet.</div>`;
        }

        const statusKpis = [
          { title: 'Fully Paid', value: data.status?.fully_paid ?? 0, icon: 'bi-check2-circle', color: 'success', status: 'fully_paid' },
          { title: 'Partial Paid', value: data.status?.partial_paid ?? 0, icon: 'bi-hourglass-split', color: 'info', status: 'partial_paid' },
          { title: 'Unpaid', value: data.status?.unpaid ?? 0, icon: 'bi-x-circle', color: 'danger', status: 'unpaid' },
          { title: 'Need Attention', value: (data.status?.partial_paid ?? 0) + (data.status?.unpaid ?? 0), icon: 'bi-exclamation-circle', color: 'warning', status: 'outstanding', hint: 'Students with balance — click to list' }
        ];

        const kpiWrap = document.getElementById('payment-kpis');
        if (kpiWrap) {
          kpiWrap.innerHTML = statusKpis.map(k => `
            <div class="payment-kpi-card" data-status="${k.status}" title="${k.hint || 'Click for student list'}">
              <div class="payment-kpi-icon text-${k.color}"><i class="bi ${k.icon}"></i></div>
              <div class="payment-kpi-value">${esc(k.value)}</div>
              <div class="payment-kpi-title">${esc(k.title)}</div>
            </div>
          `).join('');
        }

        const statusTotal = (data.status?.fully_paid || 0) + (data.status?.partial_paid || 0) + (data.status?.unpaid || 0);
        const statusCanvas = document.getElementById('paymentStatusChart');
        if (statusCanvas) {
          const sData = [
            data.status?.fully_paid || 0,
            data.status?.partial_paid || 0,
            data.status?.unpaid || 0
          ];
          new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
              labels: ['Fully Paid', 'Partial Paid', 'Unpaid'],
              datasets: [{
                data: sData,
                backgroundColor: ['#427431', '#3661B9', '#dc3545'],
                borderWidth: 2,
                borderColor: '#fff'
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: true,
              cutout: '58%',
              plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14, font: { size: 11 } } },
                tooltip: {
                  callbacks: {
                    label: (ctx) => {
                      const v = ctx.raw || 0;
                      const pct = statusTotal ? Math.round((v / statusTotal) * 100) : 0;
                      return ` ${ctx.label}: ${v} (${pct}%)`;
                    }
                  }
                }
              }
            }
          });
        }

        const methodCanvas = document.getElementById('paymentMethodChart');
        if (methodCanvas) {
          const chartRows = Array.isArray(data.methods_chart) && data.methods_chart.length
            ? data.methods_chart
            : Object.entries(data.methods || {}).map(([method, total]) => ({ label: method, total }));
          const labels = chartRows.map(r => r.label || r.method);
          const values = chartRows.map(r => Number(r.total || 0));
          new Chart(methodCanvas, {
            type: 'bar',
            data: {
              labels,
              datasets: [{
                label: 'Collected',
                data: values,
                backgroundColor: ['#427431', '#0b3c5d', '#3661B9', '#64748b', '#b45309'].slice(0, labels.length),
                borderRadius: 6,
                maxBarThickness: 48
              }]
            },
            options: {
              responsive: true,
              indexAxis: labels.length > 4 ? 'y' : 'x',
              plugins: {
                legend: { display: false },
                tooltip: {
                  callbacks: {
                    label: (ctx) => ' ' + Number(ctx.raw || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                  }
                }
              },
              scales: {
                x: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                y: { beginAtZero: true, grid: { display: false } }
              }
            }
          });
        }

        const recentBody = document.getElementById('recent-payments');
        if (recentBody) {
          recentBody.innerHTML = (data.recent && data.recent.length)
            ? data.recent.map(p => {
                const cur = (p.currency || '').trim();
                const amt = Number(p.amount_paid || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const method = (p.payment_method || '—').toLowerCase();
                const methodClass = method.includes('mopay') ? 'bg-primary' : method.includes('bank') ? 'bg-info text-dark' : 'bg-secondary';
                return `
                  <tr>
                    <td class="fw-semibold">${esc(p.student && p.student.trim() ? p.student : '—')}</td>
                    <td>${cur ? `<span class="pay-currency-pill">${esc(cur)}</span>` : ''}<span class="fw-bold text-success">${amt}</span></td>
                    <td><span class="badge ${methodClass}">${esc(p.payment_method || '—')}</span></td>
                    <td class="text-muted small">${p.paid_at ? new Date(p.paid_at).toLocaleString() : '—'}</td>
                  </tr>`;
              }).join('')
            : `<tr><td colspan="4" class="text-center text-muted py-4">No active receipts yet</td></tr>`;
        }

      } catch (err) {
        console.error('Payment dashboard failed:', err);
        dashboard.innerHTML = `
          <div class="alert alert-danger mb-0">
            <strong>Payment dashboard error.</strong><br>${esc(err.message || 'Unknown error')}
          </div>`;
      }
    });
    
    // Payment KPI click handler
    document.addEventListener('click', async (e) => {
      const card = e.target.closest('.payment-kpi-card[data-status]');
      if (!card) return;

      const status = card.dataset.status;
      const titles = {
        outstanding: 'Students With Outstanding Balance',
        fully_paid: 'Fully Paid Students',
        partial_paid: 'Partial Paid Students',
        unpaid: 'Unpaid Students'
      };

      document.getElementById('paymentModalTitle').textContent = titles[status];
      const modal = new bootstrap.Modal(document.getElementById('paymentListModal'));
      modal.show();

      const body = document.getElementById('paymentModalBody');
      body.innerHTML = `<tr><td colspan="5" class="text-center">Loading…</td></tr>`;

      try {
        const res = await fetch(`payment_dashboard_stats.php?status=${status}`, {
          credentials: 'same-origin'
        });
        const data = await res.json();

        body.innerHTML = data.length
          ? data.map(s => `
            <tr>
              <td>${s.student_name}</td>
              <td>${s.email ?? '—'}</td>
              <td class="fw-semibold">${Number(s.total_paid).toLocaleString()}</td>
              <td><span class="badge bg-${s.status === 'fully_paid' ? 'success' : s.status === 'partial_paid' ? 'info' : 'danger'}">${s.status.replace('_',' ')}</span></td>
              <td>${s.last_payment ?? '—'}</td>
            </tr>
          `).join('')
          : `<tr><td colspan="5" class="text-center text-muted">No students</td></tr>`;

      } catch (err) {
        body.innerHTML = `<tr><td colspan="5" class="text-danger text-center">Failed to load</td></tr>`;
      }
    });
    
    window.pcvcAppStatusLabels = <?= json_encode($isSuperExecutive ? pcvc_application_status_labels() : [], JSON_UNESCAPED_UNICODE) ?>;

    function pcvcEscapeHtml(s) {
      return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function pcvcEscAttr(s) {
      return String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    window.pcvcOpenAgentStudentsModal = async function (agentEmail, agentName) {
      const modalEl = document.getElementById('agentStudentsModal');
      const titleEl = document.getElementById('agentStudentsModalLabel');
      const subEl = document.getElementById('agentStudentsModalSub');
      const bodyEl = document.getElementById('agentStudentsModalBody');
      const alertEl = document.getElementById('agentStudentsModalAlert');
      const reportBtn = document.getElementById('agentStudentsOpenReportBtn');
      if (!modalEl || !titleEl || !subEl || !bodyEl) return;

      const email = String(agentEmail || '').trim();
      const name = String(agentName || '').trim();
      if (!email) return;

      alertEl.classList.add('d-none');
      alertEl.textContent = '';
      titleEl.textContent = 'Students — ' + (name || email);
      subEl.textContent = 'Agent email: ' + email + ' · ScholarSync MIS applications (with documents on file).';
      bodyEl.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr>';

      if (reportBtn) {
        reportBtn.onclick = function () {
          if (typeof window.loadInFrame === 'function') {
            const u = 'application-list.php?agent_email=' + encodeURIComponent(email);
            window.loadInFrame(u, 'Agent students — ' + (name || email));
          }
          const inst = bootstrap.Modal.getInstance(modalEl);
          if (inst) inst.hide();
        };
      }

      const modal = (typeof bootstrap.Modal.getOrCreateInstance === 'function')
        ? bootstrap.Modal.getOrCreateInstance(modalEl)
        : (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl));
      modal.show();

      try {
        const params = new URLSearchParams({ action: 'list', agent_email: email, q: '' });
        const res = await fetch('api/applications.php?' + params.toString(), { credentials: 'same-origin' });
        const json = await res.json();
        if (!json || !json.success || !Array.isArray(json.data)) {
          throw new Error(json && json.message ? String(json.message) : 'Could not load applications.');
        }
        const rows = json.data;
        const labels = window.pcvcAppStatusLabels || {};
        if (!rows.length) {
          bodyEl.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No applications found for this agent.</td></tr>';
          return;
        }
        bodyEl.innerHTML = rows.map(function (app) {
          const bio = app.bio || {};
          const meta = app.meta || {};
          const study = app.study || {};
          const nm = pcvcEscapeHtml(((bio.first_name || '') + ' ' + (bio.last_name || '')).trim()) || '—';
          const em = pcvcEscapeHtml(bio.email || '') || '—';
          const studyLine = [study.universities, study.regions, study.countries].filter(Boolean).join(' · ');
          const inst = pcvcEscapeHtml(studyLine) || '—';
          const eff = meta.effective_status ? String(meta.effective_status) : '';
          const st = eff ? pcvcEscapeHtml(labels[eff] || eff) : '—';
          let applied = '—';
          if (meta.created_at) {
            const d = String(meta.created_at).slice(0, 10);
            applied = pcvcEscapeHtml(d);
          }
          return '<tr><td>' + nm + '</td><td>' + em + '</td><td class="small">' + inst + '</td><td class="small">' + st + '</td><td class="text-nowrap small">' + applied + '</td></tr>';
        }).join('');
      } catch (err) {
        console.error(err);
        bodyEl.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">—</td></tr>';
        alertEl.textContent = err && err.message ? err.message : 'Failed to load.';
        alertEl.classList.remove('d-none');
      }
    };

    // Initialize DataTable
    $(document).ready(function() {
      if ($('#agentTable').length) {
        $('#agentTable').DataTable({
          pageLength: 10,
          lengthChange: false,
          order: [[3, 'desc']],
          language: {
            emptyTable: "No agents yet.",
            search: "Search agent:",
            paginate: {
              previous: "Prev",
              next: "Next"
            }
          }
        });
        $('#agentTable').on('click', '.js-agent-students', function (e) {
          e.preventDefault();
          e.stopPropagation();
          const em = ($(this).attr('data-agent-email') || '').trim();
          const nm = ($(this).attr('data-agent-name') || '').trim();
          if (typeof window.pcvcOpenAgentStudentsModal === 'function') {
            window.pcvcOpenAgentStudentsModal(em, nm);
          }
        });
      }
      if ($('#staffAgentTable').length) {
        $('#staffAgentTable').DataTable({
          paging: false,
          searching: false,
          info: false,
          ordering: false,
          language: { emptyTable: 'No allocation data for your account.' }
        });
      }

      (function pcvcInitStudentAgentReassign() {
        const searchEl = document.getElementById('pcvcStudentAgentSearch');
        const resultsEl = document.getElementById('pcvcStudentAgentSearchResults');
        if (!searchEl || !resultsEl) {
          return;
        }
        let deb;
        searchEl.addEventListener('input', function () {
          clearTimeout(deb);
          const q = searchEl.value.trim();
          if (q.length < 2) {
            resultsEl.innerHTML = '';
            return;
          }
          deb = setTimeout(pcvcRunStudentAgentSearch, 380);
        });

        async function pcvcRunStudentAgentSearch() {
          const q = searchEl.value.trim();
          if (q.length < 2) {
            return;
          }
          resultsEl.innerHTML = '<p class="text-muted small mb-0">Searching…</p>';
          try {
            const url = 'api/applications.php?action=search_students_for_agent&q=' + encodeURIComponent(q);
            const res = await fetch(url, { credentials: 'same-origin' });
            const json = await res.json();
            if (!json || !json.success) {
              resultsEl.innerHTML = '<p class="text-danger small">' + pcvcEscapeHtml((json && json.message) ? String(json.message) : 'Search failed.') + '</p>';
              return;
            }
            const list = Array.isArray(json.data && json.data.students) ? json.data.students : [];
            if (!list.length) {
              resultsEl.innerHTML = '<p class="text-muted small mb-0">No matches.</p>';
              return;
            }
            const agents = Array.isArray(window.pcvcAgentPickerOptions) ? window.pcvcAgentPickerOptions : [];
            const rows = list.map(function (s) {
              const id = Number(s.id || 0);
              const nm = pcvcEscapeHtml(((s.first_name || '') + ' ' + (s.last_name || '')).trim() || '—');
              const em = pcvcEscapeHtml(s.email || '');
              const ref = pcvcEscapeHtml(String(s.application_id != null && s.application_id !== '' ? s.application_id : '—'));
              const cur = pcvcEscapeHtml(s.current_agent_label || '—');
              const curEm = String(s.current_agent_email || '').trim().toLowerCase();
              let opts = '<option value="">Select new agent…</option>';
              agents.forEach(function (a) {
                if (!a || !a.email) {
                  return;
                }
                const ae = String(a.email).trim();
                const sel = curEm && ae.toLowerCase() === curEm ? ' selected' : '';
                const lab = pcvcEscapeHtml(a.name || ae) + ' (' + pcvcEscapeHtml(ae) + ')';
                opts += '<option value="' + pcvcEscAttr(ae) + '"' + sel + '>' + lab + '</option>';
              });
              return '<tr data-app-id="' + id + '"><td class="align-middle">' + nm + '</td><td class="align-middle">' + em + '</td><td class="align-middle">' + ref + '</td><td class="align-middle small">' + cur + '</td><td class="align-middle" style="min-width:220px"><select class="form-select form-select-sm js-pcvc-new-agent">' + opts + '</select></td><td class="align-middle"><button type="button" class="btn btn-sm btn-primary js-pcvc-save-agent">Save</button></td></tr>';
            }).join('');
            resultsEl.innerHTML = '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr><th>Student</th><th>Email</th><th>App ref</th><th>Current agent</th><th>New agent</th><th></th></tr></thead><tbody>' + rows + '</tbody></table></div>';

            resultsEl.querySelectorAll('.js-pcvc-save-agent').forEach(function (btn) {
              btn.addEventListener('click', async function () {
                const tr = btn.closest('tr');
                if (!tr) {
                  return;
                }
                const appId = parseInt(tr.getAttribute('data-app-id'), 10);
                const sel = tr.querySelector('.js-pcvc-new-agent');
                const val = sel ? String(sel.value || '').trim() : '';
                if (!appId || !val) {
                  alert('Choose a new agent from the list first.');
                  return;
                }
                btn.disabled = true;
                const prev = btn.textContent;
                btn.textContent = 'Saving…';
                try {
                  const fd = new FormData();
                  fd.append('application_id', String(appId));
                  fd.append('agent_email', val);
                  const res2 = await fetch('api/applications.php?action=update_recruiting_agent', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                  });
                  const raw = await res2.text();
                  let j2;
                  try {
                    j2 = JSON.parse(raw);
                  } catch (e2) {
                    throw new Error('Invalid server response');
                  }
                  if (!j2 || !j2.success) {
                    throw new Error((j2 && j2.message) ? String(j2.message) : 'Save failed');
                  }
                  btn.textContent = 'Saved';
                  btn.classList.remove('btn-primary');
                  btn.classList.add('btn-success');
                  setTimeout(function () {
                    pcvcRunStudentAgentSearch();
                  }, 600);
                } catch (err) {
                  console.error(err);
                  alert(err && err.message ? err.message : 'Save failed');
                  btn.textContent = prev;
                  btn.disabled = false;
                }
              });
            });
          } catch (e) {
            console.error(e);
            resultsEl.innerHTML = '<p class="text-danger small">Network error.</p>';
          }
        }
      })();
    });
    
    // Initialize Agent Chart
    document.addEventListener('DOMContentLoaded', function() {
      const agentChartEl = document.getElementById('agentChart');
      if (agentChartEl) {
        const ctx = agentChartEl.getContext('2d');
        const agentChart = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: <?= json_encode($chart_labels ?? []) ?>,
            datasets: [{
              label: 'Total Students per Agent',
              data: <?= json_encode($chart_data ?? []) ?>,
              backgroundColor: 'rgba(1, 47, 107, 0.6)',
              borderColor: 'rgba(1, 47, 107, 1)',
              borderWidth: 1
            }]
          },
          options: {
            responsive: true,
            scales: {
              y: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
              }
            }
          }
        });
      }

      const staffAgentChartEl = document.getElementById('staffAgentChart');
      if (staffAgentChartEl && typeof Chart !== 'undefined') {
        const staffLbl = <?= json_encode($staffAgentChartLabels ?? []) ?>;
        const staffDat = <?= json_encode($staffAgentChartData ?? []) ?>;
        new Chart(staffAgentChartEl.getContext('2d'), {
          type: 'bar',
          data: {
            labels: staffLbl,
            datasets: [{
              label: 'Students',
              data: staffDat,
              backgroundColor: [
                'rgba(66, 116, 49, 0.88)',
                'rgba(54, 97, 185, 0.82)',
                'rgba(14, 165, 233, 0.78)',
                'rgba(245, 158, 11, 0.88)',
                'rgba(13, 159, 110, 0.88)'
              ],
              borderRadius: 10,
              borderSkipped: false
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 1.1,
            plugins: {
              legend: { display: false },
              tooltip: {
                mode: 'index',
                intersect: false
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: { precision: 0, stepSize: 1 }
              },
              x: {
                grid: { display: false },
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 11 } }
              }
            }
          }
        });
      }
    });
    
    // Load notifications on page load
    loadNotifications();
    setInterval(loadNotifications, 30000);
    
    // Reminder audience select handler
    document.addEventListener('DOMContentLoaded', () => {
      const audienceSelect = document.getElementById('audienceSelect');
      const audienceValue = document.getElementById('audienceValueWrapper');
      if (audienceSelect) {
        audienceSelect.addEventListener('change', () => {
          const v = audienceSelect.value;
          if (v === 'me') {
            audienceValue.classList.add('d-none');
            document.getElementById('audienceValue').value = '';
          } else {
            audienceValue.classList.remove('d-none');
            const placeholder = v === 'role' ? 'e.g. superadmin, staff, agent' :
                              v === 'specific_admin' ? 'admin_id (number)' :
                              'user@example.com';
            document.getElementById('audienceValue').placeholder = placeholder;
          }
        });
      }
    });
    
    // Program search handled by assets/js/system-settings-search.js
    
    // Modal functions from settings.js (openUniversityModal / openLevelModal / openProgramModal)
    
    // Delete program function
    async function deleteProgram(id) {
      if (!confirm('Delete this program?')) return;

      try {
        const res = await fetch('settings_actions.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            action: 'delete_program',
            id
          })
        });

        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'Delete failed');

        showToast('Deleted', 'Program removed successfully', false, 'success');

        document.querySelectorAll(`[onclick*="deleteProgram(${id})"]`)
          .forEach(btn => btn.closest('[data-program]')?.remove());

      } catch (e) {
        showToast('Error', e.message, true);
      }
    }
    
    // Add program chip function
    window.addProgramChip = function (programName) {
      if (!programName) return;

      const list = document.getElementById('program_list');
      if (!list) {
        console.error('[AI-UI] program_list container not found');
        return;
      }

      const existing = Array.from(list.querySelectorAll('[data-program]'))
        .some(el => el.dataset.program.toLowerCase() === programName.toLowerCase());

      if (existing) return;

      const chip = document.createElement('span');
      chip.className = 'badge bg-primary-subtle text-primary border px-2 py-1 d-flex align-items-center gap-2';
      chip.dataset.program = programName;

      chip.innerHTML = `
        <span class="fw-normal">${programName}</span>
        <button type="button"
                class="btn-close btn-close-sm"
                aria-label="Remove"
                style="font-size: 0.6rem;">
        </button>
      `;

      chip.querySelector('.btn-close').addEventListener('click', () => {
        chip.remove();
      });

      list.appendChild(chip);
    };
    
    // Program mode toggle
    document.addEventListener('DOMContentLoaded', () => {
      const modeManual = document.getElementById('mode_manual');
      const modeAI = document.getElementById('mode_ai');
      const university = document.getElementById('program_university');
      const level = document.getElementById('program_level');
      const manualInput = document.getElementById('program_input');
      const aiBox = document.getElementById('ai_program_text');
      const aiBtn = document.getElementById('analyzeProgramsBtn');
      
      function setMode(mode) {
        if (mode === 'manual') {
          level.disabled = false;
          level.required = true;
          manualInput.disabled = false;
          aiBox.disabled = true;
          aiBtn.disabled = true;
          aiBox.value = '';
        } else {
          level.disabled = true;
          level.required = false;
          level.value = '';
          manualInput.disabled = true;
          manualInput.value = '';
          aiBox.disabled = false;
          aiBtn.disabled = false;
        }
      }
      
      if (modeManual && modeAI) {
        setMode('manual');
        modeManual.addEventListener('change', () => setMode('manual'));
        modeAI.addEventListener('change', () => setMode('ai'));
      }
    });
    
    // AI program parser
    document.addEventListener('DOMContentLoaded', () => {
      const analyzeBtn = document.getElementById('analyzeProgramsBtn');
      if (analyzeBtn) {
        analyzeBtn.addEventListener('click', async function() {
          const textarea = document.getElementById('ai_program_text');
          if (!textarea) return;

          const text = textarea.value.trim();
          if (!text) {
            alert('Paste program list first');
            return;
          }

          const universityEl = document.getElementById('program_university');
          const university = universityEl ? universityEl.value : '';

          if (!university) {
            alert('Please select a University first.');
            return;
          }

          const btn = this;
          btn.disabled = true;
          btn.textContent = 'Analyzing…';

          try {
            const response = await fetch('ai_parse_programs.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              credentials: 'same-origin',
              body: JSON.stringify({
                text: text,
                university_id: university
              })
            });

            const data = await response.json();

            if (data.error) {
              throw new Error(data.error);
            }

            if (!Array.isArray(data.programs)) {
              throw new Error('Invalid response format: programs[] missing');
            }

            if (data.programs.length === 0) {
              alert('AI found no programs.');
              return;
            }

            // Add chips
            data.programs.forEach(function (name) {
              if (typeof window.addProgramChip === 'function') {
                window.addProgramChip(name);
              }
            });

            textarea.value = '';

            // Show success toast
            showToast('AI Completed', data.programs.length + ' programs extracted', 'success');

          } catch (err) {
            console.error('[AI-JS] ERROR:', err);
            showToast('AI Analysis Failed', err.message, 'error');
          } finally {
            btn.disabled = false;
            btn.textContent = '🧠 Analyze & Prepare Programs';
          }
        });
      }
    });
    
    // University program counts
    document.addEventListener('DOMContentLoaded', () => {
      const modal = document.getElementById('programModal');
      if (!modal) return;

      modal.addEventListener('shown.bs.modal', async () => {
        const select = document.getElementById('program_university');
        if (!select) return;

        // Prevent duplicate processing
        if (select.dataset.countsLoaded === '1') return;

        try {
          const res = await fetch('university_program_counts.php', {
            credentials: 'same-origin'
          });

          if (!res.ok) {
            console.error('Program count API HTTP error:', res.status);
            return;
          }

          const json = await res.json();
          if (!json.ok || !json.counts) return;

          const counts = json.counts;

          Array.from(select.options).forEach(opt => {
            const id = opt.value;
            if (!id || !counts[id]) return;

            const count = counts[id];

            // Clean text
            opt.textContent = opt.textContent
              .replace(/^●\s*/, '')
              .replace(/\s—\s\d+\sprogram(s)?$/i, '');

            // Add count indicator
            opt.textContent = `● ${opt.textContent} — ${count} program${count > 1 ? 's' : ''}`;

            // Add classes and attributes
            opt.classList.add('option-has-programs');
            opt.dataset.programCount = count;

            // Color tiers
            if (count >= 20) {
              opt.style.color = '#dc3545';
              opt.style.fontWeight = '700';
              opt.dataset.programCountHigh = '1';
            } else {
              opt.style.color = '#427431';
              opt.style.fontWeight = '600';
            }
          });

          select.dataset.countsLoaded = '1';

        } catch (e) {
          console.error('University program count failed:', e);
        }
      });
    });
    
    // Form submission handlers
    document.addEventListener('DOMContentLoaded', () => {
      // University form submission
      const universityForm = document.getElementById('universityForm');
      if (universityForm) {
        universityForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          const formData = new FormData(universityForm);
          
          try {
            const response = await fetch('settings_actions.php', {
              method: 'POST',
              body: formData
            });
            
            const data = await response.json();
            
            if (data.ok) {
              showToast('Success', 'University saved successfully', 'success');
              bootstrap.Modal.getInstance(document.getElementById('universityModal')).hide();
              // Reload the page to show updated data
              setTimeout(() => location.reload(), 1000);
            } else {
              showToast('Error', data.msg || 'Failed to save university', 'error');
            }
          } catch (error) {
            showToast('Error', 'Network error. Please try again.', 'error');
          }
        });
      }
      
      // Level form submission
      const levelForm = document.getElementById('levelForm');
      if (levelForm) {
        levelForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          const formData = new FormData(levelForm);
          
          try {
            const response = await fetch('settings_actions.php', {
              method: 'POST',
              body: formData
            });
            
            const data = await response.json();
            
            if (data.ok) {
              showToast('Success', 'Level saved successfully', 'success');
              bootstrap.Modal.getInstance(document.getElementById('levelModal')).hide();
              // Reload the page to show updated data
              setTimeout(() => location.reload(), 1000);
            } else {
              showToast('Error', data.msg || 'Failed to save level', 'error');
            }
          } catch (error) {
            showToast('Error', 'Network error. Please try again.', 'error');
          }
        });
      }
      
      // Save programs button
      const saveProgramsBtn = document.getElementById('saveProgramsBtn');
      if (saveProgramsBtn) {
        saveProgramsBtn.addEventListener('click', async () => {
          const university = document.getElementById('program_university').value;
          const level = document.getElementById('program_level').value;
          const mode = document.getElementById('mode_ai')?.checked ? 'ai' : 'manual';
          const programChips = document.querySelectorAll('#program_list [data-program], #program_list .badge');
          
          if (!university) {
            showToast('Error', 'Please select a university', 'error');
            return;
          }
          
          if (mode === 'manual' && !level) {
            showToast('Error', 'Please select a program level', 'error');
            return;
          }
          
          if (programChips.length === 0) {
            showToast('Error', 'Please add at least one program', 'error');
            return;
          }
          
          const programs = Array.from(programChips).map(chip => {
            if (chip.dataset.program) return chip.dataset.program;
            return chip.textContent.replace('×', '').trim();
          });
          
          try {
            let data;

            if (mode === 'ai') {
              const response = await fetch('ai_save_programs.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                  university_id: university,
                  programs
                })
              });
              data = await response.json();
            } else {
              const formData = new FormData();
              formData.append('action', 'save_program');
              formData.append('university_id', university);
              formData.append('level_id', level);
              programs.forEach(p => formData.append('programs[]', p));

              const response = await fetch('settings-handler.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
              });
              data = await response.json();
            }
            
            if (data.ok) {
              const inserted = data.inserted ?? programs.length;
              const skipped = data.skipped ?? 0;
              const msg = skipped > 0
                ? `${inserted} program(s) saved, ${skipped} duplicate(s) skipped`
                : `${inserted} program(s) saved successfully`;
              showToast('Success', msg, 'success');
              bootstrap.Modal.getInstance(document.getElementById('programModal')).hide();
              setTimeout(() => location.reload(), 1000);
            } else {
              showToast('Error', data.msg || 'Failed to save programs', 'error');
            }
          } catch (error) {
            showToast('Error', 'Network error. Please try again.', 'error');
          }
        });
      }
    });
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
      const sidebar = document.getElementById('sidebar');
      const mobileToggle = document.querySelector('.mobile-toggle');
      
      if (window.innerWidth < 992 && 
          sidebar.classList.contains('show') && 
          !sidebar.contains(e.target) && 
          !mobileToggle.contains(e.target)) {
        sidebar.classList.remove('show');
      }
    });
    
    // Auto-hide sidebar on mobile when window is resized
    window.addEventListener('resize', () => {
      if (window.innerWidth >= 992) {
        document.getElementById('sidebar').classList.remove('show');
      }
    });
  </script>
  
  <!-- Include profile and password modals -->
  <?php include 'profile_modal.php'; ?>
  <?php include 'change_password_modal.php'; ?>
  
</body>
</html>