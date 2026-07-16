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

    $tavilyKey = '';
    $groqKey = '';
    $geminiKey = '';
    $firecrawlKey = '';

    // Phase 0: Fetch keys from DB
    $stmtKey = $pdo->prepare("SELECT service_name, api_key FROM api_keys");
    $stmtKey->execute();
    while ($rowK = $stmtKey->fetch(PDO::FETCH_ASSOC)) {
        if ($rowK['service_name'] === 'Tavily') $tavilyKey = $rowK['api_key'];
        if ($rowK['service_name'] === 'Groq') $groqKey = $rowK['api_key'];
        if ($rowK['service_name'] === 'Gemini') $geminiKey = $rowK['api_key'];
        if ($rowK['service_name'] === 'Firecrawl') $firecrawlKey = $rowK['api_key'];
    }

    // Function to log API usage
    $logUsage = function($service) use ($pdo) {
        $stmtU = $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE service_name = ?");
        $stmtU->execute([$service]);
    };

    // Phase 1: Web Search (Tavily with Firecrawl fallback)
    $query = "$companyName battery recycling EPR targets EV business deals executives management team LinkedIn email";
    $context = "";
    
    // Attempt Tavily First
    $tavilySuccess = false;
    if (!empty($tavilyKey)) {
        $ch = curl_init('https://api.tavily.com/search');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'api_key' => $tavilyKey,
            'query' => $query,
            'search_depth' => 'advanced',
            'max_results' => 5 // Reduced to save tokens
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $tRes = curl_exec($ch);
        curl_close($ch);
        
        if ($tRes !== false) {
            $tData = json_decode($tRes, true);
            if (!isset($tData['error']) && !empty($tData['results'])) {
                $tavilySuccess = true;
                $logUsage('Tavily');
                foreach ($tData['results'] as $res) {
                    $context .= "Source URL: " . $res['url'] . "\nContent: " . $res['content'] . "\n\n";
                }
            }
        }
    }

    // Fallback to Firecrawl if Tavily failed
    if (!$tavilySuccess && !empty($firecrawlKey)) {
        $chF = curl_init('https://api.firecrawl.dev/v1/search');
        curl_setopt($chF, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chF, CURLOPT_POST, true);
        curl_setopt($chF, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $firecrawlKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($chF, CURLOPT_POSTFIELDS, json_encode([
            'query' => $query,
            'limit' => 5
        ]));
        curl_setopt($chF, CURLOPT_TIMEOUT, 40);
        $fRes = curl_exec($chF);
        curl_close($chF);
        
        if ($fRes !== false) {
            $fData = json_decode($fRes, true);
            if (!empty($fData['data'])) {
                $logUsage('Firecrawl');
                foreach ($fData['data'] as $res) {
                    $context .= "Source URL: " . ($res['url'] ?? '') . "\nContent: " . ($res['description'] ?? '') . "\n\n";
                }
            }
        }
    }

    if (empty($context)) {
        $context = "No significant search results found for $companyName.";
    }

    // Truncate context to prevent LLM token limit errors (max 15000 chars roughly 3500 tokens)
    // Attempt Groq First (With 1 Retry for Rate Limits)
    $groqSuccess = false;
    $resultJsonStr = '{}';
    $groqError = '';
    
    // Reduce context even further to prevent TPM limits (max 10000 chars roughly 2500 tokens)
    $context = substr($context, 0, 10000);

    // Fetch materials context
    $matStmt = $pdo->prepare("SELECT m.name, cm.target_tons FROM company_materials cm JOIN materials m ON cm.material_id = m.id WHERE cm.company_id = ? AND m.is_active = 1 AND cm.target_tons > 0");
    $matStmt->execute([$companyId]);
    $companyMats = $matStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $materialsContext = "";
    $mathContext = "";
    
    if (count($companyMats) > 0) {
        $materialsContext = "Their official EPR targets are:\n";
        foreach ($companyMats as $mat) {
            $materialsContext .= "- " . $mat['name'] . ": " . number_format($mat['target_tons'], 2) . " Tons\n";
            $targetTonsCalc = is_numeric($mat['target_tons']) ? $mat['target_tons'] : 0;
            if ($mat['name'] === 'Lithium' && $targetTonsCalc > 0) {
                $mathContext .= "For Lithium ($targetTonsCalc Tons): if MiniMines secures 100% of this feed, we can generate up to ".($targetTonsCalc * 0.148)." Tons of EPR Certificates. We can recover approx ".($targetTonsCalc * 0.1)." Tons of High-Purity Nickel & Cobalt via HHM™ process, and refine approx ".($targetTonsCalc * 0.08)." Tons of Lithium Carbonate equivalent.\n";
            }
        }
    } else {
        $materialsContext = "Their official EPR targets are currently unknown or 0.\n";
    }
    
    // Phase 2: LLM Processing
    $prompt = "You are a highly analytical Sourcing Agent for 'MiniMines', a battery recycling company using a patented Hybrid Hydrometallurgy (HHM™) process. Analyze the web search context about '$companyName'.
$materialsContext
$mathContext

Context:
$context

Generate a highly strategic Sourcing Agent Console report.
Rule 1: NEVER hallucinate contact details. If an email or linkedin profile is NOT found in the context, output 'Not Publicly Available'.
Rule 2: ALWAYS provide the 'proof_source_url' for any contact found.
Rule 3: ONLY extract real, full human names for contacts (e.g. 'John Doe'). NEVER extract short codes, symbols, or non-human strings like '\NAS\'. If no valid human name is found, output an empty array for contacts: []

Output a JSON object strictly matching this schema:
{
  \"sourcing_sector\": \"e.g., EV 2W, EV 4W, Consumer Electronics\",
  \"chemistry\": \"e.g., NMC 532, LFP\",
  \"classification\": \"e.g., Low-Hanging Fruit, High-Value Target\",
  \"company_overview\": {
    \"core_business\": \"Brief description of what the company actually manufactures or does (e.g., EV 2-Wheeler Manufacturer, Mobile OEM).\",
    \"key_products\": \"Main products or brands they make.\",
    \"battery_usage\": \"How and where they use batteries in their business context.\",
    \"scale\": \"Their operational scale if known (Regional, National, Global, etc.).\"
  },
  \"strategic_summary\": \"A short paragraph analyzing their EPR liabilities, current partnerships, and urgency.\",
  \"potential\": {
    \"epr_certificates\": \"Calculated tons of certificates generated\",
    \"recovery_metals\": \"Nickel/Cobalt recovery estimates\",
    \"offset_dependency\": \"Reduction in raw material import dependency\"
  },
  \"contacts\": [
    {
      \"name\": \"Name of executive or key person\",
      \"role\": \"Role/Title\",
      \"email\": \"Email if available, else 'Not Publicly Available'\",
      \"linkedin\": \"LinkedIn URL if available, else 'Not Publicly Available'\",
      \"proof_source_url\": \"The exact Source URL from the context where you found this person\"
    }
  ],
  \"recent_news_trends\": [
    {
      \"date\": \"YYYY-MM or Recent\",
      \"headline\": \"Headline of the news or trend\",
      \"summary\": \"1 sentence summary\"
    }
  ]
}";

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        if (empty($groqKey)) break;
        
        $chG = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt($chG, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chG, CURLOPT_POST, true);
        curl_setopt($chG, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $groqKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($chG, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'llama-3.1-8b-instant',
            'messages' => [
                ['role' => 'system', 'content' => 'You output strictly valid JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1
        ]));
        curl_setopt($chG, CURLOPT_TIMEOUT, 60);
        $gRes = curl_exec($chG);
        curl_close($chG);

        if ($gRes !== false) {
            $gData = json_decode($gRes, true);
            if (!isset($gData['error'])) {
                $groqSuccess = true;
                $logUsage('Groq');
                $resultJsonStr = $gData['choices'][0]['message']['content'] ?? '{}';
                break;
            } else {
                $groqError = json_encode($gData['error']);
                // If it's a rate limit error, wait 2.5 seconds and try again
                if (isset($gData['error']['code']) && $gData['error']['code'] === 'rate_limit_exceeded' && $attempt == 1) {
                    sleep(2);
                    continue;
                }
                break; // Break if it's not a rate limit error or we already retried
            }
        } else {
            break; // Curl failed entirely
        }
    }

    $geminiError = '';
    // Fallback to Gemini if Groq failed (Rate limit, too large, etc)
    if (!$groqSuccess && !empty($geminiKey)) {
        $chGem = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $geminiKey);
        curl_setopt($chGem, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chGem, CURLOPT_POST, true);
        curl_setopt($chGem, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($chGem, CURLOPT_POSTFIELDS, json_encode([
            'contents' => [
                ['parts' => [['text' => "You must output strictly valid JSON.\n\n" . $prompt]]]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.1
            ]
        ]));
        curl_setopt($chGem, CURLOPT_TIMEOUT, 60);
        $gemRes = curl_exec($chGem);
        curl_close($chGem);
        
        if ($gemRes !== false) {
            $gemData = json_decode($gemRes, true);
            if (!isset($gemData['error']) && isset($gemData['candidates'][0]['content']['parts'][0]['text'])) {
                $logUsage('Gemini');
                $resultJsonStr = $gemData['candidates'][0]['content']['parts'][0]['text'];
            } else if (isset($gemData['error'])) {
                $geminiError = json_encode($gemData['error']);
            }
        } else {
            $geminiError = "Gemini CURL connection failed.";
        }
        
        if (empty($resultJsonStr) || $resultJsonStr == '{}') {
            return ['error' => "Both APIs failed.\nGroq: $groqError\nGemini: $geminiError (Check if Gemini key is valid)"];
        }
    }

    $finalResult = json_decode($resultJsonStr, true);

    // Hard filter to kill LLM hallucinations like '\NAS\'
    if (isset($finalResult['contacts']) && is_array($finalResult['contacts'])) {
        foreach ($finalResult['contacts'] as $key => $contact) {
            $name = $contact['name'] ?? '';
            // Only filter if the name contains backslashes or curly braces (clear signs of hallucination/code injection)
            if (strpos($name, '\\') !== false || strpos($name, '{') !== false || strpos($name, '[') !== false) {
                unset($finalResult['contacts'][$key]);
            }
        }
        $finalResult['contacts'] = array_values($finalResult['contacts']); // Re-index array
    }
    
    // Re-encode for DB
    $resultJsonStrForDb = json_encode($finalResult);

    // Save to cache if successful
    if ($finalResult && !isset($finalResult['error'])) {
        $stmt = $pdo->prepare("
            INSERT INTO company_research (company_id, research_data) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE research_data = VALUES(research_data)
        ");
        $stmt->execute([$companyId, $resultJsonStrForDb]);
    }

    return $finalResult;
}
?>
