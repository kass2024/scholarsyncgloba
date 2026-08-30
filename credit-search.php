<?php
/**
 * credit-search.php
 *
 * Smart search UI + AJAX endpoint for `credit_transfer_applications`.
 * - Live search with debounce
 * - Prepared statements (safe)
 * - Multi-field search (names, email, phone, city, user_id, programs, university, postal_code)
 * - Tokenized query (matches all words, any order)
 * - Highlighting of matches
 * - Pagination (server-side)
 * - Sort by unread first, then newest first
 *
 * The shared application connection from db.php is required.
 */

// ----------------------- DB BOOTSTRAP -----------------------
$haveConn = false;
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
    if (isset($conn) && $conn instanceof mysqli) {
        $haveConn = true;
    }
}
if (!$haveConn) {
    http_response_code(500);
    die('DB connection is not configured.');
}
$conn->set_charset('utf8mb4');

// ----------------------- HELPERS -----------------------
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function highlight_terms($text, $terms) {
    $text = (string)$text;
    if (!$terms) return h($text);
    $escaped = array_map(function($t){
        return preg_quote($t, '/');
    }, $terms);
    $pattern = '/(' . implode('|', array_filter($escaped)) . ')/iu';
    $safe = h($text);
    // Convert entities back temporarily to match indices? Easier approach:
    // Run highlighting on the raw, then escape & wrap safely.
    $out = preg_replace_callback($pattern, function($m){
        return "\x00MARK_OPEN\x00" . $m[0] . "\x00MARK_CLOSE\x00";
    }, $text);
    $out = h($out);
    $out = str_replace(['&#0;','&#x0;'], '', $out);
    $out = str_replace(["\x00MARK_OPEN\x00","\x00MARK_CLOSE\x00"], ['<mark>','</mark>'], $out);
    return $out;
}

function parse_terms($q) {
    // Split on spaces; keep words >= 2 chars
    $q = trim((string)$q);
    if ($q === '') return [];
    $parts = preg_split('/\s+/u', $q);
    return array_values(array_filter(array_map('trim', $parts), function($w){ return mb_strlen($w) >= 2; }));
}

