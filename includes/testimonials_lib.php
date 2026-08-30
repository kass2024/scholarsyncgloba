<?php
declare(strict_types=1);

/**
 * Site testimonials (published on homepage / testimonials page; managed by superadmin).
 * Table: `site_testimonials` — created automatically once; if MySQL is unavailable, pages still load (empty list).
 */

function pcvc_site_testimonials_table_exists(mysqli $conn): bool
{
    try {
        $res = @$conn->query("SHOW TABLES LIKE 'site_testimonials'");
        return $res !== false && $res->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Creates `site_testimonials` if missing. Safe to call multiple times per request (no-op if exists).
 */
function pcvc_ensure_testimonials_table(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        if (!@$conn->ping()) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }

    if (pcvc_site_testimonials_table_exists($conn)) {
        return;
    }

    // Single-line DDL avoids odd parsing issues; ENGINE=InnoDB utf8mb4
    $sql = 'CREATE TABLE IF NOT EXISTS site_testimonials ('
        . 'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,'
        . 'name VARCHAR(191) NOT NULL DEFAULT \'\','
        . 'role_title VARCHAR(255) NOT NULL DEFAULT \'\','
        . 'quote TEXT NOT NULL,'
        . 'photo_path VARCHAR(512) NOT NULL DEFAULT \'\','
        . 'sort_order INT NOT NULL DEFAULT 0,'
        . 'is_published TINYINT(1) NOT NULL DEFAULT 1,'
        . 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
        . 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
        . 'KEY idx_sort (sort_order, id),'
        . 'KEY idx_pub (is_published)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    try {
        if (!$conn->query($sql)) {
            return;
        }
    } catch (Throwable $e) {
        // Connection lost or server down — page can still render without testimonials
        return;
    }
}

/**
 * @return list<array{id:int,name:string,role_title:string,quote:string,photo_path:string,sort_order:int}>
 */
function pcvc_get_published_testimonials(mysqli $conn, int $limit = 12): array
{
    pcvc_ensure_testimonials_table($conn);

    if (!pcvc_site_testimonials_table_exists($conn)) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    try {
        $stmt = $conn->prepare(
            'SELECT id, name, role_title, quote, photo_path, sort_order
             FROM site_testimonials
             WHERE is_published = 1
             ORDER BY sort_order ASC, id DESC
             LIMIT ?'
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res) {
            $stmt->close();
            return [];
        }
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = $row;
        }
        $stmt->close();
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}
