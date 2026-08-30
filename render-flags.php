<?php
require_once __DIR__ . '/db.php';
/* ===============================
   VALIDATION
================================ */
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$table = $_GET['table'] ?? 'student_applications';

$allowed_tables = [
  'student_applications',
  'malta_applications',
  'turkey_applications'
];

if ($id <= 0 || !in_array($table, $allowed_tables, true)) {
  exit;
}

/* ===============================
   STATUS FLAGS CONFIG
   (ORDER MATTERS – UI ORDER)
================================ */
$flags = [
  'incomplete_app' => ['Incomplete App', 'light'],
  'submitted'      => ['Submitted', 'secondary'],
  'sent_to_platform' => ['Sent to Platform', 'primary'],
  'app_paid'       => ['App Paid', 'success'],   // ✅ FIXED
  'admit'          => ['Admit', 'primary'],
  'i20_sent'       => ['I-20 Sent', 'info'],
  'sevis_paid'     => ['Sevis Paid', 'secondary'],
  'visa_scheduled' => ['Visa Sch.', 'warning'],
  'visa_approved'  => ['Visa OK', 'success'],
  'enrolled'       => ['Enrolled', 'success'],
  'addn_doc'       => ['Add Doc', 'dark'],
  'deny'           => ['Rejected', 'danger'],
  'app_start'      => ['App Start', 'secondary']
];

/* ===============================
   FETCH CURRENT STATUS VALUES
================================ */
$fields = implode(', ', array_keys($flags));

$stmt = $conn->prepare("
  SELECT $fields
  FROM `$table`
  WHERE id = ?
  LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data   = $result ? $result->fetch_assoc() : [];
$stmt->close();

/* ===============================
   RENDER BUTTONS
================================ */
foreach ($flags as $key => [$label, $color]) {

  $status   = !empty($data[$key]);
  $btnClass = $status ? "btn-$color" : "btn-outline-$color";
  $text     = $status ? "✔ $label" : $label;
  $disabled = $status ? 'disabled' : '';

  echo "
    <button
      type='button'
      class='btn btn-sm $btnClass btn-flag'
      data-id='$id'
      data-flag='$key'
      data-table='$table'
      $disabled
    >
      $text
    </button>
  ";
}
