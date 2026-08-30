<?php
// admin/application-list.php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/includes/company_branding.php';

$sessionRole = isset($_SESSION['role']) ? trim((string) $_SESSION['role']) : '';
$dbRole = '';
$adminPk = 0;
if (!empty($_SESSION['id'])) {
    $adminPk = (int) $_SESSION['id'];
} elseif (!empty($_SESSION['admin_id'])) {
    $adminPk = (int) $_SESSION['admin_id'];
}
if ($adminPk > 0) {
    $stRole = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
    if ($stRole) {
        $stRole->bind_param('i', $adminPk);
        $stRole->execute();
        $rowRole = $stRole->get_result()->fetch_assoc();
        $stRole->close();
        if ($rowRole) {
            $dbRole = trim((string) ($rowRole['role'] ?? ''));
        }
    }
}
// Superadmin if either session or DB matches (delete API still enforces DB)
$canDeleteApplication = xander_is_superadmin_role($dbRole) || xander_is_superadmin_role($sessionRole);

$appRoot = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Applications | <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Select2 (searchable dropdowns — study choice add panel) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

    <!-- ================= CUSTOM STYLES ================= -->
   <style>

        /* One scroll for the app shell: no body scrollbar next to <main> / list */
        html {
            height: 100%;
        }
        body {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }

        /* Study choice add form — Select2, full width per column */
        #studyChoiceAddInner .select2-container {
            width: 100% !important;
            max-width: 100%;
        }
        #studyChoiceAddInner .select2-container--bootstrap-5 .select2-selection {
            min-height: 2.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            border-color: rgb(226 232 240);
            background: #fff;
        }
        #studyChoiceAddInner .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        #studyChoiceAddInner .select2-container--bootstrap-5.select2-container--open .select2-selection {
            border-color: rgb(99 102 241);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }
        #studyChoiceAddInner .select2-container--bootstrap-5 .select2-selection__placeholder {
            color: rgb(148 163 184);
        }
        #studyChoiceAddInner .select2-container--bootstrap-5 .select2-dropdown {
            border-radius: 0.5rem;
            border-color: rgb(226 232 240);
            box-shadow: 0 10px 40px -10px rgba(15, 23, 42, 0.18);
        }
        #studyChoiceAddInner .select2-results__option--highlighted {
            background-color: rgb(79 70 229) !important;
        }

        /* Assign-to dropdown (superadmin) — Select2 matches study-choice styling */
        #assignmentEditorPanel .select2-container {
            width: 100% !important;
            max-width: 100%;
        }
        #assignmentEditorPanel .select2-container--bootstrap-5 .select2-selection {
            min-height: 2.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            border-color: rgb(226 232 240);
            background: #fff;
        }
        #assignmentEditorPanel .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        #assignmentEditorPanel .select2-container--bootstrap-5.select2-container--open .select2-selection {
            border-color: rgb(99 102 241);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }
        #assignmentEditorPanel .select2-container--bootstrap-5 .select2-dropdown {
            border-radius: 0.5rem;
            border-color: rgb(226 232 240);
            box-shadow: 0 10px 40px -10px rgba(15, 23, 42, 0.18);
        }
        #assignmentEditorPanel .select2-results__option--highlighted {
            background-color: rgb(79 70 229) !important;
        }
        #assignmentEditorPanel .pcvc-assign-opt__name {
            font-weight: 600;
            font-size: 0.875rem;
            color: rgb(15 23 42);
        }
        #assignmentEditorPanel .pcvc-assign-opt__meta {
            font-size: 0.75rem;
            color: rgb(100 116 139);
            margin-top: 0.15rem;
        }
        #assignmentEditorPanel .pcvc-assign-sel-one {
            font-size: 0.875rem;
            color: rgb(15 23 42);
        }

        /* Open Select2 list above sticky Application Journey column */
        .select2-container--open {
            z-index: 1100 !important;
        }

/* =====================================================
   GLOBAL FOUNDATION
   ===================================================== */
:root {
    --bg-dark: #0f172a;
    --bg-dark-hover: #1e293b;

    --text-light: #e5e7eb;
    --text-muted: #94a3b8;
    --text-dark: #0f172a;

    --primary: #4f46e5;
    --primary-soft: #eef2ff;

    --border-soft: #e5e7eb;
    --border-muted: #e2e8f0;

    --success: #22c55e;
}

body {
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont;
    color: var(--text-dark);
}

