<?php declare(strict_types=1);

/**
 * Example demonstrating the most basic usage of the Chat API:
 * - Create a client for a specific provider.
 * - Create a chat instance.
 * - Send a single message.
 * - Display the response text and basic metadata.
 */

require __DIR__ . '/bootstrap.php';

use AIAccess\Provider;
use AIAccess\ServiceException;


// --- 1. CREATE THE CLIENT ---

// Choose the AI provider you want to use. Available options: 'openai', 'claude', 'gemini', 'deepseek', 'grok'
$provider = 'openai'; // <--- CHANGE THIS TO YOUR DESIRED PROVIDER

// Reads API key from the corresponding {$provider}.key.txt file in the same directory
$apiKey = loadApiKey(__DIR__ . "/{$provider}.key.txt");

// Instantiate the client based on the chosen provider
$client = match ($provider) {
	'openai' => new Provider\OpenAI\Client($apiKey),
	'claude' => new Provider\Claude\Client($apiKey),
	'gemini' => new Provider\Gemini\Client($apiKey),
	'deepseek' => new Provider\DeepSeek\Client($apiKey),
	'grok' => new Provider\Grok\Client($apiKey),
	default => throw new LogicException("Invalid provider selected: '$provider'"),
};

echo "Selected Provider: $provider\n";


// --- 2. PERFORM A BASIC CHAT ---

// Get a recommended default model name suitable for the chosen client
$model = getDefaultChatModel($client);
echo "Using Model: $model\n\n";

// Create a chat session instance
$chat = $client->createChat($model);

// Define the user's message
$userMessage = 'Write a short, three-sentence poem about the PHP language.';
echo "User > $userMessage\n\n";

try {
	// Send the message to the AI and get the response object
	// sendMessage() automatically adds the user message and the model's response to the chat history.
	$response = $chat->sendMessage($userMessage);

	// Display response & details
	$responseText = $response->getText();
	echo "Model:\n" . $responseText . "\n";

	$finishReason = $response->getFinishReason();
	echo 'Finish Reason: ' . ($finishReason?->name ?? 'Unknown') . ' (raw: ' . $response->getRawFinishReason() . ")\n";

	$usage = $response->getUsage();
	if ($usage) {
		echo 'Token Usage (Input/Output/Reasoning): '
			. ($usage->inputTokens ?? '?') . ' / '
			. ($usage->outputTokens ?? '?') . ' / '
			. ($usage->reasoningTokens ?? '?') . "\n";
	}

} catch (ServiceException $e) {
	echo "\nError: [" . $e::class . '] ' . $e->getMessage() . "\n";
	if ($e->getPrevious()) {
		echo 'Previous Error: ' . $e->getPrevious()->getMessage() . "\n";
	}
}
