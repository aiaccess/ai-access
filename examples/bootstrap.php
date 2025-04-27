<?php declare(strict_types=1);

/**
 * Bootstrap file for AI Access examples.
 *
 * Sets up the environment, loads API keys, and provides helper functions
 * to initialize the AI client and get default model names.
 */

use AIAccess\Provider;

// Ensure vendor directory exists
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
	echo "Error: Dependencies not installed. Please run 'composer install' in the root directory.\n";
	exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

// Sets up the environment
error_reporting(E_ALL);
ini_set('display_errors', '1');
if (PHP_SAPI !== 'cli') {
	echo "<pre style='word-wrap: break-word; white-space: pre-wrap;'>\n";
}


/**
 * Reads the API key from a corresponding .key.txt file.
 */
function loadApiKey(string $keyFile): string
{
	$apiKey = @file_get_contents($keyFile);

	if (!$apiKey || !trim($apiKey)) {
		echo "Error reading API key file '$keyFile'\nPlease create this file and place your API key inside it. You can find instructions on obtaining API keys in the readme.md file.";
		exit(1);
	}

	return trim($apiKey);
}


/**
 * Returns a default/recommended chat model name based on the provided client instance.
 * These are examples; you might need to choose different models based on availability or cost.
 */
function getDefaultChatModel(ChatService $client): string
{
	return match (true) {
		$client instanceof Provider\OpenAI\Client => 'gpt-4o-mini', // OpenAI's fast and capable model
		$client instanceof Provider\Claude\Client => 'claude-3-haiku-20240307', // Anthropic's fast and affordable model
		$client instanceof Provider\Gemini\Client => 'gemini-2.5-flash-latest', // Google's fast multimodal model
		$client instanceof Provider\DeepSeek\Client => 'deepseek-chat', // DeepSeek's general chat model
		$client instanceof Provider\Grok\Client => 'grok-3.0-flash', // xAI's fast Grok model
		default => throw new LogicException('Unknown or unsupported client type for default chat model selection: ' . $client::class),
	};
}


/**
 * Returns a default/recommended embedding model name based on the provided client instance.
 */
function getDefaultEmbeddingModel(EmbeddingService $client): string
{
	return match (true) {
		$client instanceof Provider\OpenAI\Client => 'text-embedding-3-small', // OpenAI's efficient embedding model
		$client instanceof Provider\Gemini\Client => 'text-embedding-004', // Google's latest embedding model
		// Claude, DeepSeek, Grok do not have embedding APIs via this library currently
		default => throw new LogicException('Unknown or unsupported client type for default embedding model selection: ' . $client::class),
	};
}
