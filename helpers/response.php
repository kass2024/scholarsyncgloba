<?php
/**
 * ======================================================
 * STANDARD JSON RESPONSE HELPER (PRODUCTION READY)
 * ======================================================
 *
 * ✔ Consistent API contract
 * ✔ Safe headers
 * ✔ Frontend-friendly structure
 * ✔ No fatal crashes
 * ✔ No exceptions leaking to client
 *
 * ------------------------------------------------------
 * USAGE
 * ------------------------------------------------------
 * jsonResponse($data);
 * jsonResponse("Not found", false, 404);
 * jsonResponse(["email" => "Invalid"], false, 422);
 * jsonResponse($data, true, 200, ["count" => 10]);
 *
 * ------------------------------------------------------
 * RESPONSE FORMAT
 * ------------------------------------------------------
 * {
 *   "success": true|false,
 *   "data": {...},        // present if success = true
 *   "message": "string", // present if success = false
 *   "errors": {...},     // optional (validation errors)
 *   "meta": {...}        // optional
 * }
 * ======================================================
 */

function jsonResponse(
    mixed $payload = null,
    bool $success = true,
    int $httpCode = 200,
    array $meta = []
): void {

    /**
     * --------------------------------------------------
     * SAFETY: HEADERS ALREADY SENT
     * --------------------------------------------------
     */
    if (headers_sent()) {
        echo json_encode([
            "success" => false,
            "message" => "Response headers already sent"
        ]);
        exit;
    }

    /**
     * --------------------------------------------------
     * HTTP HEADERS
     * --------------------------------------------------
     */
    http_response_code($httpCode);
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("X-Content-Type-Options: nosniff");

    /**
     * --------------------------------------------------
     * BASE RESPONSE
     * --------------------------------------------------
     */
    $response = [
        "success" => $success
    ];

    /**
     * --------------------------------------------------
     * SUCCESS RESPONSE
     * --------------------------------------------------
     */
    if ($success === true) {
        $response["data"] = $payload ?? (object)[];
    }

    /**
     * --------------------------------------------------
     * ERROR RESPONSE
     * --------------------------------------------------
     */
    if ($success === false) {
        if (is_string($payload)) {
            $response["message"] = $payload;
        } else {
            $response["message"] = "Request failed";
        }

        if (is_array($payload)) {
            $response["errors"] = $payload;
        }
    }

    /**
     * --------------------------------------------------
     * META (OPTIONAL)
     * --------------------------------------------------
     */
    if (!empty($meta)) {
        $response["meta"] = $meta;
    }

    /**
     * --------------------------------------------------
     * JSON ENCODE (SAFE FOR PRODUCTION)
     * --------------------------------------------------
     */
    $json = json_encode(
        $response,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_PARTIAL_OUTPUT_ON_ERROR
    );

    if ($json === false) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Internal JSON encoding error"
        ]);
        exit;
    }

    echo $json;
    exit;
}
