<?php
/**
 * APPLYBOARD INSTITUTION ALLOCATION – CANADA
 * ------------------------------------------
 * Idempotent deployment script:
 * - Ensures university_admins table
 * - Creates missing Canada universities
 * - Creates / matches staff admins by email (and known name aliases)
 * - Assigns admins in charge per allocation
 * - Links Applyboard platform to those universities
 *
 * Usage (CLI):
 *   php scripts/seed_applyboard_canada_allocation.php
 *
 * Or open once in browser while logged in as superadmin (optional safeguard skipped in CLI).
 */
declare(strict_types=1);

$isCli = (PHP_SAPI === 'cli');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers/university_admins_schema.php';

pcvc_ensure_university_admins_schema($conn);

if (!$isCli) {
    session_start();
    if (($_SESSION['role'] ?? '') !== 'superadmin') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden: superadmin only (or run via CLI).\n";
        exit(1);
    }
    header('Content-Type: text/plain; charset=utf-8');
}

function out(string $msg): void
{
    echo $msg . PHP_EOL;
}

function normalize_name(string $name): string
{
    $name = mb_strtolower(trim($name));
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    $name = str_replace(['–', '—', '−'], '-', $name);
    return $name;
}

function find_region_id(mysqli $conn): int
{
    $r = $conn->query("SELECT id FROM regions WHERE UPPER(name) = 'CANADA' LIMIT 1");
    $row = $r ? $r->fetch_assoc() : null;
    if ($row) {
        return (int) $row['id'];
    }
    $conn->query("INSERT INTO regions (name) VALUES ('CANADA')");
    return (int) $conn->insert_id;
}

function find_country_id(mysqli $conn): int
{
    $r = $conn->query("SELECT id FROM countries WHERE name = 'Canada' ORDER BY id ASC LIMIT 1");
    $row = $r ? $r->fetch_assoc() : null;
    if ($row) {
        return (int) $row['id'];
    }
    $regionId = find_region_id($conn);
    // countries table may or may not have region_id — try simple insert
    if ($conn->query("INSERT INTO countries (name) VALUES ('Canada')")) {
        return (int) $conn->insert_id;
    }
    throw new RuntimeException('Could not resolve Canada country id');
}

function find_applyboard_platform_id(mysqli $conn): int
{
    $r = $conn->query("SELECT id FROM platforms WHERE platform_name = 'Applyboard' LIMIT 1");
    $row = $r ? $r->fetch_assoc() : null;
    if ($row) {
        return (int) $row['id'];
    }
    $r = $conn->query("SELECT id FROM platforms WHERE platform_name LIKE '%Applyboard%' ORDER BY id ASC LIMIT 1");
    $row = $r ? $r->fetch_assoc() : null;
    if ($row) {
        return (int) $row['id'];
    }
    throw new RuntimeException('Applyboard platform not found');
}

/**
 * @param string[] $aliases
 */
function ensure_university(mysqli $conn, string $canonicalName, array $aliases, int $regionId, int $countryId): int
{
    $candidates = array_values(array_unique(array_merge([$canonicalName], $aliases)));
    foreach ($candidates as $candidate) {
        $stmt = $conn->prepare('SELECT id, name FROM universities WHERE name = ? LIMIT 1');
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $id = (int) $row['id'];
            // Prefer canonical display name when matching an alias
            if ($row['name'] !== $canonicalName && $candidate !== $canonicalName) {
                // Keep existing name if it is more specific (e.g. Algoma Brampton / Sheridan ALL CAPS)
                // Only rename when it is a short alias of the same school.
            }
            out("  University exists: {$row['name']} (id={$id})");
            return $id;
        }
    }

    // Fuzzy: LIKE on first distinctive tokens
    foreach ($candidates as $candidate) {
        $like = '%' . str_replace(' ', '%', $candidate) . '%';
        $stmt = $conn->prepare('SELECT id, name FROM universities WHERE name LIKE ? ORDER BY id ASC LIMIT 1');
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            out("  University matched fuzzy: {$row['name']} (id={$row['id']}) for [{$canonicalName}]");
            return (int) $row['id'];
        }
    }

    $stmt = $conn->prepare('INSERT INTO universities (name, region_id, country_id) VALUES (?, ?, ?)');
    $stmt->bind_param('sii', $canonicalName, $regionId, $countryId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed inserting university: ' . $canonicalName);
    }
    $id = (int) $conn->insert_id;
    $stmt->close();
    out("  Created university: {$canonicalName} (id={$id})");
    return $id;
}

/**
 * @param string[] $nameHints
 */