/* =====================================================
   SCROLLBAR (MINIMAL & MODERN)
   ===================================================== */
.scrollbar::-webkit-scrollbar {
    width: 6px;
}

.scrollbar::-webkit-scrollbar-thumb {
    background-color: #64748b;
    border-radius: 999px;
}

/* =====================================================
   SIDEBAR (NAVIGATION ZONE)
   ===================================================== */
aside {
    background-color: var(--bg-dark);
    color: var(--text-light);
}

aside h2 {
    color: #f8fafc;
}

aside p {
    color: var(--text-muted);
}

/* Student list rows – JS SAFE */
#studentList li {
    padding: 0.9rem 1.25rem;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    transition: background-color .15s ease, transform .15s ease;
}

#studentList li:hover {
    background-color: var(--bg-dark-hover);
}

#studentList li.active {
    background: linear-gradient(
        90deg,
        var(--primary),
        #6366f1
    );
}

/* Sidebar row: keep student name on its own line (never merge with time row) */
#studentList li.pcvc-sidebar-app-item {
    flex-direction: column;
    align-items: stretch;
}
#studentList .pcvc-sidebar-name {
    display: block;
    width: 100%;
}
#studentList .pcvc-sidebar-time {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    column-gap: 6px;
    row-gap: 2px;
    width: 100%;
    box-sizing: border-box;
}

/* =====================================================
   CARDS (CONTENT ZONE)
   ===================================================== */
.card {
    background-color: #ffffff;
    border-radius: 1rem;
    border: 1px solid var(--border-soft);
    box-shadow:
        0 1px 2px rgba(0,0,0,.05),
        0 12px 28px rgba(0,0,0,.08);
    transition: transform .2s ease, box-shadow .2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow:
        0 4px 8px rgba(0,0,0,.06),
        0 20px 40px rgba(0,0,0,.12);
}

/* Section headers */
.section-title {
    font-size: .75rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    font-weight: 600;
    color: #475569;
    margin-bottom: 1rem;
}

/* =====================================================
   AI RELATED PROGRAMS PANEL
   ===================================================== */
#aiDecisionPanel {
    background: linear-gradient(
        135deg,
        #fffbeb,
        #f8fafc
    );
    border: 1px solid #fde68a;
    box-shadow: 0 16px 40px rgba(217, 119, 6, 0.12);
}

/* =====================================================
   APPLICATION JOURNEY (STATUS / TIMELINE ZONE)
   ===================================================== */
#applicationTracking {
    background: linear-gradient(
        180deg,
        #ffffff,
        #f8fafc
    );
    border-radius: 1.25rem;
    border: 1px solid var(--border-muted);
    box-shadow: 0 16px 40px rgba(0,0,0,.12);
    max-width: 100%;
}

/* Journey status badge */
#journeyStatusBadge {
    font-size: 11px;
    font-weight: 600;
    padding: .25rem .6rem;
    border-radius: 999px;
    background-color: #e0e7ff;
    color: #3730a3;
}

/* Timeline dots */
.timeline-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background-color: #cbd5e1;
}

.timeline-dot.completed {
    background-color: var(--success);
    box-shadow: 0 0 0 4px rgba(34,197,94,.15);
}

.timeline-dot.current {
    background-color: #6366f1;
    box-shadow: 0 0 0 6px rgba(99,102,241,.25);
}

/* =====================================================
   TABLES (DATA PRESENTATION)
   ===================================================== */
table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background-color: #f1f5f9;
}

th {
    font-size: .7rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    font-weight: 600;
    color: #475569;
}

td,
th {
    padding: .6rem;
    border: 1px solid var(--border-soft);
}

/* =====================================================
   EMPTY STATES
   ===================================================== */
#emptyState {
    background: linear-gradient(
        135deg,
        #f8fafc,
        #eef2ff
    );
    border-radius: 1.25rem;
    padding: 4rem 2rem;
}

#journeyEmpty {
    color: var(--text-muted);
}

</style>


</head>

<body class="bg-slate-100 text-slate-800">
<div class="flex h-screen min-h-0 overflow-hidden">

    <!-- ================= SIDEBAR ================= -->
    <aside class="w-96 flex min-h-0 min-w-0 flex-col overflow-hidden border-r border-slate-800">
        <div class="px-6 py-5 border-b">
            <h2 class="text-lg font-bold">Applications</h2>
            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?> · All student submissions</p>
        </div>

        <div class="p-4 border-b">
          <input
    id="searchInput"
    type="text"
    placeholder="Search name,email,region or country"
    class="w-full px-4 py-2 border rounded-lg text-sm
           text-slate-900 placeholder-slate-400
           focus:ring focus:ring-blue-200 focus:outline-none"
