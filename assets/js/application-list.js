/**
 * =====================================================
 * API URL (works inside admin-dashboard iframe + subfolders)
 * =====================================================
 */
function pcvcSecureFileUrl(relPath, inline) {
    if (!relPath) return "#";
    try {
        const enc = btoa(unescape(encodeURIComponent(relPath)));
        let u = "download.php?f=" + encodeURIComponent(enc);
        if (inline) u += "&inline=1";
        return u;
    } catch (e) {
        return "#";
    }
}

function projectApiPath(relativePath) {
    const rel = String(relativePath || "").replace(/^\//, "");
    const base =
        typeof window.APP_ROOT === "string" && window.APP_ROOT.length
            ? String(window.APP_ROOT).replace(/\/$/, "")
            : "";
    return base ? `${base}/${rel}` : rel;
}

/** Deep link from Agent Tracking Summary (admin-dashboard) */
function getAgentEmailFromUrl() {
    try {
        const u = new URL(window.location.href);
        const v = u.searchParams.get("agent_email");
        return v && String(v).trim() ? String(v).trim() : "";
    } catch (e) {
        return "";
    }
}

function stripAgentEmailFromPageUrl() {
    try {
        const u = new URL(window.location.href);
        if (!u.searchParams.has("agent_email")) {
            return;
        }
        u.searchParams.delete("agent_email");
        const next = u.pathname + (u.search || "") + (u.hash || "");
        window.history.replaceState({}, "", next);
    } catch (e) {
        /* ignore */
    }
}

function updateAgentEmailFilterBanner() {
    const el = document.getElementById("agentEmailFilterBanner");
    if (!el) {
        return;
    }
    const ae = getAgentEmailFromUrl();
    if (!ae) {
        el.classList.add("hidden");
        el.textContent = "";
        return;
    }
    el.classList.remove("hidden");
    el.textContent =
        "Showing only applications where the recruiting agent email is: " + ae;
}

/**
 * =====================================================
 * GLOBAL ELEMENTS
 * =====================================================
 */
const studentListEl = document.getElementById("studentList");
const detailsEl = document.getElementById("applicationDetails");
const emptyStateEl = document.getElementById("emptyState");
const aiPanelEl = document.getElementById("aiDecisionPanel");


const searchInput = document.getElementById("searchInput");
const filterAssignedStaff = document.getElementById("filterAssignedStaff");
const filterAppStatus = document.getElementById("filterAppStatus");
const filterClearBtn = document.getElementById("filterClearBtn");
const filterRegion = document.getElementById("filterRegion");
const filterUniversity = document.getElementById("filterUniversity");
const filterLevel = document.getElementById("filterLevel");

/** Currently selected application numeric id (student_applications.id) for inline edits */
let currentViewApplicationId = null;
let currentDocumentItems = [];
let currentStudentBio = { first_name: "", last_name: "", email: "", phone: "" };
let studyChoiceRegionsLoaded = false;
let studyChoiceAddFormWired = false;
let missingDocsModalWired = false;

/**
 * =====================================================
 * INITIAL LOAD
 * =====================================================
 */
document.addEventListener("DOMContentLoaded", () => {
    startTimeAgoTicker();
    loadFilterOptions().finally(() => loadStudents());
    wireStudyChoiceAddFormOnce();
    wireMissingDocsModalOnce();
    document.getElementById("btnSaveAssignment")?.addEventListener("click", saveApplicationAssignment);
});

/**
 * =====================================================
 * EVENT LISTENERS
 * =====================================================
 */
searchInput?.addEventListener("input", debounce(loadStudents, 300));
filterRegion?.addEventListener("change", loadStudents);
filterUniversity?.addEventListener("change", loadStudents);
filterLevel?.addEventListener("change", loadStudents);
filterAssignedStaff?.addEventListener("change", () => loadStudents());
filterAppStatus?.addEventListener("change", () => loadStudents());
filterClearBtn?.addEventListener("click", () => {
    if (filterAssignedStaff) filterAssignedStaff.value = "";
    if (filterAppStatus) filterAppStatus.value = "";
    if (searchInput) searchInput.value = "";
    stripAgentEmailFromPageUrl();
    updateAgentEmailFilterBanner();
    loadStudents();
});

/**
 * =====================================================
 * FILTER OPTIONS (staff + status labels)
 * =====================================================
 */
async function loadFilterOptions() {
    window.pcvcStatusLabels = {};
    try {
        const res = await fetch(projectApiPath("api/applications.php?action=filter_options"), {
            cache: "no-store"
        });
        const json = await res.json();
        if (!json?.success || !json?.data) return;

        const staff = Array.isArray(json.data.staff) ? json.data.staff : [];
        window.pcvcStaffAssignOptions = staff;
        const statuses = Array.isArray(json.data.statuses) ? json.data.statuses : [];

        statuses.forEach((s) => {
            if (s?.value) window.pcvcStatusLabels[s.value] = s.label || s.value;
        });

        if (filterAppStatus) {
            const keep = filterAppStatus.value;
            filterAppStatus.innerHTML = '<option value="">All statuses</option>';
            statuses.forEach((s) => {
                if (!s?.value) return;
                const opt = document.createElement("option");
                opt.value = s.value;
                opt.textContent = s.label || s.value;
                filterAppStatus.appendChild(opt);
            });
            if (keep && [...filterAppStatus.options].some((o) => o.value === keep)) {
                filterAppStatus.value = keep;
            }
        }

        if (filterAssignedStaff) {
            const keepStaff = filterAssignedStaff.value;
            filterAssignedStaff.innerHTML =
                '<option value="">All staff</option><option value="-1">Unassigned</option>';
            staff.forEach((row) => {
                const id = row?.id;
                if (!id) return;
                const opt = document.createElement("option");
                opt.value = String(id);
                opt.textContent = row.label || `Staff #${id}`;
                filterAssignedStaff.appendChild(opt);
            });
            if (keepStaff && [...filterAssignedStaff.options].some((o) => o.value === keepStaff)) {
                filterAssignedStaff.value = keepStaff;
            }
        }
    } catch (e) {
        console.warn("loadFilterOptions:", e);
    }
}

/**
 * =====================================================
 * LOAD STUDENT LIST (SIDEBAR)
 * =====================================================
 */
function loadStudents() {
    const params = new URLSearchParams({
        action: "list",
        q: searchInput?.value?.trim() || "",
        region_id: filterRegion?.value || "",
        university_id: filterUniversity?.value || "",
        program_level_id: filterLevel?.value || ""
    });
    if (filterAssignedStaff?.value) {
        params.set("assigned_to", filterAssignedStaff.value);
    }
    if (filterAppStatus?.value) {
        params.set("application_status", filterAppStatus.value);
    }
    const agentEmail = getAgentEmailFromUrl();
    if (agentEmail) {
        params.set("agent_email", agentEmail);
    }
    updateAgentEmailFilterBanner();

    studentListEl.innerHTML =
        `<li class="p-4 text-sm text-gray-400">Loading...</li>`;

    fetch(projectApiPath(`api/applications.php?${params}`))
        .then(r => r.json())
        .then(res => {
            console.log("LIST RESPONSE:", res);

            studentListEl.innerHTML = "";

            if (!res?.success || !Array.isArray(res.data) || !res.data.length) {
                const emptyMsg = agentEmail
                    ? "No applications found for this agent."
                    : "No applications found";
                studentListEl.innerHTML =
                    `<li class="p-4 text-sm text-gray-400">${emptyMsg}</li>`;
                return;
            }

            res.data.forEach(app => {
                studentListEl.appendChild(renderStudentItem(app));
            });
            refreshTimeAgoLabels();
        })
        .catch(err => {
            console.error("loadStudents error:", err);
            studentListEl.innerHTML =
                `<li class="p-4 text-sm text-red-500">Failed to load applications</li>`;
        });
}

/**
 * =====================================================
 * RENDER STUDENT ITEM (SIDEBAR)
 * =====================================================
 */
function renderStudentItem(app) {
    const bio   = app.bio || {};
    const meta  = app.meta || {};
    const study = app.study || {};

    const li = document.createElement("li");
    li.className = "pcvc-sidebar-app-item p-3 cursor-pointer hover:bg-slate-100";

    const firstName = String(bio.first_name ?? "").trim();
    const lastName = String(bio.last_name ?? "").trim();
    const fullName = [firstName, lastName].filter(Boolean).join(" ") || "—";

   
 // Build study line safely (AGGREGATED FIELDS)
const studyLine = [
    study.universities,
    study.regions,
    study.countries
].filter(Boolean).join(" • ");


    const timeData = formatFullTime(meta.created_at);
    const timeDisplay = timeData ? `
        <div class="pcvc-sidebar-time mt-1 text-[11px] font-medium application-time"
             style="color:${timeData.color}">
            <span class="shrink-0" aria-hidden="true">${timeData.icon}</span>
            <span data-time-ago="${escapeAttr(String(meta.created_at || ""))}">${timeAgo(meta.created_at)}</span>
            <span class="text-gray-400 shrink-0">•</span>
            <span>${timeData.date}</span>
            <span class="text-gray-400 shrink-0">•</span>
            <span>${timeData.time}</span>
        </div>
    ` : "";

    const assignedDisplay = escapeHTML(String(meta.assigned_display || "ScholarSync Global"));

    const assignedLine = `
        <div class="mt-1 text-[11px] leading-snug text-slate-600">
            <span class="text-slate-500 font-medium">Assigned:</span>
            <span>${assignedDisplay}</span>
        </div>
    `;

    const effKey = meta.effective_status || "";
    const effLabel =
        effKey && window.pcvcStatusLabels && window.pcvcStatusLabels[effKey]
            ? window.pcvcStatusLabels[effKey]
            : effKey;
    const statusLine = effLabel
        ? `<div class="mt-0.5 text-[11px] text-slate-700"><span class="text-slate-500 font-medium">Status:</span> ${escapeHTML(String(effLabel))}</div>`
        : "";

    const unreadHtml =
        Number(meta.is_read) === 0
            ? `<span class="unread-dot w-2 h-2 shrink-0 bg-blue-600 rounded-full mt-1.5" aria-label="Unread"></span>`
            : "";

    li.innerHTML = `
        <div class="min-w-0 flex flex-col gap-0.5">
            <div class="flex items-start justify-between gap-2 w-full">
                <div class="pcvc-sidebar-name font-semibold text-sm break-words min-w-0 flex-1 leading-snug">
                    ${escapeHTML(fullName)}
                </div>
                ${unreadHtml}
            </div>

            <div class="text-xs text-gray-500 whitespace-normal break-words">
                ${escapeHTML(String(bio.email ?? ""))}
            </div>

           ${
    studyLine
        ? `<div class="text-xs text-slate-600 mt-0.5 whitespace-normal break-words">
            ${escapeHTML(studyLine)}
          </div>`
        : ""
}


            ${timeDisplay}
            ${assignedLine}
            ${statusLine}
        </div>
    `;

    li.addEventListener("click", () => {
    showAiDecision(app.id);          // 👈 AI FIRST
    loadApplication(app.id, li);     // 👈 DETAILS SECOND
});

    return li;
}
/**
 * =====================================================
 * AI FETCH UTILITIES (REQUIRED)
 * =====================================================
 */
let aiAbortController = null;

async function safeFetchJSON(url, options = {}) {
    const res = await fetch(url, options);
    const text = await res.text();

    let data;
    try {
        data = JSON.parse(text);
    } catch (e) {
        console.error("❌ Invalid JSON from:", url);
        console.error("RAW RESPONSE:", text);
        return null; // 👈 DO NOT THROW
    }

    // ⛔ DO NOT THROW FOR AI SERVICE
    if (!res.ok) {
        console.warn("⚠️ Request failed:", res.status, url);
        return null; // 👈 THIS IS THE KEY CHANGE
    }

    if (data?.success === false) {
        console.warn("⚠️ API error:", data);
        return null;
    }

    return data;
}
/**
 * =====================================================
 * AI RELATED PROGRAM SCAN (NO PLATFORM PROPOSALS)
 * =====================================================
 */
async function showAiDecision(appId) {
    if (!appId) return;

    if (aiAbortController) {
        aiAbortController.abort();
    }
    aiAbortController = new AbortController();

    emptyStateEl?.classList.add("hidden");
    aiPanelEl?.classList.remove("hidden");

    const statusEl = document.getElementById("aiRelatedStatus");
    const summaryEl = document.getElementById("aiRelatedSummary");

    if (statusEl) statusEl.textContent = "Scanning…";
    if (summaryEl) {
        summaryEl.innerHTML = `<span class="text-slate-500">AI is checking related programs at other universities…</span>`;
    }

    try {
        const res = await safeFetchJSON(
            projectApiPath(
                `api/ai-decision.php?application_id=${encodeURIComponent(appId)}`
            ),
            { signal: aiAbortController.signal }
        );

        if (!res || !res.data) {
            throw new Error("AI service unavailable");
        }

        const suggestions = Array.isArray(res.data.study_choice_suggestions)
            ? res.data.study_choice_suggestions
            : [];
        const scan = res.data.related_program_scan || {};
        const pending = Number(res.data.pending_count ?? suggestions.length) || 0;

        renderRelatedStudySuggestions(suggestions);

        if (statusEl) {
            statusEl.textContent = pending > 0 ? `${pending} pending approval` : "No matches";
        }
        if (summaryEl) {
            if (pending > 0) {
                summaryEl.innerHTML = `
                    <span class="text-amber-950 font-medium">${pending} related program${pending === 1 ? "" : "s"}</span>
                    queued for approval under Study Choices.
                    ${scan.emails ? ` <span class="text-slate-500">(${Number(scan.emails)} admin email${Number(scan.emails) === 1 ? "" : "s"} sent.)</span>` : ""}
                `;
            } else if (scan.triggered) {
                summaryEl.innerHTML = `<span class="text-slate-600">No additional related programs found at other universities.</span>`;
            } else {
                summaryEl.innerHTML = `<span class="text-slate-600">Related-program scan did not run (no assigned university on this application).</span>`;
            }
        }
    } catch (err) {
        if (err?.name === "AbortError") {
            console.debug("AI request aborted");
            return;
        }

        console.warn("AI related-program scan unavailable:", err.message);

        if (statusEl) statusEl.textContent = "Unavailable";
        if (summaryEl) {
            summaryEl.innerHTML = `<span class="text-red-600">AI related-program matching is currently unavailable. Please try again later.</span>`;
        }
    }
}

/**
 * =====================================================
 * LOAD FULL APPLICATION
 * =====================================================
 */
function loadApplication(id, listItem) {
    if (!id) return;

    if (studentListEl) {
        studentListEl.querySelectorAll("li.pcvc-sidebar-app-item").forEach((el) => {
            el.classList.remove("active");
        });
    }
    if (listItem) {
        listItem.classList.add("active");
    }

    fetch(projectApiPath(`api/applications.php?action=view&id=${id}`))
        .then(r => r.json())
        .then(res => {
            console.log("VIEW RESPONSE:", res);

            if (!res?.success || !res.data) {
                alert("Failed to load application details");
                currentViewApplicationId = null;
                document.getElementById("studyChoiceAddPanel")?.classList.add("hidden");
                listItem?.classList.remove("active");
                return;
            }

            emptyStateEl.classList.add("hidden");
            detailsEl.classList.remove("hidden");

            renderApplication(res.data, id);
loadJourney(id); // 👈 USE STUDENT APPLICATION ID


const dot = listItem?.querySelector(".unread-dot");
if (dot) dot.remove();

        })
        .catch((err) => {
            console.error("loadApplication error:", err);
            listItem?.classList.remove("active");
        });
}

/**
 * Superadmin delete: prefer API view meta (authoritative), fallback to page bootstrap flag.
 */
function resolveCanDeleteApplication(data) {
    const meta = data?.meta || {};
    if (meta.can_delete_application === true) {
        return true;
    }
    if (
        typeof window.CAN_DELETE_APPLICATION !== "undefined" &&
        window.CAN_DELETE_APPLICATION === true
    ) {
        return true;
    }
    return false;
}

/** Superadmin + DB column — authoritative from view API meta */
function resolveCanEditAssignment(data) {
    return data?.meta?.can_edit_assignment === true;
}

/** Who owns the file — shown to every role; editing is superadmin-only */
function renderAssignedReadOnly(meta) {
    const el = document.getElementById("applicationAssignedDisplay");
    if (!el) {
        return;
    }
    const def =
        typeof window.PCVC_DEFAULT_ASSIGNED_LABEL === "string" &&
        window.PCVC_DEFAULT_ASSIGNED_LABEL.length
            ? window.PCVC_DEFAULT_ASSIGNED_LABEL
            : "ScholarSync Global";
    const raw = meta?.assigned_display;
    const name =
        raw != null && String(raw).trim() !== ""
            ? String(raw).trim()
            : def;
    el.textContent = `Assigned: ${name}`;
}

function destroyAssignStaffSelect2IfAny(el) {
    if (!el || typeof window.jQuery !== "function" || !window.jQuery.fn.select2) {
        return;
    }
    const $el = window.jQuery(el);
    if ($el.length && $el.hasClass("select2-hidden-accessible")) {
        $el.select2("destroy");
    }
}

function formatAssignStaffSelect2Result(state) {
    if (!state.id) {
        return state.text;
    }
    const opt = state.element;
    if (!opt) {
        return state.text;
    }
    const name =
        (opt.getAttribute("data-pcvc-name") || "").trim() || state.text;
    const em = (opt.getAttribute("data-email") || "").trim();
    const ph = (opt.getAttribute("data-phone") || "").trim();
    const $root = window.jQuery("<div/>").addClass("pcvc-assign-opt");
    window.jQuery("<div/>").addClass("pcvc-assign-opt__name").text(name).appendTo($root);
    const sub = [em, ph].filter(Boolean).join(" · ");
    if (sub) {
        window.jQuery("<div/>").addClass("pcvc-assign-opt__meta").text(sub).appendTo($root);
    }
    return $root;
}

function formatAssignStaffSelect2Selection(state) {
    if (!state.id) {
        return state.text;
    }
    const opt = state.element;
    if (!opt) {
        return state.text;
    }
    const name =
        (opt.getAttribute("data-pcvc-name") || "").trim() || state.text;
    const em = (opt.getAttribute("data-email") || "").trim();
    const ph = (opt.getAttribute("data-phone") || "").trim();
    const parts = [name];
    if (em) {
        parts.push(em);
    }
    if (ph) {
        parts.push(ph);
    }
    return window
        .jQuery("<span/>")
        .addClass("pcvc-assign-sel-one")
        .text(parts.join(" · "));
}

/** Keep assign dropdown stable while searching (Select2 otherwise reorders matches). */
function sortAssignStaffSelect2Results(data) {
    if (!Array.isArray(data)) {
        return data;
    }
    return data.slice().sort((a, b) => {
        const ida = a.id != null && a.id !== false ? String(a.id) : "";
        const idb = b.id != null && b.id !== false ? String(b.id) : "";
        if (ida === "0") {
            return idb === "0" ? 0 : -1;
        }
        if (idb === "0") {
            return 1;
        }
        const elA = a.element;
        const elB = b.element;
        const nameA =
            (elA && typeof elA.getAttribute === "function"
                ? (elA.getAttribute("data-pcvc-name") || "").trim()
                : "") || String(a.text || "").trim();
        const nameB =
            (elB && typeof elB.getAttribute === "function"
                ? (elB.getAttribute("data-pcvc-name") || "").trim()
                : "") || String(b.text || "").trim();
        const cmp = nameA.localeCompare(nameB, undefined, { sensitivity: "base" });
        if (cmp !== 0) {
            return cmp;
        }
        return (parseInt(ida, 10) || 0) - (parseInt(idb, 10) || 0);
    });
}

function mountAssignStaffSelect2() {
    const el = document.getElementById("assignStaffSelect");
    if (!el || typeof window.jQuery !== "function" || !window.jQuery.fn.select2) {
        return;
    }
    const $el = window.jQuery(el);
    if ($el.hasClass("select2-hidden-accessible")) {
        $el.select2("destroy");
    }
    const $par = window.jQuery(document.body);
    $el.select2({
        theme: "bootstrap-5",
        width: "100%",
        minimumResultsForSearch: 0,
        dropdownParent: $par && $par.length ? $par : window.jQuery(document.body),
        templateResult: formatAssignStaffSelect2Result,
        templateSelection: formatAssignStaffSelect2Selection,
        sorter: sortAssignStaffSelect2Results
    });
}

function getAssignStaffSelectValue(sel) {
    if (!sel) {
        return 0;
    }
    if (typeof window.jQuery === "function" && window.jQuery.fn.select2) {
        const $el = window.jQuery(sel);
        if ($el.hasClass("select2-hidden-accessible")) {
            const v = $el.val();
            const s = v === null || v === undefined ? "" : String(v);
            return parseInt(s, 10) || 0;
        }
    }
    return parseInt(sel.value, 10) || 0;
}

function fillAssignStaffSelect() {
    const sel = document.getElementById("assignStaffSelect");
    if (!sel) {
        return;
    }
    destroyAssignStaffSelect2IfAny(sel);
    const defLabel =
        typeof window.PCVC_DEFAULT_ASSIGNED_LABEL === "string" &&
        window.PCVC_DEFAULT_ASSIGNED_LABEL.length
            ? window.PCVC_DEFAULT_ASSIGNED_LABEL
            : "ScholarSync Global";
    sel.innerHTML = "";
    const o0 = document.createElement("option");
    o0.value = "0";
    o0.textContent = `Unassigned (${defLabel})`;
    sel.appendChild(o0);
    const staff = Array.isArray(window.pcvcStaffAssignOptions)
        ? window.pcvcStaffAssignOptions
        : [];
    staff.forEach((row) => {
        const id = row?.id;
        if (!id) {
            return;
        }
        const opt = document.createElement("option");
        opt.value = String(id);
        const label = (row.label || `Staff #${id}`).trim();
        const em = String(row.email || "").trim();
        const ph = String(row.phone || "").trim();
        opt.setAttribute("data-pcvc-name", label);
        opt.setAttribute("data-email", em);
        opt.setAttribute("data-phone", ph);
        const bits = [label];
        if (em) {
            bits.push(em);
        }
        if (ph) {
            bits.push(ph);
        }
        opt.textContent = bits.join(" · ");
        sel.appendChild(opt);
    });
}

function renderAssignmentEditor(data) {
    const panel = document.getElementById("assignmentEditorPanel");
    if (!panel) {
        return;
    }
    if (!resolveCanEditAssignment(data)) {
        destroyAssignStaffSelect2IfAny(
            document.getElementById("assignStaffSelect")
        );
        panel.classList.add("hidden");
        return;
    }
    panel.classList.remove("hidden");
    fillAssignStaffSelect();
    const sel = document.getElementById("assignStaffSelect");
    const meta = data?.meta || {};
    const tid = Number(meta.assigned_to_admin_id || 0);
    if (sel) {
        sel.value = String(tid);
        if (sel.value !== String(tid) && tid > 0) {
            const opt = document.createElement("option");
            opt.value = String(tid);
            const disp = (meta.assigned_display || `Staff #${tid}`).trim();
            opt.setAttribute("data-pcvc-name", disp);
            opt.setAttribute("data-email", "");
            opt.setAttribute("data-phone", "");
            opt.textContent = disp;
            sel.appendChild(opt);
            sel.value = String(tid);
        }
    }
    mountAssignStaffSelect2();
    if (sel && typeof window.jQuery === "function" && window.jQuery.fn.select2) {
        const $s = window.jQuery(sel);
        if ($s.hasClass("select2-hidden-accessible")) {
            $s.val(String(tid)).trigger("change");
        }
    }
    const statusEl = document.getElementById("assignmentSaveStatus");
    if (statusEl) {
        statusEl.textContent = "";
    }
}

async function saveApplicationAssignment() {
    const panel = document.getElementById("assignmentEditorPanel");
    if (!panel || panel.classList.contains("hidden")) {
        return;
    }
    const appId = currentViewApplicationId;
    if (!appId) {
        alert("Select an application first.");
        return;
    }
    const sel = document.getElementById("assignStaffSelect");
    const statusEl = document.getElementById("assignmentSaveStatus");
    const btn = document.getElementById("btnSaveAssignment");
    const newVal = String(getAssignStaffSelectValue(sel));

    if (statusEl) {
        statusEl.textContent = "";
    }
    const prevText = btn ? btn.textContent : "";
    if (btn) {
        btn.disabled = true;
        btn.textContent = "Saving…";
    }

    const fd = new FormData();
    fd.append("application_id", String(appId));
    fd.append("assigned_to_admin_id", newVal);

    try {
        const res = await fetch(
            projectApiPath("api/applications.php?action=update_assignment"),
            {
                method: "POST",
                body: fd,
                credentials: "same-origin"
            }
        );
        const raw = await res.text();
        let json;
        try {
            json = JSON.parse(raw);
        } catch (e) {
            console.error("assignment save (non-JSON):", raw);
            if (statusEl) {
                statusEl.textContent = "Invalid server response.";
            }
            return;
        }
        if (!json?.success) {
            if (statusEl) {
                statusEl.textContent = json?.message || "Save failed.";
            }
            return;
        }
        const d = json.data || {};
        let msg = d.notified
            ? "Assignment saved. The new assignee was notified (email / WhatsApp where configured)."
            : "Assignment saved.";
        const jobsCreated = Number(d.jobs_created || 0);
        if (jobsCreated > 0) {
            msg += ` ${jobsCreated} job${jobsCreated > 1 ? "s" : ""} added to their Job Do List.`;
            showToast(msg, () => {
                window.location.href = "job_todo_list.php";
            }, "Assignment");
        } else {
            showToast(msg, null, "Assignment");
        }
        if (statusEl) {
            statusEl.textContent = msg;
        }
        loadStudents();
        loadApplication(appId, null);
    } catch (err) {
        console.error(err);
        if (statusEl) {
            statusEl.textContent = "Network error.";
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = prevText || "Save assignment";
        }
    }
}

/**
 * =====================================================
 * RENDER FULL APPLICATION
 * =====================================================
 */
function renderApplication(data, applicationNumericId) {
    const {
        bio = {},
        address = {},
        parents = {},
        emergency = {},
        education = {},
        study_choices = [],
        study_choice_suggestions = [],
        documents = {},
        document_items = [],
        agent = {},
        meta = {}
    } = data;

    const canDelete = resolveCanDeleteApplication(data);
    window.__lastCanDeleteApplication = canDelete;

    // HEADER
    setText("studentName", `${bio.first_name || ""} ${bio.last_name || ""}`.trim());
    setText("studentEmail", bio.email);
    setText("studentPhone", bio.phone);
    const appMetaEl = document.getElementById("applicationMeta");
    if (appMetaEl) {
        if (meta.created_at) {
            const ts = String(meta.created_at);
            appMetaEl.innerHTML = `Applied <span data-time-ago="${escapeAttr(ts)}" class="tabular-nums">${timeAgo(ts)}</span>`;
        } else {
            appMetaEl.textContent = "-";
        }
    }

    renderAssignedReadOnly(meta);

    // PERSONAL
    setText("pGender", bio.gender);
    setText("pDob", bio.dob);
    setText("pNationality", bio.nationality);
    setText("pBirthCountry", bio.country_of_birth);
    setText("pPassport", bio.passport_number);
    setText("pNationalId", bio.national_id);

    // ADDRESS
    setText("addrLine", `${address.line1 || ""} ${address.line2 || ""}`.trim());
    setText("addrCity", `${address.city || ""} ${address.state || ""}`.trim());
    setText("addrPostal", address.postal_code);

    // FAMILY & EMERGENCY
    setText("pFather", parents.father);
    setText("pMother", parents.mother);
    setText("eName", emergency.name);
    setText("eEmail", emergency.email);
    setText("ePhone", emergency.phone);
    setText("eRelation", emergency.relationship);

    // EDUCATION
    setText("eduInstitution", education.institution);
    setText("eduCountry", education.country);
    setText("eduStart", education.start_date);
    setText("eduGrad", education.graduation);
    setText(
        "eduGap",
        education.study_gap === "Yes"
            ? education.study_gap_details
            : "No"
    );

    currentViewApplicationId = applicationNumericId;
    currentStudentBio = {
        first_name: String(bio.first_name || "").trim(),
        last_name: String(bio.last_name || "").trim(),
        email: String(bio.email || "").trim(),
        phone: String(bio.phone || "").trim(),
    };

    renderStudyChoices(study_choices);
    renderRelatedStudySuggestions(study_choice_suggestions);
    currentDocumentItems = normalizeDocumentItems(document_items, documents);
    renderDocuments(currentDocumentItems);
    updateMissingDocsNotifyButton();
    renderAgent(agent);

    renderDeleteControls(applicationNumericId, canDelete);
    renderAssignmentEditor(data);
    const scPanel = document.getElementById("studyChoiceAddPanel");
    if (scPanel) {
        scPanel.classList.remove("hidden");
        void prepareStudyChoiceAddForm();
    }
}

/**
 * =====================================================
 * DELETE APPLICATION (superadmin only — enforced server-side)
 * =====================================================
 */
function buildDeleteApplicationButton(applicationNumericId) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className =
        "inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-red-600 border border-red-700 rounded-lg shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 transition whitespace-nowrap w-full sm:w-auto";
    btn.setAttribute("aria-label", "Delete this application permanently");
    btn.title = "Permanently remove this application (Superadmin only)";
    btn.innerHTML = `
        <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.651 51.651 0 0 0-1.819-.004m-7.5 0c-.834 0-1.64.105-2.448.298" />
        </svg>
        <span>Delete application</span>
    `;
    btn.addEventListener("click", () => deleteApplication(applicationNumericId));
    return btn;
}

