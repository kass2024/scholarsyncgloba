<?php
/**
 * FAQ resolution from knowledge_base (FULLTEXT + BOOLEAN + LIKE fallback).
 * Expects InnoDB FULLTEXT index on (question, answer) as in schema dump.
 */

declare(strict_types=1);

/**
 * Detect when the user is asking for a live person (skip loose FAQ matches; let the model respond clearly).
 */
function pcvc_user_requests_human_advisor(string $message): bool
{
    $m = mb_strtolower(trim($message));
    if ($m === '') {
        return false;
    }

    if (preg_match(
        '/\b(talk|speak|chat)\s+(to|with)\s+(a\s+)?(human|person|advisor|agent|representative|someone|real\s+person)\b/u',
        $m
    )) {
        return true;
    }
    if (preg_match(
        '/\b(need|want|get|connect|transfer)\s+(to\s+)?(a\s+)?(human|person|agent|advisor|representative)\b/u',
        $m
    )) {
        return true;
    }
    if (preg_match(
        '/\b(human\s+being|real\s+person|live\s+(agent|person|advisor|support)|customer\s+service|human\s+support)\b/u',
        $m
    )) {
        return true;
    }
    if (preg_match('/\b(speak|talk)\s+to\s+someone\b/u', $m)) {
        return true;
    }
    if (preg_match('/\boperator\b/u', $m)) {
        return true;
    }

    return false;
}

/**
 * Tokens too generic for LIKE fallback (they match inside long unrelated answers).
 *
 * @return array<string, true>
 */
function pcvc_kb_like_stopwords(): array
{
    static $set = null;
    if ($set !== null) {
        return $set;
    }
    $words = [
        'about', 'after', 'also', 'anyone', 'application', 'apply', 'before', 'being', 'between',
        'bachelor', 'canada', 'college', 'could', 'customer', 'degree', 'document', 'documents',
        'during', 'each', 'email', 'everyone', 'everything', 'first', 'from', 'give', 'have',
        'help', 'here', 'high', 'human', 'information', 'into', 'just', 'last', 'like', 'live',
        'long', 'low', 'master', 'more', 'much', 'must', 'need', 'nothing', 'only', 'other',
        'over', 'part', 'people', 'person', 'phone', 'please', 'real', 'representative',
        'required', 'school', 'service', 'should', 'some', 'someone', 'something', 'speak',
        'staff', 'still', 'student', 'study', 'such', 'support', 'talk', 'tell', 'than', 'that',
        'their', 'there', 'these', 'they', 'this', 'those', 'through', 'time', 'under',
        'until', 'very', 'visa', 'want', 'was', 'well', 'were', 'what', 'when', 'where',
        'which', 'while', 'will', 'with', 'within', 'without', 'would', 'your', 'website',
        'university', 'advisor', 'contact', 'agent',
    ];
    $set = [];
    foreach ($words as $w) {
        $set[$w] = true;
    }

    return $set;
}

/**
 * @return bool Whether knowledge_base exists in the current database.
 */
function pcvc_kb_table_exists(mysqli $conn): bool
{
    $r = $conn->query("SHOW TABLES LIKE 'knowledge_base'");
    return $r instanceof mysqli_result && $r->num_rows > 0;
}

/**
 * Build a simple BOOLEAN MODE query from user text (+word +word …).
 */
function pcvc_kb_boolean_query(string $message): string
{
    $message = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $message);
    $words = preg_split('/\s+/', $message, -1, PREG_SPLIT_NO_EMPTY);
    $parts = [];
    foreach ($words as $w) {
        if (mb_strlen($w) >= 3) {
            $safe = preg_replace('/[^\p{L}\p{N}]/u', '', $w);
            if ($safe !== '') {
                $parts[] = '+' . $safe;
            }
        }
        if (count($parts) >= 8) {
            break;
        }
    }
    return implode(' ', $parts);
}

/**
 * LIKE fallback: prefer matches on `question` only; stricter `answer` matches to avoid false positives.
 *
 * @return string|null
 */
