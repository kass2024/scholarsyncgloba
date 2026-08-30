<?php
require_once __DIR__ . '/db.php';
/* ================================
   FETCH PLATFORMS
================================ */
$platforms = $conn->query("
    SELECT id, platform_name 
    FROM platforms 
    ORDER BY platform_name ASC
");

/* ================================
   FETCH STAFF
================================ */
$admins = $conn->query("
    SELECT id, full_name, role, email 
    FROM admins 
    WHERE role IN ('staff','superadmin') 
    ORDER BY full_name ASC
");

// Build staff array for smart search
$staffList = [];
while($s = $admins->fetch_assoc()){
    $staffList[] = ["id"=>$s["id"], "name"=>$s["full_name"]];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Smart Staff Task Allocation</title>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.rotate-180 { transform: rotate(180deg); }
.checkbox-item:hover { background-color: #eef6ff; }

/* Smart dropdown style */
.smart-item:hover { background: #d1fae5; cursor:pointer; }
.smart-box {
    max-height: 180px;
    overflow-y: auto;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: white;
    position: absolute;
    width: 100%;
    z-index: 30;
    display: none;
}
</style>

<script>

/* Make staff list available to JS */
const staffList = <?php echo json_encode($staffList); ?>;

/* ======================================================
   EXPAND / COLLAPSE (Original Code)
====================================================== */
window.toggleSection = function(id){
    const box = document.getElementById(id);
    const icon = document.getElementById("icon-" + id);
    box.classList.toggle("hidden");
    icon.classList.toggle("rotate-180");
};

/* ======================================================
   LOAD STAFF REVIEW PANEL (Original)
====================================================== */
function loadStaffReview() {
    fetch("review_ajax.php")
        .then(r => r.text())
        .then(html => { document.getElementById("reviewContainer").innerHTML = html; });
}

/* ======================================================
   ADD CUSTOM TASK FIELD (Original)
====================================================== */
function addCustomTask() {
    const container = document.getElementById("customTaskContainer");
    const input = document.createElement("input");

    input.type = "text";
    input.name = "custom_tasks[]";
    input.placeholder = "Enter custom task…";
    input.className = "w-full mt-2 p-2 rounded-lg border bg-gray-50";

    container.appendChild(input);
}

/* ======================================================
   SAVE NEW TASKS (Original)
====================================================== */
function saveTask(type){
    const form = document.getElementById("taskForm");
    const formData = new FormData(form);

    formData.append("action", type === 0 ? "save_only" : "save_and_email");

   fetch("save_task_ajax.php", { method: "POST", body: formData })
.then(r => r.json())
.then(res => {

    // 🚨 PREVENT DUPLICATE SAVING ❌
    if (res.status === "deny") {
        Swal.fire({
            icon: "error",
            title: "Duplicate Detected",
            text: res.message,
            confirmButtonColor: "#d33",
        });
        return; // STOP HERE – DO NOT SAVE ANYTHING
    }


        Swal.fire({
            icon: res.status,
            title: res.message,
            timer: 1800,
            showConfirmButton: false
        });

        if(res.status === "success"){
            form.reset();
            document.getElementById("customTaskContainer").innerHTML = "";
            loadStaffReview();
        }
    });
}

/* ======================================================
   INLINE EDIT – SHOW EDIT MODE (Original)
====================================================== */

/* ======================================================
   INLINE EDIT – CANCEL (Original)
====================================================== */


/* ======================================================
   INLINE EDIT – SAVE (Original + fixed checkbox handling)
====================================================== */

/* ======================================================
   ORIGINAL STAFF FILTER (KEPT UNTOUCHED)
====================================================== */
function filterStaff() {
    const filter = document.getElementById("staffSearch").value.toLowerCase();
    const options = document.querySelectorAll("#staffSelect option");

    options.forEach(o => {
        if (!o.value) return;
        o.style.display = o.textContent.toLowerCase().includes(filter) ? "block" : "none";
    });
}

/* ======================================================
   SMART SEARCH + SMART DROPDOWN (New enhancement)
====================================================== */
document.addEventListener("DOMContentLoaded", () => {

    loadStaffReview();

    const input = document.getElementById("staffSearch");
    const dropdown = document.getElementById("staffSelect");
    const smartBox = document.getElementById("smartStaffBox");

    input.addEventListener("input", () => {
        const q = input.value.toLowerCase();
        smartBox.innerHTML = "";

        if (!q.trim()) {
            smartBox.style.display = "none";
            return;
        }

        const matched = staffList.filter(s => s.name.toLowerCase().includes(q));

        matched.forEach(s => {
            let div = document.createElement("div");
            div.className = "smart-item px-3 py-2 text-sm";
            div.innerText = s.name;
            div.onclick = () => {
                input.value = s.name;
                dropdown.value = s.id;
                smartBox.style.display = "none";
            };
            smartBox.appendChild(div);
        });

        smartBox.style.display = matched.length ? "block" : "none";
    });
});

/* ======================================================
   UNIVERSITY SEARCH (Original)
====================================================== */
document.addEventListener("DOMContentLoaded", () => {
    const search = document.getElementById("universitySearch");
    if (search) {
        search.addEventListener("keyup", () => {
            const f = search.value.toLowerCase();
            document.querySelectorAll(".uni-item").forEach(i => {
                i.style.display = i.textContent.toLowerCase().includes(f) ? "flex" : "none";
            });
        });
    }
});

/* ======================================================
   FILTER UNIVERSITIES IN EDIT MODE (Original)
====================================================== */
function saveGroupEdit(staffId) {

    const tasksEl = document.getElementById("editTasks" + staffId);
    const notesEl = document.getElementById("editNotes" + staffId);

    if (!tasksEl) {
        Swal.fire("Error", "Task editor not found", "error");
        return;
    }

    const tasks = tasksEl.value.trim();
    const notes = notesEl ? notesEl.value.trim() : "";

    if (!tasks) {
        Swal.fire("Error", "Tasks cannot be empty", "error");
        return;
    }

    let formData = new FormData();
    formData.append("staff_id", staffId);
    formData.append("tasks", tasks);
    formData.append("notes", notes);

    // UNIVERSITIES
    document
        .querySelectorAll("#editUniList" + staffId + " input[type='checkbox']:checked")
        .forEach(cb => formData.append("edit_uni_ids[]", cb.value));

    // PLATFORMS
    document
        .querySelectorAll("#editPlatList" + staffId + " input[type='checkbox']:checked")
        .forEach(cb => formData.append("edit_plat_ids[]", cb.value));

    Swal.fire({
        title: "Saving changes…",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch("update_task_ajax.php", {
        method: "POST",
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === "success") {
            Swal.fire("Saved", res.message, "success");
            loadStaffReview(); // 🔄 reload review panel
        } else {
            Swal.fire("Error", res.message, "error");
        }
    })
    .catch(() => {
        Swal.fire("Error", "Server not responding", "error");
    });
}


</script>
<script>
function sendFinalCompiled() {
    Swal.fire({
        title: "Send Final Compiled Report?",
        text: "This will email staff and generate the overall PDF.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, Send",
        confirmButtonColor: "#16a34a"
    }).then(result => {
        if (!result.isConfirmed) return;

        // show loading immediately
        Swal.fire({
            title: "Processing…",
            text: "Emails and PDFs are being generated. Please wait.",
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch("send_final_compiled.php", { method: "POST" })
        .then(r => r.json())
        .then(res => {

            // ✅ EXPECTED RESPONSE
            if (res.status === "processing") {
                Swal.fire({
                    icon: "success",
                    title: "Processing Started",
                    text: "Emails are being sent and the final PDF will be generated shortly.",
                    confirmButtonColor: "#16a34a"
                });
            } 
            else if (res.status === "success") {
                Swal.fire("Completed", res.message, "success");
                if (res.pdf_url) {
                    window.open(res.pdf_url, "_blank");
                }
            } 
            else {
                Swal.fire("Error", res.message || "Unexpected error", "error");
            }
        })
        .catch(() => {
            Swal.fire("Error", "Server not responding", "error");
        });
    });
}
</script>


</head>

<body class="bg-gray-100 py-8">

<div class="max-w-6xl mx-auto px-4">

<!-- TITLE -->
<h1 class="text-4xl font-bold text-center text-green-700 mb-10">
    Smart Staff Task Allocation System
</h1>

<!-- FORM WRAPPER -->
<div class="bg-white shadow-lg p-8 rounded-2xl mb-12 border border-green-200">

<h2 class="text-2xl font-semibold text-green-700 mb-6">Assign Tasks to Staff</h2>

<form id="taskForm" class="space-y-6">

    <!-- STAFF INPUT -->
    <div class="relative">
        <label class="font-semibold text-gray-700 text-sm">Assign To (Staff)</label>
        
        <input id="staffSearch"
               placeholder="Search staff…"
               class="w-full p-2 rounded-lg border border-green-300 focus:ring-2 focus:ring-green-400 bg-gray-50 mb-2">

        <!-- Smart suggestion box -->
        <div id="smartStaffBox" class="smart-box"></div>

        <!-- Your original dropdown (kept) -->
        <select id="staffSelect" name="staff_id" class="w-full p-2 rounded-lg border bg-gray-50" required>
            <option value="">Select Staff</option>
            <?php foreach($staffList as $s){ ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php } ?>
        </select>
    </div>

  <!-- PLATFORM ASSIGNMENT (NEW) -->
<div>
    <label class="font-semibold text-gray-700 text-sm">Assign Platforms</label>

    <div class="border rounded-xl bg-white shadow-inner p-3 max-h-48 overflow-y-scroll space-y-2">
        <?php 
        mysqli_data_seek($platforms,0);
        while($p=$platforms->fetch_assoc()){ ?>
        
        <label class="flex items-center space-x-3 cursor-pointer">
            <input type="checkbox" name="platform_ids[]" value="<?= $p['id'] ?>"
                   class="h-4 w-4 text-blue-600">
            <span><?= $p['platform_name'] ?></span>
        </label>

        <?php } ?>
    </div>
</div>

    <!-- CUSTOM TASKS -->
    <div>
        <label class="font-semibold text-gray-700 text-sm">Custom Tasks</label>
        <div id="customTaskContainer"></div>

        <button type="button" onclick="addCustomTask()"
                class="mt-2 px-3 py-1 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 shadow">
            + Add Custom Task
        </button>
    </div>

    <!-- UNIVERSITIES -->
    <div>
        <label class="font-semibold text-gray-700 text-sm">Assign Key Universities</label>
        <input id="universitySearch" placeholder="Search universities…"
               class="w-full p-2 rounded-lg border bg-gray-50 mb-3">

        <div id="universityList"
             class="h-64 overflow-y-scroll border rounded-xl bg-white shadow-inner p-3 space-y-2">
            <?php 
            $universities = $conn->query("SELECT * FROM universities ORDER BY name ASC");
            while($u=$universities->fetch_assoc()){ ?>
                <label class="uni-item flex items-center space-x-3 cursor-pointer p-1 rounded">
                    <input type="checkbox" name="university_ids[]" value="<?= $u['id'] ?>"
                           class="h-4 w-4 text-green-600">
                    <span><?= $u['name'] ?></span>
                </label>
            <?php } ?>
        </div>
    </div>

    <!-- EXTRA RESPONSIBILITY -->
    <div>
        <label class="font-semibold text-gray-700 text-sm">Additional Responsibilities</label>
        <textarea name="extra_responsibility"
                  class="w-full p-2 rounded-lg border bg-gray-50 h-28"></textarea>
    </div>

    <!-- BUTTONS -->
    <div class="flex flex-col md:flex-row gap-4">
        <button type="button" onclick="saveTask(0)"
                class="w-full md:w-1/2 bg-green-700 text-white py-2 rounded-xl hover:bg-green-800 shadow">
            Save Only
        </button>

        <button type="button" onclick="saveTask(1)"
                class="w-full md:w-1/2 bg-green-600 text-white py-2 rounded-xl hover:bg-green-700 shadow">
            Save & Send Email
        </button>
    </div>

</form>
</div>

<!-- REVIEW PANEL -->
<h2 class="text-2xl font-semibold text-green-700 mb-6">Review Staff Responsibilities</h2>
<div id="reviewContainer" class="space-y-6"></div>

<!-- FINAL EMAIL -->
<div class="mt-10 text-center">
    <button type="button"
            onclick="sendFinalCompiled()"
            class="px-8 py-3 bg-green-600 text-white text-lg rounded-xl shadow hover:bg-green-700">
        Send FINAL COMPILED EMAIL & DOWNLOAD PDF
    </button>
</div>

</div>
</body>
</html>
