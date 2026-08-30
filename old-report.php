<?php
date_default_timezone_set('UTC');
require_once 'auth.php';
require_once __DIR__ . '/db.php';
// Fetch regions
$regions_result = $conn->query("SELECT id, name FROM regions ORDER BY name ASC");
$regions = [];
while ($row = $regions_result->fetch_assoc()) {
    $regions[] = $row;
}

// Fetch universities with region_id included
$universities_result = $conn->query("SELECT id, name, region_id FROM universities ORDER BY name ASC");
$universities = [];
while ($row = $universities_result->fetch_assoc()) {
    $universities[] = $row;
}

// Fetch default student applications
$applicants = [];
$query = "
  SELECT sa.*, 
         r.name AS region_name, 
         u.name AS university_name,
         sa.region_id, sa.university_id
  FROM student_applications sa
  LEFT JOIN regions r ON sa.region_id = r.id
  LEFT JOIN universities u ON sa.university_id = u.id
  ORDER BY sa.id DESC
";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $applicants[] = $row;
}

// Fetch applicants from malta_applications only for region_id=4 and university_id=29
$malta_applicants = [];
$malta_query = "
  SELECT ma.*, 
         r.name AS region_name, 
         u.name AS university_name,
         ma.region_id, ma.university_id
  FROM malta_applications ma
  LEFT JOIN regions r ON ma.region_id = r.id
  LEFT JOIN universities u ON ma.university_id = u.id
  WHERE ma.region_id = 4 AND ma.university_id = 29
  ORDER BY ma.id DESC
";
$malta_result = $conn->query($malta_query);
while ($row = $malta_result->fetch_assoc()) {
    $malta_applicants[] = $row;
}

// Fetch applicants from turkey_applications only for region_id=4 and university_id=36
$turkey_applicants = [];
$turkey_query = "
  SELECT ta.*, 
         r.name AS region_name, 
         u.name AS university_name,
         ta.region_id, ta.university_id
  FROM turkey_applications ta
  LEFT JOIN regions r ON ta.region_id = r.id
  LEFT JOIN universities u ON ta.university_id = u.id
  WHERE ta.region_id = 4 AND ta.university_id = 36
  ORDER BY ta.id DESC
";
$turkey_result = $conn->query($turkey_query);
while ($row = $turkey_result->fetch_assoc()) {
    $turkey_applicants[] = $row;
}

// Fetch applicants from georgia_applications for region_id=4 and university_id=41
$georgia_applicants = [];
$georgia_query = "
  SELECT ga.*, 
         r.name AS region_name, 
         u.name AS university_name,
         ga.region_id, ga.university_id
  FROM georgia_applications ga
  LEFT JOIN regions r ON ga.region_id = r.id
  LEFT JOIN universities u ON ga.university_id = u.id
  WHERE ga.region_id = 4 AND ga.university_id = 41
  ORDER BY ga.id DESC