>

        </div>

        <div id="agentEmailFilterBanner" class="hidden mx-4 mb-2 rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs text-indigo-900" role="status"></div>

        <div class="px-4 pb-3 space-y-2 border-b border-slate-200 bg-slate-50/80">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Track by assignee &amp; status</div>
            <select id="filterAssignedStaff" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm text-slate-900 bg-white">
                <option value="">All staff</option>
                <option value="-1">Unassigned</option>
            </select>
            <select id="filterAppStatus" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm text-slate-900 bg-white">
                <option value="">All statuses</option>
            </select>
            <div class="flex gap-2">
                <button type="button" id="filterClearBtn" class="flex-1 px-2 py-1.5 text-xs font-medium rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100">Clear filters</button>
            </div>
        </div>

        <ul
            id="studentList"
            class="flex-1 overflow-y-auto scrollbar divide-y text-sm bg-white"
        ></ul>
    </aside>

    <!-- ================= MAIN CONTENT ================= -->
    <main class="flex-1 min-h-0 min-w-0 overflow-y-auto overflow-x-hidden bg-slate-50 p-8">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            <!-- ================= LEFT COLUMN ================= -->
            <!-- min-w-0: grid children default to min-width:auto; Select2/containers can spill into col 3 and sit UNDER the journey panel -->
            <div class="lg:col-span-2 space-y-6 min-w-0">

                <!-- EMPTY STATE -->
                <div
                    id="emptyState"
                    class="flex flex-col items-center justify-center h-full
                           text-gray-400 text-center"
                >
                    <div class="text-lg font-medium">No application selected</div>
                    <div class="text-sm mt-1">
                        Select a student from the left to view details
                    </div>
                </div>

                <!-- ================= AI RELATED PROGRAMS PANEL ================= -->
                <div
                    id="aiDecisionPanel"
                    class="hidden card border-amber-200 p-6 space-y-3"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-amber-950">
                            Related programs at other universities
                        </h3>
                        <span
                            id="aiRelatedStatus"
                            class="text-xs font-semibold px-3 py-1 rounded-full
                                   bg-amber-100 text-amber-900"
                        >
                            —
                        </span>
                    </div>

                    <p class="text-xs text-amber-900/80 leading-snug">
                        AI scans other universities for the same or related programs, then queues matches for approval under Study Choices.
                    </p>

                    <div
                        id="aiRelatedSummary"
                        class="text-sm text-slate-700"
                    ></div>
                </div>

                <!-- ================= APPLICATION DETAILS ================= -->
                <div id="applicationDetails" class="hidden space-y-6">

                    <!-- HEADER -->
                    <section class="card p-6">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h2 id="studentName" class="text-xl font-bold"></h2>
                                <p id="studentEmail" class="text-sm text-gray-500"></p>
                                <p id="studentPhone" class="text-sm text-gray-500"></p>
                                <p id="applicationMeta" class="text-xs text-gray-400 mt-1"></p>
                                <p id="applicationAssignedDisplay" class="text-sm text-slate-600 mt-1.5"></p>
                            </div>
                            <div
                                id="applicationActions"
                                class="flex-shrink-0 w-full sm:w-auto flex flex-wrap gap-2 justify-end sm:justify-start items-start"
                            >
                                <div id="applicationActionsDynamic" class="flex flex-wrap gap-2 justify-end"></div>
                                <?php if ($canDeleteApplication): ?>
                                <button
                                    type="button"
                                    id="btnDeleteApplicationHeader"
                                    class="pcvc-btn-delete-app"
                                    style="display:none;align-items:center;gap:0.35rem;padding:0.55rem 1rem;font-size:0.875rem;font-weight:600;color:#fff;background:#dc2626;border:1px solid #b91c1c;border-radius:0.5rem;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,0.08);"
                                    disabled
                                    title="Select an application, then click to delete permanently"
                                >Delete application</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <?php if ($canDeleteApplication): ?>
                    <!-- Superadmin only: reassignment + notify (enforced in api/applications.php) -->
                    <section id="assignmentEditorPanel" class="card p-6 hidden" aria-labelledby="assignmentEditorHeading">
                        <div id="assignmentEditorHeading" class="section-title">Assign to staff</div>
                        <p class="text-xs text-slate-600 mb-4 leading-snug">
                            Only a superadmin can change who owns this file. After you save, the newly assigned person (staff or superadmin) is notified by email and by WhatsApp when their number and Meta templates are configured.
                        </p>
                        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                            <label class="block min-w-[220px] flex-1">
                                <span class="text-xs font-medium text-slate-700">Assigned to</span>
                                <select
                                    id="assignStaffSelect"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                ></select>
                            </label>
                            <button
                                type="button"
                                id="btnSaveAssignment"
                                class="inline-flex w-full shrink-0 items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                            >
                                Save assignment
                            </button>
                        </div>
                        <p id="assignmentSaveStatus" class="mt-2 min-h-[1.25rem] text-xs text-slate-600" role="status"></p>
                    </section>
                    <?php endif; ?>

                    <!-- PERSONAL INFO -->
                    <section class="card p-6">
                        <div class="section-title">Personal Information</div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>Gender: <span id="pGender"></span></div>
                            <div>DOB: <span id="pDob"></span></div>
                            <div>Nationality: <span id="pNationality"></span></div>
                            <div>Birth Country: <span id="pBirthCountry"></span></div>
                            <div>Passport: <span id="pPassport"></span></div>
                            <div>National ID: <span id="pNationalId"></span></div>
                        </div>
                    </section>

                    <!-- ADDRESS -->
                    <section class="card p-6">
                        <div class="section-title">Address</div>
                        <div class="text-sm space-y-1">
                            <div id="addrLine"></div>
                            <div id="addrCity"></div>
                            <div id="addrPostal"></div>
                        </div>
                    </section>

                    <!-- FAMILY & EMERGENCY -->
                    <section class="card p-6">
                        <div class="section-title">Family & Emergency</div>

                        <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                            <div>Father: <span id="pFather"></span></div>
                            <div>Mother: <span id="pMother"></span></div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>Name: <span id="eName"></span></div>
                            <div>Email: <span id="eEmail"></span></div>
                            <div>Phone: <span id="ePhone"></span></div>
                            <div>Relationship: <span id="eRelation"></span></div>
                        </div>
                    </section>

                    <!-- EDUCATION -->
                    <section class="card p-6">
                        <div class="section-title">Education Background</div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>Institution: <span id="eduInstitution"></span></div>
                            <div>Country: <span id="eduCountry"></span></div>
                            <div>Start Date: <span id="eduStart"></span></div>
                            <div>Graduation: <span id="eduGrad"></span></div>
                            <div class="col-span-2">Study Gap: <span id="eduGap"></span></div>
                        </div>
                    </section>

                    <!-- STUDY CHOICES -->
                    <section class="card p-6">
                        <div class="section-title">Study Choices</div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm border">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="p-2 border">Region</th>
                                        <th class="p-2 border">University</th>
                                        <th class="p-2 border">Country</th>
                                        <th class="p-2 border">Level</th>
                                        <th class="p-2 border">Program</th>
                                    </tr>
                                </thead>
                                <tbody id="studyChoicesTable"></tbody>
                            </table>
                        </div>

                        <div
                            id="relatedStudySuggestionsPanel"
                            class="mt-6 hidden border-t border-amber-200 pt-6"
                        >
                            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm">
                                <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <h4 class="text-sm font-semibold text-amber-950">
                                            Related programs pending approval
                                        </h4>
                                        <p class="mt-1 text-sm text-amber-900/80 leading-snug">
                                            AI-matched similar programs at other universities (assigned or unassigned). Approve to add them to study choices, or reject to dismiss.
                                        </p>
                                    </div>
                                    <span id="relatedStudySuggestionsCount" class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-900"></span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm border border-amber-200 bg-white">
                                        <thead class="bg-amber-100/80">
                                            <tr>
                                                <th class="p-2 border text-left">Suggested university</th>
                                                <th class="p-2 border text-left">Program</th>
                                                <th class="p-2 border text-left">Matched from</th>
                                                <th class="p-2 border text-left">Admin(s)</th>
                                                <th class="p-2 border text-left">Match</th>
                                                <th class="p-2 border text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="relatedStudySuggestionsTable"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div
                            id="studyChoiceAddPanel"
                            class="mt-6 hidden border-t border-slate-200 pt-6"
                            aria-labelledby="studyChoiceAddHeading"
                        >
                            <div
                                id="studyChoiceAddInner"
                                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div class="mb-4">
                                    <h4 id="studyChoiceAddHeading" class="text-sm font-semibold text-slate-900">
                                        Add another study choice
                                    </h4>
                                    <p class="mt-1 text-sm text-slate-600 leading-snug">
                                        Choose region, then university, level, and program. Open any field and type to search.
                                        Saving emails the student; duplicate combinations are ignored.
                                    </p>
                                </div>

                                <!-- Two rows of two: avoids one overcrowded line and truncated placeholders -->
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <label class="block min-w-0">
                                        <span class="mb-1.5 block text-xs font-medium text-slate-700">Region</span>
                                        <select id="addStudyRegion" class="pcvc-add-study-select w-full max-w-full text-sm text-slate-900" title="Regions">
                                            <option value="">Loading…</option>
                                        </select>
                                    </label>
                                    <label class="block min-w-0">
                                        <span class="mb-1.5 block text-xs font-medium text-slate-700">University</span>
                                        <select id="addStudyUniversity" class="pcvc-add-study-select w-full max-w-full text-sm text-slate-900" disabled title="Universities">
                                            <option value="">Select region first</option>
                                        </select>
                                    </label>
                                    <label class="block min-w-0">
                                        <span class="mb-1.5 block text-xs font-medium text-slate-700">Level</span>
                                        <select id="addStudyLevel" class="pcvc-add-study-select w-full max-w-full text-sm text-slate-900" disabled title="Level">
                                            <option value="">Select university</option>
                                        </select>
                                    </label>
                                    <label class="block min-w-0">
                                        <span class="mb-1.5 block text-xs font-medium text-slate-700">Program</span>
                                        <select id="addStudyProgram" class="pcvc-add-study-select w-full max-w-full text-sm text-slate-900" disabled title="Program">
                                            <option value="">Select level</option>
                                        </select>
                                    </label>
                                </div>

                                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p id="studyChoiceAddStatus" class="text-xs text-slate-500 min-h-[1.25rem] sm:flex-1 sm:min-w-0" role="status"></p>
                                    <button
                                        type="button"
                                        id="btnAddStudyChoice"
                                        class="w-full shrink-0 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-45 sm:w-auto sm:min-w-[14rem]"
                                        disabled
                                    >
                                        Add &amp; notify student
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- DOCUMENTS -->
                    <section class="card p-6">
                        <div class="section-title mb-4">Documents</div>

                        <div id="documentsList" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>

                        <!-- Missing-docs reminder bar (sits directly under the upload cards) -->
                        <div
                            id="missingDocsReminderBar"
                            class="hidden mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-amber-900">
                                        Need missing documents?
                                    </div>
                                    <div id="missingDocsReminderHint" class="text-xs text-amber-800 mt-0.5">
                                        Send a WhatsApp + email reminder to the student. Tick the documents that are still missing.
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    id="btnNotifyMissingDocs"
                                    class="shrink-0 rounded-lg bg-amber-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-600 disabled:opacity-45"
                                    disabled
                                >
                                    Notify student
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- AGENT -->
                    <section class="card p-6">
                        <div class="section-title">Agent</div>
                        <div id="agentInfo" class="text-sm text-gray-700"></div>
                    </section>

                </div>
            </div>

           <!-- ================= RIGHT COLUMN: APPLICATION JOURNEY ================= -->