function ensure_admin(mysqli $conn, string $fullName, string $email, array $nameHints = []): int
{
    $email = strtolower(trim($email));
    $stmt = $conn->prepare('SELECT id, full_name, email FROM admins WHERE LOWER(email) = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        out("  Admin by email: {$row['full_name']} <{$row['email']}> (id={$row['id']})");
        // Keep full_name in sync if empty-ish
        return (int) $row['id'];
    }

    $hints = array_values(array_unique(array_merge([$fullName], $nameHints)));
    foreach ($hints as $hint) {
        $like = '%' . trim($hint) . '%';
        $stmt = $conn->prepare(
            "SELECT id, full_name, email FROM admins
             WHERE full_name LIKE ? OR CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) LIKE ?
             ORDER BY id ASC LIMIT 5"
        );
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        $matches = [];
        while ($m = $res->fetch_assoc()) {
            $matches[] = $m;
        }
        $stmt->close();

        if (count($matches) === 1) {
            $row = $matches[0];
            $id = (int) $row['id'];
            // Update email to the allocation email if not already taken
            $chk = $conn->prepare('SELECT id FROM admins WHERE LOWER(email) = ? AND id != ? LIMIT 1');
            $chk->bind_param('si', $email, $id);
            $chk->execute();
            $taken = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$taken && strtolower((string) $row['email']) !== $email) {
                $upd = $conn->prepare('UPDATE admins SET email = ?, full_name = ? WHERE id = ?');
                $upd->bind_param('ssi', $email, $fullName, $id);
                $upd->execute();
                $upd->close();
                out("  Admin matched by name, email updated: {$fullName} <{$email}> (id={$id})");
            } else {
                out("  Admin matched by name: {$row['full_name']} <{$row['email']}> (id={$id})");
            }
            return $id;
        }
    }

    // Create new staff account
    $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $first = $parts[0] ?? 'Staff';
    $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
    $usernameBase = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $fullName) ?? 'staff');
    $usernameBase = trim($usernameBase, '.') ?: 'staff';
    $username = $usernameBase;
    $n = 1;
    while (true) {
        $chk = $conn->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
        $chk->bind_param('s', $username);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$exists) {
            break;
        }
        $n++;
        $username = $usernameBase . $n;
    }

    $defaultPassword = 'ScholarSync@ChangeMe2026';
    $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
    $role = 'staff';

    $stmt = $conn->prepare(
        "INSERT INTO admins (username, first_name, last_name, email, password_hash, full_name, role)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssssss', $username, $first, $last, $email, $hash, $fullName, $role);
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed creating admin ' . $fullName . ': ' . $stmt->error);
    }
    $id = (int) $conn->insert_id;
    $stmt->close();
    out("  Created admin: {$fullName} <{$email}> username={$username} (id={$id}) — temp password: {$defaultPassword}");
    return $id;
}

