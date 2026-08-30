<?php
session_start();
require_once __DIR__ . '/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>🎓 Budapest Applications Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/a2d9c7e5f6.js" crossorigin="anonymous"></script>
</head>

<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen font-[Inter] text-gray-800">

<!-- Navbar -->
<nav class="bg-gradient-to-r from-blue-700 to-indigo-600 text-white shadow-lg">
  <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
    <h1 class="text-2xl font-bold"><i class="fa-solid fa-graduation-cap mr-2"></i>Budapest Winter School Dashboard</h1>
    <a href="index.php" class="text-white bg-white/20 hover:bg-white/30 rounded-lg px-4 py-2 text-sm font-medium transition">Back to Application</a>
  </div>
</nav>

<!-- Container -->
<div class="max-w-7xl mx-auto p-6">
  <div class="flex justify-between items-center mb-6">
    <h2 class="text-xl font-semibold text-gray-800">All Submitted Applications</h2>
    <div class="flex gap-2">
      <input type="text" id="searchInput" placeholder="Search by name or email..." 
             class="px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:outline-none w-64">
      <button id="refreshBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">
        <i class="fa-solid fa-rotate-right"></i>
      </button>
    </div>
  </div>

  <!-- Applicants Grid -->
  <div id="appsGrid" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
    <div class="text-center col-span-full text-gray-400 py-10">Loading applications...</div>
  </div>
</div>

<!-- Modal -->
<div id="docsModal" class="fixed inset-0 hidden items-center justify-center bg-black/40 z-50">
  <div class="bg-white w-11/12 md:w-2/3 lg:w-1/2 rounded-2xl p-6 shadow-2xl relative">
    <button id="closeModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800">
      <i class="fa-solid fa-xmark text-xl"></i>
    </button>
    <h3 class="text-lg font-semibold mb-3 text-blue-700 flex items-center">
      <i class="fa-solid fa-folder-open mr-2"></i> Uploaded Documents
    </h3>
    <div id="docsList" class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm"></div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const grid = document.getElementById("appsGrid");
  const searchInput = document.getElementById("searchInput");
  const refreshBtn = document.getElementById("refreshBtn");
  const modal = document.getElementById("docsModal");
  const closeModal = document.getElementById("closeModal");
  const docsList = document.getElementById("docsList");

  async function loadApps(query="") {
    grid.innerHTML = `<div class='col-span-full text-center text-gray-400 py-10'>Loading...</div>`;
    const res = await fetch("getBudapestApps.php?q=" + encodeURIComponent(query));
    const data = await res.json();
    if (!data.length) {
      grid.innerHTML = `<div class='col-span-full text-center text-gray-400 py-10'>No applications found.</div>`;
      return;
    }

    grid.innerHTML = "";
    data.forEach(app => {
      const card = document.createElement("div");
      card.className = "bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-lg transition p-5 flex flex-col";
      card.innerHTML = `
        <div class="flex items-center justify-between mb-3">
          <div>
            <h3 class="text-lg font-semibold text-gray-800">${app.full_name}</h3>
            <p class="text-sm text-gray-500">${app.email}</p>
            <p class="text-sm text-gray-500">${app.phone}</p>
          </div>
          <div class="bg-blue-100 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full">#${app.id}</div>
        </div>

        <div class="mb-3">
          <p class="text-sm font-semibold text-gray-700 flex items-center gap-2">
            <i class="fa-solid fa-bed text-yellow-500"></i> Accommodation:
          </p>
          <p class="text-sm mt-1 text-gray-700 bg-yellow-50 border border-yellow-100 px-3 py-2 rounded-lg font-medium">
            ${app.accommodation || "—"}
          </p>
        </div>

        <div class="mt-2 text-sm text-gray-600">
          <p class="font-medium text-gray-700 mb-2 flex items-center gap-2">
            <i class="fa-solid fa-file-lines text-blue-600"></i> Documents
          </p>
          <div class="grid grid-cols-1 gap-2">
            ${renderDocs(app.docs || {})}
          </div>
        </div>

        <div class="mt-auto pt-3 text-right text-xs text-gray-400 border-t border-gray-100">
          Submitted: ${app.created_at || "—"}
        </div>
      `;
      grid.appendChild(card);
    });
  }

  function renderDocs(docs) {
    const labels = {
      valid_passport: "Valid Passport",
      degree_certificate: "Degree Certificate",
      transcripts: "Transcripts",
      cv_resume: "Curriculum Vitae",
      passport_photo: "Passport Photo",
      payment_proof: "Payment Proof"
    };
    let html = "";
    for (const [key, url] of Object.entries(docs)) {
      if (!url) continue;
      html += `
        <a href="${url}" target="_blank" 
           class="flex items-center justify-between bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium px-3 py-2 rounded-lg transition group">
          <span class="truncate">${labels[key] || key}</span>
          <i class="fa-solid fa-arrow-up-right-from-square text-blue-600 group-hover:text-blue-800"></i>
        </a>`;
    }
    if (!html) html = `<p class="text-gray-400 italic">No files uploaded.</p>`;
    return html;
  }

  closeModal.addEventListener("click", () => modal.classList.add("hidden"));
  refreshBtn.addEventListener("click", () => loadApps(searchInput.value));
  searchInput.addEventListener("input", e => loadApps(e.target.value));

  loadApps();
});
</script>

</body>
</html>