function renderDeleteControls(applicationNumericId, canDelete) {
    const dyn = document.getElementById("applicationActionsDynamic");
    if (dyn) {
        dyn.innerHTML = "";
    }

    const headerBtn = document.getElementById("btnDeleteApplicationHeader");
    const journeyBtn = document.getElementById("btnDeleteApplicationJourney");

    if (headerBtn) {
        if (!applicationNumericId || !canDelete) {
            headerBtn.style.display = "none";
            headerBtn.disabled = true;
            headerBtn.onclick = null;
        } else {
            headerBtn.style.display = "inline-flex";
            headerBtn.disabled = false;
            headerBtn.onclick = () => deleteApplication(applicationNumericId);
        }
    }

    if (journeyBtn) {
        if (!applicationNumericId || !canDelete) {
            journeyBtn.style.display = "none";
            journeyBtn.disabled = true;
            journeyBtn.onclick = null;
        } else {
            journeyBtn.style.display = "flex";
            journeyBtn.disabled = false;
            journeyBtn.onclick = () => deleteApplication(applicationNumericId);
        }
    }

    const journeyWrap = document.getElementById("journeyDeleteActions");
    if (journeyWrap) {
        if (!canDelete || !applicationNumericId) {
            journeyWrap.classList.add("hidden");
        } else {
            journeyWrap.classList.remove("hidden");
        }
    }

    if (
        canDelete &&
        applicationNumericId &&
        !headerBtn &&
        !journeyBtn &&
        dyn
    ) {
        dyn.appendChild(buildDeleteApplicationButton(applicationNumericId));
    }

    const journeyWrapOnly = document.getElementById("journeyDeleteActions");
    if (
        canDelete &&
        applicationNumericId &&
        !journeyBtn &&
        journeyWrapOnly
    ) {
        journeyWrapOnly.innerHTML = "";
        journeyWrapOnly.appendChild(
            buildDeleteApplicationButton(applicationNumericId)
        );
        journeyWrapOnly.classList.remove("hidden");
    }
}

