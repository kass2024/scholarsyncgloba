<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/marketing_brochure_schema.php';

pcvc_marketing_brochure_ensure_schema($conn);

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(400);
    echo '<h1 style="font-family:sans-serif;text-align:center;padding:40px">Missing brochure reference.</h1>';
    exit;
}

$stmt = $conn->prepare(
    'SELECT b.id, b.title, b.slug, b.description, b.pdf_filename, b.pdf_path,
            b.html_content, b.attach_pdf, b.extraction_status,
            b.view_count, b.share_count, b.created_at, b.region_id, r.name AS region_name
     FROM marketing_brochures b
     LEFT JOIN regions r ON r.id = b.region_id
     WHERE b.slug = ? AND b.is_active = 1
     LIMIT 1'
);
$stmt->bind_param('s', $slug);
$stmt->execute();
$brochure = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$brochure) {
    http_response_code(404);
    echo '<h1 style="font-family:sans-serif;text-align:center;padding:60px">Brochure not found.</h1>';
    exit;
}

$conn->query('UPDATE marketing_brochures SET view_count = view_count + 1 WHERE id = ' . (int) $brochure['id']);

$shareToken = trim((string) ($_GET['s'] ?? ''));
if ($shareToken !== '') {
    $u = $conn->prepare('UPDATE marketing_brochure_shares
                         SET open_count = open_count + 1, last_opened_at = NOW()
                         WHERE share_token = ?');
    $u->bind_param('s', $shareToken);
    $u->execute();
    $u->close();
}

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script  = $_SERVER['SCRIPT_NAME'] ?? '';
$baseDir = rtrim(str_replace('\\', '/', dirname($script)), '/');
$pdfUrl  = $baseDir . '/' . ltrim((string) $brochure['pdf_path'], '/');
$pageUrl = $scheme . '://' . $host . $baseDir . '/brochure-view.php?slug=' . urlencode((string) $brochure['slug']);

$regionName = trim((string) ($brochure['region_name'] ?? '')) ?: 'Global';
$createdAt  = !empty($brochure['created_at']) ? date('F j, Y', strtotime((string) $brochure['created_at'])) : '';

$title       = (string) $brochure['title'];
$description = trim((string) ($brochure['description'] ?? '')) ?: ($title . ' — official brochure for ' . $regionName . '.');
$htmlContent = (string) ($brochure['html_content'] ?? '');
$attachPdf   = (int) ($brochure['attach_pdf'] ?? 1) === 1;
$hasHtml     = trim($htmlContent) !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars($regionName) ?> | ScholarSync Global</title>
<meta name="description" content="<?= htmlspecialchars($description) ?>">

<meta property="og:type" content="article">
<meta property="og:title" content="<?= htmlspecialchars($title) ?>">
<meta property="og:description" content="<?= htmlspecialchars($description) ?>">
<meta property="og:url" content="<?= htmlspecialchars($pageUrl) ?>">
<meta property="og:image" content="<?= htmlspecialchars($baseDir) ?>/scholarsync-global-logo.jpg">
<meta name="twitter:card" content="summary_large_image">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
:root{
    --brand:#427431;--brand-dark:#2f5a26;--brand-soft:#e8f1e1;
    --accent:#E21D1E;--whatsapp:#25D366;--info:#3661B9;
    --text:#1e293b;--muted:#64748b;--border:#e2e8f0;
    --bg:#f5f7fb;--surface:#fff;
    --shadow-sm:0 1px 3px rgba(15,23,42,.07);
    --shadow:0 12px 30px -12px rgba(15,23,42,.18);
    --shadow-lg:0 28px 70px -20px rgba(15,23,42,.25);
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
    font-family:'Inter',system-ui,sans-serif;
    background:var(--bg);color:var(--text);line-height:1.6;
}

/* ---------- Header ---------- */
.site-header{
    background:linear-gradient(135deg,var(--brand) 0%,var(--brand-dark) 100%);
    color:#fff;padding:14px 0;
    box-shadow:0 4px 20px rgba(0,0,0,.18);
    border-bottom:3px solid var(--accent);
    position:sticky;top:0;z-index:50;
}
.container{max-width:1180px;margin:0 auto;padding:0 22px}
.site-header .row{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.brand{display:flex;align-items:center;gap:12px}
.brand-mark{
    width:42px;height:42px;border-radius:10px;background:#fff;
    display:grid;place-items:center;color:var(--brand);font-weight:800;font-size:1.2rem;
    box-shadow:0 2px 8px rgba(0,0,0,.15);
}
.brand-name{font-weight:800;font-size:1.1rem;letter-spacing:.4px}
.brand-tag{font-size:.74rem;opacity:.85;text-transform:uppercase;letter-spacing:1.2px}
.site-header .tools{display:flex;gap:8px;flex-wrap:wrap}
.btn-pill{
    background:rgba(255,255,255,.15);
    border:1px solid rgba(255,255,255,.28);
    color:#fff;padding:8px 14px;border-radius:999px;font-size:.82rem;font-weight:600;
    text-decoration:none;display:inline-flex;align-items:center;gap:6px;
    transition:.2s;cursor:pointer;
}
.btn-pill:hover{background:rgba(255,255,255,.28);color:#fff}
.btn-pill.solid{background:#fff;color:var(--brand)}
.btn-pill.solid:hover{background:#fdfdfd}
.btn-pill .lbl{display:inline}
@media (max-width:780px){
    .site-header{padding:10px 0}
    .brand-mark{width:36px;height:36px;font-size:1rem}
    .brand-name{font-size:.95rem}
    .brand-tag{font-size:.66rem;letter-spacing:.8px}
    .site-header .tools{gap:6px}
    .btn-pill{padding:8px 11px;font-size:0;border-radius:10px}
    .btn-pill i{font-size:1.05rem}
    .btn-pill .lbl{display:none}
}

/* ---------- Hero ---------- */
.hero{
    background:linear-gradient(135deg,#fff 0%,#f0f4f8 100%);
    padding:36px 0 26px;
    position:relative;overflow:hidden;
}
.hero::before{
    content:'';position:absolute;right:-120px;top:-120px;
    width:380px;height:380px;border-radius:50%;
    background:radial-gradient(circle,rgba(66,116,49,.08) 0%,transparent 70%);
}
.hero-inner{display:grid;grid-template-columns:1.4fr 1fr;gap:36px;align-items:center}
@media (max-width:860px){.hero-inner{grid-template-columns:1fr}}
@media (max-width:780px){
    .hero{padding:20px 0 16px}
    .hero-card{display:none}
}
.hero .region-pill{
    display:inline-flex;align-items:center;gap:6px;
    background:var(--brand-soft);color:var(--brand);
    padding:6px 14px;border-radius:999px;font-weight:700;font-size:.78rem;
    text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;
}
.hero h1{
    font-size:2.2rem;line-height:1.18;font-weight:800;
    color:var(--text);margin-bottom:14px;
}
@media (max-width:780px){
    .hero h1{font-size:1.5rem;line-height:1.22;margin-bottom:10px}
    .hero .region-pill{margin-bottom:10px;font-size:.7rem;padding:4px 12px}
}
.hero .lead{font-size:1.05rem;color:var(--muted);max-width:600px;margin-bottom:18px}
@media (max-width:780px){.hero .lead{font-size:.92rem;margin-bottom:12px;display:none}}
.hero .meta-row{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:24px;color:var(--muted);font-size:.85rem}
.hero .meta-row span{display:inline-flex;align-items:center;gap:6px}
@media (max-width:780px){
    .hero .meta-row{gap:8px;font-size:.74rem;margin-bottom:14px}
    .hero .meta-row span:nth-child(n+3){display:none}
}
.hero .actions{display:flex;gap:10px;flex-wrap:wrap}
@media (max-width:780px){
    .hero .actions{display:none} /* covered by sticky action bar */
}

/* ---------- Mobile action bar (sticky, below header) ---------- */
.mobile-action-bar{display:none}
@media (max-width:780px){
    .mobile-action-bar{
        display:grid;grid-template-columns:repeat(4,1fr);gap:6px;
        background:#fff;border-bottom:1px solid var(--border);
        padding:8px 14px;position:sticky;top:54px;z-index:40;
        box-shadow:0 4px 14px -8px rgba(15,23,42,.18);
    }
    .mobile-action-bar button,.mobile-action-bar a{
        background:#fff;border:1px solid var(--border);border-radius:10px;
        padding:9px 4px;font-size:.65rem;font-weight:600;color:var(--text);
        text-decoration:none;cursor:pointer;
        display:flex;flex-direction:column;align-items:center;gap:3px;line-height:1;
    }
    .mobile-action-bar i{font-size:1.05rem;color:var(--brand)}
    .mobile-action-bar .primary{background:var(--brand);color:#fff;border-color:var(--brand)}
    .mobile-action-bar .primary i{color:#fff}
}
.btn{
    border:none;padding:12px 20px;border-radius:12px;font-weight:600;font-size:.92rem;
    cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;
    transition:.2s;
}
.btn-primary{background:var(--brand);color:#fff;box-shadow:var(--shadow-sm)}
.btn-primary:hover{background:var(--brand-dark);transform:translateY(-1px)}
.btn-wa{background:var(--whatsapp);color:#fff}
.btn-wa:hover{background:#1da750;color:#fff}
.btn-outline{background:transparent;border:1.5px solid var(--brand);color:var(--brand)}
.btn-outline:hover{background:var(--brand-soft)}

.hero-card{
    background:#fff;border-radius:20px;padding:22px;border:1px solid var(--border);
    box-shadow:var(--shadow-lg);
    position:relative;
}
.hero-card .pdf-thumb{
    border-radius:14px;overflow:hidden;
    background:linear-gradient(135deg,var(--brand) 0%,var(--brand-dark) 100%);
    color:#fff;padding:36px 20px;text-align:center;
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.1);
}
.hero-card .pdf-thumb i{font-size:5rem;opacity:.9}
.hero-card .pdf-thumb .file-name{
    margin-top:14px;font-weight:600;font-size:.95rem;
    word-break:break-all;opacity:.95;
}
.hero-card .qr-box{
    margin-top:14px;background:#fafbfd;border-radius:12px;padding:14px;
    display:flex;align-items:center;gap:14px;border:1px solid var(--border);
}
.hero-card .qr-box img{width:90px;height:90px;border-radius:8px;background:#fff;padding:4px;border:1px solid var(--border)}
.hero-card .qr-box .label{font-size:.78rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.hero-card .qr-box .url{font-size:.78rem;color:var(--text);word-break:break-all}

/* ---------- Reader section ---------- */
.section{padding:38px 0}
.section h2{
    font-size:1.4rem;font-weight:700;margin-bottom:8px;
    display:flex;align-items:center;gap:10px;color:var(--text);
}
.section h2::before{
    content:'';width:6px;height:24px;background:var(--brand);border-radius:3px;
}
.section .lead2{color:var(--muted);font-size:.92rem;margin-bottom:18px}
@media (max-width:780px){
    .section{padding:18px 0}
    .section h2{font-size:1.1rem;margin-bottom:4px}
    .section h2::before{height:18px;width:5px}
    .section .lead2{font-size:.78rem;margin-bottom:12px}
}

/* ---------- Article (extracted HTML) ---------- */
.article-card{
    background:#fff;border:1px solid var(--border);border-radius:18px;
    padding:34px 42px;box-shadow:var(--shadow);
    max-width:880px;margin:0 auto;font-size:1.02rem;line-height:1.75;
}
@media (max-width:680px){
    .article-card{padding:20px 18px;border-radius:14px;font-size:.95rem;line-height:1.65}
    .article-card .brochure-heading{font-size:1.05rem;margin:18px 0 6px}
    .article-card .brochure-subheading{font-size:.96rem;margin:14px 0 4px}
    .article-card .brochure-list{padding-left:18px}
    .article-card .brochure-list li{margin-bottom:6px}
}
.article-card .brochure-heading{
    font-size:1.35rem;font-weight:800;color:var(--brand-dark);
    margin:28px 0 8px;letter-spacing:.3px;
    padding-bottom:8px;border-bottom:2px solid var(--brand-soft);
}
.article-card .brochure-subheading{
    font-size:1.08rem;font-weight:700;color:var(--text);margin:22px 0 6px;
}
.article-card .brochure-para{
    margin:0 0 14px;color:var(--text);
}
.article-card .brochure-list{
    margin:0 0 18px;padding-left:22px;
}
.article-card .brochure-list li{
    margin-bottom:8px;color:var(--text);
}
.article-card ul.brochure-list li::marker{color:var(--brand)}
.article-card ol.brochure-list li::marker{color:var(--brand);font-weight:700}
.article-card a{color:var(--info);text-decoration:underline;word-break:break-word}
.article-card a:hover{color:var(--brand-dark)}
.article-card > :first-child{margin-top:0}
.article-card strong{color:var(--text);font-weight:700}

/* AI-formatted blocks */
.article-card .brochure-callout,
.article-card .brochure-warning,
.article-card .brochure-tip{
    border-radius:12px;padding:14px 16px 14px 46px;margin:16px 0;
    position:relative;font-size:.95rem;line-height:1.65;
}
.article-card .brochure-callout::before,
.article-card .brochure-warning::before,
.article-card .brochure-tip::before{
    position:absolute;left:14px;top:14px;
    width:24px;height:24px;border-radius:50%;
    display:grid;place-items:center;
    font-size:.95rem;color:#fff;font-weight:800;font-family:'Inter',system-ui,sans-serif;
    line-height:1;
}
.article-card .brochure-callout{
    background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;
}
.article-card .brochure-callout::before{background:#3661B9;content:'i'}
.article-card .brochure-warning{
    background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;
}
.article-card .brochure-warning::before{background:#dc2626;content:'!'}
.article-card .brochure-tip{
    background:#ecfdf5;border:1px solid #bbf7d0;color:#14532d;
}
.article-card .brochure-tip::before{background:#16a34a;content:'★';font-size:.78rem}
.article-card .brochure-callout strong,
.article-card .brochure-warning strong,
.article-card .brochure-tip strong{color:inherit}

/* AI-formatted tables (with horizontal scroll on phones) */
.article-card .brochure-table-wrap{
    overflow-x:auto;-webkit-overflow-scrolling:touch;
    margin:18px -4px;border-radius:12px;
}
.article-card .brochure-table{
    border-collapse:collapse;width:100%;min-width:480px;
    font-size:.92rem;background:#fff;
}
.article-card .brochure-table thead{background:var(--brand-soft)}
.article-card .brochure-table th{
    text-align:left;font-weight:700;color:var(--brand-dark);
    padding:10px 14px;border-bottom:2px solid #c7e0bc;
    font-size:.85rem;letter-spacing:.2px;
}
.article-card .brochure-table td{
    padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:top;
}
.article-card .brochure-table tr:last-child td{border-bottom:none}
.article-card .brochure-table tr:nth-child(even) td{background:#fafbfd}

@media (max-width:680px){
    .article-card .brochure-callout,
    .article-card .brochure-warning,
    .article-card .brochure-tip{
        padding:12px 12px 12px 40px;font-size:.88rem;
    }
    .article-card .brochure-table{font-size:.85rem;min-width:380px}
    .article-card .brochure-table th,
    .article-card .brochure-table td{padding:8px 10px}
}

.pdf-viewer{
    background:#1e293b;border-radius:16px;overflow:hidden;
    box-shadow:var(--shadow-lg);position:relative;
}
.pdf-viewer .toolbar{
    background:#0f172a;color:#fff;padding:10px 16px;
    display:flex;justify-content:space-between;align-items:center;
    font-size:.85rem;gap:10px;
}
.pdf-viewer .toolbar a{color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-weight:600;opacity:.85;transition:.2s}
.pdf-viewer .toolbar a:hover{opacity:1}
.pdf-viewer iframe{width:100%;height:800px;border:none;background:#fff;display:block}

/* Mobile PDF card — replaces unreliable inline iframe rendering on phones */
.pdf-mobile-card{display:none}
@media (max-width:780px){
    .pdf-viewer iframe,.pdf-viewer .toolbar{display:none}
    .pdf-viewer{background:transparent;box-shadow:none}
    .pdf-mobile-card{
        display:block;background:#fff;border:1px solid var(--border);
        border-radius:16px;padding:20px;box-shadow:var(--shadow);
    }
    .pdf-mobile-card .pdf-icon{
        width:60px;height:60px;border-radius:14px;
        background:linear-gradient(135deg,#fee2e2,#fecaca);color:var(--accent);
        display:grid;place-items:center;font-size:1.9rem;margin:0 auto 12px;
    }
    .pdf-mobile-card .pdf-name{
        text-align:center;font-weight:700;font-size:.92rem;color:var(--text);
        word-break:break-word;margin-bottom:4px;
    }
    .pdf-mobile-card .pdf-sub{
        text-align:center;font-size:.74rem;color:var(--muted);margin-bottom:16px;
    }
    .pdf-mobile-card .pdf-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .pdf-mobile-card .pdf-actions a{
        padding:12px 6px;border-radius:11px;text-decoration:none;
        display:flex;flex-direction:column;align-items:center;gap:4px;
        font-size:.78rem;font-weight:700;line-height:1.1;
    }
    .pdf-mobile-card .pdf-actions a i{font-size:1.2rem}
    .pdf-mobile-card .open-btn{background:var(--brand);color:#fff}
    .pdf-mobile-card .download-btn{background:#f1f5f9;color:var(--text);border:1px solid var(--border)}
}

/* ---------- Features ---------- */
.features{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:18px;margin-top:24px;
}
.feature{
    background:#fff;border:1px solid var(--border);border-radius:14px;
    padding:20px;box-shadow:var(--shadow-sm);transition:.2s;
}
.feature:hover{transform:translateY(-3px);box-shadow:var(--shadow)}
.feature .icon{
    width:46px;height:46px;border-radius:12px;color:#fff;
    background:linear-gradient(135deg,var(--brand) 0%,var(--brand-dark) 100%);
    display:grid;place-items:center;font-size:1.3rem;margin-bottom:12px;
}
.feature h5{font-size:1rem;font-weight:700;margin-bottom:6px}
.feature p{font-size:.85rem;color:var(--muted);margin:0}
@media (max-width:780px){
    .features-section{display:none} /* keep mobile focused on requirements */
    .desktop-only-article{display:none} /* mobile uses the reader overlay instead */
}

/* ---------- CTA ---------- */
.cta{
    background:linear-gradient(135deg,var(--brand) 0%,var(--brand-dark) 100%);
    color:#fff;border-radius:20px;padding:38px;text-align:center;
    box-shadow:var(--shadow-lg);position:relative;overflow:hidden;
}
.cta::before{
    content:'';position:absolute;left:-80px;bottom:-80px;
    width:300px;height:300px;border-radius:50%;
    background:radial-gradient(circle,rgba(255,255,255,.15) 0%,transparent 70%);
}
.cta h2{font-size:1.6rem;font-weight:800;margin-bottom:8px;justify-content:center}
.cta h2::before{display:none}
.cta p{max-width:620px;margin:0 auto 22px;opacity:.92;font-size:1rem}
.cta .actions{justify-content:center;display:flex;gap:10px;flex-wrap:wrap}
.cta .btn-primary{background:#fff;color:var(--brand)}
.cta .btn-primary:hover{background:#fafafa}
.cta .btn-outline{border-color:#fff;color:#fff}
.cta .btn-outline:hover{background:rgba(255,255,255,.12)}
@media (max-width:780px){
    .cta{padding:24px 20px;border-radius:16px}
    .cta h2{font-size:1.15rem;margin-bottom:6px}
    .cta p{font-size:.85rem;margin-bottom:14px}
    .cta .btn{padding:10px 16px;font-size:.85rem}
}

/* ---------- Mobile Reader Overlay (full-screen smart reader) ---------- */
.reader-overlay{display:none}
@media (max-width:780px){
    .reader-overlay{display:flex}
}
.reader-overlay{
    position:fixed;inset:0;background:#fff;z-index:400;
    transform:translateY(100%);transition:transform .32s cubic-bezier(.4,0,.2,1);
    overflow-y:auto;-webkit-overflow-scrolling:touch;
    flex-direction:column;
}
.reader-overlay.show{transform:translateY(0)}
.reader-head{
    position:sticky;top:0;background:#fff;z-index:5;
    padding:12px 16px;border-bottom:1px solid var(--border);
    display:flex;align-items:center;gap:10px;
    box-shadow:0 2px 8px -4px rgba(15,23,42,.08);
}
.reader-head .close{
    width:38px;height:38px;border-radius:11px;border:none;background:#f1f5f9;
    color:var(--text);font-size:1.3rem;cursor:pointer;flex:0 0 38px;
    display:grid;place-items:center;
}
.reader-head .ttl{
    flex:1;min-width:0;
}
.reader-head .ttl .t1{
    font-size:.7rem;text-transform:uppercase;letter-spacing:1px;
    color:var(--brand);font-weight:700;
}
.reader-head .ttl .t2{
    font-size:.95rem;font-weight:700;color:var(--text);
    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.reader-head .quick{display:flex;gap:6px}
.reader-head .quick button,.reader-head .quick a{
    width:38px;height:38px;border-radius:11px;border:1px solid var(--border);
    background:#fff;color:var(--text);cursor:pointer;
    display:grid;place-items:center;font-size:1.05rem;text-decoration:none;
}
.reader-head .quick .primary{background:var(--brand);color:#fff;border-color:var(--brand)}

.reader-body{
    padding:18px 18px 28px;flex:1;
}
.reader-progress{
    position:sticky;top:62px;background:#e2e8f0;height:3px;z-index:4;
}
.reader-progress .bar{
    height:100%;background:linear-gradient(90deg,var(--brand),var(--accent));
    width:0;transition:width .12s linear;
}

.reader-body .article-card{
    padding:0;border:none;box-shadow:none;background:transparent;
    max-width:100%;font-size:1rem;line-height:1.72;
}
.reader-body .article-card .brochure-heading{
    font-size:1.25rem;margin:24px 0 10px;line-height:1.3;
}
.reader-body .article-card .brochure-heading:first-child{margin-top:6px}
.reader-body .article-card .brochure-subheading{
    font-size:1.05rem;margin:20px 0 8px;
}
.reader-body .article-card .brochure-para{margin:0 0 14px}
.reader-body .article-card .brochure-list{margin:0 0 18px;padding-left:22px}
.reader-body .article-card .brochure-list li{margin-bottom:8px;line-height:1.6}

.reader-foot{
    background:linear-gradient(135deg,var(--brand) 0%,var(--brand-dark) 100%);
    color:#fff;padding:22px 18px 26px;margin-top:14px;
}
.reader-foot h4{font-size:1.05rem;font-weight:800;margin-bottom:4px}
.reader-foot p{font-size:.85rem;opacity:.9;margin-bottom:14px;line-height:1.5}
.reader-foot .rf-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.reader-foot .rf-actions a,.reader-foot .rf-actions button{
    background:#fff;color:var(--brand);border:none;cursor:pointer;
    padding:11px 8px;border-radius:11px;font-weight:700;font-size:.82rem;
    display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;
}
.reader-foot .rf-actions .outline{background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,.6)}

/* QR overlay (mobile) */
.qr-overlay{
    position:fixed;inset:0;background:rgba(15,23,42,.6);
    display:none;align-items:center;justify-content:center;z-index:300;
    padding:24px;backdrop-filter:blur(4px);
}
.qr-overlay.show{display:flex}
.qr-overlay .box{
    background:#fff;border-radius:18px;padding:24px;max-width:340px;width:100%;
    text-align:center;position:relative;box-shadow:var(--shadow-lg);
}
.qr-overlay .box img{width:240px;height:240px;border-radius:12px;border:1px solid var(--border);padding:6px;background:#fff}
.qr-overlay .box h4{font-size:1.05rem;font-weight:700;margin-bottom:6px;color:var(--text)}
.qr-overlay .box p{font-size:.78rem;color:var(--muted);margin-bottom:14px}
.qr-overlay .box .close-x{
    position:absolute;top:10px;right:10px;background:#f1f5f9;border:none;
    width:32px;height:32px;border-radius:50%;font-size:1.1rem;cursor:pointer;color:var(--muted);
}
.qr-overlay .box .url-line{
    font-size:.72rem;color:var(--muted);word-break:break-all;
    background:#f8fafc;padding:8px 12px;border-radius:8px;margin-top:12px;
}

/* ---------- Footer ---------- */
.site-footer{
    background:#0f172a;color:#cbd5e1;padding:30px 0;margin-top:38px;
}
.site-footer .row{display:flex;flex-wrap:wrap;gap:14px;justify-content:space-between;align-items:center}
.site-footer .links a{color:#cbd5e1;text-decoration:none;margin-right:18px;font-size:.85rem}
.site-footer .links a:hover{color:#fff}
.site-footer small{font-size:.78rem;opacity:.7}

/* ---------- Toast for copy ---------- */
.fly-toast{
    position:fixed;bottom:28px;left:50%;transform:translateX(-50%);
    background:#1e293b;color:#fff;padding:12px 22px;border-radius:999px;
    box-shadow:var(--shadow-lg);font-size:.88rem;font-weight:600;
    z-index:200;opacity:0;transition:.25s;pointer-events:none;
}
.fly-toast.show{opacity:1;transform:translateX(-50%) translateY(-6px)}
</style>
</head>
<body>

<header class="site-header">
    <div class="container">
        <div class="row">
            <div class="brand">
                <div class="brand-mark">P</div>
                <div>
                    <div class="brand-name">ScholarSync Global</div>
                    <div class="brand-tag">Official brochure · <?= htmlspecialchars($regionName) ?></div>
                </div>
            </div>
            <div class="tools">
                <?php if ($attachPdf): ?>
                    <a href="<?= htmlspecialchars($pdfUrl) ?>" download class="btn-pill" title="Download PDF"><i class="bi bi-download"></i> <span class="lbl">Download PDF</span></a>
                <?php endif; ?>
                <button class="btn-pill" onclick="copyPageLink()" title="Copy link"><i class="bi bi-link-45deg"></i> <span class="lbl">Copy link</span></button>
                <button class="btn-pill" onclick="openQr()" title="Scan QR"><i class="bi bi-qr-code"></i> <span class="lbl">Scan</span></button>
                <button class="btn-pill solid" onclick="shareNative()" title="Share"><i class="bi bi-share-fill"></i> <span class="lbl">Share</span></button>
            </div>
        </div>
    </div>
</header>

<!-- Mobile-only quick action bar — clear path to read / download / share / scan -->
<div class="mobile-action-bar">
    <button class="primary" onclick="openReader()"><i class="bi bi-book-half"></i> Read</button>
    <?php if ($attachPdf): ?>
        <a href="<?= htmlspecialchars($pdfUrl) ?>" download><i class="bi bi-download"></i> PDF</a>
    <?php else: ?>
        <button onclick="copyPageLink()"><i class="bi bi-link-45deg"></i> Copy</button>
    <?php endif; ?>
    <button onclick="shareNative()"><i class="bi bi-share-fill"></i> Share</button>
    <button onclick="openQr()"><i class="bi bi-qr-code"></i> Scan</button>
</div>

<section class="hero">
    <div class="container hero-inner">
        <div>
            <span class="region-pill"><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($regionName) ?></span>
            <h1><?= htmlspecialchars($title) ?></h1>
            <p class="lead"><?= nl2br(htmlspecialchars($description)) ?></p>
            <div class="meta-row">
                <?php if ($createdAt): ?><span><i class="bi bi-calendar3"></i> Published <?= htmlspecialchars($createdAt) ?></span><?php endif; ?>
                <span><i class="bi bi-eye"></i> <?= number_format((int) $brochure['view_count']) ?> views</span>
                <span><i class="bi bi-share"></i> <?= number_format((int) $brochure['share_count']) ?> shares</span>
                <span><i class="bi bi-file-earmark-pdf"></i> Official PDF</span>
            </div>
            <div class="actions">
                <button class="btn btn-primary" onclick="openReader()"><i class="bi bi-book-half"></i> Read brochure</button>
                <?php if ($attachPdf): ?>
                    <a href="<?= htmlspecialchars($pdfUrl) ?>" download class="btn btn-outline"><i class="bi bi-download"></i> Download PDF</a>
                <?php endif; ?>
                <button class="btn btn-wa" onclick="shareWhatsApp()"><i class="bi bi-whatsapp"></i> Send via WhatsApp</button>
            </div>
        </div>
        <div class="hero-card">
            <div class="pdf-thumb">
                <i class="bi bi-file-earmark-richtext-fill"></i>
                <div class="file-name"><?= htmlspecialchars($title) ?></div>
            </div>
            <div class="qr-box">
                <img alt="QR" src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode($pageUrl) ?>">
                <div>
                    <div class="label">Scan to share</div>
                    <div class="url" id="pageUrlText"><?= htmlspecialchars($pageUrl) ?></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ Beautified article (desktop view; mobile uses the reader overlay) ============ -->
<main class="container section desktop-only-article" id="read">
    <h2><i class="bi bi-book-fill" style="color:var(--brand)"></i> <?= htmlspecialchars($title) ?></h2>
    <p class="lead2">Region: <strong><?= htmlspecialchars($regionName) ?></strong><?php if ($createdAt): ?> · Published <?= htmlspecialchars($createdAt) ?><?php endif; ?></p>

    <article class="article-card">
        <?php if ($hasHtml): ?>
            <?= $htmlContent /* sanitized inline by extractor */ ?>
        <?php else: ?>
            <h3 class="brochure-heading">About this brochure</h3>
            <p class="brochure-para"><?= nl2br(htmlspecialchars($description)) ?></p>
            <p class="brochure-para">The full content is available in the original document below — open it in your browser, download it, or contact our team for a personalised walk-through.</p>
        <?php endif; ?>
    </article>
</main>

<?php if ($attachPdf): ?>
<!-- ============ Original PDF (attached) ============ -->
<section class="container section" id="pdf">
    <h2><i class="bi bi-file-earmark-pdf-fill" style="color:var(--accent)"></i> Original PDF document</h2>
    <p class="lead2">The official PDF version is attached below — open it inline, zoom, or download.</p>
    <div class="pdf-viewer">
        <div class="toolbar">
            <span><i class="bi bi-file-earmark-pdf-fill" style="color:var(--accent)"></i> <?= htmlspecialchars($brochure['pdf_filename']) ?></span>
            <span>
                <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open in new tab</a>
                &nbsp;·&nbsp;
                <a href="<?= htmlspecialchars($pdfUrl) ?>" download><i class="bi bi-download"></i> Download</a>
            </span>
        </div>
        <iframe src="<?= htmlspecialchars($pdfUrl) ?>#view=FitH&toolbar=1" title="Brochure PDF" loading="lazy"></iframe>

        <!-- Mobile fallback (phones can't reliably render inline PDFs) -->
        <div class="pdf-mobile-card">
            <div class="pdf-icon"><i class="bi bi-file-earmark-pdf-fill"></i></div>
            <div class="pdf-name"><?= htmlspecialchars($brochure['pdf_filename']) ?></div>
            <div class="pdf-sub">Tap below to view the official PDF.</div>
            <div class="pdf-actions">
                <a class="open-btn" href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right"></i> Open PDF
                </a>
                <a class="download-btn" href="<?= htmlspecialchars($pdfUrl) ?>" download>
                    <i class="bi bi-download"></i> Download
                </a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="container section features-section">
    <h2><i class="bi bi-stars" style="color:var(--accent)"></i> Why choose ScholarSync Global</h2>
    <p class="lead2">Trusted by hundreds of students applying to <?= htmlspecialchars($regionName) ?> and beyond.</p>
    <div class="features">
        <div class="feature">
            <div class="icon"><i class="bi bi-people-fill"></i></div>
            <h5>Personalised guidance</h5>
            <p>Dedicated counsellors guide you through the documents required for <?= htmlspecialchars($regionName) ?> admissions.</p>
        </div>
        <div class="feature">
            <div class="icon"><i class="bi bi-shield-check"></i></div>
            <h5>Verified universities</h5>
            <p>We only work with accredited universities and recognised institutions, ensuring credibility every step of the way.</p>
        </div>
        <div class="feature">
            <div class="icon"><i class="bi bi-clock-history"></i></div>
            <h5>Fast turnaround</h5>
            <p>Our team responds within hours and follows the full application timeline so deadlines are never missed.</p>
        </div>
        <div class="feature">
            <div class="icon"><i class="bi bi-cash-coin"></i></div>
            <h5>Affordable plans</h5>
            <p>Transparent pricing and flexible payment plans tailored to suit your financial situation.</p>
        </div>
    </div>
</section>

<section class="container">
    <div class="cta">
        <h2>Ready to take the next step?</h2>
        <p>Get in touch with our team and we'll walk you through the documents above and prepare a tailored admission plan for you.</p>
        <div class="actions">
            <button class="btn btn-primary" onclick="shareWhatsApp()"><i class="bi bi-whatsapp"></i> Chat on WhatsApp</button>
            <a href="mailto:infos@scholarsyncglobal.ca?subject=<?= rawurlencode($title) ?>" class="btn btn-outline"><i class="bi bi-envelope-fill"></i> Email us</a>
        </div>
    </div>
</section>

<footer class="site-footer">
    <div class="container row">
        <div>
            <strong>ScholarSync Global</strong>
            <div><small>© <?= date('Y') ?> · All rights reserved · <?= htmlspecialchars($regionName) ?> brochure</small></div>
        </div>
        <div class="links">
            <a href="mailto:infos@scholarsyncglobal.ca">Contact</a>
            <a href="<?= htmlspecialchars($pdfUrl) ?>" download>Download PDF</a>
            <a href="#pdf">Read again</a>
        </div>
    </div>
</footer>

<!-- Full-screen mobile-first smart reader (opens when "Read" is tapped) -->
<div class="reader-overlay" id="readerOverlay" aria-hidden="true" role="dialog" aria-label="Brochure reader">
    <div class="reader-head">
        <button class="close" onclick="closeReader()" aria-label="Close reader">&times;</button>
        <div class="ttl">
            <div class="t1"><?= htmlspecialchars($regionName) ?> · Brochure</div>
            <div class="t2"><?= htmlspecialchars($title) ?></div>
        </div>
        <div class="quick">
            <?php if ($attachPdf): ?>
                <a href="<?= htmlspecialchars($pdfUrl) ?>" download title="Download PDF"><i class="bi bi-download"></i></a>
            <?php endif; ?>
            <button class="primary" onclick="shareNative()" title="Share"><i class="bi bi-share-fill"></i></button>
        </div>
    </div>
    <div class="reader-progress"><div class="bar" id="readerBar"></div></div>
    <div class="reader-body">
        <article class="article-card">
            <?php if ($hasHtml): ?>
                <?= $htmlContent /* sanitized inline by extractor */ ?>
            <?php else: ?>
                <h3 class="brochure-heading">About this brochure</h3>
                <p class="brochure-para"><?= nl2br(htmlspecialchars($description)) ?></p>
                <p class="brochure-para">The full content is available in the original document — tap "Download PDF" above.</p>
            <?php endif; ?>
        </article>

        <div class="reader-foot">
            <h4>Need help with this?</h4>
            <p>Our team is one tap away — we'll walk you through every requirement.</p>
            <div class="rf-actions">
                <button onclick="shareWhatsApp()"><i class="bi bi-whatsapp"></i> Chat on WhatsApp</button>
                <a class="outline" href="mailto:infos@scholarsyncglobal.ca?subject=<?= rawurlencode($title) ?>"><i class="bi bi-envelope"></i> Email us</a>
            </div>
        </div>
    </div>
</div>

<!-- QR overlay (used by the Scan button on header + mobile bar) -->
<div class="qr-overlay" id="qrOverlay" onclick="if(event.target===this)closeQr()">
    <div class="box">
        <button class="close-x" onclick="closeQr()" aria-label="Close">&times;</button>
        <h4><i class="bi bi-qr-code" style="color:var(--brand)"></i> Scan to share</h4>
        <p>Point a phone camera at the code to open this brochure.</p>
        <img alt="QR code" src="https://api.qrserver.com/v1/create-qr-code/?size=480x480&margin=2&data=<?= urlencode($pageUrl) ?>">
        <div class="url-line"><?= htmlspecialchars($pageUrl) ?></div>
    </div>
</div>

<div class="fly-toast" id="flyToast">Link copied!</div>

<script>
const PAGE_URL  = <?= json_encode($pageUrl) ?>;
const SHARE_MSG = <?= json_encode("Hi! Have a look at our brochure: " . $title . " — " . $pageUrl) ?>;

function showToast(msg){
    const t=document.getElementById('flyToast');
    t.textContent=msg;t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'),2200);
}
function copyPageLink(){
    if(navigator.clipboard){
        navigator.clipboard.writeText(PAGE_URL).then(()=>showToast('Link copied to clipboard!'));
    }else{
        const ta=document.createElement('textarea');ta.value=PAGE_URL;document.body.appendChild(ta);
        ta.select();document.execCommand('copy');document.body.removeChild(ta);
        showToast('Link copied to clipboard!');
    }
}
function shareWhatsApp(){
    window.open('https://wa.me/?text='+encodeURIComponent(SHARE_MSG),'_blank');
}
async function shareNative(){
    if(navigator.share){
        try{ await navigator.share({title:document.title,text:<?= json_encode($description) ?>,url:PAGE_URL}); }
        catch(e){}
    }else{copyPageLink();}
}
function openQr(){document.getElementById('qrOverlay').classList.add('show');document.body.style.overflow='hidden';}
function closeQr(){document.getElementById('qrOverlay').classList.remove('show');document.body.style.overflow='';}

/* ---------- Mobile-first reader overlay ---------- */
function openReader(){
    const isMobile = window.matchMedia('(max-width:780px)').matches;
    if(!isMobile){
        // On desktop just scroll to the inline article
        const t=document.getElementById('read');
        if(t) t.scrollIntoView({behavior:'smooth',block:'start'});
        return;
    }
    const o=document.getElementById('readerOverlay');
    if(!o){location.hash='read';return;}
    o.classList.add('show');
    o.setAttribute('aria-hidden','false');
    document.body.style.overflow='hidden';
    o.scrollTop=0;
    document.getElementById('readerBar').style.width='0%';
    history.replaceState(null,'',location.pathname+location.search+'#read');
}
function closeReader(){
    const o=document.getElementById('readerOverlay');
    o.classList.remove('show');
    o.setAttribute('aria-hidden','true');
    document.body.style.overflow='';
    if(location.hash==='#read') history.replaceState(null,'',location.pathname+location.search);
}
(function(){
    const o=document.getElementById('readerOverlay');
    if(!o) return;
    const bar=document.getElementById('readerBar');
    o.addEventListener('scroll',()=>{
        const max=o.scrollHeight-o.clientHeight;
        const pct=max>0?(o.scrollTop/max*100):0;
        bar.style.width=Math.min(100,Math.max(0,pct))+'%';
    },{passive:true});
    // Open automatically if user lands on #read deep link (e.g. from share)
    if(window.matchMedia('(max-width:780px)').matches && location.hash==='#read'){
        setTimeout(openReader,80);
    }
})();
document.addEventListener('keydown',e=>{
    if(e.key==='Escape'){closeQr();closeReader();}
});
</script>
</body>
</html>
