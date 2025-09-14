function openai_call($prompt, $model = "gpt-4", $max_tokens = 150) {
    $apiKey = OPENAI_API_KEY;
    $url = "https://api.openai.com/v1/chat/completions";

    $data = [
        "model" => $model,
        "messages" => [
            ["role" => "user", "content" => $prompt]
        ],
        "max_tokens" => $max_tokens,
        "temperature" => 0.7
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    if(curl_errno($ch)){
        curl_close($ch);
        return false; // or handle error
    }
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    } else {
        return null; // or handle error
    }
}