/**
 * Journey timeline is filled async; re-apply sidebar delete after DOM settles.
 */
function syncJourneyDeleteOnly(applicationNumericId, canDelete) {
    const journeyBtn = document.getElementById("btnDeleteApplicationJourney");
    if (journeyBtn) {
        if (!applicationNumericId || !canDelete) {
            journeyBtn.style.display = "none";
            journeyBtn.disabled = true;
            journeyBtn.onclick = null;
        } else {
            journeyBtn.style.display = "flex";
            journeyBtn.disabled = false;
            journeyBtn.onclick = () => deleteApplication(applicationNumericId);
        }
        const journeyWrap = document.getElementById("journeyDeleteActions");
        if (journeyWrap) {
            if (!canDelete || !applicationNumericId) {
                journeyWrap.classList.add("hidden");
            } else {
                journeyWrap.classList.remove("hidden");
            }
        }
        return;
    }

    const journeyWrap = document.getElementById("journeyDeleteActions");
    if (!journeyWrap) {
        return;
    }
    journeyWrap.innerHTML = "";
    if (!applicationNumericId || !canDelete) {
        journeyWrap.classList.add("hidden");
        return;
    }
    journeyWrap.appendChild(buildDeleteApplicationButton(applicationNumericId));
    journeyWrap.classList.remove("hidden");
}

