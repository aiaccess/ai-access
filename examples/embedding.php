<?php declare(strict_types=1);

/**
 * Example demonstrating the Embedding API:
 * - Calculate embeddings (numerical representations) for input texts.
 * - Display parts of the resulting vectors.
 * - Calculate and display cosine similarity between vectors.
 *
 * NOTE: Only OpenAI and Gemini clients currently support the Embedding API via this library.
 */

require __DIR__ . '/bootstrap.php';

use AIAccess\Embedding\Vector;
use AIAccess\Provider;
use AIAccess\ServiceException;


// --- 1. CREATE THE CLIENT ---

// Choose the AI provider you want to use. Available options: 'openai', 'gemini'
$provider = 'openai'; // <--- CHANGE THIS TO YOUR DESIRED PROVIDER

// Reads API key from the corresponding {$provider}.key.txt file in the same directory
$apiKey = loadApiKey(__DIR__ . "/{$provider}.key.txt");

// Instantiate the client based on the chosen provider
$client = match ($provider) {
	'openai' => new Provider\OpenAI\Client($apiKey),
	'gemini' => new Provider\Gemini\Client($apiKey),
	default => throw new LogicException("Provider '$provider' does not support Embedding API via this library."),
};

echo "Selected Provider: $provider\n";


// --- 2. PREPARE INPUT TEXTS ---

$textsToEmbed = [
	'The quick brown fox jumps over the lazy dog.',
	'PHP is a popular general-purpose scripting language.',
	'Paris is the capital of France.',
	'Berlin is the capital of Germany.',
];

echo "Input Texts to Embed:\n";
foreach ($textsToEmbed as $i => $text) {
	echo "- [$i]: \"$text\"\n";
}


// --- 3. CALCULATE EMBEDDINGS ---

echo "\nCalculating embeddings...\n";

try {
	$embeddingModel = getDefaultEmbeddingModel($client);
	echo "Using Embedding Model: $embeddingModel\n\n";

	// Call the calculateEmbeddings method with the model and input array
	// Provider-specific options can be passed as named arguments here if needed
	// e.g., for OpenAI: dimensions: 256
	// e.g., for Gemini: taskType: 'RETRIEVAL_DOCUMENT'
	$results = $client->calculateEmbeddings(
		model: $embeddingModel,
		input: $textsToEmbed,
		// dimensions: 256, // Example OpenAI option (uncomment if needed)
	);

	echo 'Successfully generated ' . count($results) . " embedding vectors.\n";

	// --- 4. PROCESS AND DISPLAY RESULTS ---

	if (!empty($results)) {
		echo "\n--- Embedding Results ---\n";

		foreach ($results as $index => $vector) {
			/** @var Vector $vector */
			$vectorArray = $vector->toArray();
			$dimensions = count($vectorArray);
			$firstFive = implode(', ', array_slice($vectorArray, 0, 5));

			echo "\nVector [$index] (Dimensions: $dimensions):\n";
			echo "  First 5 values: [$firstFive, ...]\n";

			// Calculate cosine similarity with the first vector (index 0)
			if ($index > 0) {
				$similarity = $results[0]->cosineSimilarity($vector);
				echo sprintf("  Cosine Similarity with Vector[0] ('...fox...'): %.4f\n", $similarity);
			}
			// Calculate cosine similarity between Paris and Berlin
			if ($index === 3 && isset($results[2])) { // If this is Berlin (idx 3) and Paris (idx 2) exists
				$similarityParisBerlin = $results[2]->cosineSimilarity($vector);
				echo sprintf("  Cosine Similarity with Vector[2] ('Paris...'): %.4f\n", $similarityParisBerlin);
			}
		}
		echo "\n-------------------------\n";

		// Example of serialization (useful for storage)
		$binaryData = $results[0]->serialize();
		echo "\nSerialized Vector[0] (" . strlen($binaryData) . " bytes): [Binary Data]\n";
	// $deserializedVector = Vector::deserialize($binaryData);
	// echo "Deserialized matches original: " . ($deserializedVector->toArray() === $results[0]->toArray() ? 'Yes' : 'No') . "\n";

	} else {
		echo "No embedding results were returned.\n";
	}

} catch (ServiceException $e) {
	echo "\nError calculating embeddings: [" . $e::class . '] ' . $e->getMessage() . "\n";
} catch (LogicException $e) {
	// Catch errors like empty input array
	echo "\nLogic Error during embedding preparation: " . $e->getMessage() . "\n";
}