function pcvc_kb_like_fallback(mysqli $conn, string $message, int $clientId): ?string
{
    $stop = pcvc_kb_like_stopwords();
    $message = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $message);
    $words = preg_split('/\s+/', $message, -1, PREG_SPLIT_NO_EMPTY);
    usort($words, static function ($a, $b) {
        return mb_strlen($b) <=> mb_strlen($a);
    });

    $candidates = [];
    foreach ($words as $w) {
        $lw = mb_strtolower($w);
        if (mb_strlen($w) < 5 || isset($stop[$lw])) {
            continue;
        }
        $candidates[] = $w;
    }
    $candidates = array_values(array_unique($candidates));
    if ($candidates === []) {
        return null;
    }

    $candidates = array_slice($candidates, 0, 6);

    foreach ($candidates as $w) {
        $like = '%' . $w . '%';
        $sql = 'SELECT answer FROM knowledge_base
                WHERE is_active = 1 AND client_id = ?
                  AND question LIKE ?
                ORDER BY priority DESC, id ASC
                LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('is', $clientId, $like);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($row && isset($row['answer']) && trim((string) $row['answer']) !== '') {
                return trim((string) $row['answer']);
            }
        } else {
            $stmt->close();
        }
    }

    foreach ($candidates as $w) {
        if (mb_strlen($w) < 8) {
            continue;
        }
        $like = '%' . $w . '%';
        $sql = 'SELECT answer FROM knowledge_base
                WHERE is_active = 1 AND client_id = ?
                  AND answer LIKE ?
                ORDER BY priority DESC, id ASC
                LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('is', $clientId, $like);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($row && isset($row['answer']) && trim((string) $row['answer']) !== '') {
                return trim((string) $row['answer']);
            }
        } else {
            $stmt->close();
        }
    }

    return null;
}

/**
 * Find a curated FAQ answer when the user message matches stored knowledge.
 *
 * @return string|null Plain-text answer or null to fall back to OpenAI
 */
function pcvc_find_faq_answer(mysqli $conn, string $userMessage, int $clientId = 1): ?string
{
    $userMessage = trim($userMessage);
    if ($userMessage === '' || !pcvc_kb_table_exists($conn)) {
        return null;
    }

    // 1) NATURAL LANGUAGE MODE (best when query has enough meaningful terms)
    $sqlNl = 'SELECT answer,
                MATCH(question, answer) AGAINST (? IN NATURAL LANGUAGE MODE) AS rel
              FROM knowledge_base
              WHERE is_active = 1 AND client_id = ?
                AND MATCH(question, answer) AGAINST (? IN NATURAL LANGUAGE MODE)
              ORDER BY rel DESC, priority DESC, id ASC
              LIMIT 1';
    $stmt = $conn->prepare($sqlNl);
    if ($stmt) {
        $stmt->bind_param('sis', $userMessage, $clientId, $userMessage);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($row && isset($row['answer']) && trim((string) $row['answer']) !== '') {
                $rel = isset($row['rel']) ? (float) $row['rel'] : 1.0;
                if ($rel >= 0.08) {
                    return trim((string) $row['answer']);
                }
            }
        } else {
            $stmt->close();
        }
    }

    // 2) BOOLEAN MODE (helps short queries and keyword-heavy questions)
    $bool = pcvc_kb_boolean_query($userMessage);
    if ($bool !== '') {
        $sqlBool = 'SELECT answer,
                    MATCH(question, answer) AGAINST (? IN BOOLEAN MODE) AS rel
                  FROM knowledge_base
                  WHERE is_active = 1 AND client_id = ?
                    AND MATCH(question, answer) AGAINST (? IN BOOLEAN MODE)
                  ORDER BY rel DESC, priority DESC, id ASC
                  LIMIT 1';
        $stmt = $conn->prepare($sqlBool);
        if ($stmt) {
            $stmt->bind_param('sis', $bool, $clientId, $bool);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();
                if ($row && isset($row['answer']) && trim((string) $row['answer']) !== '') {
                    $rel = isset($row['rel']) ? (float) $row['rel'] : 1.0;
                    if ($rel >= 0.02) {
                        return trim((string) $row['answer']);
                    }
                }
            } else {
                $stmt->close();
            }
        }
    }

    // 3) LIKE on keywords (no FULLTEXT match — e.g. very short input)
    return pcvc_kb_like_fallback($conn, $userMessage, $clientId);
}