async function deleteApplication(applicationNumericId) {
    if (!window.__lastCanDeleteApplication) {
        alert("Only Super Admin can delete applications.");
        return;
    }
    if (
        !confirm(
            "Permanently delete this application and related jobs? This cannot be undone."
        )
    ) {
        return;
    }

    const fd = new FormData();
    fd.append("id", String(applicationNumericId));

    try {
        const res = await fetch(
            projectApiPath("api/applications.php?action=delete"),
            {
                method: "POST",
                body: fd,
                credentials: "same-origin"
            }
        );
        const raw = await res.text();
        let json;
        try {
            json = JSON.parse(raw);
        } catch (parseErr) {
            console.error("Delete response (not JSON):", raw);
            alert(
                res.ok
                    ? "Delete failed: invalid server response."
                    : `Delete failed (HTTP ${res.status}).`
            );
            return;
        }

        if (!json?.success) {
            alert(json?.message || "Delete failed.");
            return;
        }

        const dyn = document.getElementById("applicationActionsDynamic");
        if (dyn) {
            dyn.innerHTML = "";
        }
        const headerBtn = document.getElementById("btnDeleteApplicationHeader");
        const journeyBtn = document.getElementById("btnDeleteApplicationJourney");
        if (headerBtn) {
            headerBtn.style.display = "none";
            headerBtn.disabled = true;
            headerBtn.onclick = null;
        }
        if (journeyBtn) {
            journeyBtn.style.display = "none";
            journeyBtn.disabled = true;
            journeyBtn.onclick = null;
        }
        const journeyDel = document.getElementById("journeyDeleteActions");
        if (journeyDel) {
            journeyDel.classList.add("hidden");
        }
        if (detailsEl) {
            detailsEl.classList.add("hidden");
        }
        if (emptyStateEl) {
            emptyStateEl.classList.remove("hidden");
        }
        const journeyPanel = document.getElementById("applicationTracking");
        if (journeyPanel) {
            journeyPanel.classList.add("hidden");
        }
        if (aiPanelEl) {
            aiPanelEl.classList.add("hidden");
        }

        loadStudents();
    } catch (e) {
        console.error("deleteApplication:", e);
        alert("Delete failed. Please try again.");
    }
}
/**
 * =====================================================
 * LOAD APPLICATION JOURNEY (TRACK EVERYTHING)
 * =====================================================
 */
/**
 * =====================================================
 * LOAD APPLICATION JOURNEY
 * =====================================================
 */
function loadJourney(applicationId) {
    if (!applicationId) return;

    const panel = document.getElementById("applicationTracking");
    const timeline = document.getElementById("trackingTimeline");
    const empty = document.getElementById("journeyEmpty");

    if (!panel || !timeline) return;

    const canDelete = !!window.__lastCanDeleteApplication;

    panel.classList.remove("hidden");
    timeline.innerHTML = `
        <div class="text-xs text-slate-400">Loading journey…</div>
    `;
    empty.classList.add("hidden");

    fetch(projectApiPath(`api/applications.php?action=journey&id=${applicationId}`))
        .then(r => r.json())
        .then(res => {
            console.log("JOURNEY RESPONSE:", res);

            if (!res?.success || !Array.isArray(res.data) || res.data.length === 0) {
                timeline.innerHTML = "";
                empty.classList.remove("hidden");
                syncJourneyDeleteOnly(applicationId, canDelete);
                return;
            }

            timeline.innerHTML = "";
            res.data.forEach(job => {
                timeline.appendChild(renderJourneyStep(job));
            });
            refreshTimeAgoLabels();
            syncJourneyDeleteOnly(applicationId, canDelete);
        })
        .catch(err => {
            console.error("Journey load error:", err);
            timeline.innerHTML = "";
            empty.classList.remove("hidden");
            syncJourneyDeleteOnly(applicationId, canDelete);
        });
}
/**
 * =====================================================
 * RENDER JOURNEY STEP
 * =====================================================
 */
function renderJourneyStep(job) {
    const completed = job.status === "completed";

    const el = document.createElement("div");
    el.className = "relative flex gap-4";

    el.innerHTML = `
        <!-- DOT -->
        <div class="relative z-10 pt-1">
            <span class="timeline-dot ${completed ? "completed" : ""}"></span>
        </div>

        <!-- CONTENT -->
        <div class="pb-6">
            <div class="font-semibold text-slate-800">
                ${escapeHTML(job.university_name || "Unknown University")}
            </div>

            <div class="text-slate-500 mt-0.5">
                Platform: ${escapeHTML(job.platform_name || "—")}
            </div>

            <div class="text-slate-500">
                Admin: ${escapeHTML(job.admin_name || "—")}
            </div>

            <div class="text-[11px] mt-1 ${completed ? "text-green-600" : "text-slate-400"}">
                ${completed ? "Completed" : "In progress"} • <span data-time-ago="${escapeAttr(String(job.created_at || ""))}" class="tabular-nums">${timeAgo(job.created_at)}</span>
            </div>
        </div>
    `;

    return el;
}

/**
 * =====================================================
 * STUDY CHOICES
 * =====================================================
 */
function renderStudyChoices(choices) {
    const tbody = document.getElementById("studyChoicesTable");
    tbody.innerHTML = "";

    if (!choices.length) {
        tbody.innerHTML =
            `<tr><td colspan="5" class="p-3 text-center text-gray-400">
                No study choices
            </td></tr>`;
        return;
    }

    choices.forEach(c => {
        tbody.insertAdjacentHTML("beforeend", `
            <tr>
                <td class="p-2 border">${escapeHTML(c.region)}</td>
                <td class="p-2 border">${escapeHTML(c.university)}</td>
                <td class="p-2 border">${escapeHTML(c.university_country || "-")}</td>
                <td class="p-2 border">${escapeHTML(c.program_level_abbr || c.program_level)}</td>
                <td class="p-2 border">${escapeHTML(c.program)}</td>
            </tr>
        `);
    });
}

/**
 * Related program suggestions (approval queue)
 */
function renderRelatedStudySuggestions(suggestions) {
    const panel = document.getElementById("relatedStudySuggestionsPanel");
    const tbody = document.getElementById("relatedStudySuggestionsTable");
    const countEl = document.getElementById("relatedStudySuggestionsCount");
    if (!panel || !tbody) return;

    const rows = Array.isArray(suggestions) ? suggestions : [];
    tbody.innerHTML = "";

    if (!rows.length) {
        panel.classList.add("hidden");
        if (countEl) countEl.textContent = "";
        return;
    }

    panel.classList.remove("hidden");
    if (countEl) countEl.textContent = `${rows.length} pending`;

    rows.forEach((s) => {
        const id = Number(s.id || 0);
        const lvl = s.program_level_abbr || s.program_level || "";
        tbody.insertAdjacentHTML(
            "beforeend",
            `<tr data-suggestion-id="${id}">
                <td class="p-2 border">
                    <div class="font-medium text-slate-900">${escapeHTML(s.university || "")}</div>
                    <div class="text-xs text-slate-500">${escapeHTML(s.region || "")}${s.university_country ? " · " + escapeHTML(s.university_country) : ""}</div>
                </td>
                <td class="p-2 border">
                    <div>${escapeHTML(s.program || "")}</div>
                    <div class="text-xs text-slate-500">${escapeHTML(lvl)}</div>
                </td>
                <td class="p-2 border text-xs text-slate-600">
                    ${escapeHTML(s.source_university || "")}<br>${escapeHTML(s.source_program || "")}
                </td>
                <td class="p-2 border text-xs">${escapeHTML(s.admins_in_charge || "Unassigned")}</td>
                <td class="p-2 border text-xs">
                    <span class="font-semibold text-amber-800">${escapeHTML(String(s.match_score || ""))}</span>
                    <div class="text-slate-500">${escapeHTML(s.match_reason || "")}</div>
                </td>
                <td class="p-2 border text-right whitespace-nowrap">
                    <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-600 text-white hover:bg-emerald-700 mr-1"
                        data-approve-suggestion="${id}">Approve</button>
                    <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded-md bg-slate-200 text-slate-700 hover:bg-slate-300"
                        data-reject-suggestion="${id}">Reject</button>
                </td>
            </tr>`
        );
    });

    tbody.querySelectorAll("[data-approve-suggestion]").forEach((btn) => {
        btn.addEventListener("click", () => decideRelatedSuggestion(Number(btn.getAttribute("data-approve-suggestion")), "approve"));
    });
    tbody.querySelectorAll("[data-reject-suggestion]").forEach((btn) => {
        btn.addEventListener("click", () => decideRelatedSuggestion(Number(btn.getAttribute("data-reject-suggestion")), "reject"));
    });
}

