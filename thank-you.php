<?php
session_start();

if (!isset($_GET['id'])) {
  header('Location: index.php');
  exit;
}

unset($_SESSION['user_id']);

$newId = 'user-' . time() . '-' . random_int(1000, 9999);

$pageTitle = 'Application Submitted | ScholarSync Global';
$pageDescription = 'Your application was received successfully.';
include 'header.php';
?>

<main class="thank-you-main" style="max-width: 640px; margin: 0 auto; padding: 48px 20px 64px;">
  <div style="background: #fff; border-radius: 18px; padding: 40px 28px; box-shadow: 0 20px 40px rgba(0,0,0,.08); border: 1px solid rgba(15,23,42,.08); text-align: center;">
    <h1 style="color: #427431; font-size: 1.5rem; margin-bottom: 12px;">Application submitted successfully</h1>
    <p style="color: #475569; line-height: 1.6; margin-bottom: 8px;">Your application has been received and is under review.</p>
    <p style="color: #1e293b; font-weight: 600; margin-bottom: 28px;"><strong>Reference ID:</strong> <?= htmlspecialchars($_GET['id'], ENT_QUOTES, 'UTF-8') ?></p>
    <a href="job-application.php?id=<?= htmlspecialchars($newId, ENT_QUOTES, 'UTF-8') ?>" style="display: inline-block; background: #E21D1E; color: #fff; padding: 14px 28px; border-radius: 10px; text-decoration: none; font-weight: 700;">Submit another application</a>
  </div>
</main>

<script>
sessionStorage.removeItem('user_id');
sessionStorage.setItem('user_id', "<?= htmlspecialchars($newId, ENT_QUOTES, 'UTF-8') ?>");
</script>

<?php include 'footer.php'; ?>

</body>
</html>
