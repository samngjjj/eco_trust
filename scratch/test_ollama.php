<?php
$ollamaUrl = "http://localhost:11434/api/tags";

echo "Checking Ollama models list at $ollamaUrl...\n";

$ch = curl_init($ollamaUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    die("Error calling Ollama tags API: $error\n");
}

echo "HTTP Code: $httpCode\n";
$data = json_decode($response, true);
if (isset($data['models'])) {
    echo "Available Models:\n";
    foreach ($data['models'] as $model) {
        echo " - " . $model['name'] . " (Size: " . round($model['size'] / (1024*1024*1024), 2) . " GB)\n";
    }
} else {
    echo "Response does not contain models. Full response:\n";
    echo $response . "\n";
}

// Test a simple chat prompt
echo "\nTesting simple chat with qwen2.5:7b...\n";
$chatUrl = "http://localhost:11434/api/chat";
$payload = [
    "model" => "qwen2.5:7b",
    "messages" => [
        ["role" => "user", "content" => "你好，請用繁體中文自我介紹。"]
    ],
    "stream" => false
];

$ch = curl_init($chatUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo "Chat prompt test failed: $error\n";
} else {
    echo "HTTP Code: $httpCode\n";
    $data = json_decode($response, true);
    echo "Reply:\n" . ($data['message']['content'] ?? 'No content') . "\n";
}
?>
