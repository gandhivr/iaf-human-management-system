require_once 'config.php'; // or wherever OPENAI_API_KEY is defined
include 'openai_api.php';  // assuming your function above is saved here

$prompt = "Explain the importance of training management in the Air Force.";
$response = openai_call($prompt);

if ($response) {
    echo nl2br(htmlspecialchars($response));
} else {
    echo "Failed to get response from OpenAI.";
}
