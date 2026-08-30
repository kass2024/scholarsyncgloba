<?php
/**
 * SETTINGS HANDLER — FINAL PRODUCTION (SCHEMA SAFE)
 * ------------------------------------------------
 * Handles:
 * - Universities
 * - Program Levels
 * - Programs (SINGLE + MULTIPLE SAVE)
 */

declare(strict_types=1);
session_start();

/* =====================================================
   HARDEN OUTPUT (JSON ONLY)
===================================================== */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
ob_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/university_admins_schema.php';
header('Content-Type: application/json');
pcvc_ensure_university_admins_schema($conn);

/* =====================================================
   FATAL ERROR CAPTURE
===================================================== */
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo json_encode(['ok' => false, 'msg' => 'Server error (fatal)']);
    }
});

/* =====================================================
   AUTH
===================================================== */
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    ob_clean();
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

/* =====================================================
   HELPERS
===================================================== */
function respond(bool $ok, string $msg): void {
    ob_clean();
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
    exit;
}

function post(string $key, $default = null) {
    return $_POST[$key] ?? $default;
}

/* =====================================================
   AUTO ROUTING (SAFE)
===================================================== */
$action = post('action');

if (!$action) {
    if (isset($_POST['country_id'])) {
        $action = 'save_university';
    } elseif (isset($_POST['abbreviation']) || isset($_POST['code'])) {
        $action = 'save_level';
    } elseif (isset($_POST['university_id'])) {
        $action = 'save_program';
    }
}

if (!$action) {
    respond(false, 'Missing action');
}

/* =====================================================
   UNIVERSITIES (WITH PLATFORMS) — FINAL
===================================================== */
if ($action === 'save_university') {

    $id         = (int) post('id', 0);
    $name       = trim((string) post('name', ''));
    $region_id  = (int) post('region_id', 0);
    $country_id = (int) post('country_id', 0);

    // 🔹 Platforms + admins in charge (multiple)
    $platform_ids = $_POST['platform_ids'] ?? [];
    $admin_ids = $_POST['admin_ids'] ?? [];

    if ($name === '' || !$region_id || !$country_id) {
        respond(false, 'University name, region and country required');
    }

    /* ===============================
       DUPLICATE CHECK
    =============================== */
    if ($id > 0) {
        $stmt = $conn->prepare(
            "SELECT id
             FROM universities
             WHERE name = ? AND region_id = ? AND country_id = ? AND id != ?
             LIMIT 1"
        );
        $stmt->bind_param("siii", $name, $region_id, $country_id, $id);
    } else {
        $stmt = $conn->prepare(
            "SELECT id
             FROM universities
             WHERE name = ? AND region_id = ? AND country_id = ?
             LIMIT 1"
        );
        $stmt->bind_param("sii", $name, $region_id, $country_id);
    }

    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        respond(false, 'University already exists');
    }
    $stmt->close();

    /* ===============================
       SAVE UNIVERSITY
    =============================== */
    if ($id > 0) {
        // Update
        $stmt = $conn->prepare(
            "UPDATE universities
             SET name = ?, region_id = ?, country_id = ?
             WHERE id = ?"
        );
        $stmt->bind_param("siii", $name, $region_id, $country_id, $id);
        if (!$stmt->execute()) respond(false, 'Database error');
        $stmt->close();
    } else {
        // Insert
        $stmt = $conn->prepare(
            "INSERT INTO universities (name, region_id, country_id)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sii", $name, $region_id, $country_id);
        if (!$stmt->execute()) respond(false, 'Database error');
        $id = $conn->insert_id;
        $stmt->close();
    }

    /* ===============================
       PLATFORM MAPPING (MANY-TO-MANY)
    =============================== */
    // Clear old mappings (safe for edit)
    $conn->query(
        "DELETE FROM university_platforms
         WHERE university_id = {$id}"
    );

    // Insert new mappings
    if (is_array($platform_ids) && !empty($platform_ids)) {
        $stmt = $conn->prepare(
            "INSERT INTO university_platforms (university_id, platform_id)
             VALUES (?, ?)"
        );

        foreach ($platform_ids as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) continue;

            $stmt->bind_param("ii", $id, $pid);
            $stmt->execute();
        }
        $stmt->close();
    }

    /* ===============================
       ADMINS IN CHARGE (MANY-TO-MANY)
    =============================== */
    $conn->query(
        "DELETE FROM university_admins
         WHERE university_id = {$id}"
    );

    if (is_array($admin_ids) && !empty($admin_ids)) {
        $stmt = $conn->prepare(
            "INSERT INTO university_admins (university_id, admin_id)
             VALUES (?, ?)"
        );

        foreach ($admin_ids as $aid) {
            $aid = (int) $aid;
            if ($aid <= 0) {
                continue;
            }

            $stmt->bind_param("ii", $id, $aid);
            $stmt->execute();
        }
        $stmt->close();
    }

    respond(true, 'University saved');
}

