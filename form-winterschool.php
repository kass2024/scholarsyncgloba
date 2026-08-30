<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>🎓 Budapest Winter School Application</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen font-[Inter] text-gray-800">

<div class="max-w-3xl mx-auto mt-10 mb-16 bg-white shadow-2xl rounded-2xl overflow-hidden border border-gray-100">
  <!-- Header -->
  <div class="bg-gradient-to-r from-blue-700 to-indigo-600 text-white p-8 text-center">
    <h1 class="text-2xl md:text-3xl font-bold">🎓8 DAYS WINTER SCHOOL IN BUDAPEST</h1>
  </div>

  <!-- Body -->
  <div class="p-8 md:p-10 space-y-8">
    
    <!-- About -->
    <div class="bg-blue-50 border border-blue-100 p-5 rounded-xl">
      <h2 class="text-lg font-semibold text-blue-800 mb-2">About the Program</h2>
      <p class="text-sm leading-relaxed text-gray-700">
        Join our immersive <strong>8-day journey</strong> of academic enrichment, cultural exploration, and winter magic 
        in the heart of Europe. Participants will:
      </p>
      <ul class="list-disc list-inside mt-2 text-sm text-gray-700">
        <li>Attend interactive classes in Business, Marketing, Tourism, and Communication</li>
        <li>Join engaging afternoon & evening cultural activities</li>
        <li>Enjoy the festive lights and Christmas markets of Budapest’s Advent season</li>
      </ul>
    </div>

    <!-- Form -->
    <form id="budapestForm" method="POST" enctype="multipart/form-data" class="space-y-6">
      
      <!-- Personal Info -->
      <div class="grid md:grid-cols-2 gap-5">
        <div>
          <label class="block font-medium text-gray-700 mb-1">Full Name</label>
          <input type="text" name="full_name" id="full_name" required
            class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-4 py-2.5 shadow-sm">
        </div>
        <div>
          <label class="block font-medium text-gray-700 mb-1">Email</label>
          <input type="email" name="email" id="email" required
            class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-4 py-2.5 shadow-sm">
        </div>
        <div class="md:col-span-2">
          <label class="block font-medium text-gray-700 mb-1">Phone</label>
          <input type="tel" name="phone" id="phone" required
            class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-4 py-2.5 shadow-sm">
        </div>
      </div>

      <hr class="border-gray-200 my-4">

      <!-- Accommodation Option -->
      <div class="border border-yellow-200 bg-yellow-50 p-5 rounded-xl">
        <h2 class="text-lg font-semibold text-yellow-700 mb-3">
          <i class="fa-solid fa-sack-dollar mr-2"></i> Select Accommodation Option
        </h2>
        <div class="space-y-3 text-sm text-gray-700">
          <label class="flex items-center justify-between border border-gray-200 p-3 rounded-lg hover:bg-yellow-100 cursor-pointer">
            <div>
              <input type="radio" name="accommodation" value="Without Accommodation (€690)" class="mr-3" required>
              Without Accommodation
            </div>
            <span class="font-semibold text-gray-800">€690</span>
          </label>
          <label class="flex items-center justify-between border border-gray-200 p-3 rounded-lg hover:bg-yellow-100 cursor-pointer">
            <div>
              <input type="radio" name="accommodation" value="With Accommodation (Shared twin room €990)" class="mr-3" required>
              With Accommodation <span class="text-gray-600">(Shared twin room)</span>
            </div>
            <span class="font-semibold text-gray-800">€990</span>
          </label>
        </div>
      </div>

      <hr class="border-gray-200 my-4">

      <!-- Upload Sections -->
      <?php
      $fields = [
        "valid_passport" => "1️⃣ Valid Passport",
        "degree_certificate" => "2️⃣ Degree Certificate",
        "transcripts" => "3️⃣ Reports / Transcripts",
        "cv_resume" => "4️⃣ Curriculum Vitae (CV)",
        "passport_photo" => "5️⃣ Passport-Size Photo",
        "payment_proof" => "6️⃣ Payment Proof (250 USD)"
      ];
      foreach ($fields as $key => $label): ?>
        <div class="border border-gray-200 p-4 rounded-xl hover:shadow-md transition bg-gray-50">
          <label class="font-semibold text-gray-800"><?= $label ?></label>
          <input type="file" id="<?= $key ?>_file" required
            class="block w-full mt-2 text-sm text-gray-700 border-2 border-dashed border-blue-200 rounded-lg cursor-pointer bg-white px-3 py-3 hover:border-blue-400 focus:outline-none">
          <input type="hidden" name="<?= $key ?>">
          <div id="<?= $key ?>_view" class="mt-2 text-sm text-blue-700"></div>
        </div>
      <?php endforeach; ?>

      <button type="submit"
        class="w-full bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 text-white font-semibold py-3 rounded-xl shadow-lg transition">
        <i class="fa-solid fa-paper-plane mr-2"></i> Submit Application
      </button>
    </form>
  </div>
