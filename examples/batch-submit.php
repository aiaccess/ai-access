<?php declare(strict_types=1);

/**
 * Example demonstrating Part 1 of Batch Processing: SUBMISSION.
 * - Creates a batch container.
 * - Adds multiple independent chat requests.
 * - Submits the batch for asynchronous processing by the provider.
 * - Outputs the Batch ID needed for later retrieval.
 *
 * NOTE: Only OpenAI and Claude clients currently support the Batch API via this library.
 * Run batch-retrieve.php later with the generated Batch ID to get results.
 */

require __DIR__ . '/bootstrap.php';

use AIAccess\Chat\Role;
use AIAccess\Provider;
use AIAccess\ServiceException;


// --- 1. CREATE THE CLIENT ---

// Choose the AI provider you want to use. Available options: 'openai', 'claude'
$provider = 'openai'; // <--- CHANGE THIS TO YOUR DESIRED PROVIDER

// Reads API key from the corresponding {$provider}.key.txt file in the same directory
$apiKey = loadApiKey(__DIR__ . "/{$provider}.key.txt");

// Instantiate the client based on the chosen provider
$client = match ($provider) {
	'openai' => new Provider\OpenAI\Client($apiKey),
	'claude' => new Provider\Claude\Client($apiKey),
	default => throw new LogicException("Provider '$provider' does not support Batch API via this library."),
};

echo "Selected Provider: $provider\n";


// --- 2. PREPARE THE BATCH JOB ---

// Get a recommended default model name suitable for the chosen client
$model = getDefaultChatModel($client);
echo "Using Model: $model\n\n";

// Create a chat session instance
$chat = $client->createChat($model);

echo "Preparing batch job...\n";

try {
	// Create a new batch container associated with the client
	$batch = $client->createBatch();

	// Add Request 1: Simple Greeting
	$customId1 = 'greeting-request-' . uniqid();
	$chat1 = $batch->addChat($model, $customId1);
	$chat1->setSystemInstruction('Be extremely brief.');
	$chat1->addMessage('Hello!', Role::User);
	echo "- Added chat request with custom ID: $customId1\n";

	// Add Request 2: Translation
	$customId2 = 'translate-request-' . uniqid();
	$chat2 = $batch->addChat($model, $customId2);
	$chat2->setSystemInstruction('Translate the following text to Spanish.');
	$chat2->addMessage('The weather is nice today.', Role::User);
	// Optionally set specific options for this request
	// setMaxTokensOption($chat2, $client, 50);
	echo "- Added chat request with custom ID: $customId2\n";

	// Add Request 3: Code Explanation
	$customId3 = 'code-explain-' . uniqid();
	$chat3 = $batch->addChat($model, $customId3);
	$chat3->addMessage('Explain this PHP code in simple terms: `echo "Hello World!";`', Role::User);
	echo "- Added chat request with custom ID: $customId3\n";


	// --- 3. SUBMIT THE BATCH ---

	echo "\nSubmitting batch job to the provider...\n";
	$batchResponse = $batch->submit(); // This returns immediately

	$batchId = $batchResponse->getId();
	$initialStatus = $batchResponse->getStatus();

	echo "\n--- BATCH SUBMITTED SUCCESSFULLY ---\n";
	echo "Provider: $provider\n";
	echo "Batch ID: $batchId\n"; // <-- You need this ID for retrieval
	echo 'Initial Status: ' . ($initialStatus->name) . "\n";

	echo "\nNEXT STEPS:\n";
	echo "1. Copy the Batch ID above ('$batchId').\n";
	echo "2. Wait some time for the provider to process the batch (minutes to hours).\n";
	echo "3. Run the `batch-retrieve.php` script, providing this Batch ID.\n";
	echo "   Example CLI: php examples/batch-retrieve.php '$batchId'\n";
	echo "-------------------------------------\n";

} catch (ServiceException $e) {
	echo "\nError creating or submitting batch job: [" . $e::class . '] ' . $e->getMessage() . "\n";
} catch (LogicException $e) {
	// Catch errors like adding duplicate custom IDs
	echo "\nLogic Error during batch preparation: " . $e->getMessage() . "\n";
}
