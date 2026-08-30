<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$adminId = (int) $_SESSION['admin_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $typedName = trim($_POST['typed_name'] ?? '');
    $signatureBase64 = $_POST['signature_base64'] ?? '';

    if ($typedName === '' || $signatureBase64 === '') {
        $error = 'Signature is required.';
    } else {

        $sql = "
            INSERT INTO admin_signatures (admin_id, typed_name, signature_base64)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                typed_name = VALUES(typed_name),
                signature_base64 = VALUES(signature_base64)
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $adminId, $typedName, $signatureBase64);
        $stmt->execute();

        header("Location: contract.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Create Signature</title>

<style>
body {
  font-family: Arial, sans-serif;
  background: #f4f6f8;
  padding: 40px;
}
.card {
  max-width: 650px;
  margin: auto;
  background: #fff;
  padding: 30px;
  border-radius: 8px;
}
canvas {
  border: 2px dashed #888;
  width: 100%;
  height: 200px;
  cursor: crosshair;
}
button {
  padding: 10px 16px;
  margin-top: 10px;
}
</style>
</head>

<body>

<div class="card">
  <h2>Create Your Signature</h2>

  <?php if ($error): ?>
    <p style="color:red"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <form method="POST" onsubmit="return saveSignature()">

    <label>Full Legal Name</label>
    <input type="text" name="typed_name" required style="width:100%;padding:8px">

    <p><strong>Draw your signature</strong></p>
    <canvas id="signaturePad"></canvas>

    <input type="hidden" name="signature_base64" id="signature_base64">

    <button type="button" onclick="clearPad()">Clear</button>
    <button type="submit">Save Signature</button>
  </form>
</div>

<script>
const canvas = document.getElementById('signaturePad');
const ctx = canvas.getContext('2d');
canvas.width = canvas.offsetWidth;
canvas.height = canvas.offsetHeight;

let drawing = false;

canvas.addEventListener('mousedown', e => { drawing = true; ctx.moveTo(e.offsetX, e.offsetY); });
canvas.addEventListener('mouseup', () => drawing = false);
canvas.addEventListener('mousemove', e => {
  if (!drawing) return;
  ctx.lineWidth = 2;
  ctx.lineCap = 'round';
  ctx.strokeStyle = '#000';
  ctx.lineTo(e.offsetX, e.offsetY);
  ctx.stroke();
});

function clearPad() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function saveSignature() {
  const dataURL = canvas.toDataURL('image/png');
  document.getElementById('signature_base64').value = dataURL;
  return true;
}
</script>

</body>
</html>