async function decideRelatedSuggestion(suggestionId, decision) {
    if (!suggestionId) return;
    const action =
        decision === "approve"
            ? "approve_study_choice_suggestion"
            : "reject_study_choice_suggestion";

    if (decision === "approve") {
        const ok = confirm("Approve this suggestion and add it to the student’s study choices? The student will be emailed.");
        if (!ok) return;
    } else {
        const ok = confirm("Reject this related-program suggestion?");
        if (!ok) return;
    }

    try {
        const fd = new FormData();
        fd.append("suggestion_id", String(suggestionId));
        if (decision === "approve") fd.append("notify_student", "1");

        const res = await fetch(projectApiPath(`api/applications.php?action=${action}`), {
            method: "POST",
            body: fd,
            credentials: "same-origin",
        });
        const json = await res.json();
        if (!json?.success) {
            throw new Error(json?.message || "Request failed");
        }
        const d = json.data || {};

        if (decision === "approve" && Array.isArray(d.study_choices)) {
            renderStudyChoices(d.study_choices);
        }
        if (Array.isArray(d.suggestions)) {
            renderRelatedStudySuggestions(d.suggestions);
        } else if (currentViewApplicationId) {
            const viewRes = await fetch(
                projectApiPath(`api/applications.php?action=view&id=${currentViewApplicationId}`),
                { credentials: "same-origin" }
            );
            const viewJson = await viewRes.json();
            const payload = viewJson.data || {};
            if (Array.isArray(payload.study_choice_suggestions)) {
                renderRelatedStudySuggestions(payload.study_choice_suggestions);
            }
            if (decision === "approve" && Array.isArray(payload.study_choices)) {
                renderStudyChoices(payload.study_choices);
            }
        }

        if (typeof showToast === "function") {
            showToast(d.message || d.msg || "Updated", null, "Related programs");
        }
    } catch (e) {
        alert(e.message || "Could not update suggestion");
    }
}

/**
 * =====================================================
 * DOCUMENTS (admin upload / replace + missing-doc notify)
 * =====================================================
 */
function normalizeDocumentItems(documentItems, legacyDocs) {
    if (Array.isArray(documentItems) && documentItems.length) {
        return documentItems;
    }
    const legacy = legacyDocs && typeof legacyDocs === "object" ? legacyDocs : {};
    const defs = [
        { key: "degree_transcripts", label: "Degree Transcripts", multiple: true },
        { key: "high_school_degree", label: "High School Degree", multiple: false },
        { key: "passport", label: "Passport", multiple: false },
        { key: "cv_resume", label: "CV / Resume", multiple: false },
        { key: "personal_statement", label: "Personal Statement", multiple: false },
        { key: "recommendation_letters", label: "Recommendation Letters", multiple: false },
        { key: "english_certificate", label: "English Certificate", multiple: false },
        { key: "birth_certificate", label: "Birth Certificate", multiple: false },
        { key: "payment_proof", label: "Payment Proof", multiple: false },
    ];
    return defs.map((def) => {
        const raw = legacy[def.key];
        const paths = [];
        if (def.multiple) {
            const arr = Array.isArray(raw) ? raw : [];
            arr.forEach((p) => {
                if (typeof p === "string" && p.trim()) paths.push(p);
            });
        } else if (typeof raw === "string" && raw.trim()) {
            paths.push(raw);
        }
        return {
            key: def.key,
            label: def.label,
            multiple: def.multiple,
            paths,
            present: paths.length > 0,
        };
    });
}

function updateMissingDocsNotifyButton() {
    const btn = document.getElementById("btnNotifyMissingDocs");
    const bar = document.getElementById("missingDocsReminderBar");
    const hint = document.getElementById("missingDocsReminderHint");
    if (!btn) return;

    if (!currentViewApplicationId) {
        if (bar) bar.classList.add("hidden");
        btn.disabled = true;
        return;
    }

    if (bar) bar.classList.remove("hidden");
    btn.disabled = false;

    const missing = (currentDocumentItems || []).filter((it) => !it.present && !(Array.isArray(it.paths) && it.paths.length));
    if (hint) {
        if (missing.length > 0) {
            const names = missing.slice(0, 2).map((it) => it.label || it.key).join(", ");
            const extra = missing.length > 2 ? ` and ${missing.length - 2} more` : "";
            hint.textContent = `${missing.length} missing: ${names}${extra}. Send a WhatsApp + email reminder.`;
            btn.textContent = `Notify student (${missing.length})`;
        } else {
            hint.textContent = "All documents are on file. You can still send a custom reminder.";
            btn.textContent = "Send reminder";
        }
    }
}

function renderDocuments(items) {
    const list = document.getElementById("documentsList");
    if (!list) return;
    list.innerHTML = "";

    const rows = Array.isArray(items) ? items : [];
    if (!rows.length) {
        list.innerHTML = `<div class="text-sm text-gray-400">No document types configured</div>`;
        return;
    }

    rows.forEach((item) => {
        list.appendChild(buildDocumentAdminCard(item));
    });
}

function buildDocumentAdminCard(item) {
    const key = String(item.key || "");
    const label = String(item.label || key.replace(/_/g, " "));
    const paths = Array.isArray(item.paths) ? item.paths.filter(Boolean) : [];
    const urls = Array.isArray(item.urls) ? item.urls.filter(Boolean) : [];
    const present = paths.length > 0 || item.present === true;
    const primaryPath = paths[0] || "";
    const primaryUrl = urls[0] || (primaryPath ? pcvcSecureFileUrl(primaryPath, true) : "");
    const fileName = primaryPath ? primaryPath.split("/").pop() : "";

    const div = document.createElement("div");
    div.className = present
        ? "flex flex-col gap-2 p-3 border border-slate-200 rounded-lg bg-slate-50"
        : "flex flex-col gap-2 p-3 border border-amber-200 rounded-lg bg-amber-50";

    const statusBadge = present
        ? `<span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">On file</span>`
        : `<span class="text-[10px] font-semibold uppercase tracking-wide text-amber-800">Missing</span>`;

    const fileLine = present
        ? `<span class="text-xs text-slate-500 truncate max-w-full" title="${escapeAttr(fileName)}">${escapeHTML(fileName)}</span>`
        : `<span class="text-xs text-amber-700">Not uploaded yet</span>`;

    const viewBtn = present
        ? `<a href="${escapeAttr(primaryUrl)}" target="_blank" rel="noopener" class="text-blue-600 text-xs font-semibold hover:underline shrink-0">View</a>`
        : "";

    const actionLabel = present ? "Replace" : "Upload";

    div.innerHTML = `
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-semibold text-slate-900">${escapeHTML(label)}</span>
                    ${statusBadge}
                </div>
                ${fileLine}
            </div>
            ${viewBtn}
        </div>
        <div class="flex justify-end">
            <button type="button" class="pcvc-doc-upload-btn rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" data-doc-key="${escapeAttr(key)}">${escapeHTML(actionLabel)}</button>
        </div>
    `;

    div.querySelector(".pcvc-doc-upload-btn")?.addEventListener("click", () => {
        promptDocumentUpload(key);
    });

    return div;
}

function promptDocumentUpload(docKey) {
    if (!currentViewApplicationId) {
        alert("Select an application first.");
        return;
    }
    const inp = document.createElement("input");
    inp.type = "file";
    inp.accept = ".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,application/pdf,image/*";
    inp.addEventListener("change", () => {
        const file = inp.files?.[0];
        if (file) void uploadApplicationDocument(docKey, file);
    });
    inp.click();
}

async function uploadApplicationDocument(docKey, file) {
    if (!currentViewApplicationId || !docKey || !file) return;

    const fd = new FormData();
    fd.append("application_id", String(currentViewApplicationId));
    fd.append("document_key", docKey);
    fd.append("file", file);

    try {
        const res = await fetch(
            projectApiPath("api/applications.php?action=replace_document"),
            { method: "POST", credentials: "same-origin", body: fd }
        );
        const data = await res.json();
        if (!res.ok || data.success === false) {
            const err = data.message || data.errors?.error || "Upload failed";
            alert(err);
            return;
        }
        if (Array.isArray(data.data?.documents)) {
            currentDocumentItems = data.data.documents;
            renderDocuments(currentDocumentItems);
            updateMissingDocsNotifyButton();
        } else {
            loadApplication(currentViewApplicationId, null);
        }
        showToast(data.data?.message || "Document saved.");
    } catch (e) {
        console.error("uploadApplicationDocument:", e);
        alert("Upload failed. Check your connection and try again.");
    }
}

function wireMissingDocsModalOnce() {
    if (missingDocsModalWired) return;
    missingDocsModalWired = true;

    document.getElementById("btnNotifyMissingDocs")?.addEventListener("click", openMissingDocsModal);
    document.getElementById("missingDocsModalClose")?.addEventListener("click", closeMissingDocsModal);
    document.getElementById("missingDocsCancel")?.addEventListener("click", closeMissingDocsModal);
    document.getElementById("missingDocsSendBtn")?.addEventListener("click", () => {
        void sendMissingDocsNotification();
    });

    document.getElementById("missingDocsModal")?.addEventListener("click", (ev) => {
        if (ev.target?.id === "missingDocsModal") closeMissingDocsModal();
    });
}

function buildMissingDocsDefaultMessage(selectedLabels) {
    const fullName = `${currentStudentBio.first_name || ""} ${currentStudentBio.last_name || ""}`.trim();
    const greeting = fullName ? `Dear ${fullName},` : "Dear Applicant,";
    const docs = (Array.isArray(selectedLabels) && selectedLabels.length)
        ? selectedLabels.map((l) => `  • ${l}`).join("\n")
        : "  • (no documents selected yet)";

    return [
        greeting,
        "",
        "Thank you for choosing ScholarSync Global.",
        "We are reviewing your application and still need the following document(s) to move forward:",
        "",
        docs,
        "",
        "Kindly upload them through your student portal as soon as possible. Once received, our team will continue processing your application without delay.",
        "",
        "If you have any difficulty uploading or need clarification on any document, simply reply to this message and we will assist you right away.",
        "",
        "Warm regards,",
        "Admissions Team",
        "ScholarSync Global",
    ].join("\n");
}

function collectMissingDocsSelectedLabels() {
    const checked = [...document.querySelectorAll('#missingDocsChecklist input[name="missing_doc_key"]:checked')];
    const keys = checked.map((el) => el.value).filter(Boolean);
    return keys
        .map((k) => (currentDocumentItems.find((it) => it.key === k) || {}).label || k.replace(/_/g, " "))
        .filter(Boolean);
}