</div>

<!-- Spinner Overlay -->
<div id="ai-spinner-overlay" class="hidden fixed inset-0 bg-white/80 backdrop-blur-sm flex items-center justify-center z-50">
  <div class="text-center">
    <div class="w-14 h-14 border-4 border-gray-300 border-t-blue-600 rounded-full animate-spin mx-auto"></div>
    <p class="mt-4 text-gray-600 font-medium">Uploading document... please wait</p>
    <div class="w-64 mt-3 h-2 bg-gray-200 rounded-full overflow-hidden">
      <div class="ai-bar h-full bg-blue-600 w-0 transition-all duration-300"></div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  function setupUpload(field) {
    const fileInput = document.getElementById(`${field}_file`);
    const hiddenInput = document.querySelector(`input[name="${field}"]`);
    const viewDiv = document.getElementById(`${field}_view`);
    const overlay = document.getElementById("ai-spinner-overlay");
    const bar = overlay.querySelector(".ai-bar");

    function showSpinner() {
      overlay.classList.remove("hidden");
      bar.style.width = "0%";
      let p = 0;
      overlay._sim = setInterval(() => {
        if (p < 90) { p += 5; bar.style.width = p + "%"; }
      }, 300);
    }

    function hideSpinner() {
      clearInterval(overlay._sim);
      bar.style.width = "100%";
      setTimeout(() => overlay.classList.add("hidden"), 400);
    }

    fileInput.addEventListener("change", async () => {
      const file = fileInput.files[0];
      if (!file) return;
      const formData = new FormData();
      formData.append("file", file);
      formData.append("field", field);

      const fullName = document.getElementById("full_name").value.trim();
      const email = document.getElementById("email").value.trim();
      if (fullName) {
        const parts = fullName.split(" ");
        formData.append("first_name", parts.slice(0, -1).join(" ") || parts[0] || "");
        formData.append("last_name", parts.slice(-1).join(" ") || "");
      }
      formData.append("email", email);

      showSpinner();
      try {
        const res = await fetch("upload_file_budapest.php", { method: "POST", body: formData });
        const data = await res.json();
        hideSpinner();
        if (data.status === "success") {
          hiddenInput.value = data.file_path;
          viewDiv.innerHTML = `<a href="${data.file_path}" target="_blank" class="text-blue-600 underline hover:text-blue-800"><i class="fa-solid fa-link"></i> View File</a>`;
          alert("✅ " + (data.message || "Uploaded successfully!"));
        } else {
          alert("❌ " + (data.message || "Upload failed"));
          fileInput.value = "";
          hiddenInput.value = "";
          viewDiv.innerHTML = "";
        }
      } catch (err) {
        hideSpinner();
        alert("⚠️ Network error: " + err.message);
        fileInput.value = "";
        hiddenInput.value = "";
        viewDiv.innerHTML = "";
      }
    });
  }

  // Initialize uploads
  ["valid_passport","degree_certificate","transcripts","cv_resume","passport_photo","payment_proof"].forEach(setupUpload);

  // Submit
  const form = document.getElementById("budapestForm");
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const res = await fetch("save-budapest.php", { method: "POST", body: new FormData(form) });
    const data = await res.json();
    if (data.status === "success") {
      alert("🎉 Application submitted successfully! A confirmation email has been sent.");
      form.reset();
      document.querySelectorAll(".file-view").forEach((d) => (d.innerHTML = ""));
    } else {
      alert("❌ " + data.message);
    }
  });
});
</script>

</body>
</html>