<aside
    id="applicationTracking"
    class="hidden lg:block lg:col-span-1 min-w-0 sticky top-8 self-start p-6 h-fit"
>

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-sm font-semibold text-slate-900">
            Application Journey
        </h3>

        <span
            id="journeyStatusBadge"
            class="text-[11px] font-semibold px-2.5 py-1 rounded-full
                   bg-slate-100 text-slate-600"
        >
            In progress
        </span>
    </div>

    <!-- Superadmin only: delete in journey sidebar (PHP + wired by application-list.js) -->
    <div
        id="journeyDeleteActions"
        class="mb-4 pb-4 border-b border-slate-200<?php echo $canDeleteApplication ? '' : ' hidden'; ?>"
        aria-live="polite"
    >
        <?php if ($canDeleteApplication): ?>
        <button
            type="button"
            id="btnDeleteApplicationJourney"
            class="pcvc-btn-delete-app"
            style="display:none;width:100%;align-items:center;justify-content:center;gap:0.35rem;padding:0.6rem 1rem;font-size:0.875rem;font-weight:600;color:#fff;background:#dc2626;border:1px solid #b91c1c;border-radius:0.5rem;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,0.08);"
            disabled
            title="Select an application, then click to delete permanently"
        >Delete application</button>
        <?php endif; ?>
    </div>

    <!-- Timeline -->
   <div
    id="trackingTimeline"
    class="relative flex flex-col gap-6 text-xs pl-6"