function refreshMissingDocsMessage() {
    const ta = document.getElementById("missingDocsMessage");
    if (!ta) return;
    ta.value = buildMissingDocsDefaultMessage(collectMissingDocsSelectedLabels());
    ta.dataset.userEdited = "0";
}

function openMissingDocsModal() {
    if (!currentViewApplicationId) {
        alert("Select an application first.");
        return;
    }
    const modal = document.getElementById("missingDocsModal");
    const checklist = document.getElementById("missingDocsChecklist");
    const statusEl = document.getElementById("missingDocsSendStatus");
    if (!modal || !checklist) return;

    // Pre-fill editable contact fields
    const phoneEl = document.getElementById("missingDocsPhone");
    const emailEl = document.getElementById("missingDocsEmail");
    if (phoneEl) phoneEl.value = (currentStudentBio.phone || "").replace(/\s+/g, " ").trim();
    if (emailEl) emailEl.value = currentStudentBio.email || "";

    // Build checklist
    checklist.innerHTML = "";
    const items = currentDocumentItems.length
        ? currentDocumentItems
        : normalizeDocumentItems([], {});

    items.forEach((item) => {
        const row = document.createElement("label");
        row.className = "flex items-start gap-2 text-sm cursor-pointer";
        const onFile = item.present === true || (Array.isArray(item.paths) && item.paths.length > 0);
        row.innerHTML = `
            <input type="checkbox" class="mt-0.5" name="missing_doc_key" value="${escapeAttr(item.key)}" ${onFile ? "" : "checked"}>
            <span>
                <span class="font-medium text-slate-800">${escapeHTML(item.label || item.key)}</span>
                ${onFile ? '<span class="text-slate-400 text-xs"> (already on file)</span>' : '<span class="text-amber-700 text-xs"> (missing)</span>'}
            </span>
        `;
        checklist.appendChild(row);
    });

    // Default message, auto-refresh as checkboxes change (unless user edited it)
    const ta = document.getElementById("missingDocsMessage");
    if (ta) {
        ta.value = buildMissingDocsDefaultMessage(collectMissingDocsSelectedLabels());
        ta.dataset.userEdited = "0";
        ta.addEventListener("input", () => { ta.dataset.userEdited = "1"; }, { once: false });
    }
    checklist.addEventListener("change", () => {
        if (ta && ta.dataset.userEdited !== "1") {
            ta.value = buildMissingDocsDefaultMessage(collectMissingDocsSelectedLabels());
        }
    });
    document.getElementById("missingDocsResetMessage")?.addEventListener("click", refreshMissingDocsMessage, { once: false });

    if (statusEl) {
        statusEl.classList.add("hidden");
        statusEl.textContent = "";
        statusEl.className = "text-sm hidden";
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");
    modal.setAttribute("aria-hidden", "false");
}

function closeMissingDocsModal() {
    const modal = document.getElementById("missingDocsModal");
    if (!modal) return;
    modal.classList.add("hidden");
    modal.classList.remove("flex");
    modal.setAttribute("aria-hidden", "true");
}

/* =====================================================
   SEND — SMART SPINNER (button + overlay + progressive stages)
===================================================== */
function setMissingDocsSpinner(active, stages) {
    const overlay = document.getElementById("missingDocsSendingOverlay");
    const titleEl = document.getElementById("missingDocsSendingTitle");
    const hintEl  = document.getElementById("missingDocsSendingHint");
    const sendBtn = document.getElementById("missingDocsSendBtn");
    const cancel  = document.getElementById("missingDocsCancel");
    const closeBt = document.getElementById("missingDocsModalClose");

    if (active) {
        if (overlay) {
            overlay.classList.remove("hidden");
            overlay.setAttribute("aria-hidden", "false");
        }
        if (sendBtn) {
            if (!sendBtn.dataset.origHtml) sendBtn.dataset.origHtml = sendBtn.innerHTML;
            sendBtn.disabled = true;
            sendBtn.innerHTML = `
                <span class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.35"></circle>
                        <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round" fill="none"></path>
                    </svg>
                    Sending…
                </span>`;
        }
        if (cancel)  cancel.disabled = true;
        if (closeBt) closeBt.disabled = true;

        // Stage cycler
        if (Array.isArray(stages) && stages.length && titleEl && hintEl) {
            let i = 0;
            titleEl.textContent = stages[i].title;
            hintEl.textContent  = stages[i].hint;
            clearInterval(window.__missingDocsStageTimer);
            window.__missingDocsStageTimer = setInterval(() => {
                i = (i + 1) % stages.length;
                titleEl.textContent = stages[i].title;
                hintEl.textContent  = stages[i].hint;
            }, 1400);
        }
    } else {
        clearInterval(window.__missingDocsStageTimer);
        window.__missingDocsStageTimer = null;
        if (overlay) {
            overlay.classList.add("hidden");
            overlay.setAttribute("aria-hidden", "true");
        }
        if (sendBtn) {
            sendBtn.disabled = false;
            if (sendBtn.dataset.origHtml) {
                sendBtn.innerHTML = sendBtn.dataset.origHtml;
                delete sendBtn.dataset.origHtml;
            }
        }
        if (cancel)  cancel.disabled = false;
        if (closeBt) closeBt.disabled = false;
    }
}

async function sendMissingDocsNotification() {
    if (!currentViewApplicationId) return;

    const keys = [...document.querySelectorAll('#missingDocsChecklist input[name="missing_doc_key"]:checked')]
        .map((el) => el.value)
        .filter(Boolean);
    const sendWa = document.getElementById("missingSendWa")?.checked ?? true;
    const sendEm = document.getElementById("missingSendEmail")?.checked ?? true;
    const message = (document.getElementById("missingDocsMessage")?.value || "").trim();
    const phoneOverride = (document.getElementById("missingDocsPhone")?.value || "").trim();
    const emailOverride = (document.getElementById("missingDocsEmail")?.value || "").trim();
    const statusEl = document.getElementById("missingDocsSendStatus");

    if (!keys.length) {
        alert("Select at least one document to request.");
        return;
    }
    if (!sendWa && !sendEm) {
        alert("Select WhatsApp and/or email.");
        return;
    }
    if (sendWa && !phoneOverride) {
        alert("Phone number is required for WhatsApp.");
        document.getElementById("missingDocsPhone")?.focus();
        return;
    }
    if (sendEm && !emailOverride) {
        alert("Email is required for email reminder.");
        document.getElementById("missingDocsEmail")?.focus();
        return;
    }

    const fd = new FormData();
    fd.append("application_id", String(currentViewApplicationId));
    fd.append("missing_keys", JSON.stringify(keys));
    fd.append("custom_note", message);
    fd.append("custom_message", message);
    fd.append("override_phone", phoneOverride);
    fd.append("override_email", emailOverride);
    fd.append("send_whatsapp", sendWa ? "1" : "0");
    fd.append("send_email", sendEm ? "1" : "0");

    // Build smart progressive stages based on what's being sent
    const stages = [{ title: "Preparing reminder…", hint: "Validating phone, email and message" }];
    if (sendWa) {
        stages.push({ title: "Checking WhatsApp number…", hint: "Verifying " + phoneOverride + " is on WhatsApp" });
        stages.push({ title: "Sending WhatsApp…", hint: "Delivering via approved template" });
    }
    if (sendEm) stages.push({ title: "Sending Email…", hint: "Dispatching to " + emailOverride });
    stages.push({ title: "Finalising…", hint: "Just a moment" });

    setMissingDocsSpinner(true, stages);
    if (statusEl) {
        statusEl.classList.add("hidden");
        statusEl.textContent = "";
        statusEl.className = "text-sm hidden";
    }

    try {
        const res = await fetch(
            projectApiPath("api/applications.php?action=notify_missing_documents"),
            { method: "POST", credentials: "same-origin", body: fd }
        );
        const data = await res.json();
        if (!res.ok || data.success === false) {
            const err = data.errors?.error || data.message || "Send failed";
            if (statusEl) {
                statusEl.classList.remove("hidden");
                statusEl.className = "text-sm text-red-600";
                statusEl.textContent = err;
            } else {
                alert(err);
            }
            return;
        }
        const wa = data.data?.whatsapp || {};
        const em = data.data?.email || {};
        const okParts = [];
        const skipParts = [];
        if (wa.sent) {
            okParts.push(`WhatsApp (${wa.method || "sent"})`);
        } else if (sendWa) {
            if (wa.not_on_whatsapp) {
                skipParts.push("WhatsApp skipped — number not on WhatsApp");
            } else if (wa.error) {
                skipParts.push(`WhatsApp failed: ${wa.error}`);
            }
        }
        if (em.sent) {
            okParts.push("Email");
        } else if (sendEm && em.error) {
            skipParts.push(`Email failed: ${em.error}`);
        }

        let msg = "";
        let tone = "text-emerald-700";
        if (okParts.length && skipParts.length) {
            msg = `Sent via ${okParts.join(" and ")}. ${skipParts.join(". ")}.`;
            tone = "text-amber-700";
        } else if (okParts.length) {
            msg = `Sent via ${okParts.join(" and ")}.`;
        } else if (skipParts.length) {
            msg = skipParts.join(". ") + ".";
            tone = "text-red-600";
        } else {
            msg = data.data?.message || "Notification sent.";
        }

        if (statusEl) {
            statusEl.classList.remove("hidden");
            statusEl.className = "text-sm " + tone;
            statusEl.textContent = msg;
        }
        showToast(msg);

        // Only auto-close on full success
        if (okParts.length && !skipParts.length) {
            setTimeout(closeMissingDocsModal, 1200);
        }
    } catch (e) {
        console.error("sendMissingDocsNotification:", e);
        if (statusEl) {
            statusEl.classList.remove("hidden");
            statusEl.className = "text-sm text-red-600";
            statusEl.textContent = "Send failed. Try again.";
        } else {
            alert("Send failed.");
        }
    } finally {
        setMissingDocsSpinner(false);
    }
}

/**
 * =====================================================
 * AGENT
 * =====================================================
 */
function renderAgent(agent) {
    const el = document.getElementById("agentInfo");

    if (!agent?.name && !agent?.email) {
        el.innerText = "No agent information";
        return;
    }

    el.innerHTML = `
        <div><strong>Name:</strong> ${escapeHTML(agent.name || "-")}</div>
        <div><strong>Email:</strong> ${escapeHTML(agent.email || "-")}</div>
    `;
}

/**
 * =====================================================
 * UTILITIES
 * =====================================================
 */
function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.innerText = value || "-";
}

