<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$search = "%{$q}%";

$stmt = $conn->prepare("SELECT id, full_name, email, phone, accommodation, ai_summary, ai_confidence,
    valid_passport, degree_certificate, transcripts, cv_resume, passport_photo, payment_proof, created_at
    FROM budapest_applications
    WHERE full_name LIKE ? OR email LIKE ?
    ORDER BY id DESC");
$stmt->bind_param('ss', $search, $search);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($r = $res->fetch_assoc()) {
    $r['ai_confidence'] = floatval($r['ai_confidence'] ?? 0);
    $r['docs'] = [
        "valid_passport" => $r['valid_passport'],
        "degree_certificate" => $r['degree_certificate'],
        "transcripts" => $r['transcripts'],
        "cv_resume" => $r['cv_resume'],
        "passport_photo" => $r['passport_photo'],
        "payment_proof" => $r['payment_proof']
    ];
    // keep accommodation visible
    $r['accommodation'] = $r['accommodation'] ?? '';
    $data[] = $r;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>