>

        <!-- Vertical line -->
        <div
            class="absolute left-[7px] top-0 bottom-0 w-px bg-slate-200"
        ></div>

        <!-- JS injects journey steps here -->
    </div>

    <!-- Empty state -->
    <div
        id="journeyEmpty"
        class="hidden text-center text-xs text-slate-400 py-6"
    >
        No journey activity yet
    </div>
</aside>


        </div>
    </main>
</div>

<!-- Missing documents notification modal -->
<div id="missingDocsModal" class="fixed inset-0 z-[200] hidden items-center justify-center bg-slate-900/50 p-4" aria-hidden="true">
    <div class="relative w-full max-w-lg rounded-xl bg-white shadow-xl" role="dialog">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h3 class="text-base font-bold text-slate-900">Notify student — missing documents</h3>
            <button type="button" id="missingDocsModalClose" class="text-2xl leading-none text-slate-400 hover:text-slate-700">&times;</button>
        </div>

        <!-- Sending overlay (smart spinner) -->
        <div id="missingDocsSendingOverlay" class="hidden absolute inset-0 z-10 rounded-xl bg-white/85 backdrop-blur-sm flex flex-col items-center justify-center gap-3" aria-hidden="true">
            <svg class="animate-spin h-10 w-10 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"></circle>
                <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round" fill="none"></path>
            </svg>
            <div class="text-center">
                <div id="missingDocsSendingTitle" class="text-sm font-bold text-slate-900">Sending…</div>
                <div id="missingDocsSendingHint" class="mt-0.5 text-xs text-slate-600">This usually takes a few seconds.</div>
            </div>
            <div class="flex gap-1.5">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-bounce" style="animation-delay:0ms"></span>
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-bounce" style="animation-delay:150ms"></span>
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-bounce" style="animation-delay:300ms"></span>
            </div>
        </div>
        <div class="px-5 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
            <p class="text-sm text-slate-600">Send via WhatsApp (approved template) and/or email. Tick the documents that are still missing. You can edit the phone, email, and message before sending.</p>

            <div class="flex gap-3">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="missingSendWa" checked> WhatsApp</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="missingSendEmail" checked> Email</label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Phone (WhatsApp)</label>
                    <input id="missingDocsPhone" type="tel" inputmode="tel" autocomplete="off"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        placeholder="+250788123456">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Email</label>
                    <input id="missingDocsEmail" type="email" autocomplete="off"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        placeholder="student@example.com">
                </div>
            </div>

            <div id="missingDocsChecklist" class="space-y-2 border border-slate-200 rounded-lg p-3 bg-slate-50 max-h-48 overflow-y-auto"></div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-semibold text-slate-600">Message</label>
                    <button type="button" id="missingDocsResetMessage" class="text-[11px] font-semibold text-emerald-700 hover:underline">Reset to default</button>
                </div>
                <textarea id="missingDocsMessage" rows="8" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono"></textarea>
                <p class="mt-1 text-[11px] text-slate-500">This text is sent in the email body and (as a fallback) in WhatsApp. The approved WhatsApp template only uses: student name, missing-document list, and portal link.</p>
            </div>

            <div id="missingDocsSendStatus" class="text-sm hidden"></div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
            <button type="button" id="missingDocsCancel" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="button" id="missingDocsSendBtn" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Send notification</button>
        </div>
    </div>
</div>

<script>
window.APP_ROOT = <?= json_encode($appRoot, JSON_UNESCAPED_SLASHES) ?>;
window.CAN_DELETE_APPLICATION = <?= json_encode($canDeleteApplication) ?>;
window.PCVC_DEFAULT_ASSIGNED_LABEL = <?= json_encode(PCVC_DEFAULT_ASSIGNED_PERSON_LABEL, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="assets/js/application-list.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/application-list.js') ?>"></script>
</body>
</html>
