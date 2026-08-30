

<?php
date_default_timezone_set('UTC');
require_once 'auth.php';
require_once __DIR__ . '/db.php';
// Fetch applicants from student_applications table
$query = "
  SELECT sa.*, 
         r.name AS region_name, 
         u.name AS university_name
  FROM student_applications sa
  INNER JOIN regions r ON sa.region_id = r.id
  INNER JOIN universities u ON sa.university_id = u.id
  WHERE sa.university_id = 1 AND sa.region_id = 1
  ORDER BY sa.id DESC
";

$result = $conn->query($query);
$applicants = [];
while ($row = $result->fetch_assoc()) {
    $applicants[] = $row;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Catholic Applicants Report</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
    }

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

    .applicant-list li:hover {
      background: #f0f7ff;
    }

    .applicant-list li.active {
      background: #dbe9ff;
      font-weight: bold;
    }

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

    .file-label {
      width: 200px;
      font-weight: 600;
      color: #333;
    }

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

    .download-btn:hover {
      background: #427431;
    }

    .unread-dot {
      display: inline-block;
      width: 8px;
      height: 8px;
      background: #007bff;
      border-radius: 50%;
      margin-left: 6px;
      margin-top: 2px;
    }

    li.read .unread-dot {
      display: none;
    }

    .elapsed {
      font-size: 0.75rem;
      color: #888;
    }

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

    .main-view p {
      color: #777;
    }

    /* ✅ Responsive Layout */
    @media (max-width: 768px) {
      body {
        flex-direction: column;
      }

      .sidebar {
        width: 100%;
        min-height: auto;
        border-right: none;
        border-bottom: 1px solid #ccc;
      }

      .main-view {
        padding: 1rem;
      }

      .file-row {
        flex-direction: column;
        align-items: flex-start;
      }

      .file-label {
        width: 100%;
      }

      .detail-block {
        flex-direction: column;
      }

      .detail-block strong {
        width: 100%;
        margin-bottom: 4px;
      }
    }
  </style>
</head>
<body>
<div class="sidebar">
  <input type="text" id="searchInput" placeholder="Search applicants...">
  <ul class="applicant-list" id="applicantList">
    <?php foreach ($applicants as $i => $applicant): ?>
      <li data-index="<?= $i ?>" data-id="<?= $applicant['id'] ?>" class="<?= $applicant['is_read'] ? 'read' : '' ?>">
        <div><strong><?= htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']) ?></strong></div>
        <div><small><?= htmlspecialchars($applicant['email']) ?></small></div>
        <div class="meta-line">
          <span class="elapsed"><?= htmlspecialchars($applicant['created_at'] ?? '') ?></span>
          <span class="unread-dot"></span>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="main-view" id="detailView"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
   const applicants = <?php echo json_encode($applicants ?? []); ?>;
  const detailView = document.getElementById('detailView');
  const searchInput = document.getElementById('searchInput');

  const toTags = (val) => {
    if (!val) return '';
    try {
      const parsed = typeof val === 'string' ? JSON.parse(val) : val;
      const arr = Array.isArray(parsed) ? parsed : val.split(',');
      return arr.map(v => `<span class="tag">${v.trim()}</span>`).join(' ');
    } catch {
      return val.split(',').map(v => `<span class="tag">${v.trim()}</span>`).join(' ');
    }
  };

 const getElapsedTime = (timestamp) => {
  const now = new Date();
  const then = new Date(timestamp + 'Z'); // Treat as UTC

  const nowUTC = Date.UTC(
    now.getUTCFullYear(),
    now.getUTCMonth(),
    now.getUTCDate(),
    now.getUTCHours(),
    now.getUTCMinutes(),
    now.getUTCSeconds()
  );

  const thenUTC = Date.UTC(
    then.getUTCFullYear(),
    then.getUTCMonth(),
    then.getUTCDate(),
    then.getUTCHours(),
    then.getUTCMinutes(),
    then.getUTCSeconds()
  );

  const diffInSeconds = Math.floor((nowUTC - thenUTC) / 1000);

  if (diffInSeconds < 60) return "Just now";
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
  return `${Math.floor(diffInSeconds / 86400)}d ago`;
};



  const fileFields = {
    'degree_transcripts': 'Degree Transcripts',
    'high_school_degree': 'High School Degree',
    'valid_passport': 'Valid Passport',
    'recommendation_letters': 'Recommendation Letters',
    'personal_statement': 'Personal Statement',
    'cv_resume': 'CV / Resume',
    'english_certificate': 'English Certificate',
    'birth_certificate': 'Birth Certificate',
    'payment_proof': 'Payment Proof'
  };

  const renderDetail = (a) => {
    let filesHTML = '';
    for (const [key, label] of Object.entries(fileFields)) {
      if (a[key]) {
        const fullFileName = a[key].split('/').pop();
        const filename = fullFileName.replace(/^[a-z0-9]{10,}[_-]/i, '');
        filesHTML += `
          <div class="file-row">
            <div class="file-label">${label}</div>
            <a class="file-link" href="${a[key]}" target="_blank">${filename}</a>
            <a class="download-btn" href="${a[key]}" download>Download</a>
          </div>`;
      }
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
    <div class="detail-block"><strong>Bachelor Program:</strong><span>${a.bachelor_program || ''}</span></div>
    <div class="detail-block"><strong>Masters Program:</strong><span>${a.masters_program || ''}</span></div>
    <div class="detail-block"><strong>PhD Program:</strong><span>${a.phd_program || ''}</span></div>
    
    <div class="detail-block"><strong>University:</strong><span>${a.university_name || '—'}</span></div>
    <div class="detail-block"><strong>Region:</strong><span>${a.region_name || '—'}</span></div>


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
  </div>
`;

  };

  // Attach listeners to each applicant list item
  document.querySelectorAll('#applicantList li').forEach((li, i) => {
    const applicant = applicants[i];

    const timeSpan = li.querySelector('.elapsed');
    if (timeSpan) timeSpan.textContent = getElapsedTime(applicant.created_at);

    li._dotRef = li.querySelector('.unread-dot');

    if (applicant.is_read == 1) {
      li.classList.add('read');
      if (li._dotRef) li._dotRef.style.display = 'none';
    } else {
      li.classList.remove('read');
      if (li._dotRef) li._dotRef.style.display = 'inline-block';
    }

    li.addEventListener('click', () => {
      document.querySelectorAll('#applicantList li').forEach(el => el.classList.remove('active'));
      li.classList.add('active');
      if (!li.classList.contains('read')) {
        markAsRead(applicant.id, li);
      }
      renderDetail(applicant);
    });

    if (i === 0) {
      li.classList.add('active');
      renderDetail(applicant);
    }
  });

  searchInput.addEventListener('input', function () {
    const val = this.value.toLowerCase();
    document.querySelectorAll('#applicantList li').forEach(li => {
      li.style.display = li.textContent.toLowerCase().includes(val) ? 'block' : 'none';
    });
  });

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
    } else {
      console.error('Failed to mark as read:', data.message);
    }
  })
  .catch(err => console.error('Mark as read failed:', err));
}

});
</script>


</body>
</html>