// ----------------------- AJAX HANDLER -----------------------
if (isset($_GET['ajax'])) {
    $q        = isset($_GET['q']) ? trim($_GET['q']) : '';
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = min(50, max(5, (int)($_GET['per_page'] ?? 10)));
    $offset   = ($page - 1) * $perPage;
    $terms    = parse_terms($q);

    // Base select
    $cols = "id, user_id, first_name, middle_name, last_name, email, mobile_number, phone_number, city, state, postal_code, university, current_program, proposed_program, submitted_at, is_read";
    $sql  = "FROM credit_transfer_applications WHERE 1 ";

    $bindings = [];
    $types    = '';

    if ($terms) {
        // Build AND-of-ORs (each term must match at least one field)
        // Fields we search across:
        $searchable = [
            'user_id','first_name','middle_name','last_name','email','mobile_number','phone_number',
            'city','state','postal_code','university','current_program','proposed_program','company'
        ];

        foreach ($terms as $t) {
            $sql .= " AND (";
            $orParts = [];
            foreach ($searchable as $f) {
                $orParts[] = "$f LIKE ?";
                $bindings[] = '%' . $t . '%';
                $types .= 's';
            }
            $sql .= implode(' OR ', $orParts) . ") ";
        }
    }

    // Sorting: unread first, then newest
    $order = " ORDER BY is_read ASC, submitted_at DESC, id DESC ";

    // Count total
    $countSql = "SELECT COUNT(*) " . $sql;
    $stmt = $conn->prepare($countSql);
    if ($types) $stmt->bind_param($types, ...$bindings);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    // Fetch page
    $dataSql = "SELECT $cols " . $sql . $order . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($dataSql);
    if ($types) {
        $types2 = $types . 'ii';
        $params = array_merge($bindings, [$perPage, $offset]);
        $stmt->bind_param($types2, ...$params);
    } else {
        $stmt->bind_param('ii', $perPage, $offset);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Prepare highlighting terms
    $hlTerms = $terms;

    // Compose HTML response (table + pager)
    ob_start();
    ?>
    <div class="results-meta">
        <div><strong><?= (int)$total ?></strong> result<?= $total==1?'':'s' ?> found<?= $q!=='' ? " for <em>".h($q)."</em>" : '' ?>.</div>
        <div>Page <?= (int)$page ?> of <?= max(1, (int)ceil($total / $perPage)) ?> • Showing <?= count($rows) ?> per page</div>
    </div>
    <div class="table-wrap">
        <table class="smart-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User / Name</th>
                    <th>Contact</th>
                    <th>Location</th>
                    <th>Academic</th>
                    <th>Status</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="empty">No matching records.</td></tr>
            <?php else: foreach ($rows as $r):
                $fullNameRaw = trim(($r['first_name']??'') . ' ' . ($r['middle_name']??'') . ' ' . ($r['last_name']??''));
                $fullName = $fullNameRaw !== '' ? $fullNameRaw : '(no name)';
                $isRead = (int)$r['is_read'] === 1;
                $submitted = $r['submitted_at'] ? date('Y-m-d H:i', strtotime($r['submitted_at'])) : '';
            ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td>
                        <div class="mono"><?= highlight_terms($r['user_id'] ?? '', $hlTerms) ?></div>
                        <div class="strong"><?= highlight_terms($fullName, $hlTerms) ?></div>
                    </td>
                    <td>
                        <div><?= highlight_terms($r['email'] ?? '', $hlTerms) ?></div>
                        <div class="mono"><?= highlight_terms($r['mobile_number'] ?? '', $hlTerms) ?><?= $r['phone_number'] ? ' · ' . highlight_terms($r['phone_number'], $hlTerms) : '' ?></div>
                    </td>
                    <td>
                        <div><?= highlight_terms($r['city'] ?? '', $hlTerms) ?><?= $r['state'] ? ', ' . highlight_terms($r['state'], $hlTerms) : '' ?></div>
                        <div class="mono"><?= highlight_terms($r['postal_code'] ?? '', $hlTerms) ?></div>
                    </td>
                    <td>
                        <div class="strong"><?= highlight_terms($r['university'] ?? '', $hlTerms) ?></div>
                        <div class="mono">
                            <?= $r['current_program'] ? 'Cur: ' . highlight_terms($r['current_program'], $hlTerms) : '' ?>
                            <?= ($r['current_program'] && $r['proposed_program']) ? ' · ' : '' ?>
                            <?= $r['proposed_program'] ? 'Prop: ' . highlight_terms($r['proposed_program'], $hlTerms) : '' ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?= $isRead?'read':'unread' ?>"><?= $isRead ? 'Read' : 'Unread' ?></span>
                    </td>
                    <td>
                        <span class="mono"><?= h($submitted) ?></span>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    // Pager
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($totalPages > 1):
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        ?>
        <div class="pager">
            <button class="pg" data-page="1" <?= $page<=1?'disabled':'' ?>>« First</button>
            <button class="pg" data-page="<?= (int)($page-1) ?>" <?= $page<=1?'disabled':'' ?>>‹ Prev</button>
            <?php for($p=$start;$p<=$end;$p++): ?>
                <button class="pg <?= $p==$page?'active':'' ?>" data-page="<?= (int)$p ?>"><?= (int)$p ?></button>
            <?php endfor; ?>
            <button class="pg" data-page="<?= (int)($page+1) ?>" <?= $page>=$totalPages?'disabled':'' ?>>Next ›</button>
            <button class="pg" data-page="<?= (int)$totalPages ?>" <?= $page>=$totalPages?'disabled':'' ?>>Last »</button>
        </div>
    <?php
    endif;

    $html = ob_get_clean();

    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Credit Transfer Applications — Smart Search</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    :root {
        --bg:#0b1020; --panel:#111731; --soft:#1a2247; --text:#e9ecf1; --muted:#aab1c6;
        --acc:#6ca2ff; --mark:#ffe38f; --badge:#263b72; --badgeU:#6c2f2f; --good:#2a8f60;
        --shadow: 0 6px 18px rgba(0,0,0,.25);
    }
    * { box-sizing: border-box; }
    body { margin:0; font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
        background:linear-gradient(160deg, #0b1020 0%, #0b1428 100%); color:var(--text); }
    .wrap { max-width:1100px; margin:40px auto; padding:0 16px; }
    .card { background:linear-gradient(180deg, var(--panel), #0f1530); border:1px solid #1f2a52; border-radius:16px; box-shadow:var(--shadow); overflow:hidden; }
    .head { padding:18px 20px; border-bottom:1px solid #1f2a52; display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .title { font-size:18px; font-weight:800; letter-spacing:.3px; }
    .search { position:relative; flex:1 1 340px; }
    .search input {
        width:100%; padding:14px 44px 14px 14px; border-radius:12px; border:1px solid #253160; background:#0e1430; color:var(--text);
        outline:none; transition:border-color .2s;
    }
    .search input::placeholder { color:#7280b8; }
    .search svg { position:absolute; right:12px; top:50%; transform:translateY(-50%); opacity:.8 }
    .controls { display:flex; gap:8px; align-items:center; }
    .sel, .btn {
        border-radius:12px; border:1px solid #253160; background:#0e1430; color:var(--text); padding:10px 12px; cursor:pointer;
    }
    .sel { appearance:none; }
    .btn { font-weight:700; }
    .btn.clear { background:#1a2247; }
    .body { padding:0; }
    .results-meta { display:flex; justify-content:space-between; padding:14px 18px; color:var(--muted); font-size:13px; border-bottom:1px solid #1f2a52; }
    .table-wrap { overflow:auto; }
    table.smart-table { width:100%; border-collapse:collapse; }
    table.smart-table th, table.smart-table td { padding:12px 14px; border-bottom:1px solid #1f2a52; vertical-align:top; }
    table.smart-table thead th { text-align:left; font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#9fb2f9; background:#10173a; position:sticky; top:0; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; color:#cbd4ff; font-size:12px; }
    .strong { font-weight:700; }
    .badge { font-size:12px; padding:4px 10px; border-radius:999px; background:var(--badge); display:inline-block; border:1px solid #2a3b75; }
    .badge.unread { background:var(--badgeU); border-color:#7a3b3b; color:#ffe2e2; }
    mark { background:var(--mark); color:#140c00; padding:0 .15em; border-radius:2px; }
    .empty { text-align:center; color:#9aa4c9; }
    .pager { display:flex; gap:6px; padding:14px; justify-content:flex-end; background:#0f1530; border-top:1px solid #1f2a52; }
    .pg { border-radius:10px; border:1px solid #233061; background:#0e1430; color:var(--text); padding:8px 10px; cursor:pointer; }
    .pg[disabled] { opacity:.4; cursor:not-allowed; }
    .pg.active { background:var(--acc); color:#081127; border-color:#6ca2ff; font-weight:800; }
    @media (max-width: 720px) {
        .results-meta { flex-direction:column; gap:6px; }
        .head { flex-direction:column; align-items:stretch; }
        .controls { justify-content:space-between; }
    }
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div class="title">Credit Transfer Applications — Smart Search</div>
            <div class="search">
                <input id="q" type="text" placeholder="Search by name, email, phone, user ID, city, university, program, postal code…" autofocus>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M21 21l-4.2-4.2M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="#9fb2f9" stroke-width="1.6" stroke-linecap="round"/></svg>
            </div>
            <div class="controls">
                <select id="perPage" class="sel" title="Rows per page">
                    <option value="10">10 / page</option>
                    <option value="20">20 / page</option>
                    <option value="30">30 / page</option>
                    <option value="50">50 / page</option>
                </select>
                <button id="clearBtn" class="btn clear" type="button">Clear</button>
            </div>
        </div>
        <div id="results" class="body">
            <!-- AJAX results inject here -->
        </div>
    </div>
</div>

<script>
(function(){
    const $q = document.getElementById('q');
    const $per = document.getElementById('perPage');
    const $res = document.getElementById('results');
    const $clear = document.getElementById('clearBtn');

    let page = 1, currentQ = '', currentPer = parseInt($per.value,10)||10, inflight = null, timer = null;

    function fetchResults() {
        const params = new URLSearchParams({
            ajax: '1',
            q: currentQ.trim(),
            page: String(page),
            per_page: String(currentPer)
        });
        if (inflight) inflight.abort();
        const ctrl = new AbortController();
        inflight = ctrl;

        $res.innerHTML = `<div style="padding:18px;color:#aab1c6;">Searching…</div>`;

        fetch(`?${params.toString()}`, { signal: ctrl.signal })
          .then(r => r.text())
          .then(html => {
              if (ctrl.signal.aborted) return;
              $res.innerHTML = html;
              // Wire pager buttons
              $res.querySelectorAll('.pg').forEach(btn => {
                btn.addEventListener('click', () => {
                    const p = parseInt(btn.dataset.page,10);
                    if (!isNaN(p)) {
                        page = p;
                        fetchResults();
                    }
                });
              });
          })
          .catch(err => {
              if (ctrl.signal.aborted) return;
              $res.innerHTML = `<div style="padding:18px;color:#ffb4b4;">Error loading results.</div>`;
          });
    }

    function debouncedFetch() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            page = 1; // reset to first page on new query
            fetchResults();
        }, 250);
    }

    $q.addEventListener('input', () => {
        currentQ = $q.value;
        debouncedFetch();
    });
    $per.addEventListener('change', () => {
        currentPer = parseInt($per.value,10)||10;
        page = 1;
        fetchResults();
    });
    $clear.addEventListener('click', () => {
        $q.value = '';
        currentQ = '';
        page = 1;
        fetchResults();
        $q.focus();
    });

    // Initial load
    fetchResults();
})();
</script>
</body>
</html>
