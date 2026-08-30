<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/payment_receipt_recorded_by.php';

pcvc_ensure_payment_receipt_recorded_by_schema($conn);
$doneByFilterOptions = pcvc_receipt_recorded_by_filter_options($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Receipt Viewer</title>

<style>
/* =========================
   BASE
========================= */
*{box-sizing:border-box}
body{
    background:#f3eded;
    font-family:"Courier New", monospace;
    margin:0;
    padding:32px 16px;
    color:#111;
}

.container{
    max-width:1200px;
    margin:auto;
}

/* =========================
   FILTER BAR
========================= */
.filter-bar{
    background:#fff;
    padding:18px;
    border-radius:16px;
    display:grid;
    grid-template-columns:1fr 200px auto;
    gap:14px;
    margin-bottom:22px;
    box-shadow:0 6px 14px rgba(0,0,0,.08);
}

@media (max-width:900px){
    .filter-bar{
        grid-template-columns:1fr;
    }
}

.filter-bar input,
.filter-bar select{
    padding:14px 16px;
    border-radius:12px;
    border:1px solid #d1d5db;
    font-size:14px;
    width:100%;
}

.filter-bar input:focus,
.filter-bar select:focus{
    outline:none;
    border-color:#2563eb;
}

.filter-bar select{
    cursor:pointer;
    background:#fff;
}

/* =========================
   QUICK FILTERS
========================= */
.quick-filters{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.quick-filters button{
    padding:10px 16px;
    border-radius:999px;
    border:none;
    cursor:pointer;
    font-size:12px;
    background:#e5e7eb;
    transition:.15s;
}

.quick-filters button:hover{
    background:#d1d5db;
}

.quick-filters button.active{
    background:#2563eb;
    color:#fff;
}

/* =========================
   GRID
========================= */
.receipt-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(340px,1fr));
    gap:22px;
}

/* =========================
   CARD
========================= */
.receipt-card{
    background:#fff;
    border-radius:16px;
    padding:18px;
    font-size:13px;
    box-shadow:0 6px 14px rgba(0,0,0,.12);
    position:relative;
}

.receipt-card.canceled{
    opacity:.55;
}

/* WATERMARK */
.receipt-card.canceled::after{
    content:"CANCELLED";
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:44px;
    font-weight:bold;
    color:rgba(220,38,38,.25);
    transform:rotate(-25deg);
    pointer-events:none;
}

/* =========================
   HEADER
========================= */
.header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:10px;
}

.actions{
    display:flex;
    gap:6px;
}

.actions a,
.actions button{
    font-size:11px;
    padding:6px 10px;
    border-radius:8px;
    border:none;
    cursor:pointer;
    color:#fff;
    text-decoration:none;
}

.btn-print{background:#2563eb}
.btn-edit{background:#f59e0b}
.btn-cancel{background:#dc2626}

.actions button:disabled{
    opacity:.5;
    cursor:not-allowed;
}

hr{
    border:none;
    border-top:1px dashed #000;
    margin:12px 0;
}

/* =========================
   TABLE
========================= */
table{
    width:100%;
    border-collapse:collapse;
}

th{
    text-align:left;
    border-bottom:1px dashed #000;
    padding-bottom:4px;
}

td{
    padding:3px 0;
}

/* =========================
   TOTALS
========================= */
.total{
    border-top:1px dashed #000;
    margin-top:10px;
    padding-top:8px;
    font-weight:bold;
}

.done-by{
    margin-top:6px;
    color:#4b5563;
    font-size:12px;
}

@media print{
    .done-by{display:none !important;}
}

/* =========================
   STATES
========================= */
.state{
    grid-column:1/-1;
    text-align:center;
    padding:40px 0;
    color:#6b7280;
    font-size:14px;
}
.loading::after{
    content:" Loading…";
    animation:dot 1.2s infinite;
}
@keyframes dot{
    0%{content:" Loading"}
    33%{content:" Loading."}
    66%{content:" Loading.."}
    100%{content:" Loading..."}
}
</style>
</head>

<body>
<div class="container">

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <input id="search" placeholder="Search customer name…" autocomplete="off">

        <select id="doneBy" aria-label="Filter by done by">
            <?php foreach ($doneByFilterOptions as $opt): ?>
            <option value="<?= htmlspecialchars((string) $opt['id'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string) $opt['label'], ENT_QUOTES, 'UTF-8') ?>
            </option>
            <?php endforeach; ?>
        </select>

        <div class="quick-filters">
            <button data-range="" class="active">All</button>
            <button data-range="today">Today</button>
            <button data-range="week">This Week</button>
            <button data-range="month">This Month</button>
        </div>
    </div>

    <!-- RESULTS -->
    <div id="results" class="receipt-grid">
        <div class="state loading">Loading</div>
    </div>

</div>

<script>
/* =========================
   STATE
========================= */
let timer=null;
let controller=null;
let dateRange='';
let doneByFilter='';

/* =========================
   EVENTS
========================= */
document.getElementById('search').addEventListener('input',()=>{
    clearTimeout(timer);
    timer=setTimeout(loadReceipts,300);
});

document.getElementById('doneBy').addEventListener('change',()=>{
    doneByFilter=document.getElementById('doneBy').value;
    loadReceipts();
});

document.querySelectorAll('.quick-filters button').forEach(btn=>{
    btn.addEventListener('click',()=>{
        document.querySelectorAll('.quick-filters button')
            .forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        dateRange=btn.dataset.range;
        loadReceipts();
    });
});

/* =========================
   AJAX LOADER
========================= */
function loadReceipts(){
    const customer=document.getElementById('search').value.trim();
    const results=document.getElementById('results');

    if(controller) controller.abort();
    controller=new AbortController();

    results.innerHTML='<div class="state loading">Loading</div>';

    const qs=new URLSearchParams({
        customer,
        range:dateRange,
        done_by:doneByFilter
    });

    fetch(`receipts_ajax.php?${qs.toString()}`,{
        signal:controller.signal
    })
    .then(r=>{
        if(!r.ok) throw new Error('Network error');
        return r.text();
    })
    .then(html=>{
        results.innerHTML=html.trim() || 
            '<div class="state">No receipts found</div>';
    })
    .catch(err=>{
        if(err.name!=='AbortError'){
            results.innerHTML=
                '<div class="state">Failed to load receipts</div>';
        }
    });
}

/* =========================
   INIT
========================= */
loadReceipts();
</script>
</body>
</html>