function formatFullTime(dateStr) {
    if (!dateStr) return null;

    const t = normalizeDateInput(dateStr);
    if (Number.isNaN(t)) return null;
    const d = new Date(t);
    if (isNaN(d.getTime())) return null;

    const now = new Date();
    const diffDays = Math.floor((now - d) / (1000 * 60 * 60 * 24));

    let color = "#94a3b8"; // slate-400
    let icon = "⏱";

    if (diffDays <= 1) {
        color = "#16a34a"; // green-600
        icon = "🆕";
    } else if (diffDays <= 5) {
        color = "#f59e0b"; // amber-500
        icon = "⏱";
    } else {
        color = "#dc2626"; // red-600
        icon = "📅";
    }

    const date = d.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric"
    });

    const time = d.toLocaleTimeString("en-US", {
        hour: "2-digit",
        minute: "2-digit"
    });

    return { color, icon, date, time, diffDays };
}

/**
 * MySQL / PHP often returns "YYYY-MM-DD HH:mm:ss" without timezone.
 * Normalize so elapsed time matches wall-clock expectations across browsers.
 */
function normalizeDateInput(dateStr) {
    const s = String(dateStr || "").trim();
    if (!s) return NaN;
    // Explicit offset / Z — use as-is (API returns ISO UTC with Z).
    if (/Z$/i.test(s) || /[+-]\d{2}:\d{2}$/.test(s)) {
        return Date.parse(s);
    }
    // MySQL naive UTC from DB session time_zone '+00:00' — must not parse as local.
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/.test(s)) {
        return Date.parse(s.replace(" ", "T") + "Z");
    }
    return Date.parse(s);
}

/**
 * Human-readable relative time: seconds & minutes first, then hours+minutes
 * under 24h (e.g. "3h 15m ago"), then days / months / years.
 */
function timeAgo(dateStr) {
    if (!dateStr) return "-";
    const t = normalizeDateInput(dateStr);
    if (Number.isNaN(t)) return "-";

    let diffMs = Date.now() - t;
    if (diffMs < 0) diffMs = 0;

    const sec = Math.floor(diffMs / 1000);
    if (sec < 10) return "just now";
    if (sec < 60) return `${sec}s ago`;

    const min = Math.floor(sec / 60);
    if (min < 60) return `${min}m ago`;

    const hr = Math.floor(sec / 3600);
    if (hr < 24) {
        const remMin = Math.floor((sec % 3600) / 60);
        if (remMin > 0) return `${hr}h ${remMin}m ago`;
        return `${hr}h ago`;
    }

    const day = Math.floor(sec / 86400);
    if (day < 30) return `${day}d ago`;

    const mo = Math.floor(day / 30);
    if (mo < 12) return `${mo}mo ago`;

    const yr = Math.floor(day / 365);
    return `${yr > 0 ? yr : 1}y ago`;
}

let __timeAgoTimer = null;

function refreshTimeAgoLabels() {
    document
        .querySelectorAll(
            "#studentList [data-time-ago], #applicationMeta [data-time-ago], #trackingTimeline [data-time-ago]"
        )
        .forEach((el) => {
            const ts = el.getAttribute("data-time-ago");
            if (ts) {
                el.textContent = timeAgo(ts);
            }
        });
}

function startTimeAgoTicker() {
    if (__timeAgoTimer) clearInterval(__timeAgoTimer);
    refreshTimeAgoLabels();
    __timeAgoTimer = setInterval(refreshTimeAgoLabels, 30000);
}

function debounce(fn, delay) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), delay);
    };
}

function escapeHTML(str) {
    const s = str == null ? "" : String(str);
    return s.replace(/[&<>"']/g, (m) =>
        ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        }[m])
    );
}

