<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/csrf.php';
require_once __DIR__ . '/helpers/marketing_brochure_schema.php';
require_once __DIR__ . '/helpers/marketing_brochure_ai.php';
require_once __DIR__ . '/helpers/env_load.php';

pcvc_marketing_brochure_ensure_schema($conn);

if (!isset($_SESSION['id'])) {
    header('Location: admin-login.php');
    exit;
}

$csrfToken = pcvc_csrf_token();
$aiEnabled = pcvc_brochure_ai_enabled();
$aiPaused  = function_exists('pcvc_brochure_ai_quota_paused') && pcvc_brochure_ai_quota_paused();
$defaultCountryDial = pcvc_brochure_default_dial_code();

$regions = [];
if ($r = $conn->query('SELECT id, name FROM regions ORDER BY name ASC')) {
    while ($row = $r->fetch_assoc()) {
        $regions[] = $row;
    }
}

$universities = [];
if ($u = $conn->query('SELECT id, name, region_id FROM universities ORDER BY name ASC')) {
    while ($row = $u->fetch_assoc()) {
        $universities[] = [
            'id'        => (int) $row['id'],
            'name'      => (string) $row['name'],
            'region_id' => (int) ($row['region_id'] ?? 0),
        ];
    }
}

/** Map ISO-2 country codes from .env to a dial code (subset of common values). */
function pcvc_brochure_default_dial_code(): string
{
    $iso = strtoupper(trim((string) (function_exists('xander_env_get') ? xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE') : '')));
    $map = [
        'RW' => '+250', 'KE' => '+254', 'UG' => '+256', 'TZ' => '+255',
        'NG' => '+234', 'GH' => '+233', 'ZA' => '+27',  'ET' => '+251',
        'BI' => '+257', 'CD' => '+243', 'CM' => '+237', 'SN' => '+221',
        'CI' => '+225', 'EG' => '+20',  'MA' => '+212', 'DZ' => '+213',
        'GB' => '+44',  'US' => '+1',   'CA' => '+1',   'FR' => '+33',
        'DE' => '+49',  'IT' => '+39',  'ES' => '+34',  'TR' => '+90',
        'IN' => '+91',  'PK' => '+92',  'BD' => '+880', 'CN' => '+86',
        'AE' => '+971', 'SA' => '+966', 'QA' => '+974',
    ];
    return $map[$iso] ?? '+250';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smart Brochure Sharing | Marketing Materials</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --brand:#427431;
    --brand-dark:#2f5a26;
    --brand-soft:#e8f1e1;
    --accent:#E21D1E;
    --accent-soft:#fde4e4;
    --whatsapp:#25D366;
    --info:#3661B9;
    --bg:#eef2f7;
    --surface:#ffffff;
    --border:#e2e8f0;
    --text:#1e293b;
    --muted:#64748b;
    --shadow-sm:0 1px 2px rgba(15,23,42,.06);
    --shadow:0 8px 24px -8px rgba(15,23,42,.12), 0 0 0 1px rgba(15,23,42,.04);
    --shadow-lg:0 24px 60px -20px rgba(15,23,42,.25);
    --radius:16px;
    --radius-sm:10px;
}
*{box-sizing:border-box}
body{
    font-family:'Inter',system-ui,sans-serif;
    background:var(--bg);
    color:var(--text);
    margin:0;
    padding:0;
    line-height:1.5;
}
.page-wrap{max-width:1400px;margin:0 auto;padding:24px}
.hero{
    background:linear-gradient(135deg,var(--brand) 0%,var(--brand-dark) 100%);
    border-radius:var(--radius);
    color:#fff;
    padding:28px 32px;
    position:relative;
    overflow:hidden;
    box-shadow:var(--shadow-lg);
}
.hero::before{
    content:'';position:absolute;right:-60px;top:-60px;
    width:240px;height:240px;border-radius:50%;
    background:radial-gradient(circle,rgba(255,255,255,.15) 0%,transparent 70%);
}
.hero h1{
    font-size:1.6rem;font-weight:800;margin:0 0 6px;
    display:flex;align-items:center;gap:12px;
}
.hero p{margin:0;opacity:.85;font-size:.95rem;max-width:720px}
.hero .badge-strip{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
.hero .chip{
    background:rgba(255,255,255,.18);
    border:1px solid rgba(255,255,255,.25);
    padding:6px 12px;border-radius:999px;font-size:.78rem;font-weight:600;
    backdrop-filter:blur(4px);
}
.stats-grid{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:14px;margin:20px 0;
}
.stat-card{
    background:var(--surface);border-radius:var(--radius-sm);
    padding:18px 20px;border:1px solid var(--border);
    box-shadow:var(--shadow-sm);
    display:flex;align-items:center;gap:14px;
}
.stat-card .icon{
    width:46px;height:46px;border-radius:12px;
    display:grid;place-items:center;font-size:1.4rem;color:#fff;
}
.stat-card .icon.green{background:linear-gradient(135deg,#427431,#2f5a26)}
.stat-card .icon.red{background:linear-gradient(135deg,#E21D1E,#a31313)}
.stat-card .icon.blue{background:linear-gradient(135deg,#3661B9,#1f3f80)}
.stat-card .icon.gold{background:linear-gradient(135deg,#f59e0b,#b45309)}
.stat-card .num{font-size:1.5rem;font-weight:800;line-height:1}
.stat-card .label{font-size:.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}

.toolbar{
    background:var(--surface);border-radius:var(--radius);
    padding:18px 22px;border:1px solid var(--border);
    box-shadow:var(--shadow-sm);
    display:flex;flex-wrap:wrap;align-items:center;gap:14px;
    margin-bottom:18px;
}
.ai-banner{
    background:#fff;border:1px solid var(--border);border-radius:14px;
    padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;
    justify-content:space-between;gap:14px;flex-wrap:wrap;
    box-shadow:var(--shadow-sm);
}
.ai-banner.on{border-left:4px solid var(--brand)}
.ai-banner.warn{border-left:4px solid #f59e0b;background:#fffbeb}
.ai-banner.off{border-left:4px solid #d1d5db;background:#fafbfd}
.ai-banner.warn .ai-ic{background:linear-gradient(135deg,#f59e0b,#d97706)}
.ai-banner-l{display:flex;align-items:flex-start;gap:12px;flex:1;min-width:240px}
.ai-banner .ai-ic{
    width:42px;height:42px;border-radius:11px;display:grid;place-items:center;
    background:linear-gradient(135deg,#427431,#2f5a26);color:#fff;font-size:1.2rem;
    flex:0 0 42px;
}
.ai-banner.off .ai-ic{background:linear-gradient(135deg,#94a3b8,#64748b)}
.ai-banner strong{font-size:.95rem;color:var(--text)}
.ai-banner .ai-sub{font-size:.78rem;color:var(--muted);line-height:1.55;margin-top:3px}
.ai-banner code{background:#f1f5f9;padding:1px 6px;border-radius:4px;font-size:.72rem;color:var(--text)}

.toolbar .search-box{
    position:relative;flex:1 1 280px;
}
.toolbar .search-box i{
    position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);
}
.toolbar input,.toolbar select{
    border:1px solid var(--border);border-radius:10px;
    padding:10px 12px 10px 38px;font-size:.92rem;width:100%;
    background:#f8fafc;outline:none;transition:.2s;
}
.toolbar select{padding-left:14px}
.toolbar input:focus,.toolbar select:focus{
    border-color:var(--brand);background:#fff;
    box-shadow:0 0 0 3px rgba(66,116,49,.15);
}
.btn-brand{
    background:var(--brand);color:#fff;border:none;
    padding:10px 18px;border-radius:10px;
    font-weight:600;font-size:.92rem;display:inline-flex;align-items:center;gap:8px;
    cursor:pointer;transition:.2s;text-decoration:none;
}
.btn-brand:hover{background:var(--brand-dark);transform:translateY(-1px);color:#fff}
.btn-accent{background:var(--accent);color:#fff;border:none;padding:10px 18px;border-radius:10px;font-weight:600;font-size:.92rem;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
.btn-accent:hover{background:#a31313;color:#fff}
.btn-ghost{background:transparent;border:1px solid var(--border);padding:9px 16px;border-radius:10px;font-weight:600;font-size:.88rem;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:var(--text);text-decoration:none}
.btn-ghost:hover{background:var(--surface);border-color:var(--brand);color:var(--brand)}

/* ---------- Upload card ---------- */
.upload-card{
    background:var(--surface);border-radius:var(--radius);
    padding:24px;border:1px solid var(--border);
    box-shadow:var(--shadow);margin-bottom:18px;
}
.upload-grid{display:grid;grid-template-columns:1.1fr 1fr;gap:24px}
@media (max-width:860px){.upload-grid{grid-template-columns:1fr}}
.drop-zone{
    border:2px dashed #cbd5e1;border-radius:var(--radius-sm);
    padding:32px 20px;text-align:center;cursor:pointer;
    transition:.2s;background:#fafbfd;min-height:220px;
    display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;
}
.drop-zone:hover,.drop-zone.dragover{
    border-color:var(--brand);background:var(--brand-soft);
}
.drop-zone .big-icon{font-size:3.2rem;color:var(--brand);line-height:1}
.drop-zone strong{display:block;margin-top:8px;font-size:1.05rem}
.drop-zone small{color:var(--muted)}
.file-chip{
    margin-top:10px;background:#fff;border:1px solid var(--border);
    padding:8px 12px;border-radius:8px;font-size:.85rem;
    display:inline-flex;align-items:center;gap:8px;
}
.form-label{font-weight:600;font-size:.84rem;margin-bottom:6px;color:var(--text)}
.form-control,.form-select{
    border:1px solid var(--border);border-radius:10px;
    padding:10px 12px;font-size:.92rem;background:#f8fafc;
}
.form-control:focus,.form-select:focus{
    border-color:var(--brand);background:#fff;
    box-shadow:0 0 0 3px rgba(66,116,49,.15);
}
.region-row{display:flex;gap:8px}
.region-row .form-select{flex:1}

/* Pretty tick row */
.tick-row{
    display:flex;align-items:flex-start;gap:12px;cursor:pointer;
    background:#f8fafc;border:1px solid var(--border);border-radius:12px;
    padding:12px 14px;user-select:none;transition:.2s;
}
.tick-row:hover{border-color:var(--brand);background:var(--brand-soft)}
.tick-row input[type=checkbox]{position:absolute;opacity:0;pointer-events:none}
.tick-row .check-visual{
    flex:0 0 22px;width:22px;height:22px;border-radius:6px;
    border:2px solid #cbd5e1;background:#fff;
    display:grid;place-items:center;color:#fff;font-size:1rem;
    transition:.2s;margin-top:2px;
}
.tick-row input[type=checkbox]:checked + .check-visual{
    background:var(--brand);border-color:var(--brand);
}
.tick-row strong{display:block;font-weight:600;font-size:.92rem;color:var(--text);margin-bottom:2px}
.tick-row small{color:var(--muted);font-size:.78rem;display:block;line-height:1.4}

/* ---------- Brochure grid ---------- */
.brochure-grid{
    display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;
}
.brochure-card{
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--radius);overflow:hidden;
    transition:.2s;display:flex;flex-direction:column;
    box-shadow:var(--shadow-sm);
}
.brochure-card:hover{box-shadow:var(--shadow);transform:translateY(-2px)}
.brochure-card .cover{
    height:170px;
    background:linear-gradient(135deg,#427431 0%,#2f5a26 100%);
    color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;
    position:relative;padding:18px;
}
.brochure-card .cover .pdf-icon{font-size:3rem;opacity:.85}
.brochure-card .cover .region-tag{
    position:absolute;top:12px;left:12px;
    background:rgba(255,255,255,.22);backdrop-filter:blur(4px);
    border:1px solid rgba(255,255,255,.35);
    color:#fff;font-weight:600;padding:4px 10px;border-radius:999px;font-size:.72rem;
}
.brochure-card .cover .menu-btn{
    position:absolute;top:10px;right:10px;
    background:rgba(0,0,0,.25);border:none;color:#fff;
    width:32px;height:32px;border-radius:50%;cursor:pointer;
}
.brochure-card .body{padding:16px 18px;flex:1;display:flex;flex-direction:column;gap:6px}
.brochure-card .title{font-weight:700;font-size:1rem;color:var(--text);line-height:1.3}
.brochure-card .meta{font-size:.75rem;color:var(--muted);display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.brochure-card .share-block{
    padding:12px 14px;border-top:1px solid var(--border);
    background:linear-gradient(180deg,#fafbfd 0%,#f1f5f9 100%);
}
.brochure-card .share-label{
    display:flex;align-items:center;gap:6px;
    font-size:.7rem;font-weight:700;color:var(--muted);
    text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;
}
.brochure-card .link-row{
    display:flex;gap:6px;margin-bottom:8px;
}
.brochure-card .link-row input{
    flex:1;border:1px solid var(--border);border-radius:8px;
    background:#fff;padding:7px 10px;font-size:.74rem;
    font-family:'Courier New',monospace;color:var(--text);outline:none;
    min-width:0;cursor:text;
}
.brochure-card .link-row .copy-btn{
    flex:0 0 auto;border:none;background:var(--brand);color:#fff;
    padding:7px 12px;border-radius:8px;cursor:pointer;font-size:.78rem;
    font-weight:600;display:inline-flex;align-items:center;gap:5px;transition:.2s;
}
.brochure-card .link-row .copy-btn:hover{background:var(--brand-dark)}
.brochure-card .share-actions{
    display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;
}
.brochure-card .share-actions a,
.brochure-card .share-actions button{
    border:1px solid var(--border);background:#fff;color:var(--text);
    padding:8px 6px;border-radius:8px;cursor:pointer;text-decoration:none;
    display:inline-flex;align-items:center;justify-content:center;gap:5px;
    font-size:.78rem;font-weight:600;transition:.2s;
}
.brochure-card .share-actions a.wa{color:var(--whatsapp);border-color:#d4f5dc}
.brochure-card .share-actions a.wa:hover{background:var(--whatsapp);color:#fff;border-color:var(--whatsapp)}
.brochure-card .share-actions a.em{color:var(--info);border-color:#dde6f6}
.brochure-card .share-actions a.em:hover{background:var(--info);color:#fff;border-color:var(--info)}
.brochure-card .share-actions button.send{color:var(--accent);border-color:#fbd8d8}
.brochure-card .share-actions button.send:hover{background:var(--accent);color:#fff;border-color:var(--accent)}

.brochure-card .footer{
    padding:10px 14px;border-top:1px solid var(--border);
    display:flex;gap:6px;background:#fff;
}
.brochure-card .footer button{
    flex:1;border:none;background:transparent;padding:6px 4px;
    border-radius:8px;font-size:.78rem;font-weight:600;cursor:pointer;
    color:var(--text);transition:.2s;
    display:inline-flex;align-items:center;justify-content:center;gap:5px;
}
.brochure-card .footer button:hover{background:var(--brand-soft);color:var(--brand)}
.brochure-card .footer button.danger:hover{background:var(--accent-soft);color:var(--accent)}
.brochure-card .attach-toggle{
    display:flex;align-items:center;gap:6px;font-size:.74rem;color:var(--muted);cursor:pointer;
    padding:6px 10px;border-radius:8px;transition:.2s;
}
.brochure-card .attach-toggle:hover{background:var(--brand-soft);color:var(--brand)}
.brochure-card .attach-toggle input{margin:0;accent-color:var(--brand)}

/* ---------- Modal ---------- */
.modal-mask{
    position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:1050;
    display:none;align-items:center;justify-content:center;padding:20px;
    backdrop-filter:blur(4px);
}
.modal-mask.show{display:flex}
.modal-box{
    background:#fff;border-radius:var(--radius);max-width:560px;width:100%;
    max-height:92vh;overflow-y:auto;box-shadow:var(--shadow-lg);
    animation:popIn .2s ease;
}
@keyframes popIn{from{transform:scale(.95);opacity:0}to{transform:scale(1);opacity:1}}
.modal-head{
    padding:18px 22px;border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
    background:linear-gradient(135deg,var(--brand) 0%,var(--brand-dark) 100%);
    color:#fff;border-radius:var(--radius) var(--radius) 0 0;
}
.modal-head h3{margin:0;font-size:1.1rem;font-weight:700;display:flex;gap:10px;align-items:center}
.modal-head .close{background:rgba(255,255,255,.2);border:none;color:#fff;width:32px;height:32px;border-radius:50%;font-size:1.2rem;cursor:pointer}
.modal-body{padding:22px}
.modal-foot{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px}

.share-stepper{display:flex;gap:6px;margin-bottom:16px}
.share-stepper .step{
    flex:1;height:6px;border-radius:6px;background:#e2e8f0;
}
.share-stepper .step.active{background:var(--brand)}

.match-card{
    border:1px solid var(--border);border-radius:10px;
    padding:12px 14px;margin-top:8px;cursor:pointer;
    transition:.15s;background:#fafbfd;
}
.match-card:hover{border-color:var(--brand);background:var(--brand-soft)}
.match-card.selected{
    border-color:var(--brand);background:var(--brand-soft);
    box-shadow:0 0 0 3px rgba(66,116,49,.12);
}
.match-card .name{font-weight:700;font-size:.94rem}
.match-card .row-meta{font-size:.75rem;color:var(--muted);margin-top:2px}
.match-card .table-tag{
    display:inline-block;background:#fff;border:1px solid var(--border);
    padding:2px 8px;border-radius:999px;font-size:.7rem;color:var(--muted);
    margin-left:6px;
}

.share-url-box{
    background:#f8fafc;border:1px dashed var(--border);
    padding:10px 12px;border-radius:8px;font-size:.85rem;
    display:flex;gap:8px;align-items:center;word-break:break-all;
    margin-top:6px;
}
.share-url-box input{flex:1;border:none;background:transparent;outline:none;font-family:'Courier New',monospace;font-size:.82rem}

/* New send modal styles */
.channel-pick{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media (max-width:560px){.channel-pick{grid-template-columns:1fr}}
.channel-card{
    background:#fff;border:1.5px solid var(--border);border-radius:14px;
    padding:18px;text-align:left;cursor:pointer;
    display:flex;gap:14px;align-items:flex-start;transition:.2s;
}
.channel-card .ic{
    width:48px;height:48px;border-radius:12px;display:grid;place-items:center;
    color:#fff;font-size:1.5rem;flex:0 0 48px;
}
.channel-card.wa .ic{background:linear-gradient(135deg,#25D366,#128c7e)}
.channel-card.em .ic{background:linear-gradient(135deg,#3661B9,#1f3f80)}
.channel-card .t{font-weight:700;font-size:1rem;color:var(--text);margin-bottom:4px}
.channel-card .d{font-size:.78rem;color:var(--muted);line-height:1.45}
.channel-card.wa:hover{border-color:var(--whatsapp);box-shadow:0 8px 24px -10px rgba(37,211,102,.35);transform:translateY(-2px)}
.channel-card.em:hover{border-color:var(--info);box-shadow:0 8px 24px -10px rgba(54,97,185,.35);transform:translateY(-2px)}
.channel-card code{background:#f1f5f9;padding:1px 4px;border-radius:4px;font-size:.72rem;color:var(--text)}

.chan-pill{
    background:linear-gradient(135deg,var(--brand) 0%,var(--brand-dark) 100%);
    color:#fff;padding:6px 14px;border-radius:999px;
    font-weight:700;font-size:.82rem;
    display:inline-flex;align-items:center;gap:6px;
}
.chan-pill.wa{background:linear-gradient(135deg,#25D366,#128c7e)}
.chan-pill.em{background:linear-gradient(135deg,#3661B9,#1f3f80)}

.tab-bar{display:flex;border:1px solid var(--border);border-radius:10px;background:#f8fafc;padding:4px;margin-bottom:12px}
.tab-btn{
    flex:1;border:none;background:transparent;padding:8px 12px;border-radius:8px;
    font-weight:600;font-size:.85rem;color:var(--muted);cursor:pointer;
    display:inline-flex;align-items:center;justify-content:center;gap:6px;transition:.2s;
}
.tab-btn.active{background:#fff;color:var(--brand);box-shadow:var(--shadow-sm)}

.recip-results{margin-top:10px;max-height:260px;overflow-y:auto;display:flex;flex-direction:column;gap:6px}
.recip-row{
    background:#fff;border:1px solid var(--border);border-radius:10px;
    padding:10px 14px;cursor:pointer;transition:.15s;
    display:flex;justify-content:space-between;align-items:center;gap:10px;
}
.recip-row:hover{border-color:var(--brand);background:var(--brand-soft)}
.recip-row .nm{font-weight:700;font-size:.9rem;color:var(--text)}
.recip-row .mt{font-size:.76rem;color:var(--muted);margin-top:2px}
.recip-row .tag{
    background:#f1f5f9;color:var(--muted);font-size:.68rem;
    padding:2px 8px;border-radius:999px;font-weight:600;
    text-transform:uppercase;letter-spacing:.4px;
}

.recipient-box{
    background:var(--brand-soft);border:1px solid #c7e0bc;border-radius:12px;
    padding:14px 16px;display:flex;justify-content:space-between;align-items:center;gap:10px;
}
.recipient-box .lh{flex:1}
.recipient-box .nm{font-weight:700;font-size:1rem;color:var(--text)}
.recipient-box .mt{font-size:.82rem;color:#3a5a2d;margin-top:3px}
.recipient-box .badge{
    background:#fff;color:var(--brand);font-size:.7rem;padding:3px 10px;
    border-radius:999px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;
    border:1px solid var(--border);
}

.edit-recipient{
    background:#fff;border:1px solid var(--border);border-radius:12px;
    padding:14px 16px;margin-top:10px;
}
.edit-recipient .form-label{
    font-size:.78rem;font-weight:700;color:var(--text);
    text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;
}
.edit-recipient .form-control,.edit-recipient .form-select{
    border:1px solid var(--border);border-radius:9px;font-size:.92rem;
    padding:9px 12px;background:#f8fafc;width:100%;transition:.18s;
}
.edit-recipient .form-control:focus,.edit-recipient .form-select:focus{
    border-color:var(--brand);background:#fff;outline:none;
    box-shadow:0 0 0 3px rgba(66,116,49,.12);
}
.edit-recipient .phone-row{display:flex;gap:8px}
.edit-recipient .phone-row .dial{flex:0 0 140px;cursor:pointer}
.edit-recipient .phone-row input{flex:1}
.edit-recipient .text-muted{font-size:.74rem;line-height:1.4;margin-top:4px;display:block}

.send-status{margin-top:12px;font-size:.85rem}
.send-status.success{color:#16a34a;display:flex;align-items:center;gap:6px}
.send-status.error{color:#dc2626;display:flex;align-items:center;gap:6px}

.channel-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px}
.channel-btn{
    background:#fff;border:1px solid var(--border);
    padding:14px 8px;border-radius:12px;cursor:pointer;
    display:flex;flex-direction:column;align-items:center;gap:6px;
    font-size:.82rem;font-weight:600;color:var(--text);
    transition:.2s;text-decoration:none;
}
.channel-btn i{font-size:1.6rem}
.channel-btn:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm)}
.channel-btn.wa:hover{border-color:var(--whatsapp);color:var(--whatsapp)}
.channel-btn.em:hover{border-color:var(--info);color:var(--info)}
.channel-btn.cp:hover{border-color:var(--brand);color:var(--brand)}

.empty-state{
    background:var(--surface);border-radius:var(--radius);
    padding:64px 20px;text-align:center;border:1px dashed var(--border);
}
.empty-state i{font-size:4rem;color:#cbd5e1}
.empty-state h4{margin:14px 0 6px;color:var(--text)}
.empty-state p{color:var(--muted);margin:0}

.toast-stack{position:fixed;bottom:24px;right:24px;z-index:2000;display:flex;flex-direction:column;gap:10px}
.toast-item{
    background:#1e293b;color:#fff;padding:12px 18px;border-radius:10px;
    box-shadow:var(--shadow-lg);font-size:.88rem;min-width:240px;
    display:flex;align-items:center;gap:10px;
    animation:slideIn .25s ease;
}
.toast-item.success{background:linear-gradient(135deg,#16a34a,#15803d)}
.toast-item.error{background:linear-gradient(135deg,#dc2626,#991b1b)}
.toast-item.info{background:linear-gradient(135deg,#3661B9,#1f3f80)}
@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
.spinner-mini{
    display:inline-block;width:14px;height:14px;border:2px solid #fff;
    border-top-color:transparent;border-radius:50%;animation:spin .6s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}

.upload-progress{
    height:6px;background:#e2e8f0;border-radius:6px;overflow:hidden;margin-top:10px;display:none;
}
.upload-progress .bar{height:100%;background:linear-gradient(90deg,var(--brand),#2f5a26);width:0%;transition:width .2s}
</style>
</head>
<body>
<div class="page-wrap">

    <div class="hero">
        <h1><i class="bi bi-megaphone-fill"></i> Smart Brochure Sharing</h1>
        <p>Upload your region-specific PDF brochures, get a beautified shareable page automatically, and send it straight to customers via WhatsApp or email. Existing applicants are matched instantly across all application tables.</p>
        <div class="badge-strip">
            <span class="chip"><i class="bi bi-cloud-upload"></i> Drag &amp; drop PDF</span>
            <span class="chip"><i class="bi bi-geo-alt-fill"></i> Region-aware</span>
            <span class="chip"><i class="bi bi-whatsapp"></i> WhatsApp share</span>
            <span class="chip"><i class="bi bi-envelope-fill"></i> Email share</span>
            <span class="chip"><i class="bi bi-link-45deg"></i> Auto share link</span>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon green"><i class="bi bi-file-earmark-pdf"></i></div>
            <div>
                <div class="num" id="stat-total">0</div>
                <div class="label">Brochures</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon red"><i class="bi bi-geo-alt"></i></div>
            <div>
                <div class="num" id="stat-regions"><?= count($regions) ?></div>
                <div class="label">Regions</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon blue"><i class="bi bi-eye"></i></div>
            <div>
                <div class="num" id="stat-views">0</div>
                <div class="label">Total Views</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon gold"><i class="bi bi-share"></i></div>
            <div>
                <div class="num" id="stat-shares">0</div>
                <div class="label">Total Shares</div>
            </div>
        </div>
    </div>

    <!-- ============ Upload card ============ -->
    <div class="upload-card">
        <h5 style="margin:0 0 16px;display:flex;gap:8px;align-items:center;font-weight:700">
            <i class="bi bi-cloud-arrow-up-fill" style="color:var(--brand);font-size:1.4rem"></i>
            Upload a new brochure
        </h5>
        <form id="uploadForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="upload_brochure">

            <div class="upload-grid">
                <div>
                    <label class="form-label">PDF file</label>
                    <div class="drop-zone" id="dropZone">
                        <i class="bi bi-file-earmark-pdf-fill big-icon"></i>
                        <strong>Drop your PDF here</strong>
                        <small>or click to browse (max 25 MB)</small>
                        <div id="fileChip" class="file-chip" style="display:none">
                            <i class="bi bi-file-earmark-pdf-fill" style="color:var(--accent)"></i>
                            <span id="fileChipName"></span>
                        </div>
                    </div>
                    <input type="file" id="pdfInput" name="pdf" accept="application/pdf" hidden required>
                    <div class="upload-progress" id="uploadProgress"><div class="bar"></div></div>
                </div>

                <div style="display:flex;flex-direction:column;gap:12px">
                    <div>
                        <label class="form-label">Brochure title</label>
                        <input type="text" name="title" id="titleInput" class="form-control" placeholder="e.g. Details for Common Documents Needed for Admission in Canada" required>
                    </div>
                    <div>
                        <label class="form-label">
                            Region <small class="text-muted" style="font-weight:400">(optional)</small>
                        </label>
                        <div class="region-row">
                            <select name="region_id" id="regionSelect" class="form-select">
                                <option value="">— Choose region —</option>
                                <?php foreach ($regions as $r): ?>
                                    <option value="<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-ghost" onclick="openNewRegion()" title="Add new region">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">
                            University <small class="text-muted" style="font-weight:400">(optional)</small>
                        </label>
                        <div class="region-row">
                            <select name="university_id" id="universitySelect" class="form-select">
                                <option value="">— Choose university —</option>
                                <?php foreach ($universities as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>"
                                            data-region="<?= (int) $u['region_id'] ?>">
                                        <?= htmlspecialchars($u['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-ghost" onclick="openNewUniversity()" title="Add new university">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        <small class="text-muted" style="display:block;margin-top:4px">
                            A brochure can target a region, a university, or both. Choose at least one.
                        </small>
                    </div>
                    <div>
                        <label class="form-label">Short description (optional)</label>
                        <textarea name="description" id="descInput" class="form-control" rows="2" placeholder="What's inside this brochure? Shown on the public page."></textarea>
                    </div>
                    <label class="tick-row" for="attachPdfChk">
                        <input type="checkbox" name="attach_pdf" id="attachPdfChk" value="1" checked>
                        <span class="check-visual"><i class="bi bi-check2"></i></span>
                        <span>
                            <strong>Attach original PDF on the public page</strong>
                            <small>Customers will see the beautified HTML version and the embedded PDF + a download link. Uncheck to share only the HTML.</small>
                        </span>
                    </label>
                    <div style="display:flex;gap:8px;margin-top:auto">
                        <button type="submit" class="btn-brand" id="uploadBtn">
                            <i class="bi bi-magic"></i> Upload &amp; auto-generate share page
                        </button>
                        <button type="reset" class="btn-ghost" onclick="resetUpload()">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- ============ AI status banner ============ -->
    <div class="ai-banner <?= $aiEnabled ? ($aiPaused ? 'warn' : 'on') : 'off' ?>">
        <div class="ai-banner-l">
            <div class="ai-ic"><i class="bi bi-stars"></i></div>
            <div>
                <strong>AI extraction <?= $aiEnabled ? ($aiPaused ? 'paused (OpenAI quota)' : 'enabled') : 'not configured' ?></strong>
                <div class="ai-sub">
                    <?php if ($aiEnabled && $aiPaused): ?>
                        OpenAI returned <strong>insufficient quota</strong>. Brochures still work using the built-in formatter. Add credits at <a href="https://platform.openai.com/billing" target="_blank" rel="noopener">platform.openai.com/billing</a>, then click Re-extract.
                    <?php elseif ($aiEnabled): ?>
                        New uploads are formatted into mobile-first HTML via <code>OPENAI_API_KEY</code>. Use Re-extract to refresh older brochures.
                    <?php else: ?>
                        Add <code>OPENAI_API_KEY</code> to <code>.env</code> for AI-formatted HTML. Without it, the built-in formatter is used automatically.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($aiEnabled): ?>
            <button class="btn-brand" type="button" onclick="reextractAll(false)">
                <i class="bi bi-arrow-repeat"></i> Re-extract all
            </button>
        <?php endif; ?>
    </div>

    <!-- ============ Toolbar ============ -->
    <div class="toolbar">
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="searchBox" placeholder="Search by title or region…">
        </div>
        <select id="filterRegion" class="form-select" style="max-width:240px">
            <option value="0">All regions</option>
            <?php foreach ($regions as $r): ?>
                <option value="<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn-ghost" onclick="loadBrochures()">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>

    <!-- ============ Brochures grid ============ -->
    <div id="brochureContainer">
        <div class="empty-state">
            <i class="bi bi-hourglass-split"></i>
            <h4>Loading brochures…</h4>
            <p>Hang on a second.</p>
        </div>
    </div>
</div>

<!-- ============ Add University Modal ============ -->
<div class="modal-mask" id="newUniversityModal">
    <div class="modal-box" style="max-width:480px">
        <div class="modal-head">
            <h3><i class="bi bi-building"></i> Add a new university</h3>
            <button class="close" onclick="closeModal('newUniversityModal')">&times;</button>
        </div>
        <div class="modal-body">
            <label class="form-label">University name</label>
            <input type="text" id="newUniversityName" class="form-control" placeholder="e.g. University of Toronto" autofocus>

            <label class="form-label" style="margin-top:14px">Region (optional)</label>
            <select id="newUniversityRegion" class="form-select">
                <option value="">— None —</option>
                <?php foreach ($regions as $r): ?>
                    <option value="<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted" style="display:block;margin-top:6px">
                The university will be added to the universities list and reusable everywhere else in the system.
            </small>
        </div>
        <div class="modal-foot">
            <button class="btn-ghost" onclick="closeModal('newUniversityModal')">Cancel</button>
            <button class="btn-brand" onclick="saveNewUniversity()" id="saveUniversityBtn">
                <i class="bi bi-check2-circle"></i> Save university
            </button>
        </div>
    </div>
</div>

<!-- ============ Edit Brochure Modal ============ -->
<div class="modal-mask" id="editBrochureModal">
    <div class="modal-box" style="max-width:560px">
        <div class="modal-head">
            <h3><i class="bi bi-pencil-square"></i> Edit brochure</h3>
            <button class="close" onclick="closeModal('editBrochureModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editBrochureId" value="">

            <label class="form-label">Brochure title</label>
            <input type="text" id="editBrochureTitle" class="form-control" placeholder="Brochure title">

            <label class="form-label" style="margin-top:14px">
                Region <small class="text-muted" style="font-weight:400">(optional)</small>
            </label>
            <select id="editBrochureRegion" class="form-select">
                <option value="">— Choose region —</option>
                <?php foreach ($regions as $r): ?>
                    <option value="<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="form-label" style="margin-top:14px">
                University <small class="text-muted" style="font-weight:400">(optional)</small>
            </label>
            <select id="editBrochureUniversity" class="form-select">
                <option value="">— Choose university —</option>
                <?php foreach ($universities as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" data-region="<?= (int) $u['region_id'] ?>">
                        <?= htmlspecialchars($u['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted" style="display:block;margin-top:6px">
                Pick a region, a university, or both. The public share link will not change.
            </small>

            <label class="form-label" style="margin-top:14px">Short description (optional)</label>
            <textarea id="editBrochureDesc" class="form-control" rows="2" placeholder="Shown on the public page."></textarea>
        </div>
        <div class="modal-foot">
            <button class="btn-ghost" onclick="closeModal('editBrochureModal')">Cancel</button>
            <button class="btn-brand" id="saveBrochureBtn" onclick="saveEditBrochure()">
                <i class="bi bi-check2-circle"></i> Save changes
            </button>
        </div>
    </div>
</div>

<!-- ============ Add Region Modal ============ -->
<div class="modal-mask" id="newRegionModal">
    <div class="modal-box" style="max-width:420px">
        <div class="modal-head">
            <h3><i class="bi bi-geo-alt-fill"></i> Add a new region</h3>
            <button class="close" onclick="closeModal('newRegionModal')">&times;</button>
        </div>
        <div class="modal-body">
            <label class="form-label">Region name</label>
            <input type="text" id="newRegionName" class="form-control" placeholder="e.g. Middle East" autofocus>
            <small class="text-muted" style="display:block;margin-top:6px">It will be added to the global regions list and reusable everywhere else in the system.</small>
        </div>
        <div class="modal-foot">
            <button class="btn-ghost" onclick="closeModal('newRegionModal')">Cancel</button>
            <button class="btn-brand" onclick="saveNewRegion()" id="saveRegionBtn">
                <i class="bi bi-check2-circle"></i> Save region
            </button>
        </div>
    </div>
</div>

<!-- ============ Send to Customer Modal ============ -->
<div class="modal-mask" id="shareModal">
    <div class="modal-box" style="max-width:640px">
        <div class="modal-head">
            <h3><i class="bi bi-send-fill"></i> Send to customer <span id="shareTitle" style="font-weight:400;opacity:.85"></span></h3>
            <button class="close" onclick="closeModal('shareModal')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Step 1: pick channel -->
            <div id="sendPane1">
                <p class="text-muted" style="font-size:.9rem;margin-bottom:14px">How do you want to deliver this brochure?</p>
                <div class="channel-pick">
                    <button type="button" class="channel-card wa" onclick="pickChannel('whatsapp')">
                        <div class="ic"><i class="bi bi-whatsapp"></i></div>
                        <div>
                            <div class="t">WhatsApp message</div>
                            <div class="d">Sent through our official WhatsApp Business number — your customer sees the company name.</div>
                        </div>
                    </button>
                    <button type="button" class="channel-card em" onclick="pickChannel('email')">
                        <div class="ic"><i class="bi bi-envelope-paper-fill"></i></div>
                        <div>
                            <div class="t">Email message</div>
                            <div class="d">Delivered by SMTP from <code>infos@scholarsyncglobal.ca</code> with the brochure attached.</div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Step 2: pick recipient (search OR new) -->
            <div id="sendPane2" style="display:none">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                    <button class="btn-ghost" type="button" onclick="backToChannel()" title="Back"><i class="bi bi-arrow-left"></i></button>
                    <div class="chan-pill" id="chanPill"></div>
                </div>

                <div class="tab-bar">
                    <button type="button" class="tab-btn active" data-tab="search" onclick="switchTab('search')">
                        <i class="bi bi-search"></i> Search applicants
                    </button>
                    <button type="button" class="tab-btn" data-tab="new" onclick="switchTab('new')">
                        <i class="bi bi-person-plus"></i> New contact
                    </button>
                </div>

                <!-- Tab: search -->
                <div class="tab-panel" id="tab-search">
                    <input type="text" id="recipSearch" class="form-control" placeholder="Search by name, phone or email…" autocomplete="off">
                    <small class="text-muted" id="searchHelp">Type at least 3 characters to look across every application table.</small>
                    <div id="recipResults" class="recip-results"></div>
                </div>

                <!-- Tab: new -->
                <div class="tab-panel" id="tab-new" style="display:none">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <div>
                            <label class="form-label">Full name</label>
                            <input type="text" id="newRecipName" class="form-control" placeholder="Jane Doe">
                        </div>
                        <div id="newPhoneField">
                            <label class="form-label">WhatsApp number</label>
                            <input type="tel" id="newRecipPhone" class="form-control" placeholder="+250 788 123 456">
                        </div>
                        <div id="newEmailField" style="display:none;grid-column:span 2">
                            <label class="form-label">Email address</label>
                            <input type="email" id="newRecipEmail" class="form-control" placeholder="jane@example.com">
                        </div>
                    </div>
                    <small class="text-muted" style="display:block;margin-top:6px">We'll save this lead to your contacts so it can be reused later.</small>
                    <div style="margin-top:14px;display:flex;justify-content:flex-end">
                        <button type="button" class="btn-brand" onclick="useNewContact()">
                            Continue <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 3: confirm + send -->
            <div id="sendPane3" style="display:none">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                    <button class="btn-ghost" type="button" onclick="backToRecipient()" title="Back"><i class="bi bi-arrow-left"></i></button>
                    <div class="chan-pill" id="chanPill2"></div>
                </div>

                <div class="recipient-box" id="recipientBox"></div>

                <div id="editRecipient" class="edit-recipient">
                    <label class="form-label">Recipient name</label>
                    <input type="text" id="editName" class="form-control" placeholder="Customer name">

                    <div id="editPhoneWrap" style="margin-top:10px">
                        <label class="form-label">WhatsApp number</label>
                        <div class="phone-row">
                            <select id="editDial" class="form-select dial">
                                <option value="+250">🇷🇼 +250 RW</option>
                                <option value="+254">🇰🇪 +254 KE</option>
                                <option value="+256">🇺🇬 +256 UG</option>
                                <option value="+255">🇹🇿 +255 TZ</option>
                                <option value="+257">🇧🇮 +257 BI</option>
                                <option value="+243">🇨🇩 +243 CD</option>
                                <option value="+234">🇳🇬 +234 NG</option>
                                <option value="+233">🇬🇭 +233 GH</option>
                                <option value="+27">🇿🇦 +27 ZA</option>
                                <option value="+251">🇪🇹 +251 ET</option>
                                <option value="+1">🇺🇸 +1 US/CA</option>
                                <option value="+44">🇬🇧 +44 UK</option>
                                <option value="+33">🇫🇷 +33 FR</option>
                                <option value="+49">🇩🇪 +49 DE</option>
                                <option value="+91">🇮🇳 +91 IN</option>
                                <option value="+92">🇵🇰 +92 PK</option>
                                <option value="+86">🇨🇳 +86 CN</option>
                                <option value="+971">🇦🇪 +971 AE</option>
                                <option value="+966">🇸🇦 +966 SA</option>
                            </select>
                            <input type="tel" id="editPhone" class="form-control" placeholder="788 123 456">
                        </div>
                        <small class="text-muted" id="phoneHint">Pick the country code — the number after it should NOT include the leading 0.</small>
                    </div>

                    <div id="editEmailWrap" style="margin-top:10px;display:none">
                        <label class="form-label">Email address</label>
                        <input type="email" id="editEmail" class="form-control" placeholder="jane@example.com">
                    </div>
                </div>

                <label class="form-label" style="margin-top:14px">Message preview <small style="font-weight:400;color:var(--muted)">(you can edit before sending)</small></label>
                <textarea id="messagePreview" class="form-control" rows="6" style="font-family:inherit;line-height:1.55"></textarea>

                <label class="tick-row" style="margin-top:12px" for="emailAttachChk" id="emailAttachWrap">
                    <input type="checkbox" id="emailAttachChk" value="1" checked>
                    <span class="check-visual"><i class="bi bi-check2"></i></span>
                    <span>
                        <strong>Attach the original PDF to the email</strong>
                        <small>Recipient gets the PDF as an attachment plus the beautified HTML body. Uncheck for link-only.</small>
                    </span>
                </label>

                <div id="sendStatus" class="send-status"></div>

                <div style="margin-top:18px;display:flex;justify-content:space-between;gap:8px">
                    <button class="btn-ghost" onclick="closeModal('shareModal')">Cancel</button>
                    <button class="btn-brand" id="sendNowBtn" onclick="sendNow()">
                        <i class="bi bi-send-fill"></i> Send now
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast-stack" id="toastStack"></div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;
const ENDPOINT = 'marketing-brochure-actions.php';
let BROCHURES = [];
let SHARE_STATE = {};

/* ---------- Toast ---------- */
function toast(msg, type='info'){
    const t=document.createElement('div');
    t.className='toast-item '+type;
    t.innerHTML='<i class="bi bi-'+(type==='success'?'check-circle-fill':type==='error'?'exclamation-octagon-fill':'info-circle-fill')+'"></i>'+msg;
    document.getElementById('toastStack').appendChild(t);
    setTimeout(()=>t.remove(),4500);
}

/* ---------- Modals ---------- */
function openModal(id){document.getElementById(id).classList.add('show')}
function closeModal(id){document.getElementById(id).classList.remove('show')}
function openNewRegion(){
    document.getElementById('newRegionName').value='';
    openModal('newRegionModal');
    setTimeout(()=>document.getElementById('newRegionName').focus(),100);
}

/* ---------- New region ---------- */
async function saveNewRegion(){
    const name = document.getElementById('newRegionName').value.trim();
    if(!name){toast('Please enter a region name.','error');return;}
    const btn=document.getElementById('saveRegionBtn');
    btn.innerHTML='<span class="spinner-mini"></span> Saving…';
    btn.disabled=true;
    try{
        const fd=new FormData();
        fd.append('action','add_region');
        fd.append('csrf_token',CSRF);
        fd.append('name',name);
        const res=await fetch(ENDPOINT,{method:'POST',body:fd});
        const d=await res.json();
        if(!d.ok){toast(d.error||'Could not add region.','error');return;}
        addRegionToSelects(d.region);
        toast('Region saved: '+d.region.name,'success');
        closeModal('newRegionModal');
        document.getElementById('regionSelect').value=d.region.id;
    }catch(e){toast('Network error: '+e.message,'error');}
    finally{btn.innerHTML='<i class="bi bi-check2-circle"></i> Save region';btn.disabled=false;}
}
function addRegionToSelects(region){
    [document.getElementById('regionSelect'),document.getElementById('filterRegion'),
     document.getElementById('newUniversityRegion'),document.getElementById('editBrochureRegion')].forEach(sel=>{
        if(!sel)return;
        if(Array.from(sel.options).some(o=>o.value===String(region.id))) return;
        const o=document.createElement('option');
        o.value=region.id; o.textContent=region.name;
        sel.appendChild(o);
    });
}

/* ---------- New university ---------- */
function openNewUniversity(){
    document.getElementById('newUniversityName').value='';
    const regionPreset = document.getElementById('regionSelect').value;
    document.getElementById('newUniversityRegion').value = regionPreset || '';
    openModal('newUniversityModal');
    setTimeout(()=>document.getElementById('newUniversityName').focus(),100);
}

async function saveNewUniversity(){
    const name = document.getElementById('newUniversityName').value.trim();
    const regionId = document.getElementById('newUniversityRegion').value;
    if(!name){toast('Please enter a university name.','error');return;}
    const btn=document.getElementById('saveUniversityBtn');
    btn.innerHTML='<span class="spinner-mini"></span> Saving…';
    btn.disabled=true;
    try{
        const fd=new FormData();
        fd.append('action','add_university');
        fd.append('csrf_token',CSRF);
        fd.append('name',name);
        if(regionId) fd.append('region_id',regionId);
        const res=await fetch(ENDPOINT,{method:'POST',body:fd});
        const d=await res.json();
        if(!d.ok){toast(d.error||'Could not add university.','error');return;}
        addUniversityToSelect(d.university);
        toast('University saved: '+d.university.name,'success');
        closeModal('newUniversityModal');
        document.getElementById('universitySelect').value=d.university.id;
    }catch(e){toast('Network error: '+e.message,'error');}
    finally{btn.innerHTML='<i class="bi bi-check2-circle"></i> Save university';btn.disabled=false;}
}

function addUniversityToSelect(university){
    [document.getElementById('universitySelect'),document.getElementById('editBrochureUniversity')].forEach(sel=>{
        if(!sel) return;
        if(Array.from(sel.options).some(o=>o.value===String(university.id))) return;
        const o = document.createElement('option');
        o.value = university.id;
        o.textContent = university.name;
        o.dataset.region = university.region_id || 0;
        sel.appendChild(o);
    });
}

/* ---------- University select: filter by selected region (independent) ----------
   When a region is chosen, narrow the university list to that region.
   Universities without a region stay visible. Selecting "all regions" (no value)
   shows every university. */
(function(){
    const regionSel = document.getElementById('regionSelect');
    const uniSel    = document.getElementById('universitySelect');
    if(!regionSel || !uniSel) return;

    const allOpts = Array.from(uniSel.querySelectorAll('option[data-region]')).map(o => ({
        id: o.value,
        text: o.textContent.trim(),
        regionId: parseInt(o.dataset.region || '0', 10),
    }));

    function refresh(){
        const sel = parseInt(regionSel.value || '0', 10);
        const previous = uniSel.value;
        uniSel.innerHTML = '<option value="">— Choose university —</option>';
        allOpts
            .filter(o => !sel || o.regionId === 0 || o.regionId === sel)
            .forEach(o => {
                const opt = document.createElement('option');
                opt.value = o.id;
                opt.textContent = o.text;
                opt.dataset.region = o.regionId;
                uniSel.appendChild(opt);
            });
        if (Array.from(uniSel.options).some(o => o.value === previous)) {
            uniSel.value = previous;
        }
    }

    regionSel.addEventListener('change', refresh);
})();

/* ---------- Drop zone ---------- */
const dropZone=document.getElementById('dropZone');
const pdfInput=document.getElementById('pdfInput');
const fileChip=document.getElementById('fileChip');
const fileChipName=document.getElementById('fileChipName');
dropZone.addEventListener('click',()=>pdfInput.click());
dropZone.addEventListener('dragover',e=>{e.preventDefault();dropZone.classList.add('dragover')});
dropZone.addEventListener('dragleave',()=>dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop',e=>{
    e.preventDefault();
    dropZone.classList.remove('dragover');
    if(e.dataTransfer.files.length){
        pdfInput.files=e.dataTransfer.files;
        showFileChip(e.dataTransfer.files[0]);
    }
});
pdfInput.addEventListener('change',()=>{
    if(pdfInput.files.length)showFileChip(pdfInput.files[0]);
});
function showFileChip(f){
    fileChip.style.display='inline-flex';
    fileChipName.textContent=f.name+' ('+(f.size/1024/1024).toFixed(2)+' MB)';
    if(!document.getElementById('titleInput').value){
        document.getElementById('titleInput').value=f.name.replace(/\.pdf$/i,'').replace(/[-_]+/g,' ');
    }
}
function resetUpload(){
    fileChip.style.display='none';
    document.getElementById('uploadProgress').style.display='none';
}

/* ---------- Upload submit ---------- */
document.getElementById('uploadForm').addEventListener('submit',async e=>{
    e.preventDefault();
    if(!pdfInput.files.length){toast('Please pick a PDF file first.','error');return;}
    const btn=document.getElementById('uploadBtn');
    const prog=document.getElementById('uploadProgress');
    const bar=prog.querySelector('.bar');
    btn.disabled=true;
    btn.innerHTML='<span class="spinner-mini"></span> Uploading…';
    prog.style.display='block';bar.style.width='5%';
    try{
        const fd=new FormData(e.target);
        const xhr=new XMLHttpRequest();
        xhr.open('POST',ENDPOINT);
        xhr.upload.onprogress=evt=>{
            if(evt.lengthComputable){
                const pct=Math.min(95,Math.round(evt.loaded/evt.total*100));
                bar.style.width=pct+'%';
            }
        };
        xhr.onload=()=>{
            bar.style.width='100%';
            try{
                const d=JSON.parse(xhr.responseText);
                if(!d.ok){toast(d.error||'Upload failed.','error');return;}
                toast('Brochure uploaded successfully!','success');
                e.target.reset();resetUpload();
                loadBrochures();
            }catch(err){toast('Bad server response.','error')}
            finally{
                btn.disabled=false;
                btn.innerHTML='<i class="bi bi-cloud-upload-fill"></i> Upload & generate page';
                setTimeout(()=>{prog.style.display='none';bar.style.width='0%'},900);
            }
        };
        xhr.onerror=()=>{toast('Network error.','error');btn.disabled=false;btn.innerHTML='<i class="bi bi-cloud-upload-fill"></i> Upload & generate page';prog.style.display='none'};
        xhr.send(fd);
    }catch(err){
        toast('Upload failed: '+err.message,'error');
        btn.disabled=false;btn.innerHTML='<i class="bi bi-cloud-upload-fill"></i> Upload & generate page';
        prog.style.display='none';
    }
});

/* ---------- Load brochures ---------- */
async function reextractAll(onlyMissing){
    if(!confirm(onlyMissing
        ? 'Re-extract only brochures that don\'t have HTML yet?'
        : 'Re-extract ALL active brochures? Existing HTML will be replaced. AI is used when OpenAI credits are available; otherwise the built-in formatter is used.'))
        return;
    const fd=new FormData();
    fd.append('action','reextract_all');
    fd.append('csrf_token',CSRF);
    fd.append('limit','25');
    if(onlyMissing) fd.append('only_missing','1');
    toast('Extracting brochure content…','info');
    try{
        const res=await fetch(ENDPOINT,{method:'POST',body:fd});
        const d=await res.json();
        if(!d.ok){toast(d.error||'Bulk extract failed.','error');return;}
        if(d.succeeded===0 && d.failed>0){
            const f=(d.failures&&d.failures[0])?d.failures[0]:{};
            let msg='Extraction failed for '+d.failed+' brochure(s).';
            if(f.reason==='pdf_missing') msg+=' PDF file not found on server — re-upload the brochure.';
            else if(f.reason==='extract_empty') msg+=' Could not read text from PDF — ask your host to enable pdftotext (poppler-utils).';
            else msg+=' Check server logs.';
            toast(msg,'error');
        }else if(d.ai_used>0){
            toast('Done: '+d.succeeded+' / '+d.processed+' refreshed with AI formatting.','success');
        }else if(d.regex_used>0){
            let msg='Done: '+d.succeeded+' / '+d.processed+' refreshed (standard formatter).';
            if(d.ai_paused||d.ai_enabled) msg+=' OpenAI quota exhausted — add credits at platform.openai.com for AI formatting.';
            toast(msg,'success');
        }else{
            toast('Done: '+d.succeeded+' / '+d.processed+' refreshed.','success');
        }
        loadBrochures();
    }catch(e){toast('Bulk extract failed: '+e.message,'error')}
}

async function loadBrochures(){
    const q=document.getElementById('searchBox').value.trim();
    const region=document.getElementById('filterRegion').value;
    const url=new URL(ENDPOINT,window.location.href);
    url.searchParams.set('action','list_brochures');
    if(q) url.searchParams.set('q',q);
    if(region&&region!=='0') url.searchParams.set('region_id',region);
    try{
        const res=await fetch(url);
        const d=await res.json();
        if(!d.ok){renderEmpty(d.error||'Failed to load.');return;}
        BROCHURES=d.brochures||[];
        renderBrochures();
        updateStats();
    }catch(e){renderEmpty('Network error: '+e.message)}
}

function updateStats(){
    document.getElementById('stat-total').textContent=BROCHURES.length;
    document.getElementById('stat-views').textContent=BROCHURES.reduce((a,b)=>a+(b.view_count||0),0);
    document.getElementById('stat-shares').textContent=BROCHURES.reduce((a,b)=>a+(b.share_count||0),0);
}

function renderBrochures(){
    const c=document.getElementById('brochureContainer');
    if(!BROCHURES.length){renderEmpty();return;}
    let html='<div class="brochure-grid">';
    for(const b of BROCHURES){
        const sizeMb=(b.pdf_size_bytes/1024/1024).toFixed(2);
        const attachOn = (b.attach_pdf||0) === 1;
        const extStatus = b.extraction_status||'pending';
        const extBadge = extStatus==='ok'
            ? '<span class="table-tag" style="background:var(--brand-soft);color:var(--brand);border-color:transparent">HTML ready</span>'
            : '<span class="table-tag" style="background:#fef3c7;color:#92400e;border-color:transparent">PDF only</span>';
        html += `
            <div class="brochure-card">
                <div class="cover">
                    <span class="region-tag"><i class="bi bi-geo-alt-fill"></i> ${escapeHtml(b.region_name||'—')}</span>
                    ${b.university_name ? `<span class="region-tag" style="top:auto;bottom:12px;background:rgba(0,0,0,.25);"><i class="bi bi-building"></i> ${escapeHtml(b.university_name)}</span>` : ''}
                    <i class="bi bi-file-earmark-richtext-fill pdf-icon"></i>
                    <div style="font-size:.75rem;opacity:.85;margin-top:8px">PDF · ${sizeMb} MB</div>
                </div>
                <div class="body">
                    <div class="title">${escapeHtml(b.title)}</div>
                    <div class="meta">
                        <span><i class="bi bi-eye"></i> ${b.view_count}</span>
                        <span><i class="bi bi-share"></i> ${b.share_count}</span>
                        <span><i class="bi bi-clock"></i> ${formatDate(b.created_at)}</span>
                        ${extBadge}
                    </div>
                </div>
                <div class="share-block">
                    <div class="share-label"><i class="bi bi-link-45deg"></i> Auto-generated share link</div>
                    <div class="link-row">
                        <input type="text" value="${escapeHtml(b.share_url)}" readonly onclick="this.select()" title="Share link">
                        <button class="copy-btn" onclick="quickCopyLink(${b.id},'${escapeJs(b.share_url)}')"><i class="bi bi-clipboard-check"></i> Copy</button>
                    </div>
                    <div class="share-actions">
                        <a class="wa" href="https://wa.me/?text=${encodeURIComponent('Hi! Please find our brochure: '+b.title+'\\n'+b.share_url)}" target="_blank" rel="noopener" onclick="logQuickShare(${b.id},'whatsapp')">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                        <a class="em" href="mailto:?subject=${encodeURIComponent(b.title)}&body=${encodeURIComponent('Hi,\\n\\nPlease find our brochure: '+b.title+'\\n'+b.share_url+'\\n\\nBest regards,\\nScholarSync Global')}" onclick="logQuickShare(${b.id},'email')">
                            <i class="bi bi-envelope-fill"></i> Email
                        </a>
                        <button type="button" class="send" onclick="openShareModal(${b.id})">
                            <i class="bi bi-person-lines-fill"></i> Send to customer
                        </button>
                    </div>
                </div>
                <div class="footer">
                    <button onclick="previewBrochure('${escapeJs(b.share_url)}')"><i class="bi bi-eye-fill"></i> Preview</button>
                    <button onclick="openEditBrochure(${b.id})" title="Edit title, region & university"><i class="bi bi-pencil-square"></i> Edit</button>
                    <label class="attach-toggle" title="When ON, the customer page also shows the original PDF and a download button.">
                        <input type="checkbox" ${attachOn?'checked':''} onchange="toggleAttachPdf(${b.id}, this.checked)">
                        <i class="bi bi-file-earmark-pdf"></i> Attach PDF
                    </label>
                    <button class="danger" onclick="deleteBrochure(${b.id})" title="Delete"><i class="bi bi-trash3-fill"></i></button>
                </div>
            </div>`;
    }
    html+='</div>';
    c.innerHTML=html;
}

function renderEmpty(msg){
    document.getElementById('brochureContainer').innerHTML = `
        <div class="empty-state">
            <i class="bi bi-folder2-open"></i>
            <h4>${msg?escapeHtml(msg):'No brochures yet'}</h4>
            <p>${msg?'':'Upload your first PDF above and a public, branded share link is generated automatically.'}</p>
        </div>`;
}

function previewBrochure(url){window.open(url,'_blank');}

/* ---------- Edit brochure ---------- */
function openEditBrochure(id){
    const b = BROCHURES.find(x => x.id === id);
    if(!b){toast('Brochure not found.','error');return;}
    document.getElementById('editBrochureId').value          = b.id;
    document.getElementById('editBrochureTitle').value       = b.title || '';
    document.getElementById('editBrochureDesc').value        = b.description || '';
    document.getElementById('editBrochureRegion').value      = b.region_id ? String(b.region_id) : '';
    filterEditUniversities();
    document.getElementById('editBrochureUniversity').value  = b.university_id ? String(b.university_id) : '';
    openModal('editBrochureModal');
    setTimeout(()=>document.getElementById('editBrochureTitle').focus(),100);
}

/* Filter the edit university list by the currently selected edit region.
   Universities with no region stay visible; "no region" shows them all. */
function filterEditUniversities(){
    const regionSel = document.getElementById('editBrochureRegion');
    const uniSel    = document.getElementById('editBrochureUniversity');
    if(!regionSel || !uniSel) return;
    const sel = parseInt(regionSel.value || '0', 10);
    const previous = uniSel.value;
    Array.from(uniSel.options).forEach(opt => {
        if(opt.value === '') { opt.hidden = false; return; }
        const r = parseInt(opt.dataset.region || '0', 10);
        opt.hidden = sel > 0 && r > 0 && r !== sel;
    });
    if(!Array.from(uniSel.options).some(o => o.value === previous && !o.hidden)){
        uniSel.value = '';
    }
}
document.getElementById('editBrochureRegion')
    .addEventListener('change', filterEditUniversities);

async function saveEditBrochure(){
    const id          = parseInt(document.getElementById('editBrochureId').value || '0', 10);
    const title       = document.getElementById('editBrochureTitle').value.trim();
    const regionId    = document.getElementById('editBrochureRegion').value;
    const universityId= document.getElementById('editBrochureUniversity').value;
    const desc        = document.getElementById('editBrochureDesc').value.trim();
    if(id<=0){toast('Invalid brochure.','error');return;}
    if(!title){toast('Title is required.','error');return;}
    if(!regionId && !universityId){toast('Pick a region or a university.','error');return;}
    const btn = document.getElementById('saveBrochureBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-mini"></span> Saving…';
    try{
        const fd = new FormData();
        fd.append('action','update_brochure');
        fd.append('csrf_token',CSRF);
        fd.append('id',id);
        fd.append('title',title);
        fd.append('description',desc);
        fd.append('region_id', regionId || '0');
        fd.append('university_id', universityId || '0');
        const res = await fetch(ENDPOINT,{method:'POST',body:fd});
        const d   = await res.json();
        if(!d.ok){toast(d.error||'Update failed.','error');return;}
        toast('Brochure updated.','success');
        closeModal('editBrochureModal');
        loadBrochures();
    }catch(e){toast('Network error: '+e.message,'error');}
    finally{
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle"></i> Save changes';
    }
}

async function deleteBrochure(id){
    if(!confirm('Delete this brochure? This cannot be undone.'))return;
    const fd=new FormData();
    fd.append('action','delete_brochure');
    fd.append('csrf_token',CSRF);
    fd.append('id',id);
    const res=await fetch(ENDPOINT,{method:'POST',body:fd});
    const d=await res.json();
    if(!d.ok){toast(d.error||'Failed to delete.','error');return;}
    toast('Brochure removed.','success');
    loadBrochures();
}

/* ---------- Send-to-customer (WhatsApp Cloud API / SMTP email) ---------- */
function openShareModal(brochureId){
    const b=BROCHURES.find(x=>x.id===brochureId);
    if(!b){toast('Brochure not found.','error');return;}
    SHARE_STATE={brochure:b,channel:null,match:null,is_new:false,name:'',email:'',phone:''};
    document.getElementById('shareTitle').textContent=' — '+b.title;
    document.getElementById('recipSearch').value='';
    document.getElementById('recipResults').innerHTML='';
    document.getElementById('newRecipName').value='';
    document.getElementById('newRecipPhone').value='';
    document.getElementById('newRecipEmail').value='';
    showSendPane(1);
    openModal('shareModal');
}
function showSendPane(n){
    [1,2,3].forEach(i=>document.getElementById('sendPane'+i).style.display=(i===n)?'block':'none');
}
function pickChannel(ch){
    SHARE_STATE.channel=ch;
    const isWa = ch==='whatsapp';
    document.getElementById('chanPill').className='chan-pill '+(isWa?'wa':'em');
    document.getElementById('chanPill').innerHTML=
        '<i class="bi bi-'+(isWa?'whatsapp':'envelope-paper-fill')+'"></i> '+(isWa?'WhatsApp delivery':'Email delivery');
    document.getElementById('chanPill2').className='chan-pill '+(isWa?'wa':'em');
    document.getElementById('chanPill2').innerHTML=
        '<i class="bi bi-'+(isWa?'whatsapp':'envelope-paper-fill')+'"></i> '+(isWa?'WhatsApp delivery':'Email delivery');
    // Toggle the "new contact" tab fields based on channel
    document.getElementById('newPhoneField').style.display = isWa?'block':'none';
    document.getElementById('newEmailField').style.display = isWa?'none':'block';
    document.getElementById('recipSearch').placeholder = isWa
        ? 'Search by name or phone…'
        : 'Search by name or email…';
    document.getElementById('emailAttachWrap').style.display = isWa?'none':'flex';
    switchTab('search');
    showSendPane(2);
    setTimeout(()=>document.getElementById('recipSearch').focus(),120);
}
function backToChannel(){ showSendPane(1) }
function backToRecipient(){ showSendPane(2) }

function switchTab(tab){
    document.querySelectorAll('#sendPane2 .tab-btn').forEach(b=>{
        b.classList.toggle('active', b.dataset.tab===tab);
    });
    document.getElementById('tab-search').style.display = tab==='search'?'block':'none';
    document.getElementById('tab-new').style.display    = tab==='new'?'block':'none';
}

let searchT;
document.addEventListener('input',e=>{
    if(e.target && e.target.id==='recipSearch'){
        clearTimeout(searchT);
        const q=e.target.value.trim();
        if(q.length<3){
            document.getElementById('recipResults').innerHTML='';
            return;
        }
        searchT=setTimeout(()=>runRecipSearch(q),280);
    }
});

async function runRecipSearch(q){
    const box=document.getElementById('recipResults');
    box.innerHTML='<div class="text-muted" style="font-size:.85rem;padding:8px"><span class="spinner-mini" style="border-color:#64748b;border-top-color:transparent"></span> Searching…</div>';
    try{
        const url=new URL(ENDPOINT,window.location.href);
        url.searchParams.set('action','search_applicants');
        url.searchParams.set('q',q);
        const res=await fetch(url);
        const d=await res.json();
        if(!d.ok){box.innerHTML='<div class="text-muted" style="padding:8px">'+escapeHtml(d.error||'Search failed.')+'</div>';return;}
        renderRecipResults(d.matches||[]);
    }catch(e){
        box.innerHTML='<div class="text-muted" style="padding:8px">Search error: '+escapeHtml(e.message)+'</div>';
    }
}
function renderRecipResults(matches){
    const box=document.getElementById('recipResults');
    if(!matches.length){
        box.innerHTML = `
            <div class="recip-row" style="border-color:var(--accent);background:var(--accent-soft);cursor:default">
                <div>
                    <div class="nm"><i class="bi bi-person-x"></i> No matching applicant found</div>
                    <div class="mt">Switch to the "New contact" tab to add and send anyway.</div>
                </div>
            </div>`;
        return;
    }
    const isWa = SHARE_STATE.channel==='whatsapp';
    let html='';
    matches.forEach((m,i)=>{
        const usable = isWa ? (m.phone&&m.phone.length>=6) : (m.email&&m.email.includes('@'));
        if(!usable) return;
        html += `<div class="recip-row" onclick="chooseRecipient(${i})" data-i="${i}">
            <div>
                <div class="nm">${escapeHtml(m.name||'(no name)')}</div>
                <div class="mt">
                    ${m.phone?'<i class="bi bi-telephone"></i> '+escapeHtml(m.phone):''}
                    ${m.phone&&m.email?' · ':''}
                    ${m.email?'<i class="bi bi-envelope"></i> '+escapeHtml(m.email):''}
                </div>
            </div>
            <span class="tag">${escapeHtml(m.table.replace(/_/g,' ').replace(' applications',''))}</span>
        </div>`;
    });
    if(!html){
        box.innerHTML = `<div class="text-muted" style="padding:8px">No applicant has a usable ${isWa?'phone':'email'} for this channel.</div>`;
        return;
    }
    SHARE_STATE._lastMatches=matches;
    box.innerHTML=html;
}
function chooseRecipient(i){
    const m=SHARE_STATE._lastMatches[i];
    SHARE_STATE.match=m;
    SHARE_STATE.is_new=false;
    SHARE_STATE.name=m.name||'';
    SHARE_STATE.phone=m.phone||'';
    SHARE_STATE.email=m.email||'';
    goConfirm();
}
function useNewContact(){
    SHARE_STATE.name=document.getElementById('newRecipName').value.trim();
    if(SHARE_STATE.channel==='whatsapp'){
        SHARE_STATE.phone=document.getElementById('newRecipPhone').value.trim();
        SHARE_STATE.email='';
        if(!SHARE_STATE.phone){toast('Please enter a WhatsApp number.','error');return;}
    }else{
        SHARE_STATE.email=document.getElementById('newRecipEmail').value.trim();
        SHARE_STATE.phone='';
        if(!SHARE_STATE.email||!/.+@.+\..+/.test(SHARE_STATE.email)){toast('Please enter a valid email.','error');return;}
    }
    SHARE_STATE.is_new=true;
    SHARE_STATE.match=null;
    goConfirm();
}
const DEFAULT_DIAL = <?= json_encode($defaultCountryDial) ?>;

/** Split a phone string into ["+250","788..."] using known dial codes. */
function splitDial(raw){
    const codes = ['+971','+966','+880','+251','+250','+255','+256','+254','+257','+243','+237','+234','+233','+225','+221','+213','+212','+91','+92','+86','+90','+49','+44','+39','+34','+33','+27','+20','+1'];
    let digits = String(raw||'').replace(/\D+/g,'');
    if(!digits) return [DEFAULT_DIAL,''];
    // Already E.164 (no leading 0)
    if(String(raw||'').trim().startsWith('+') || digits.length>=11){
        for(const c of codes){
            const cd = c.replace('+','');
            if(digits.startsWith(cd)){
                return ['+'+cd, digits.slice(cd.length)];
            }
        }
    }
    // National format with leading 0 → strip and use default
    if(digits.startsWith('0')) digits = digits.replace(/^0+/,'');
    return [DEFAULT_DIAL, digits];
}

function goConfirm(){
    const isWa = SHARE_STATE.channel==='whatsapp';
    const b = SHARE_STATE.brochure;
    document.getElementById('recipientBox').innerHTML = `
        <div class="lh">
            <div class="nm"><i class="bi bi-${isWa?'whatsapp':'envelope-fill'}"></i> Edit before sending</div>
            <div class="mt">Review the customer name${isWa?', country code and phone':' and email'} below — they can be changed.</div>
        </div>
        <span class="badge">${SHARE_STATE.is_new?'NEW':'EXISTING'}</span>`;

    document.getElementById('editName').value = SHARE_STATE.name || '';
    document.getElementById('editPhoneWrap').style.display = isWa?'block':'none';
    document.getElementById('editEmailWrap').style.display = isWa?'none':'block';
    if(isWa){
        const [dial,local] = splitDial(SHARE_STATE.phone);
        const sel = document.getElementById('editDial');
        const opt = Array.from(sel.options).find(o=>o.value===dial);
        sel.value = opt ? dial : DEFAULT_DIAL;
        document.getElementById('editPhone').value = local;
    }else{
        document.getElementById('editEmail').value = SHARE_STATE.email || '';
    }

    refreshMessagePreview();
    document.getElementById('sendStatus').innerHTML='';
    showSendPane(3);
}

function refreshMessagePreview(){
    const isWa = SHARE_STATE.channel==='whatsapp';
    const b = SHARE_STATE.brochure;
    const name = document.getElementById('editName').value.trim();
    const greet = name?('Hello '+name+','):'Hello,';
    document.getElementById('messagePreview').value =
        greet+'\n\n'+
        'Please find our brochure: '+b.title+'\n'+
        b.share_url+'\n\n'+
        'Open the link to read the full document'+(isWa?' and download the PDF.':'.')+'\n'+
        'Reach out any time if you have questions.\n\n'+
        '— ScholarSync Global';
}
document.addEventListener('input',e=>{
    if(e.target && e.target.id==='editName') refreshMessagePreview();
});

async function sendNow(){
    const isWa = SHARE_STATE.channel==='whatsapp';
    const editedName = document.getElementById('editName').value.trim();
    let editedPhone = '', editedEmail = '';
    if(isWa){
        const dial = document.getElementById('editDial').value;
        let local = document.getElementById('editPhone').value.replace(/\D+/g,'').replace(/^0+/,'');
        if(!local){toast('Enter a WhatsApp phone number.','error');return;}
        editedPhone = dial.replace('+','') + local;
        if(editedPhone.length < 8){toast('Phone number looks too short.','error');return;}
    }else{
        editedEmail = document.getElementById('editEmail').value.trim();
        if(!editedEmail || !/.+@.+\..+/.test(editedEmail)){toast('Enter a valid email address.','error');return;}
    }

    // Sync state so success toast + future steps reflect the edits.
    SHARE_STATE.name  = editedName;
    SHARE_STATE.phone = isWa?('+'+editedPhone):'';
    SHARE_STATE.email = isWa?'':editedEmail;

    const btn=document.getElementById('sendNowBtn');
    btn.disabled=true;btn.innerHTML='<span class="spinner-mini"></span> Sending…';
    const stat=document.getElementById('sendStatus');
    stat.className='send-status';stat.innerHTML='';
    try{
        const fd=new FormData();
        fd.append('action', isWa?'send_whatsapp':'send_email');
        fd.append('csrf_token',CSRF);
        fd.append('brochure_id',SHARE_STATE.brochure.id);
        fd.append('name',editedName);
        fd.append('is_new_contact',SHARE_STATE.is_new?'1':'0');
        if(SHARE_STATE.match){
            fd.append('matched_table',SHARE_STATE.match.table||'');
            fd.append('matched_row_id',SHARE_STATE.match.row_id||0);
        }
        if(isWa){
            fd.append('phone','+'+editedPhone);
            fd.append('message',document.getElementById('messagePreview').value);
        }else{
            fd.append('email',editedEmail);
            fd.append('message',document.getElementById('messagePreview').value);
            if(document.getElementById('emailAttachChk').checked) fd.append('attach_pdf','1');
        }
        const res=await fetch(ENDPOINT,{method:'POST',body:fd});
        const d=await res.json();
        if(d.ok&&d.sent){
            stat.className='send-status success';
            stat.innerHTML='<i class="bi bi-check-circle-fill"></i> Sent successfully'+(isWa&&d.method?(' ('+d.method+')'):'')+'.';
            toast('Brochure sent to '+(isWa?('+'+editedPhone):editedEmail)+'.','success');
            loadBrochures();
            setTimeout(()=>closeModal('shareModal'),1400);
        }else{
            stat.className='send-status error';
            stat.innerHTML='<i class="bi bi-exclamation-octagon-fill"></i> '+escapeHtml(d.error||'Send failed.');
            toast(d.error||'Send failed.','error');
        }
    }catch(e){
        stat.className='send-status error';
        stat.innerHTML='<i class="bi bi-exclamation-octagon-fill"></i> '+escapeHtml(e.message);
    }finally{
        btn.disabled=false;btn.innerHTML='<i class="bi bi-send-fill"></i> Send now';
    }
}

/* ---------- Quick share (no lookup required) ---------- */
function quickCopyLink(id,url){
    if(navigator.clipboard){
        navigator.clipboard.writeText(url).then(()=>toast('Share link copied!','success'));
    }else{
        const ta=document.createElement('textarea');ta.value=url;document.body.appendChild(ta);
        ta.select();document.execCommand('copy');document.body.removeChild(ta);
        toast('Share link copied!','success');
    }
    logQuickShare(id,'copy');
}
function quickWhatsApp(id,title,url){
    const msg = 'Hi! Please find our brochure: '+title+'\n'+url;
    window.open('https://wa.me/?text='+encodeURIComponent(msg),'_blank');
    logQuickShare(id,'whatsapp');
}
function logQuickShare(brochureId,channel){
    const fd=new FormData();
    fd.append('action','share_brochure');
    fd.append('csrf_token',CSRF);
    fd.append('brochure_id',brochureId);
    fd.append('channel',channel);
    fd.append('notes','quick-share');
    fetch(ENDPOINT,{method:'POST',body:fd})
      .then(r=>r.json()).then(()=>setTimeout(loadBrochures,400)).catch(()=>{});
}
async function toggleAttachPdf(id,checked){
    const fd=new FormData();
    fd.append('action','set_attach_pdf');
    fd.append('csrf_token',CSRF);
    fd.append('id',id);
    if(checked) fd.append('attach_pdf','1');
    try{
        const res=await fetch(ENDPOINT,{method:'POST',body:fd});
        const d=await res.json();
        if(!d.ok){toast(d.error||'Failed to update.','error');return;}
        toast(checked?'Original PDF will be attached on the public page.':'Public page will hide the PDF (HTML only).','success');
        loadBrochures();
    }catch(e){toast('Network error: '+e.message,'error')}
}

/* ---------- Utils ---------- */
function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function escapeJs(s){return String(s??'').replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/\r?\n/g,' ')}
function formatDate(s){if(!s)return'';const d=new Date(s.replace(' ','T'));return d.toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'})}

document.getElementById('searchBox').addEventListener('input',()=>{clearTimeout(window.__searchT);window.__searchT=setTimeout(loadBrochures,260)});
document.getElementById('filterRegion').addEventListener('change',loadBrochures);
document.addEventListener('click',e=>{
    if(e.target.classList && e.target.classList.contains('modal-mask'))closeModal(e.target.id);
});

loadBrochures();
</script>
</body>
</html>