";
$georgia_result = $conn->query($georgia_query);
while ($row = $georgia_result->fetch_assoc()) {
    $georgia_applicants[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Catholic Applicants Report</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }

    body {
      font-family: 'Roboto', sans-serif;
      margin: 0;
      padding: 0;
      display: flex;
      height: 100vh;
      background: #f7f8fa;
      color: #333;
    }

    .sidebar {
      width: 300px;
      min-width: 250px;
      background: #ffffff;
      border-right: 1px solid #ddd;
      display: flex;
      flex-direction: column;
      box-shadow: 2px 0 6px rgba(0, 0, 0, 0.03);
      padding: 1rem;
    }

    .sidebar input,
    .sidebar select {
      margin-bottom: 14px;
      padding: 10px 14px;
      border: 1px solid #ccc;
      border-radius: 10px;
      font-size: 1rem;
      width: 100%;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .sidebar input:focus,
    .sidebar select:focus {
      border-color: #007bff;
      box-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
    }

    .applicant-list {
      list-style: none;
      padding: 0;
      margin: 0;
      overflow-y: auto;
      flex: 1;
    }

    .applicant-list li {
      padding: 12px 14px;
      border-bottom: 1px solid #ececec;
      cursor: pointer;
      transition: background 0.2s;
    }

    .applicant-list li:hover { background: #f0f7ff; }
    .applicant-list li.active { background: #dbe9ff; font-weight: bold; }

    .main-view {
      flex: 1;
      padding: 2rem;
      overflow-y: auto;
      background: #ffffff;
    }

    .main-view h2 {
      margin-top: 0;
      font-size: 1.6rem;
      color: #004085;
      margin-bottom: 1rem;
    }

    .detail-section {
      background: #ffffff;
      padding: 1.5rem 2rem;
      border-radius: 12px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
      margin-bottom: 2rem;
    }

    .detail-block {
      display: flex;
      align-items: flex-start;
      font-size: 0.95rem;
      line-height: 1.5;
      margin-bottom: 1rem;
    }

    .detail-block strong {
      width: 200px;
      font-weight: 600;
      color: #333;
      flex-shrink: 0;
    }

    .detail-block span,
    .detail-block .tag {
      flex: 1;
      display: block;
    }

    .tag {
      background: #e6f0ff;
      color: #007bff;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.85rem;
      display: inline-block;
      margin-right: 6px;
      margin-bottom: 4px;
    }

    .file-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
      background: #f4f6f8;
      padding: 12px;
      border-radius: 10px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .file-label { width: 200px; font-weight: 600; color: #333; }

    .file-link {
      flex: 1;
      text-decoration: none;
      color: #007bff;
      font-weight: 500;
      word-break: break-word;
    }

    .download-btn {
      padding: 6px 12px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 0.9rem;
      transition: background 0.2s ease;
    }

    .download-btn:hover { background: #427431; }

    .unread-dot {
      display: inline-block;
      width: 8px;
      height: 8px;
      background: #007bff;
      border-radius: 50%;
      margin-left: 6px;
      margin-top: 2px;
    }

    li.read .unread-dot { display: none; }

    .elapsed { font-size: 0.75rem; color: #888; }

    .meta-line {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 6px;
      font-size: 0.8rem;
      color: #999;
    }

    .main-view h3 {
      font-size: 1.2rem;
      margin-top: 2rem;
      margin-bottom: 1rem;
      color: #004085;
      border-bottom: 1px solid #e0e0e0;
      padding-bottom: 4px;
    }

    .main-view p { color: #777; }

    @media (max-width: 768px) {
      body { flex-direction: column; }
      .sidebar { width: 100%; min-height: auto; border-right: none; border-bottom: 1px solid #ccc; }
      .main-view { padding: 1rem; }
      .file-row { flex-direction: column; align-items: flex-start; }
      .file-label { width: 100%; }
      .detail-block { flex-direction: column; }
      .detail-block strong { width: 100%; margin-bottom: 4px; }
    }
  </style>
</head>
<body>
<div class="sidebar">
  <div class="filter">
    <label for="regionFilter">Filter by Region</label>
    <select id="regionFilter">
      <option value="">All Regions</option>
      <?php foreach ($regions as $region): ?>
        <option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="filter">
    <label for="universityFilter">Filter by University</label>
    <select id="universityFilter">
      <option value="">All Universities</option>
      <?php foreach ($universities as $uni): ?>
        <option value="<?= $uni['id'] ?>" data-region-id="<?= $uni['region_id'] ?>">
          <?= htmlspecialchars($uni['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <input type="text" id="searchInput" placeholder="Search by name or email...">

  <ul class="applicant-list" id="applicantList"></ul>
</div>

<div class="main-view" id="detailView">
  <p>Select an applicant to view details.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const applicants        = <?php echo json_encode($applicants ?? []); ?>;
  const maltaApplicants   = <?php echo json_encode($malta_applicants ?? []); ?>;
  const turkeyApplicants  = <?php echo json_encode($turkey_applicants ?? []); ?>;
  const georgiaApplicants = <?php echo json_encode($georgia_applicants ?? []); ?>;

  const detailView       = document.getElementById('detailView');
  const searchInput      = document.getElementById('searchInput');
  const regionFilter     = document.getElementById('regionFilter');
  const universityFilter = document.getElementById('universityFilter');
  const applicantList    = document.getElementById('applicantList');

  // -----------------------------------------
  // URL / PATH HELPERS (for download + display)
  // -----------------------------------------
  function cleanPath(raw) {
    if (!raw) return '';
    let p = String(raw).trim();

    // Strip wrapping quotes or ["..."] artifacts
    p = p.replace(/^"+|"+$/g, '');
    p = p.replace(/^\[+"?|"+\]+$/g, '');

    // Normalize slashes
    p = p.replace(/\\/g, '/');

    // If full path contains uploads/, clip before it
    const idx = p.toLowerCase().indexOf('uploads/');
    if (idx > -1) p = p.slice(idx);

    // Remove accidental leading slashes
    p = p.replace(/^\/+/, '');

    return p;
  }

  function makeHref(relPath) {
    if (!relPath) return '#';
    const enc = btoa(relPath);
    return 'download.php?f=' + encodeURIComponent(enc);
  }

  // Accept string, JSON array, or delimited list; return array of rel paths
  function extractFileList(value) {
    if (!value) return [];
    // Already array?
    if (Array.isArray(value)) return value.map(cleanPath).filter(Boolean);

    // Try JSON
    if (typeof value === 'string') {
      try {
        const parsed = JSON.parse(value);
        if (Array.isArray(parsed)) return parsed.map(cleanPath).filter(Boolean);
      } catch (e) { /* not JSON */ }
    }

    // Fallback: split on common delimiters if looks like multiple
    if (typeof value === 'string' && /[;,|\s]/.test(value) && value.indexOf('/') !== -1) {
      return value.split(/[;,|\s]+/).map(cleanPath).filter(Boolean);
    }
    return [cleanPath(value)];
  }

  function niceNameFromPath(path) {
    const full = path.split('/').pop();
    return full.replace(/^[a-z0-9]{10,}[_-]/i, '');
  }

  // -----------------------------------------
  // Filters: when region changes, refresh list of universities
  // -----------------------------------------
  regionFilter.addEventListener('change', () => {
    const selectedRegionId = regionFilter.value;
    universityFilter.innerHTML = '<option value="">Loading...</option>';

    fetch(`get-universities.php?region_id=${selectedRegionId}`)
      .then(response => response.json())
      .then(universities => {
        universityFilter.innerHTML = '<option value="">All Universities</option>';
        universities.forEach(uni => {
          const option = document.createElement('option');
          option.value = uni.id;
          option.textContent = uni.name;
          universityFilter.appendChild(option);
        });
        filterApplicants();
      })
      .catch(err => {
        console.error('Error loading universities:', err);
        universityFilter.innerHTML = '<option value="">All Universities</option>';
      });
  });

  // -----------------------------------------
  // File fields mapping for main (6-step) student_applications
  // -----------------------------------------
  const fileFields = {
    'degree_transcripts':     'Degree Transcripts',
    'high_school_degree':     'High School Degree',
    'valid_passport':         'Valid Passport',
    'recommendation_letters': 'Recommendation Letters',
    'personal_statement':     'Personal Statement',
    'cv_resume':              'CV / Resume',
    'english_certificate':    'English Certificate',
    'birth_certificate':      'Birth Certificate',
    'payment_proof':          'Payment Proof'
  };

  // -----------------------------------------
  // Time helper
  // -----------------------------------------
  const getElapsedTime = (timestamp) => {
    const now  = new Date();
    const then = new Date(timestamp + 'Z'); // Treat as UTC

    const nowUTC = Date.UTC(
      now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(),
      now.getUTCHours(), now.getUTCMinutes(), now.getUTCSeconds()
    );
    const thenUTC = Date.UTC(
      then.getUTCFullYear(), then.getUTCMonth(), then.getUTCDate(),
      then.getUTCHours(), then.getUTCMinutes(), then.getUTCSeconds()
    );
    const diff = Math.floor((nowUTC - thenUTC) / 1000);

    if (diff < 60)   return "Just now";
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
  };

  // -----------------------------------------
  // RENDERERS
  // -----------------------------------------

  // Main (6-step) renderer — unchanged fields + fixed file display
  const renderDetail = (a) => {
    let filesHTML = '';

    for (const [key, label] of Object.entries(fileFields)) {
      if (!a[key]) continue;

      const paths = extractFileList(a[key]); // supports JSON arrays or single strings
      paths.forEach((p, idx) => {
        if (!p) return;
        const href = makeHref(p);
        const nice = niceNameFromPath(p);
        filesHTML += `
          <div class="file-row">
            <div class="file-label">${label}${paths.length > 1 ? ` (${idx + 1})` : ''}</div>
            <a class="file-link" href="${href}" target="_blank" rel="noopener">${nice}</a>
            <a class="download-btn" href="${href}" download>Download</a>
          </div>`;
      });
    }

    detailView.innerHTML = `
      <div class="detail-section">
        <h2>${a.first_name || ''} ${a.last_name || ''}</h2>
        <div class="detail-block"><strong>Email:</strong><span>${a.email || ''}</span></div>
        <div class="detail-block"><strong>Phone:</strong><span>${a.area_code || ''} ${a.phone_number || ''}</span></div>
        <div class="detail-block"><strong>Gender:</strong><span class="tag">${a.gender || ''}</span></div>
        <div class="detail-block"><strong>Date of Birth:</strong><span>${a.dob || ''}</span></div>
        <div class="detail-block"><strong>Submitted:</strong><span>${a.created_at || ''}</span></div>
        <div class="detail-block"><strong>Country of Birth:</strong><span>${a.country_of_birth || ''}</span></div>
        <div class="detail-block"><strong>Nationality:</strong><span>${a.nationality || ''}</span></div>
        <div class="detail-block"><strong>Second Nationality:</strong><span>${a.second_nationality || ''}</span></div>
        <div class="detail-block"><strong>City of Birth:</strong><span>${a.city_of_birth || ''}</span></div>
        <div class="detail-block"><strong>Address Line 1:</strong><span>${a.address_line1 || ''}</span></div>
        <div class="detail-block"><strong>Address Line 2:</strong><span>${a.address_line2 || ''}</span></div>
        <div class="detail-block"><strong>City:</strong><span>${a.city || ''}</span></div>
        <div class="detail-block"><strong>State / Province:</strong><span>${a.state_province || ''}</span></div>
        <div class="detail-block"><strong>Postal Code:</strong><span>${a.postal_code || ''}</span></div>
        <div class="detail-block"><strong>Application Date:</strong><span>${a.application_date || ''}</span></div>
        <div class="detail-block"><strong>Form URL:</strong><span>${a.form_url || ''}</span></div>

        <h3>Academic Preferences</h3>

        <div class="detail-block"><strong>Advanced Diploma Program:</strong> <span>${a.advanced_diploma_program || ''}</span></div>
        <div class="detail-block"><strong>College Diploma Program:</strong> <span>${a.college_diploma_program || ''}</span></div>
        <div class="detail-block"><strong>College Certificate Program:</strong> <span>${a.college_certificate_program || ''}</span></div>
        <div class="detail-block"><strong>Graduate Certificate Program:</strong> <span>${a.graduate_certificate_program || ''}</span></div>

        <!-- Keep original mappings -->
        <div class="detail-block"><strong>Bachelor Program:</strong> <span>${a.bachelor_program || ''}</span></div>
        <div class="detail-block"><strong>Masters Program:</strong> <span>${a.masters_program || ''}</span></div>
        <div class="detail-block"><strong>PhD Program:</strong> <span>${a.phd_program || ''}</span></div>

        <div class="detail-block"><strong>University:</strong> <span>${a.university_name || '—'}</span></div>
        <div class="detail-block"><strong>Region:</strong> <span>${a.region_name || '—'}</span></div>

        <h3>Financial and Support Info</h3>
        <div class="detail-block"><strong>Destination Loan:</strong><span>${a.destination_loan || ''}</span></div>
        <div class="detail-block"><strong>Other Destination Loan:</strong><span>${a.other_destination_loan || ''}</span></div>
        <div class="detail-block"><strong>Paying Tuition Fees:</strong><span>${a.paying_tuition_fees || ''}</span></div>
        <div class="detail-block"><strong>Paying Cost of Living:</strong><span>${a.paying_cost_living || ''}</span></div>
        <div class="detail-block"><strong>Paying Travel Expenses:</strong><span>${a.paying_travel_expenses || ''}</span></div>
        <div class="detail-block"><strong>Criminal History:</strong><span>${a.criminal_history || ''}</span></div>
        <div class="detail-block"><strong>Disability:</strong><span>${a.disability || ''}</span></div>

        <h3>Emergency Contact</h3>
        <div class="detail-block"><strong>First Name:</strong><span>${a.emergency_first_name || ''}</span></div>
        <div class="detail-block"><strong>Last Name:</strong><span>${a.emergency_last_name || ''}</span></div>
        <div class="detail-block"><strong>Email:</strong><span>${a.emergency_email || ''}</span></div>
        <div class="detail-block"><strong>Phone:</strong><span>${a.emergency_area_code || ''} ${a.emergency_phone_number || ''}</span></div>
        <div class="detail-block"><strong>Relationship:</strong><span>${a.emergency_relationship || ''}</span></div>
        <div class="detail-block"><strong>Same Address:</strong><span>${a.emergency_same_address || ''}</span></div>

        <h3>Academic History</h3>
        <div class="detail-block"><strong>Study Level:</strong><span>${a.intended_study_level || ''}</span></div>
        <div class="detail-block"><strong>Previous Institution:</strong><span>${a.previous_institution_name || ''}, ${a.previous_institution_city || ''}, ${a.previous_institution_country || ''}</span></div>
        <div class="detail-block"><strong>Language of Instruction:</strong><span>${a.language_of_instruction || ''}</span></div>
        <div class="detail-block"><strong>Study Start:</strong><span>${a.previous_study_start || ''}</span></div>
        <div class="detail-block"><strong>Graduation:</strong><span>${a.previous_study_graduation || ''}</span></div>
        <div class="detail-block"><strong>Additional Secondary:</strong><span>${a.additional_secondary_school || ''}</span></div>
        <div class="detail-block"><strong>Study Gap:</strong><span>${a.study_gap || ''}</span></div>
        <div class="detail-block"><strong>Post-Secondary:</strong><span>${a.post_secondary || ''}</span></div>
        <div class="detail-block"><strong>Passport:</strong><span>${a.passport || ''}</span></div>
        <div class="detail-block"><strong>Visa Rejection:</strong><span>${a.visa_rejection || ''}</span></div>

        <h3>Agent Info</h3>
        <div class="detail-block"><strong>Agent:</strong><span>${a.agent_first_name || ''} ${a.agent_last_name || ''}</span></div>
        <div class="detail-block"><strong>Email:</strong><span>${a.agent_email || ''}</span></div>
        <div class="detail-block"><strong>Comments:</strong><span>${a.comments || ''}</span></div>

        <h3>Uploaded Documents</h3>
        ${filesHTML || '<p>No uploaded files available.</p>'}
      </div>`;
  };

  // Malta renderer
  function renderMaltaDetail(a) {
    const getFileRow = (label, path) => {
      if (!path) return '';
      const cleaned  = cleanPath(path);
      if (!cleaned) return '';
      const href     = makeHref(cleaned);
      const filename = niceNameFromPath(cleaned);
      return `
        <div class="file-row">
          <div class="file-label">${label}</div>
          <a class="file-link" href="${href}" target="_blank" rel="noopener">${filename}</a>
          <a class="download-btn" href="${href}" download>Download</a>
        </div>`;
    };

    const filesHTML = `
      ${getFileRow('Passport Copy', a.passport_copy_path)}
      ${getFileRow('Certificates', a.certificates_paths)}
      ${getFileRow('Transcript', a.transcript_path)}
      ${getFileRow('Application Form (PDF)', a.pdf_path)}
    `;

    detailView.innerHTML = `
      <div class="detail-section">
        <h2>${a.name || ''} ${a.middle_name || ''} ${a.surname || ''}</h2>
        <div class="detail-block"><strong>Email:</strong><span>${a.email || ''}</span></div>
        <div class="detail-block"><strong>Phone:</strong><span>${a.contact_number || ''}</span></div>
        <div class="detail-block"><strong>Gender:</strong><span>${a.gender || ''}</span></div>
        <div class="detail-block"><strong>Marital Status:</strong><span>${a.marital_status || ''}</span></div>
        <div class="detail-block"><strong>Date of Birth:</strong><span>${a.dob || ''}</span></div>
        <div class="detail-block"><strong>Birth Place:</strong><span>${a.birth_place || ''}</span></div>
        <div class="detail-block"><strong>Nationality:</strong><span>${a.nationality || ''}</span></div>
        <div class="detail-block"><strong>Passport Number:</strong><span>${a.passport_no || ''}</span></div>
        <div class="detail-block"><strong>Issue Date:</strong><span>${a.issue_date || ''}</span></div>
        <div class="detail-block"><strong>Expiry Date:</strong><span>${a.expiry_date || ''}</span></div>
        <div class="detail-block"><strong>Address:</strong><span>${a.address || ''}</span></div>
        <div class="detail-block"><strong>Visa Country:</strong><span>${a.visa_country || ''}</span></div>
        <div class="detail-block"><strong>Signed Date:</strong><span>${a.signed_date || ''}</span></div>
        <div class="detail-block"><strong>Signature:</strong><span>${a.signature || ''}</span></div>
        <div class="detail-block"><strong>Application Created At:</strong><span>${a.created_at || ''}</span></div>

        <h3>Academic Program Info</h3>
        <div class="detail-block"><strong>Session:</strong><span>From ${a.session_from || ''} to ${a.session_to || ''}</span></div>
        <div class="detail-block"><strong>Degree Program:</strong><span>${a.degree_program || ''}</span></div>
        <div class="detail-block"><strong>Specialty:</strong><span>${a.specialty || ''}</span></div>
        <div class="detail-block"><strong>Alternative 1:</strong><span>${a.alt1 || ''}</span></div>
        <div class="detail-block"><strong>Alternative 2:</strong><span>${a.alt2 || ''}</span></div>
        <div class="detail-block"><strong>Mode of Study:</strong><span>${a.mode_of_study || ''}</span></div>

        <h3>Secondary Education</h3>
        <div class="detail-block"><strong>School Name:</strong><span>${a.school_name || ''}</span></div>
        <div class="detail-block"><strong>School Address:</strong><span>${a.school_address || ''}</span></div>
        <div class="detail-block"><strong>School From:</strong><span>${a.school_from || ''}</span></div>
        <div class="detail-block"><strong>School To:</strong><span>${a.school_to || ''}</span></div>
        <div class="detail-block"><strong>Certificate:</strong><span>${a.school_certificate || ''}</span></div>

        <h3>College Education</h3>
        <div class="detail-block"><strong>College Name:</strong><span>${a.college_name || ''}</span></div>
        <div class="detail-block"><strong>College Address:</strong><span>${a.college_address || ''}</span></div>
        <div class="detail-block"><strong>College From:</strong><span>${a.college_from || ''}</span></div>
        <div class="detail-block"><strong>College To:</strong><span>${a.college_to || ''}</span></div>
        <div class="detail-block"><strong>College Certificate:</strong><span>${a.college_certificate || ''}</span></div>

        <h3>Additional Info</h3>
        <div class="detail-block"><strong>Studied in Malta:</strong><span>${a.studied_malta || ''}</span></div>
        <div class="detail-block"><strong>Studied Malta Info:</strong><span>${a.studied_malta_info || ''}</span></div>
        <div class="detail-block"><strong>Malta Language Test:</strong><span>${a.malta_lang || ''}</span></div>
        <div class="detail-block"><strong>Language Info:</strong><span>${a.malta_lang_info || ''}</span></div>

        <h3>Uploaded Documents</h3>
        ${filesHTML || '<p>No uploaded documents found.</p>'}
      </div>`;
  }

  // Georgia renderer
  function renderGeorgiaDetail(a) {
    const getFileRow = (label, path) => {
      if (!path) return '';
      const cleaned  = cleanPath(path);
      if (!cleaned) return '';
      const href     = makeHref(cleaned);
      const filename = niceNameFromPath(cleaned);
      return `
        <div class="file-row">
          <div class="file-label">${label}</div>
          <a class="file-link" href="${href}" target="_blank" rel="noopener">${filename}</a>
          <a class="download-btn" href="${href}" download>Download</a>
        </div>`;
    };

    const filesHTML = `
      ${getFileRow('Passport Copy', a.passport_copy_path)}
      ${getFileRow('Certificates', a.certificates_paths)}
      ${getFileRow('Transcript', a.transcript_path)}
      ${getFileRow('Application Form (PDF)', a.pdf_path)}
    `;

    detailView.innerHTML = `
      <div class="detail-section">
        <h2>${a.name || ''} ${a.middle_name || ''} ${a.surname || ''}</h2>
        <div class="detail-block"><strong>Email:</strong><span>${a.email || ''}</span></div>
        <div class="detail-block"><strong>Phone:</strong><span>${a.contact_number || ''}</span></div>
        <div class="detail-block"><strong>Gender:</strong><span>${a.gender || ''}</span></div>
        <div class="detail-block"><strong>Marital Status:</strong><span>${a.marital_status || ''}</span></div>
        <div class="detail-block"><strong>Date of Birth:</strong><span>${a.dob || ''}</span></div>
        <div class="detail-block"><strong>Birth Place:</strong><span>${a.birth_place || ''}</span></div>
        <div class="detail-block"><strong>Nationality:</strong><span>${a.nationality || ''}</span></div>
        <div class="detail-block"><strong>Passport Number:</strong><span>${a.passport_no || ''}</span></div>
        <div class="detail-block"><strong>Issue Date:</strong><span>${a.issue_date || ''}</span></div>
        <div class="detail-block"><strong>Expiry Date:</strong><span>${a.expiry_date || ''}</span></div>
        <div class="detail-block"><strong>Address:</strong><span>${a.address || ''}</span></div>
        <div class="detail-block"><strong>Visa Country:</strong><span>${a.visa_country || ''}</span></div>
        <div class="detail-block"><strong>Signed Date:</strong><span>${a.signed_date || ''}</span></div>
        <div class="detail-block"><strong>Signature:</strong><span>${a.signature || ''}</span></div>
        <div class="detail-block"><strong>Application Created At:</strong><span>${a.created_at || ''}</span></div>

        <h3>Academic Program Info</h3>
        <div class="detail-block"><strong>Session:</strong><span>From ${a.session_from || ''} to ${a.session_to || ''}</span></div>
        <div class="detail-block"><strong>Degree Program:</strong><span>${a.degree_program || ''}</span></div>
        <div class="detail-block"><strong>Specialty:</strong><span>${a.specialty || ''}</span></div>
        <div class="detail-block"><strong>Alternative 1:</strong><span>${a.alt1 || ''}</span></div>
        <div class="detail-block"><strong>Alternative 2:</strong><span>${a.alt2 || ''}</span></div>
        <div class="detail-block"><strong>Mode of Study:</strong><span>${a.mode_of_study || ''}</span></div>

        <h3>Secondary Education</h3>
        <div class="detail-block"><strong>School Name:</strong><span>${a.school_name || ''}</span></div>
        <div class="detail-block"><strong>School Address:</strong><span>${a.school_address || ''}</span></div>
        <div class="detail-block"><strong>School From:</strong><span>${a.school_from || ''}</span></div>
        <div class="detail-block"><strong>School To:</strong><span>${a.school_to || ''}</span></div>
        <div class="detail-block"><strong>Certificate:</strong><span>${a.school_certificate || ''}</span></div>

        <h3>College Education</h3>
        <div class="detail-block"><strong>College Name:</strong><span>${a.college_name || ''}</span></div>
        <div class="detail-block"><strong>College Address:</strong><span>${a.college_address || ''}</span></div>
        <div class="detail-block"><strong>College From:</strong><span>${a.college_from || ''}</span></div>
        <div class="detail-block"><strong>College To:</strong><span>${a.college_to || ''}</span></div>
        <div class="detail-block"><strong>College Certificate:</strong><span>${a.college_certificate || ''}</span></div>

        <h3>Additional Info</h3>
        <div class="detail-block"><strong>Studied in Georgia:</strong><span>${a.studied_georgia || ''}</span></div>
        <div class="detail-block"><strong>Georgia Study Info:</strong><span>${a.studied_georgia_info || ''}</span></div>
        <div class="detail-block"><strong>Georgia Language Test:</strong><span>${a.georgia_lang || ''}</span></div>
        <div class="detail-block"><strong>Language Info:</strong><span>${a.georgia_lang_info || ''}</span></div>

        <h3>Uploaded Documents</h3>
        ${filesHTML || '<p>No uploaded documents found.</p>'}
      </div>`;
  }

  // Turkey renderer
  function renderTurkeyDetail(a) {
    const getFileRow = (label, path) => {
      if (!path) return '';
      const cleaned  = cleanPath(path);
      if (!cleaned) return '';
      const href     = makeHref(cleaned);
      const filename = niceNameFromPath(cleaned);
      return `
        <div class="file-row">
          <div class="file-label">${label}</div>
          <a class="file-link" href="${href}" target="_blank" rel="noopener">${filename}</a>
          <a class="download-btn" href="${href}" download>Download</a>
        </div>`;
    };

    const filesHTML = `
      ${getFileRow('Photo', a.photo)}
      ${getFileRow('Degree', a.degree)}
      ${getFileRow('Transcript', a.transcript)}
      ${getFileRow('CV', a.cv)}
      ${getFileRow('Valid Passport', a.valid_passport)}
    `;

    detailView.innerHTML = `
      <div class="detail-section">
        <h2>${a.first_name || ''} ${a.last_name || ''}</h2>
        <div class="detail-block"><strong>Email:</strong><span>${a.email || ''}</span></div>
        <div class="detail-block"><strong>Phone:</strong><span>${a.area_code || ''} ${a.mobile || ''}</span></div>
        <div class="detail-block"><strong>Gender:</strong><span>${a.gender || ''}</span></div>
        <div class="detail-block"><strong>Date of Birth:</strong><span>${a.dob || ''}</span></div>
        <div class="detail-block"><strong>Nationality:</strong><span>${a.nationality || ''}</span></div>
        <div class="detail-block"><strong>Residence:</strong><span>${a.residence_country || ''}</span></div>
        <div class="detail-block"><strong>City:</strong><span>${a.city || ''}</span></div>
        <div class="detail-block"><strong>Address:</strong><span>${a.address || ''}</span></div>
        <div class="detail-block"><strong>Region:</strong><span>${a.region_name || ''}</span></div>
        <div class="detail-block"><strong>University:</strong><span>${a.university_name || ''}</span></div>

        <h3>Passport Info</h3>
        <div class="detail-block"><strong>Passport Number:</strong><span>${a.passport_no || ''}</span></div>
        <div class="detail-block"><strong>Issue Date:</strong><span>${a.issue_date || ''}</span></div>
        <div class="detail-block"><strong>Expiry Date:</strong><span>${a.expiry_date || ''}</span></div>

        <h3>Parent Info</h3>
        <div class="detail-block"><strong>Father Name:</strong><span>${a.father_name || ''}</span></div>
        <div class="detail-block"><strong>Father Mobile:</strong><span>${a.father_mobile || ''}</span></div>
        <div class="detail-block"><strong>Father Occupation:</strong><span>${a.father_occupation || ''}</span></div>
        <div class="detail-block"><strong>Mother Name:</strong><span>${a.mother_name || ''}</span></div>

        <h3>Agent Info</h3>
        <div class="detail-block"><strong>Agent Name:</strong><span>${a.agent_first_name || ''} ${a.agent_last_name || ''}</span></div>
        <div class="detail-block"><strong>Agent Email:</strong><span>${a.agent_email || ''}</span></div>

        <h3>Uploaded Documents</h3>
        ${filesHTML || '<p>No uploaded documents found.</p>'}
      </div>`;
  }

  // UOBS (one-page) renderer: show only relevant fields + JSON transcripts support
  // UOBS (one-page) renderer — show ALL single-page fields
function renderUOBSDetail(a) {
  // files (works with JSON array, single string, or none)
  const transcripts = extractFileList(a.degree_transcripts);
  const passport    = extractFileList(a.valid_passport);

  let filesHTML = '';
  if (transcripts.length) {
    transcripts.forEach((p, i) => {
      const href = makeHref(p), nice = niceNameFromPath(p);
      filesHTML += `
        <div class="file-row">
          <div class="file-label">Degree Transcript${transcripts.length>1?` (${i+1})`:''}</div>
          <a class="file-link" href="${href}" target="_blank" rel="noopener">${nice}</a>
          <a class="download-btn" href="${href}" download>Download</a>
        </div>`;
    });
  } else if (a.degree_transcripts) {
    // if user typed text (no uploads)
    filesHTML += `
      <div class="file-row">
        <div class="file-label">Degree Transcript</div>
        <span class="file-link">${a.degree_transcripts}</span>
      </div>`;
  }
  passport.forEach(p => {
    const href = makeHref(p), nice = niceNameFromPath(p);
    filesHTML += `
      <div class="file-row">
        <div class="file-label">Valid Passport</div>
        <a class="file-link" href="${href}" target="_blank" rel="noopener">${nice}</a>
        <a class="download-btn" href="${href}" download>Download</a>
      </div>`;
  });
  if (!filesHTML) filesHTML = '<p>No uploaded files available.</p>';

  detailView.innerHTML = `
    <div class="detail-section">
      <h2>${a.first_name || ''} ${a.last_name || ''}</h2>

      <h3>Contact</h3>
      <div class="detail-block"><strong>Email:</strong><span>${a.email || ''}</span></div>
      <div class="detail-block"><strong>Phone:</strong><span>${a.area_code || ''} ${a.phone_number || ''}</span></div>

      <h3>Personal Data</h3>
      <div class="detail-block"><strong>Middle Name:</strong><span>${a.middle_name || ''}</span></div>
      <div class="detail-block"><strong>Gender:</strong><span>${a.gender || ''}</span></div>
      <div class="detail-block"><strong>Date of Birth:</strong><span>${a.dob || ''}</span></div>
      <div class="detail-block"><strong>Place of Birth:</strong><span>${a.city_of_birth || ''}${a.country_of_birth ? ', '+a.country_of_birth : ''}</span></div>
      <div class="detail-block"><strong>Nationality:</strong><span>${a.nationality || ''}</span></div>
      <div class="detail-block"><strong>Passport No.:</strong><span>${a.passport || ''}</span></div>
      <div class="detail-block"><strong>Passport Expiry:</strong><span>${a.passport_expiry || ''}</span></div>
      <div class="detail-block"><strong>Country of Visa Application:</strong><span>${a.destination || ''}</span></div>
      <div class="detail-block"><strong>Permanent Address:</strong><span>${a.address_line1 || ''}</span></div>

      <h3>Study Choice</h3>
      <div class="detail-block"><strong>Intended Study Level:</strong><span>${a.intended_study_level || ''}</span></div>
      <div class="detail-block"><strong>Bachelor Program:</strong><span>${a.bachelor_program || ''}</span></div>
      <div class="detail-block"><strong>Masters Program:</strong><span>${a.masters_program || ''}</span></div>
      <div class="detail-block"><strong>PhD Program:</strong><span>${a.phd_program || ''}</span></div>
      <div class="detail-block"><strong>University:</strong><span>${a.university_name || '—'}</span></div>

      <h3>Educational Background — School</h3>
      <div class="detail-block"><strong>School Name:</strong><span>${a.previous_institution_name || ''}</span></div>
      <div class="detail-block"><strong>School Address:</strong><span>${a.previous_institution_street || ''}</span></div>
      <div class="detail-block"><strong>Attended Since:</strong><span>${a.previous_study_start || ''}</span></div>
      <div class="detail-block"><strong>Till:</strong><span>${a.previous_study_graduation || ''}</span></div>
      <div class="detail-block"><strong>Received Certificate:</strong><span>${a.high_school_degree || ''}</span></div>

      <h3>Educational Background — College / University</h3>
      <div class="detail-block"><strong>College / University:</strong><span>${a.post_secondary || ''}</span></div>
      <div class="detail-block"><strong>Address (City/Prov/Country):</strong><span>${a.previous_institution_city || ''}</span></div>
      <div class="detail-block"><strong>Attended Since:</strong><span>${a.college_attended_since || ''}</span></div>
      <div class="detail-block"><strong>Till:</strong><span>${a.college_attended_till || ''}</span></div>
      <div class="detail-block"><strong>Received Certificate:</strong><span>${a.college_received_certificate || ''}</span></div>

      <h3>Declaration</h3>
      <div class="detail-block"><strong>Application Date:</strong><span>${a.application_date || ''}</span></div>
      <div class="detail-block"><strong>Applicant Signature:</strong><span>${a.applicant_signature || ''}</span></div>

      <h3>Uploaded Documents</h3>
      ${filesHTML}
    </div>`;
}

  // -----------------------------------------
  // Mark as read helper (unchanged)
  // -----------------------------------------
  function markAsRead(applicantId, listItem, tableName = 'student_applications') {
    fetch('mark_read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + encodeURIComponent(applicantId) + '&table=' + encodeURIComponent(tableName)
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'ok') {
        listItem.classList.add('read');
        if (listItem._dotRef) listItem._dotRef.style.display = 'none';
      }
    });
  }

  // -----------------------------------------
  // List rendering (unchanged, except renderer switch gains UOBS branch)
  // -----------------------------------------
  function renderList(filtered) {
    applicantList.innerHTML = '';
    filtered.forEach((a) => {
      const li = document.createElement('li');
      li.innerHTML = `
        <div><strong>${a.first_name ?? ''} ${a.last_name ?? ''}</strong></div>
        <div><small>${a.email ?? ''}</small></div>
        <div class="meta-line">
          <span class="elapsed">${a.created_at ? getElapsedTime(a.created_at) : ''}</span>
          <span class="unread-dot" style="${a.is_read == 1 ? 'display: none;' : 'inline-block'}"></span>
        </div>`;

      if (a.is_read == 1) li.classList.add('read');

      li.addEventListener('click', () => {
        document.querySelectorAll('.applicant-list li').forEach(el => el.classList.remove('active'));
        li.classList.add('active');

        if (!li.classList.contains('read')) {
          let table = 'student_applications';
          if (a.region_id == '4') {
            if (a.university_id == '29')      table = 'malta_applications';
            else if (a.university_id == '36') table = 'turkey_applications';
            else if (a.university_id == '41') table = 'georgia_applications';
          }
          markAsRead(a.id, li, table);
        }

        // Choose renderer
        if (a.university_id == '29' && a.region_id == '4') {
          renderMaltaDetail(a);
        } else if (a.university_id == '36' && a.region_id == '4') {
          renderTurkeyDetail(a);
        } else if (a.university_id == '41' && a.region_id == '4') {
          renderGeorgiaDetail(a);
        } else if (String(a.university_id) === '48') { // one-page (UOBS)
          renderUOBSDetail(a);
        } else {
          renderDetail(a); // 6-step stays as-is
        }
      });

      li._dotRef = li.querySelector('.unread-dot');
      applicantList.appendChild(li);
    });

    if (filtered.length === 0) {
      applicantList.innerHTML = '<li>No applicants found.</li>';
      detailView.innerHTML = '<p>No details to show.</p>';
    }
  }

  // -----------------------------------------
  // Filtering (unchanged)
  // -----------------------------------------
  function filterApplicants() {
    const regionVal = regionFilter ? regionFilter.value : '';
    const uniVal    = universityFilter ? universityFilter.value : '';
    const searchVal = (searchInput.value || '').toLowerCase();

    // Select dataset based on region/university
    let dataset = applicants;
    if (regionVal === '4' && uniVal === '29') {
      dataset = maltaApplicants;
    } else if (regionVal === '4' && uniVal === '36') {
      dataset = turkeyApplicants;
    } else if (regionVal === '4' && uniVal === '41') {
      dataset = georgiaApplicants;
    }

    const filtered = dataset.filter(a => {
      const matchesRegion     = !regionVal || a.region_id == regionVal;
      const matchesUniversity = !uniVal    || a.university_id == uniVal;
      const base = `${a.first_name ?? ''} ${a.last_name ?? ''} ${a.email ?? ''}`.toLowerCase();
      const matchesSearch     = base.includes(searchVal);
      return matchesRegion && matchesUniversity && matchesSearch;
    });

    renderList(filtered);
  }

  if (universityFilter) universityFilter.addEventListener('change', filterApplicants);
  searchInput.addEventListener('input', filterApplicants);

  // Initial render
  renderList(applicants);
});
</script>
</body>
</html>