function link_platform(mysqli $conn, int $universityId, int $platformId): void
{
    $stmt = $conn->prepare(
        'SELECT id FROM university_platforms WHERE university_id = ? AND platform_id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $universityId, $platformId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($exists) {
        return;
    }
    $stmt = $conn->prepare('INSERT INTO university_platforms (university_id, platform_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $universityId, $platformId);
    $stmt->execute();
    $stmt->close();
}

function assign_admin(mysqli $conn, int $universityId, int $adminId): void
{
    $stmt = $conn->prepare(
        'SELECT id FROM university_admins WHERE university_id = ? AND admin_id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $universityId, $adminId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($exists) {
        return;
    }
    $stmt = $conn->prepare('INSERT INTO university_admins (university_id, admin_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $universityId, $adminId);
    $stmt->execute();
    $stmt->close();
}

/* =====================================================
   ALLOCATION DATA
===================================================== */

$regionId = find_region_id($conn);
$countryId = find_country_id($conn);
$applyboardId = find_applyboard_platform_id($conn);

out('=== APPLYBOARD INSTITUTION ALLOCATION – CANADA ===');
out("region_id={$regionId}, country_id={$countryId}, applyboard_platform_id={$applyboardId}");

$universities = [
    'Northeastern University – Toronto and Vancouver' => [
        'Northeastern University',
        'Northeastern University Toronto',
        'Northeastern University Vancouver',
    ],
    'Niagara University – Ontario' => [
        'Niagara University',
        'Niagara University Ontario',
    ],
    'Algoma University' => [
        'Algoma University Brampton All',
        'Algoma University Brampton',
    ],
    'Fanshawe College' => [],
    'University of New Brunswick' => [
        'UNB',
    ],
    'University of Alberta Year One Foundation Program (in partnership with Kaplan International)' => [
        'University of Alberta Year One Foundation Program',
    ],
    'University of Victoria (in partnership with Kaplan International)' => [
        'University of Victoria',
        'University of Victoria Kaplan',
    ],
    'Niagara College' => [],
    'St. Lawrence College' => [
        'Saint Lawrence College',
        'St Lawrence College',
    ],
    'George Brown College' => [
        'George Brown',
    ],
    'Sheridan College' => [
        'SHERIDAN COLLEGE',
    ],
    'Fleming College' => [],
    'George Brown Polytechnic' => [
        'George Brown Polytechnics',
    ],
];

$uniIds = [];
out('');
out('-- Universities --');
foreach ($universities as $name => $aliases) {
    $uniIds[$name] = ensure_university($conn, $name, $aliases, $regionId, $countryId);
    link_platform($conn, $uniIds[$name], $applyboardId);
}

$admins = [
    [
        'name' => 'Akayezu Soni',
        'email' => 'asoniamuhizi@gmail.com',
        'hints' => ['Akayezu Sonia', 'Akayezu Soni', 'asoniamuhizi', 'akayezusonia'],
        'universities' => [
            'Northeastern University – Toronto and Vancouver',
            'Niagara University – Ontario',
        ],
    ],
    [
        'name' => 'Uwitonze Yvette',
        'email' => 'scholarsyncglobal-rwanda-yvette@scholarsyncglobal.ca',
        'hints' => ['Uwitonze yvette', 'Uwitonze Yvette', 'uwiyvette'],
        'universities' => [
            'Algoma University',
            'Fanshawe College',
        ],
    ],
    [
        'name' => 'Mutware Jules Bonheur',
        'email' => 'mutwarejules65@gmail.com',
        'hints' => ['Mutware  Jules Bonheur', 'Mutware Jules', 'mutwarejules'],
        'universities' => [
            'University of New Brunswick',
            'University of Alberta Year One Foundation Program (in partnership with Kaplan International)',
            'University of Victoria (in partnership with Kaplan International)',
        ],
    ],
    [
        'name' => 'Uwamahoro Delphine',
        'email' => 'uwamahorodelphine8@gmail.com',
        'hints' => ['Uwamahoro Delphine'],
        'universities' => [
            'Niagara College',
            'St. Lawrence College',
        ],
    ],
    [
        'name' => 'Ikuzwe Yvone Bienvenue',
        'email' => 'ikuvob@gmail.com',
        'hints' => ['Ikuzwe Yvone', 'ybienvenue', 'Ikuzwe Yvone Bienvenue'],
        'universities' => [
            'George Brown College',
            'Sheridan College',
        ],
    ],
    [
        'name' => 'Karamedawe Tuza Klara',
        'email' => 'tuzaderryklara@gmail.com',
        'hints' => ['Karamedawe', 'Tuza Klara', 'tuzaderryklara'],
        'universities' => [
            'Fanshawe College',
            'Fleming College',
            'George Brown Polytechnic',
        ],
    ],
];

out('');
out('-- Admins & assignments --');
foreach ($admins as $adminDef) {
    $adminId = ensure_admin($conn, $adminDef['name'], $adminDef['email'], $adminDef['hints']);
    foreach ($adminDef['universities'] as $uniName) {
        if (!isset($uniIds[$uniName])) {
            out("  WARN: university missing in map: {$uniName}");
            continue;
        }
        assign_admin($conn, $uniIds[$uniName], $adminId);
        out("  Assigned {$adminDef['name']} → {$uniName}");
    }
}

out('');
out('-- Verification --');
$q = "
  SELECT u.name AS university,
         GROUP_CONCAT(DISTINCT a.full_name ORDER BY a.full_name SEPARATOR ', ') AS admins,
         GROUP_CONCAT(DISTINCT p.platform_name ORDER BY p.platform_name SEPARATOR ', ') AS platforms
  FROM universities u
  LEFT JOIN university_admins ua ON ua.university_id = u.id
  LEFT JOIN admins a ON a.id = ua.admin_id
  LEFT JOIN university_platforms up ON up.university_id = u.id
  LEFT JOIN platforms p ON p.id = up.platform_id
  WHERE u.id IN (" . implode(',', array_map('intval', $uniIds)) . ")
  GROUP BY u.id, u.name
  ORDER BY u.name
";
$res = $conn->query($q);
while ($row = $res->fetch_assoc()) {
    out("  {$row['university']} | admins: " . ($row['admins'] ?: '—') . ' | platforms: ' . ($row['platforms'] ?: '—'));
}

out('');
out('Done.');