/* =====================================================
   DELETE UNIVERSITY (SAFE)
===================================================== */
if ($action === 'delete_university') {
    $id = (int) post('id', 0);
    if ($id <= 0) {
        respond(false, 'Invalid university id');
    }

    $stmt = $conn->prepare('SELECT id, name FROM universities WHERE id = ? LIMIT 1');
    if (!$stmt) {
        respond(false, 'Database error');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $uni = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$uni) {
        respond(false, 'University not found');
    }

    // Block delete when already used on student applications
    $inUse = 0;
    $chk = $conn->prepare(
        'SELECT COUNT(*) AS cnt FROM application_study_choices WHERE university_id = ?'
    );
    if ($chk) {
        $chk->bind_param('i', $id);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();
        $inUse = (int) ($row['cnt'] ?? 0);
    }
    if ($inUse > 0) {
        respond(
            false,
            'Cannot delete: this university is used on ' . $inUse . ' application study choice(s). Remove those first.'
        );
    }

    $conn->begin_transaction();
    try {
        // Related-program suggestion rows
        $hasSugg = $conn->query("SHOW TABLES LIKE 'application_study_choice_suggestions'");
        if ($hasSugg && $hasSugg->num_rows > 0) {
            $st = $conn->prepare(
                'DELETE FROM application_study_choice_suggestions
                 WHERE source_university_id = ? OR suggested_university_id = ?'
            );
            if ($st) {
                $st->bind_param('ii', $id, $id);
                $st->execute();
                $st->close();
            }
        }

        // Admins mapping
        $hasUa = $conn->query("SHOW TABLES LIKE 'university_admins'");
        if ($hasUa && $hasUa->num_rows > 0) {
            $st = $conn->prepare('DELETE FROM university_admins WHERE university_id = ?');
            if ($st) {
                $st->bind_param('i', $id);
                $st->execute();
                $st->close();
            }
        }

        // Platforms mapping (also cascades via FK when present)
        $st = $conn->prepare('DELETE FROM university_platforms WHERE university_id = ?');
        if ($st) {
            $st->bind_param('i', $id);
            $st->execute();
            $st->close();
        }

        // Programs for this university
        $st = $conn->prepare('DELETE FROM programs WHERE university_id = ?');
        if ($st) {
            $st->bind_param('i', $id);
            $st->execute();
            $st->close();
        }

        $st = $conn->prepare('DELETE FROM universities WHERE id = ? LIMIT 1');
        if (!$st) {
            throw new Exception('Prepare delete failed');
        }
        $st->bind_param('i', $id);
        if (!$st->execute() || $st->affected_rows < 1) {
            $st->close();
            throw new Exception('University delete failed');
        }
        $st->close();

        $conn->commit();
        respond(true, 'University deleted');
    } catch (Throwable $e) {
        $conn->rollback();
        respond(false, 'Could not delete university');
    }
}