function escapeAttr(str) {
    if (typeof str !== "string") return "";
    return str
        .replace(/&/g, "&amp;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
/**
 * =====================================================
 * ADD STUDY CHOICE (Student Application Report)
 * Select2 searchable dropdowns + cascading loads
 * =====================================================
 */
function studyChoiceAddStudyDropdownParent() {
    if (typeof window.jQuery !== "function") return null;
    /* Body = dropdown stacks above sticky journey sidebar (avoids clipping / wrong z-index in grid) */
    return window.jQuery(document.body);
}

function destroyStudyChoiceSelect2IfAny(el) {
    if (!el || typeof window.jQuery !== "function" || !window.jQuery.fn.select2) return;
    const $el = window.jQuery(el);
    if ($el.length && $el.hasClass("select2-hidden-accessible")) {
        $el.select2("destroy");
    }
}

function mountStudyChoiceSelect2(el, opts) {
    const options = opts || {};
    if (!el || typeof window.jQuery !== "function" || !window.jQuery.fn.select2) return;
    const $el = window.jQuery(el);
    if ($el.hasClass("select2-hidden-accessible")) {
        $el.select2("destroy");
    }
    $el.prop("disabled", !!options.disabled);
    const $par = studyChoiceAddStudyDropdownParent();
    $el.select2({
        theme: "bootstrap-5",
        width: "100%",
        placeholder: options.placeholder || "Select…",
        allowClear: true,
        minimumResultsForSearch: 0,
        dropdownParent: $par && $par.length ? $par : window.jQuery(document.body)
    });
}

function addStudySelectValue(id) {
    const el = document.getElementById(id);
    if (!el) return "";
    if (
        typeof window.jQuery === "function" &&
        window.jQuery(el).hasClass("select2-hidden-accessible")
    ) {
        const v = window.jQuery(el).val();
        return v === null || v === undefined ? "" : String(v);
    }
    return el.value || "";
}

function bindStudyChoiceCascadeEvents() {
    if (typeof window.jQuery !== "function") return;
    const $ = window.jQuery;
    $("#addStudyRegion")
        .off("change.pcvcStudy select2:select.pcvcStudy select2:clear.pcvcStudy")
        .on("change.pcvcStudy select2:select.pcvcStudy select2:clear.pcvcStudy", function () {
            const el = this;
            clearTimeout(bindStudyChoiceCascadeEvents._tRegion);
            bindStudyChoiceCascadeEvents._tRegion = setTimeout(() => {
                onAddStudyRegionChange.call(el);
            }, 0);
        });
    $("#addStudyUniversity")
        .off("change.pcvcStudy select2:select.pcvcStudy select2:clear.pcvcStudy")
        .on("change.pcvcStudy select2:select.pcvcStudy select2:clear.pcvcStudy", function () {
            const el = this;
            clearTimeout(bindStudyChoiceCascadeEvents._tUni);
            bindStudyChoiceCascadeEvents._tUni = setTimeout(() => {
                onAddStudyUniversityChange.call(el);
            }, 0);
        });
    $("#addStudyLevel")
        .off("change.pcvcStudy select2:select.pcvcStudy select2:clear.pcvcStudy")
        .on("change.pcvcStudy select2:select.pcvcStudy select2:clear.pcvcStudy", function () {
            const el = this;
            clearTimeout(bindStudyChoiceCascadeEvents._tLev);
            bindStudyChoiceCascadeEvents._tLev = setTimeout(() => {
                onAddStudyLevelChange.call(el);
            }, 0);
        });
    $("#addStudyProgram")
        .off("change.pcvcStudy select2:select.pcvcStudy select2:clear.pcvcStudy")
        .on("change.pcvcStudy select2:select.pcvcStudy select2:clear.pcvcStudy", function () {
            updateAddStudyChoiceButtonState();
        });
}

function wireStudyChoiceAddFormOnce() {
    if (studyChoiceAddFormWired) return;
    studyChoiceAddFormWired = true;

    document.getElementById("btnAddStudyChoice")?.addEventListener("click", submitAddStudyChoice);
    bindStudyChoiceCascadeEvents();
}

function resetStudyChoiceAddSelects() {
    const region = document.getElementById("addStudyRegion");
    const uni = document.getElementById("addStudyUniversity");
    const lev = document.getElementById("addStudyLevel");
    const prog = document.getElementById("addStudyProgram");
    const status = document.getElementById("studyChoiceAddStatus");

    [region, uni, lev, prog].forEach((el) => destroyStudyChoiceSelect2IfAny(el));

    if (region) region.value = "";
    if (uni) {
        uni.innerHTML = '<option value="">Select region first</option>';
        uni.disabled = true;
    }
    if (lev) {
        lev.innerHTML = '<option value="">Select university</option>';
        lev.disabled = true;
    }
    if (prog) {
        prog.innerHTML = '<option value="">Select level</option>';
        prog.disabled = true;
    }
    if (status) status.textContent = "";

    if (region) {
        mountStudyChoiceSelect2(region, {
            placeholder: "Search or select region…",
            disabled: false
        });
    }
    if (uni) {
        mountStudyChoiceSelect2(uni, {
            placeholder: "Search university…",
            disabled: true
        });
    }
    if (lev) {
        mountStudyChoiceSelect2(lev, {
            placeholder: "Search level…",
            disabled: true
        });
    }
    if (prog) {
        mountStudyChoiceSelect2(prog, {
            placeholder: "Search program…",
            disabled: true
        });
    }

    bindStudyChoiceCascadeEvents();
    updateAddStudyChoiceButtonState();
}

function updateAddStudyChoiceButtonState() {
    const btn = document.getElementById("btnAddStudyChoice");
    if (!btn) return;
    const ok =
        currentViewApplicationId &&
        addStudySelectValue("addStudyRegion") &&
        addStudySelectValue("addStudyUniversity") &&
        addStudySelectValue("addStudyLevel") &&
        addStudySelectValue("addStudyProgram");
    btn.disabled = !ok;
}

async function loadStudyChoiceRegions() {
    const sel = document.getElementById("addStudyRegion");
    if (!sel) return;

    if (studyChoiceRegionsLoaded) return;

    destroyStudyChoiceSelect2IfAny(sel);
    sel.innerHTML = '<option value="">Loading…</option>';
    try {
        const r = await fetch(projectApiPath("save_application.php?action=load_meta"), {
            credentials: "same-origin"
        });
        const data = await r.json();
        const regions = Array.isArray(data.regions) ? data.regions : [];
        destroyStudyChoiceSelect2IfAny(sel);
        sel.innerHTML = '<option value="">Select region</option>';
        regions.forEach((row) => {
            const o = document.createElement("option");
            o.value = String(row.id);
            o.textContent = row.name || `Region #${row.id}`;
            sel.appendChild(o);
        });
        studyChoiceRegionsLoaded = true;
    } catch (e) {
        console.error("loadStudyChoiceRegions:", e);
        destroyStudyChoiceSelect2IfAny(sel);
        sel.innerHTML = '<option value="">Failed to load regions</option>';
    }
}

async function prepareStudyChoiceAddForm() {
    await loadStudyChoiceRegions();
    resetStudyChoiceAddSelects();
}

async function onAddStudyRegionChange() {
    const regionId = this.value;
    const uni = document.getElementById("addStudyUniversity");
    const lev = document.getElementById("addStudyLevel");
    const prog = document.getElementById("addStudyProgram");
    if (!uni || !lev || !prog) return;

    [uni, lev, prog].forEach((el) => destroyStudyChoiceSelect2IfAny(el));

    lev.innerHTML = '<option value="">Select university</option>';
    lev.disabled = true;
    prog.innerHTML = '<option value="">Select level</option>';
    prog.disabled = true;

    if (!regionId) {
        uni.innerHTML = '<option value="">Select region first</option>';
        uni.disabled = true;
        mountStudyChoiceSelect2(uni, { placeholder: "Search university…", disabled: true });
        mountStudyChoiceSelect2(lev, { placeholder: "Search level…", disabled: true });
        mountStudyChoiceSelect2(prog, { placeholder: "Search program…", disabled: true });
        updateAddStudyChoiceButtonState();
        return;
    }

    uni.innerHTML = '<option value="">Loading…</option>';
    uni.disabled = true;
    mountStudyChoiceSelect2(uni, { placeholder: "Loading universities…", disabled: true });
    mountStudyChoiceSelect2(lev, { placeholder: "Search level…", disabled: true });
    mountStudyChoiceSelect2(prog, { placeholder: "Search program…", disabled: true });

    try {
        const r = await fetch(
            projectApiPath(
                `save_application.php?action=universities&region_id=${encodeURIComponent(regionId)}`
            ),
            { credentials: "same-origin" }
        );
        const rows = await r.json();
        destroyStudyChoiceSelect2IfAny(uni);
        uni.innerHTML = '<option value="">Select university</option>';
        (Array.isArray(rows) ? rows : []).forEach((row) => {
            const o = document.createElement("option");
            o.value = String(row.id);
            o.textContent = row.name || `University #${row.id}`;
            uni.appendChild(o);
        });
        uni.disabled = false;
        mountStudyChoiceSelect2(uni, { placeholder: "Search university…", disabled: false });
        mountStudyChoiceSelect2(lev, { placeholder: "Search level…", disabled: true });
        mountStudyChoiceSelect2(prog, { placeholder: "Search program…", disabled: true });
    } catch (e) {
        console.error(e);
        destroyStudyChoiceSelect2IfAny(uni);
        uni.innerHTML = '<option value="">Load failed</option>';
        uni.disabled = true;
        mountStudyChoiceSelect2(uni, { placeholder: "Search university…", disabled: true });
        mountStudyChoiceSelect2(lev, { placeholder: "Search level…", disabled: true });
        mountStudyChoiceSelect2(prog, { placeholder: "Search program…", disabled: true });
    }
    updateAddStudyChoiceButtonState();
}

async function onAddStudyUniversityChange() {
    const uid = this.value;
    const lev = document.getElementById("addStudyLevel");
    const prog = document.getElementById("addStudyProgram");
    if (!lev || !prog) return;

    [lev, prog].forEach((el) => destroyStudyChoiceSelect2IfAny(el));

    prog.innerHTML = '<option value="">Select level</option>';
    prog.disabled = true;

    if (!uid) {
        lev.innerHTML = '<option value="">Select university</option>';
        lev.disabled = true;
        mountStudyChoiceSelect2(lev, { placeholder: "Search level…", disabled: true });
        mountStudyChoiceSelect2(prog, { placeholder: "Search program…", disabled: true });
        updateAddStudyChoiceButtonState();
        return;
    }

    lev.innerHTML = '<option value="">Loading…</option>';
    lev.disabled = true;
    mountStudyChoiceSelect2(lev, { placeholder: "Loading levels…", disabled: true });
    mountStudyChoiceSelect2(prog, { placeholder: "Search program…", disabled: true });

    try {
        const r = await fetch(
            projectApiPath(
                `save_application.php?action=program_levels&university_id=${encodeURIComponent(uid)}`
            ),
            { credentials: "same-origin" }
        );
        const rows = await r.json();
        destroyStudyChoiceSelect2IfAny(lev);
        lev.innerHTML = '<option value="">Select level</option>';
        (Array.isArray(rows) ? rows : []).forEach((row) => {
            const o = document.createElement("option");
            o.value = String(row.id);
            const abbr = row.abbreviation ? String(row.abbreviation) : "";
            const nm = row.name ? String(row.name) : "";
            o.textContent = abbr && nm ? `${abbr} — ${nm}` : nm || abbr || `Level #${row.id}`;
            lev.appendChild(o);
        });
        lev.disabled = false;
        mountStudyChoiceSelect2(lev, { placeholder: "Search level…", disabled: false });
        mountStudyChoiceSelect2(prog, { placeholder: "Search program…", disabled: true });
    } catch (e) {
        console.error(e);
        destroyStudyChoiceSelect2IfAny(lev);
        lev.innerHTML = '<option value="">Load failed</option>';
        lev.disabled = true;
        mountStudyChoiceSelect2(lev, { placeholder: "Search level…", disabled: true });
        mountStudyChoiceSelect2(prog, { placeholder: "Search program…", disabled: true });
    }
    updateAddStudyChoiceButtonState();
}

async function onAddStudyLevelChange() {
    const uid = addStudySelectValue("addStudyUniversity");
    const levelId = this.value;
    const prog = document.getElementById("addStudyProgram");
    if (!prog) return;

    destroyStudyChoiceSelect2IfAny(prog);

    if (!uid || !levelId) {
        prog.innerHTML = '<option value="">Select level first</option>';
        prog.disabled = true;
        mountStudyChoiceSelect2(prog, { placeholder: "Search program…", disabled: true });
        updateAddStudyChoiceButtonState();
        return;
    }

    prog.innerHTML = '<option value="">Loading…</option>';
    prog.disabled = true;
    mountStudyChoiceSelect2(prog, { placeholder: "Loading programs…", disabled: true });

    try {
        const r = await fetch(
            projectApiPath(
                `save_application.php?action=programs&university_id=${encodeURIComponent(
                    uid
                )}&program_level_id=${encodeURIComponent(levelId)}`
            ),
            { credentials: "same-origin" }
        );
        const rows = await r.json();
        destroyStudyChoiceSelect2IfAny(prog);
        prog.innerHTML = '<option value="">Select program</option>';
        (Array.isArray(rows) ? rows : []).forEach((row) => {
            const o = document.createElement("option");
            o.value = String(row.id);
            o.textContent = row.program_name || `Program #${row.id}`;
            prog.appendChild(o);
        });
        prog.disabled = false;
        mountStudyChoiceSelect2(prog, { placeholder: "Search program…", disabled: false });
    } catch (e) {
        console.error(e);
        destroyStudyChoiceSelect2IfAny(prog);
        prog.innerHTML = '<option value="">Load failed</option>';
        prog.disabled = true;
        mountStudyChoiceSelect2(prog, { placeholder: "Search program…", disabled: true });
    }
    updateAddStudyChoiceButtonState();
}

async function submitAddStudyChoice() {
    if (!currentViewApplicationId) return;

    const fd = new FormData();
    fd.append("application_id", String(currentViewApplicationId));
    fd.append("region_id", addStudySelectValue("addStudyRegion"));
    fd.append("university_id", addStudySelectValue("addStudyUniversity"));
    fd.append("program_level_id", addStudySelectValue("addStudyLevel"));
    fd.append("program_id", addStudySelectValue("addStudyProgram"));

    const btn = document.getElementById("btnAddStudyChoice");
    const status = document.getElementById("studyChoiceAddStatus");
    if (btn) btn.disabled = true;
    if (status) status.textContent = "Saving…";

    try {
        const res = await fetch(projectApiPath("api/applications.php?action=add_study_choice"), {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        });
        const json = await res.json();
        if (!json.success) {
            if (status) status.textContent = json.message || "Could not save.";
            alert(json.message || "Could not add study choice.");
            updateAddStudyChoiceButtonState();
            return;
        }

        const d = json.data || {};
        renderStudyChoices(Array.isArray(d.study_choices) ? d.study_choices : []);
        if (Array.isArray(d.study_choice_suggestions)) {
            renderRelatedStudySuggestions(d.study_choice_suggestions);
        }
        loadStudents();

        if (d.duplicate) {
            if (status) status.textContent = d.message || "Already listed.";
            showToast(d.message || "This choice is already on the application.", undefined, "Study choices");
        } else {
            let msg = d.message || "Study choice added.";
            if (d.jobs_created > 0) {
                msg += ` ${d.jobs_created} job(s) added to the assignee's Job Do List.`;
                showToast(msg, () => {
                    window.location.href = "job_todo_list.php";
                }, "Study choices");
            } else {
                showToast(
                    msg +
                        (d.student_notified
                            ? " Student notified by email."
                            : " Student email not sent (missing or invalid address)."),
                    undefined,
                    "Study choices"
                );
            }
            if (status) {
                status.textContent = d.student_notified
                    ? "Saved and student notified."
                    : "Saved. Email not sent (check student email).";
            }
        }

        resetStudyChoiceAddSelects();
    } catch (e) {
        console.error(e);
        if (status) status.textContent = "Network error.";
    } finally {
        updateAddStudyChoiceButtonState();
    }
}

/**
 * =====================================================
 * TOAST NOTIFICATION (CLICKABLE)
 * =====================================================
 */
function showToast(message, onClick, title) {
    let container = document.getElementById("toastContainer");

    if (!container) {
        container = document.createElement("div");
        container.id = "toastContainer";
        container.className =
            "fixed top-4 right-4 z-50 flex flex-col gap-2";
        document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.className =
        "bg-green-600 text-white px-4 py-3 rounded shadow cursor-pointer hover:bg-green-700 transition";

    const heading =
        typeof title === "string" && title.trim() !== ""
            ? title.trim()
            : "Jobs Created";

    toast.innerHTML = `
        <div class="text-sm font-semibold">${escapeHTML(String(heading ?? ""))}</div>
        <div class="text-xs">${escapeHTML(String(message ?? ""))}</div>
    `;

    toast.onclick = () => {
        if (typeof onClick === "function") onClick();
        toast.remove();
    };

    container.appendChild(toast);

    setTimeout(() => toast.remove(), 6000);
}
