<?php

function getCompanyResearch($pdo, $company, $forceRefresh = false) {
    $companyId = $company['id'];
    $companyName = $company['company_name'];
    $targetTons = $company['target_tons'] > 0 ? $company['target_tons'] : 'Unknown';

    if (!$forceRefresh) {
        $stmt = $pdo->prepare("SELECT research_data FROM company_research WHERE company_id = ?");
        $stmt->execute([$companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['research_data'])) {
            return json_decode($row['research_data'], true);
        }
    }

    $tavilyKey = $_ENV['TAVILY_API_KEY'] ?? '';
    $groqKey = $_ENV['GROQ_API_KEY'] ?? '';

    if (empty($tavilyKey) || empty($groqKey) || strpos($tavilyKey, 'YOUR_') !== false) {
        return ['error' => 'API keys are missing or invalid in .env file'];
    }

    // Step 1: Tavily Search
    $query = "$companyName battery recycling EPR targets EV business deals executives management team LinkedIn email";
    
    $ch = curl_init('https://api.tavily.com/search');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'api_key' => $tavilyKey,
        'query' => $query,
        'search_depth' => 'advanced',
        'max_results' => 10
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $tavilyResponse = curl_exec($ch);
    if ($tavilyResponse === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['error' => 'Tavily API Error: ' . $err];
    }
    curl_close($ch);

    $tavilyData = json_decode($tavilyResponse, true);
    if (isset($tavilyData['error'])) {
        return ['error' => 'Tavily API returned error: ' . json_encode($tavilyData['error'])];
    }
    
    $context = "";
    if (!empty($tavilyData['results'])) {
        foreach ($tavilyData['results'] as $res) {
            $context .= "Source URL: " . $res['url'] . "\nContent: " . $res['content'] . "\n\n";
        }
    }

    if (empty($context)) {
        $context = "No significant search results found for $companyName.";
    }

    // Mathematical hints for the AI
    $targetTonsCalc = is_numeric($targetTons) ? $targetTons : 0;
    $mathContext = "";
    if ($targetTonsCalc > 0) {
        $mathContext = "Given their EPR Target of $targetTonsCalc Tons, if MiniMines secures 100% of this feed, we can generate up to ".($targetTonsCalc * 0.148)." Tons of EPR Certificates. We can recover approx ".($targetTonsCalc * 0.1)." Tons of High-Purity Nickel & Cobalt via HHM™ process, and refine approx ".($targetTonsCalc * 0.08)." Tons of Lithium Carbonate equivalent.";
    }

    // Step 2: Groq Processing
    $prompt = "You are a highly analytical Sourcing Agent for 'MiniMines', a battery recycling company using a patented Hybrid Hydrometallurgy (HHM™) process. Analyze the web search context about '$companyName'.
Their official EPR target is $targetTons Tons.
$mathContext

Context:
$context

Generate a highly strategic Sourcing Agent Console report.
Rule 1: NEVER hallucinate contact details. If an email or linkedin profile is NOT found in the context, output 'Not Publicly Available'.
Rule 2: ALWAYS provide the 'proof_source_url' for any contact found (copy the exact Source URL from the Context).

Output a JSON object strictly matching this schema:
{
  \"sourcing_sector\": \"e.g., EV 2W, EV 4W, Consumer Electronics, or Unknown\",
  \"chemistry\": \"e.g., NMC 532, LFP, or Unknown\",
  \"classification\": \"e.g., Low-Hanging Fruit, High-Value Target, Strategic Partner\",
  \"strategic_summary\": \"A short paragraph analyzing their EPR liabilities, current partnerships, and urgency based on the context.\",
  \"potential\": {
    \"epr_certificates\": \"Calculated tons of certificates generated (use the math provided if available)\",
    \"recovery_metals\": \"Nickel/Cobalt recovery estimates (use the math provided if available)\",
    \"offset_dependency\": \"Reduction in raw material import dependency (e.g. up to 15%)\"
  },
  \"pitch\": \"A highly customized, professional, 1-paragraph email script from the MiniMines Sourcing Team to a key contact at this company, pitching our HHM process and mentioning their target tons.\",
  \"contacts\": [
    {
      \"name\": \"Name of executive or key person\",
      \"role\": \"Role/Title\",
      \"email\": \"Email if available, else 'Not Publicly Available'\",
      \"linkedin\": \"LinkedIn URL if available, else 'Not Publicly Available'\",
      \"proof_source_url\": \"The exact Source URL from the context where you found this person\"
    }
  ]
}";

    $ch2 = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $groqKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'llama-3.1-8b-instant',
        'messages' => [
            ['role' => 'system', 'content' => 'You output strictly valid JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.1
    ]));
    curl_setopt($ch2, CURLOPT_TIMEOUT, 60);

    $groqResponse = curl_exec($ch2);
    if ($groqResponse === false) {
        $err = curl_error($ch2);
        curl_close($ch2);
        return ['error' => 'Groq API Error: ' . $err];
    }
    curl_close($ch2);

    $groqData = json_decode($groqResponse, true);
    if (isset($groqData['error'])) {
        return ['error' => 'Groq API returned error: ' . json_encode($groqData['error'])];
    }
    $resultJsonStr = $groqData['choices'][0]['message']['content'] ?? '{}';
    $finalResult = json_decode($resultJsonStr, true);

    // Save to cache
    if ($finalResult && !isset($finalResult['error'])) {
        $stmt = $pdo->prepare("
            INSERT INTO company_research (company_id, research_data) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE research_data = VALUES(research_data)
        ");
        $stmt->execute([$companyId, $resultJsonStr]);
    }

    return $finalResult;
}
?>
