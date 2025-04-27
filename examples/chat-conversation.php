<?php declare(strict_types=1);

/**
 * Example demonstrating more advanced Chat API usage:
 * - Managing conversation history using addMessage().
 * - Setting system instructions.
 * - Using model options (simulating a token limit).
 * - Continuing a conversation after hitting a token limit.
 */

require __DIR__ . '/bootstrap.php';

use AIAccess\Chat\FinishReason;
use AIAccess\Chat\Role;
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


// --- 2. START AND MANAGE A CONVERSATION ---

// Get a recommended default model name suitable for the chosen client
$model = getDefaultChatModel($client);
echo "Using Model: $model\n\n";

// Create a chat session instance
$chat = $client->createChat($model);

// Set an overall instruction for the AI's behavior
$chat->setSystemInstruction('You are a helpful assistant. Be friendly and informative.');
echo "System Instruction Set.\n\n";


try {
	// --- Turn 1: Initial Question ---
	$message1 = 'What is the capital of France?';
	echo "User > $message1\n";
	$chat->addMessage($message1, Role::User); // Add user message to history BEFORE sending

	// Send the current history (no message passed to sendMessage)
	$response1 = $chat->sendMessage();
	$text1 = $response1->getText() ?? '(No text received)';
	echo 'Model < ' . trim($text1) . "\n";
	// Note: sendMessage() automatically adds the model's response (text1) to the history now.


	// --- Turn 2: Follow-up Question & Simulate Token Limit ---
	$message2 = 'Tell me about its history in detail, starting from Roman times.';
	echo "\nUser > $message2\n";
	$chat->addMessage($message2, Role::User);

	// ** Force a token limit to demonstrate continuation **
	$limitTokens = 50; // Set a very low limit for demonstration
	echo "Setting a low token limit ($limitTokens) to simulate interruption...\n";
	$optionKey = match (true) {
		$client instanceof Provider\Claude\Client => 'maxTokens',
		$client instanceof Provider\Grok\Client => 'maxOutputTokens',
		default => 'maxOutputTokens',
	};
	$chat->setOptions(...[$optionKey => $limitTokens]);


	$response2 = $chat->sendMessage(); // Send history including message2
	$text2 = $response2->getText(); // Might be null or partial text
	$finishReason2 = $response2->getFinishReason();

	if ($text2 !== null) {
		echo 'Model < (Partial) ' . trim($text2) . "...\n";
	} else {
		echo "Model < (No text received, likely stopped early)\n";
	}
	echo 'Finish Reason: ' . ($finishReason2?->name ?? 'Unknown') . "\n";


	// --- Turn 3: Continue if Token Limit Hit ---
	if ($finishReason2 === FinishReason::TokenLimit) {
		echo "\nToken limit reached. Attempting to continue...\n";

		$chat->setOptions(...[$optionKey => null]);

		// Ask the model to continue (more reliable than just sending empty message)
		$message3 = 'Please continue where you left off.';
		echo "User > $message3\n";
		// Let sendMessage handle adding the user prompt this time
		$response3 = $chat->sendMessage($message3);
		$text3 = $response3->getText() ?? '(No continuation text received)';
		$finishReason3 = $response3->getFinishReason();

		echo 'Model < (Continued) ' . trim($text3) . "\n";
		echo 'Final Finish Reason: ' . ($finishReason3?->name ?? 'Unknown') . "\n";

		// Display final usage after continuation
		$usage3 = $response3->getUsage();
		if ($usage3) {
			echo sprintf(
				"Final Token Usage (Input/Output/Reasoning): %s / %s / %s\n",
				$usage3->inputTokens ?? '?',
				$usage3->outputTokens ?? '?',
				$usage3->reasoningTokens ?? '?',
			);
		}

	} else {
		echo "\nConversation finished without hitting token limit.\n";
	}


	// --- Display Final Conversation History ---
	echo "\n--- Full Conversation History ---\n";
	foreach ($chat->getMessages() as $message) {
		$roleName = $message->getRole()->name;
		echo "[$roleName] " . $message->getText() . "\n";
	}
	echo "-------------------------------\n";


} catch (ServiceException $e) {
	echo "\nError: [" . $e::class . '] ' . $e->getMessage() . "\n";
	if ($e->getPrevious()) {
		echo 'Previous Error: ' . $e->getPrevious()->getMessage() . "\n";
	}
}
