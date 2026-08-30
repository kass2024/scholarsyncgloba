<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/helpers/env_bootstrap.php';
require_once __DIR__ . '/helpers/document_vision_gemini.php';
require_once __DIR__ . '/helpers/document_vision_claude.php';
require_once __DIR__ . '/helpers/program_ai_utils.php';

header('Content-Type: application/json');

// ===============================
// SECURITY
// ===============================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ===============================
// INPUT
// ===============================
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

$text = trim($input['text'] ?? '');

if ($text === '') {
    echo json_encode(['error' => 'Empty input']);
    exit;
}

if (!pcvc_docvision_claude_is_configured() && !pcvc_docvision_is_configured()) {
    echo json_encode([
        'error' => 'No AI provider configured. Set ANTHROPIC_API_KEY and/or GEMINI_API_KEY in .env.',
    ]);
    exit;
}

// ===============================
// UTILS
// ===============================
function chunkText(string $text, int $maxChars = 3500): array
{
    $chunks = [];
    $current = '';

    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        if (strlen($current . $line) > $maxChars) {
            $chunks[] = $current;
            $current = '';
        }
        $current .= $line . "\n";
    }

    if (trim($current) !== '') {
        $chunks[] = $current;
    }

    return $chunks;
}

function buildPrompt(string $text): string
{
    return <<<PROMPT
You are performing HIGH-ACCURACY DATA EXTRACTION for a college/university program catalogue.

TASK:
Extract EVERY program/course name from the pasted list.

FORMATTING RULES:
- For vocational, certificate, diploma, trade, or college programs WITHOUT a formal degree title (BA, BSc, MSc, PhD, etc.), format each name exactly as:
  Professional Course in {Program Title}
- For formal degree programs (Bachelor, Master, PhD, MBA, etc.), keep the original degree wording.
- Do NOT duplicate the prefix if it is already present.
- One program = one array item.

STRICT RULES (NO EXCEPTIONS):
- DO NOT summarize
- DO NOT merge similar programs
- DO NOT remove Fall / Spring / Intake variations
- Preserve subject wording exactly
- Extract ALL programs, even if more than 200
- Do NOT invent or guess programs
- Ignore headings, numbering, bullets

Return ONLY valid JSON:

{
  "programs": [
    "Professional Course in Machine Learning Analyst",
    "BSc Computer Science"
  ]
}

TEXT:
$text
PROMPT;
}

/**
 * Extract programs from one chunk: Claude first, Gemini fallback.
 *
 * @return array{programs: array<int, string>, provider: string|null, error: string|null}
 */
function extractProgramsFromChunk(string $chunk, string $systemPrompt): array
{
    $userContent = [
        ['type' => 'input_text', 'text' => buildPrompt($chunk)],
    ];

    $lastError = null;

    if (pcvc_docvision_claude_is_configured()) {
        $result = pcvc_docvision_claude_generate_json($systemPrompt, $userContent, 2, 500);
        if (empty($result['error'])) {
            $programs = $result['json']['programs'] ?? [];
            if (is_array($programs) && $programs !== []) {
                return [
                    'programs' => $programs,
                    'provider' => (string)($result['provider'] ?? 'claude'),
                    'error' => null,
                ];
            }
        } else {
            $lastError = (string)($result['error']['message'] ?? 'Claude extraction failed.');
        }
    }

    if (pcvc_docvision_is_configured()) {
        $result = pcvc_docvision_generate_json($systemPrompt, $userContent, 2, 400);
        if (empty($result['error'])) {
            $programs = $result['json']['programs'] ?? [];
            if (is_array($programs) && $programs !== []) {
                return [
                    'programs' => $programs,
                    'provider' => 'gemini',
                    'error' => null,
                ];
            }
        } else {
            $lastError = (string)($result['error']['message'] ?? 'Gemini extraction failed.');
        }
    }

    return [
        'programs' => [],
        'provider' => null,
        'error' => $lastError,
    ];
}

// ===============================
// PROCESS EACH CHUNK
// ===============================
$systemPrompt = 'You extract university and college program names with absolute completeness. Use "Professional Course in {title}" for vocational/college programs without formal degree titles.';
$chunks = chunkText($text);
$allPrograms = [];
$usedProvider = null;
$chunkErrors = [];

foreach ($chunks as $chunk) {
    $result = extractProgramsFromChunk($chunk, $systemPrompt);

    file_put_contents(
        __DIR__ . '/ai_program_debug.log',
        "\n==== " . date('Y-m-d H:i:s') . " ====\n"
        . 'PROVIDER: ' . ($result['provider'] ?? 'none') . "\n"
        . ($result['error'] ? 'ERROR: ' . $result['error'] . "\n" : '')
        . "CHUNK:\n$chunk\n"
        . 'PROGRAMS: ' . count($result['programs']) . "\n",
        FILE_APPEND
    );

    if ($result['provider'] !== null && $usedProvider === null) {
        $usedProvider = $result['provider'];
    }

    if ($result['error'] !== null) {
        $chunkErrors[] = $result['error'];
    }

    if ($result['programs'] !== []) {
        $allPrograms = array_merge($allPrograms, $result['programs']);
    }
}

// ===============================
// STRONG FALLBACK (INTERNATIONAL)
// ===============================
$usedFallback = false;
if ($allPrograms === []) {
    $usedFallback = true;
    $lines = preg_split('/\r\n|\r|\n|,/', $text);

    foreach ($lines as $line) {
        $line = trim(preg_replace('/^[\d\.\-\•]+\s*/', '', $line));

        if (strlen($line) <= 6) {
            continue;
        }

        if (
            preg_match(
                '/\b(BA|BSc|BEng|MA|MSc|MBA|MEng|PhD|Diploma|Certificate|Bachelor|Master)\b/i',
                $line
            )
            || !pcvc_program_has_degree_marker($line)
        ) {
            $allPrograms[] = pcvc_normalize_ai_program_name($line);
        }
    }
}

// ===============================
// FINAL NORMALIZATION
// ===============================
$allPrograms = array_values(array_unique(array_filter(array_map(
    static fn ($name) => pcvc_normalize_ai_program_name((string) $name),
    $allPrograms
))));

if ($allPrograms === [] && $chunkErrors !== []) {
    echo json_encode([
        'error' => $chunkErrors[0],
    ]);
    exit;
}

// ===============================
// RESPONSE
// ===============================
echo json_encode([
    'programs' => $allPrograms,
    'count' => count($allPrograms),
    'fallback' => $usedFallback,
    'provider' => $usedProvider ?? ($usedFallback ? 'regex' : null),
]);