/* =====================================================
   PROGRAM LEVELS (UNCHANGED)
===================================================== */
if ($action === 'save_level') {

    $id = (int) post('id', 0);

    $abbreviation = strtoupper(trim(
        (string) (post('abbreviation') ?? post('code', ''))
    ));
    $name = trim((string) post('name', ''));

    if ($abbreviation === '' || $name === '') {
        respond(false, 'Level code and name required');
    }

    $stmt = ($id > 0)
        ? $conn->prepare(
            "SELECT id FROM program_levels
             WHERE abbreviation=? AND id!=?
             LIMIT 1"
          )
        : $conn->prepare(
            "SELECT id FROM program_levels
             WHERE abbreviation=?
             LIMIT 1"
          );

    ($id > 0)
        ? $stmt->bind_param("si", $abbreviation, $id)
        : $stmt->bind_param("s",  $abbreviation);

    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows) respond(false, 'Level code already exists');
    $stmt->close();

    $stmt = ($id > 0)
        ? $conn->prepare(
            "UPDATE program_levels
             SET abbreviation=?, name=?
             WHERE id=?"
          )
        : $conn->prepare(
            "INSERT INTO program_levels (abbreviation, name)
             VALUES (?,?)"
          );

    ($id > 0)
        ? $stmt->bind_param("ssi", $abbreviation, $name, $id)
        : $stmt->bind_param("ss",  $abbreviation, $name);

    if (!$stmt->execute()) respond(false, 'Database error');

    respond(true, 'Program level saved');
}

/* =====================================================
   PROGRAMS (SINGLE + MULTI SAVE)
===================================================== */
/* =====================================================
   PROGRAMS — UPDATE (EDIT)
===================================================== */
if ($action === 'update_program') {

    $id = (int) post('id', 0);
    $university_id    = (int) post('university_id', 0);
    $program_level_id = (int) (post('program_level_id') ?? post('level_id', 0));

    $program_name = trim((string)($_POST['programs'][0] ?? ''));

    if (!$id || !$university_id || !$program_level_id || $program_name === '') {
        respond(false, 'Program name, university and level required');
    }

    // duplicate check (exclude self)
    $stmt = $conn->prepare(
        "SELECT id FROM programs
         WHERE program_name=? AND university_id=? AND program_level_id=? AND id!=?
         LIMIT 1"
    );
    $stmt->bind_param("siii", $program_name, $university_id, $program_level_id, $id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows) respond(false, 'Program already exists');
    $stmt->close();

    $stmt = $conn->prepare(
        "UPDATE programs
         SET program_name=?, university_id=?, program_level_id=?
         WHERE id=?"
    );
    $stmt->bind_param("siii", $program_name, $university_id, $program_level_id, $id);

    if (!$stmt->execute()) respond(false, 'Database error');

    respond(true, 'Program updated');
}

if ($action === 'save_program') {

    $id = (int) post('id', 0);

    $university_id    = (int) post('university_id', 0);
    $program_level_id = (int) (post('program_level_id') ?? post('level_id', 0));

    // 🔥 SINGLE OR MULTIPLE
    $programs = [];

    if (isset($_POST['programs']) && is_array($_POST['programs'])) {
        foreach ($_POST['programs'] as $p) {
            $p = trim((string) $p);
            if ($p !== '') $programs[] = $p;
        }
    } else {
        $single = trim((string) (post('program_name') ?? post('name', '')));
        if ($single !== '') $programs[] = $single;
    }

    if (!$university_id || !$program_level_id || empty($programs)) {
        respond(false, 'Program name, university and level required');
    }

    $conn->begin_transaction();

    try {
        foreach ($programs as $program_name) {

            // Duplicate check
            $stmt = $conn->prepare(
                "SELECT id FROM programs
                 WHERE program_name=? AND university_id=? AND program_level_id=?
                 LIMIT 1"
            );
            $stmt->bind_param("sii", $program_name, $university_id, $program_level_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows) {
                $stmt->close();
                continue; // skip duplicates silently
            }
            $stmt->close();

            // Insert
            $stmt = $conn->prepare(
                "INSERT INTO programs (program_name, university_id, program_level_id)
                 VALUES (?,?,?)"
            );
            $stmt->bind_param("sii", $program_name, $university_id, $program_level_id);
            if (!$stmt->execute()) {
                throw new Exception('Insert failed');
            }
            $stmt->close();
        }

        $conn->commit();
        respond(true, 'Program(s) saved');

    } catch (Throwable $e) {
        $conn->rollback();
        respond(false, 'Database error');
    }
}

/* =====================================================
   FALLBACK
===================================================== */
respond(false, 'Invalid action');
