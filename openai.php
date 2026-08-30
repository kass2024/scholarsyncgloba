<?php
require_once __DIR__ . '/helpers/env_bootstrap.php';

function getAIInsights($title, $description) {
    $apiKey = pcvc_env('OPENAI_API_KEY');
    $endpoint = 'https://api.openai.com/v1/chat/completions';

    if ($apiKey === '') {
        return [
            'score' => 0,
            'suggestion' => '⚠️ OpenAI API key not configured (set OPENAI_API_KEY in .env).',
        ];
    }

    if (strlen(trim($title)) < 3 || strlen(trim($description)) < 5) {
        return [
            'score' => 0,
            'suggestion' => "⚠️ Insufficient title or description for evaluation."
        ];
    }

    $prompt = <<<EOT
You are a professional job evaluator. Based on the job title and description below, provide:
1. A productivity score out of 100 (as an integer).
2. One practical suggestion to improve similar tasks.

Format your reply exactly like this:
Productivity Score: XX/100
Suggestion: Your helpful advice here.

Job Title: $title
Job Description: $description
EOT;

    $models = ['gpt-4', 'gpt-3.5-turbo'];

    foreach ($models as $model) {
        $postData = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a job productivity evaluator.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.4
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: ' . 'Bearer ' . $apiKey
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || !$response) {
            error_log("OpenAI [$model] error: $error");
            continue;
        }

        $data = json_decode($response, true);
        $message = $data['choices'][0]['message']['content'] ?? '';

        if (!$message) {
            error_log("OpenAI [$model] response missing content");
            continue;
        }

        // Extract score
        if (preg_match('/Productivity Score:\s*(\d{1,3})\s*\/\s*100/i', $message, $scoreMatch)) {
            $score = (int)$scoreMatch[1];

            // Extract suggestion
            if (preg_match('/Suggestion:\s*(.*)/i', $message, $suggestionMatch)) {
                return [
                    'score' => $score,
                    'suggestion' => trim($suggestionMatch[1])
                ];
            } else {
                return [
                    'score' => $score,
                    'suggestion' => "✅ Score found but no clear suggestion."
                ];
            }
        }

        error_log("OpenAI [$model] invalid format: $message");
    }

    return [
        'score' => 0,
        'suggestion' => "⚠️ AI evaluation failed. Please try again later."
    ];
}
