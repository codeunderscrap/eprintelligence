<?php
// inc/helpers.php – common utility functions
require_once __DIR__.'/../config.php';

/**
 * Execute a prepared query and return results.
 */
function dbQuery(string $sql, array $params = []): array {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Insert a row and return the inserted ID.
 */
function dbInsert(string $sql, array $params = []): int {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$pdo->lastInsertId();
}

/**
 * Simple priority score based on target tons and credits.
 * Weight: target_tons (70%) + credits (30%). Adjustable via priority_rules table later.
 */
function computePriorityScore(array $company): float {
    // Default weights – can be overridden later.
    $weightTons = 0.7;
    $weightCredits = 0.3;
    $target = (float)($company['target_tons'] ?? 0);
    $credits = (float)($company['credits'] ?? 0);
    // Normalize values (simple max‑based scaling) – for demo we just sum.
    return round($weightTons * $target + $weightCredits * $credits, 2);
}

/**
 * Call Tavily API with a custom prompt.
 */
function callTavily(string $query): array {
    $url = 'https://api.tavily.com/search';
    $payload = [
        'api_key' => $_ENV['TAVILY_API_KEY'],
        'query' => $query,
        'search_depth' => 'advanced',
        'max_results' => 10,
    ];
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($payload),
            'timeout' => 30,
        ],
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === false) {
        return [];
    }
    return json_decode($result, true);
}

/**
 * Call Groq LLM with company data + Tavily results.
 */
function callGroq(array $payload): array {
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $system = "You are a structured data extractor. Return ONLY a JSON object matching the schema provided. Do not include explanations.
    Schema:
    {
      \"company_summary\": \"string\",
      \"epr_relevance\": \"string\",
      \"battery_business\": \"string\",
      \"recent_projects\": [],
      \"business_deals\": [],
      \"manufacturing_locations\": [],
      \"products\": [],
      \"estimated_recycling_opportunity\": \"string\",
      \"executive_contacts\": [],
      \"risk_level\": \"string\",
      \"priority_score\": \"string\",
      \"recommended_approach\": \"string\"
    }";
    $body = [
        'model' => 'mixtral-8x7b-32768',
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode($payload)],
        ],
        'temperature' => 0.2,
    ];
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$_ENV['GROQ_API_KEY']}\r\n",
            'method'  => 'POST',
            'content' => json_encode($body),
            'timeout' => 30,
        ],
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === false) {
        return [];
    }
    $decoded = json_decode($result, true);
    // Groq returns choices[0].message.content
    return $decoded['choices'][0]['message']['content'] ?? '';
}
?>
