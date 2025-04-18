<?php

/**
 * This file is part of the AI Access library.
 * Copyright (c) 2024 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace AIAccess\OpenAI;

use AIAccess;
use AIAccess\BatchStatus;
use AIAccess\Message;


/**
 * Represents the state and eventual result of an OpenAI Batch API job.
 */
final class BatchResponse implements AIAccess\BatchResponse
{
	/** @var Message[]|null */
	private ?array $outputMessagesCache = null;
	private bool $outputRetrievalAttempted = false;


	public function __construct(
		private Client $client,
		private array $batchData,
	) {
	}


	public function getStatus(): BatchStatus
	{
		return match ($this->batchData['status'] ?? null) {
			'validating', 'in_progress', 'finalizing' => BatchStatus::InProgress,
			'completed' => BatchStatus::Completed,
			'cancelling', 'failed', 'expired', 'cancelled' => BatchStatus::Failed,
			default => BatchStatus::Other,
		};
	}


	/**
	 * Gets the output messages generated by the completed batch job.
	 * Keys in the returned array correspond to custom_id values from requests.
	 * @return Message[]|null
	 */
	public function getOutputMessages(): ?array
	{
		if ($this->outputMessagesCache !== null
			|| $this->outputRetrievalAttempted
			|| $this->getStatus() !== BatchStatus::Completed
			|| empty($this->batchData['output_file_id'])
		) {
			return $this->outputMessagesCache;
		}

		try {
			$content = $this->client->sendRequest('files/' . $this->batchData['output_file_id'] . '/content', null, 'GET');
			return $this->outputMessagesCache = $this->parseRawResponse($content);

		} catch (AIAccess\Exception | \JsonException $e) {
			$this->outputRetrievalAttempted = true;
			trigger_error('Failed to retrieve or parse batch output: ' . $e->getMessage(), E_USER_WARNING);
			return null;
		}
	}


	private function parseRawResponse(mixed $jsonl): ?array
	{
		$outputMessages = [];
		$lines = explode("\n", trim($jsonl));

		foreach ($lines as $line) {
			if (empty(trim($line))) {
				continue;
			}

			$lineData = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
			$customId = $lineData['custom_id'] ?? null;

			if ($customId === null) {
				continue;
			}

			if (isset($lineData['response']) && $lineData['response']['status_code'] === 200) {
				$responseBody = $lineData['response']['body'] ?? null;
				if ($responseBody) {
					$content = '';
					foreach ($responseBody['output'] as $item) {
						if (($item['type'] ?? null) === 'message' && is_array($item['content'] ?? null)) {
							foreach ($item['content'] as $contentBlock) {
								if (($contentBlock['type'] ?? null) === 'output_text') {
									$content .= $contentBlock['text'] ?? '';
								}
							}
						}
					}

					if ($content !== '') {
						$outputMessages[$customId] = new Message(trim($content), AIAccess\Role::Model);
					}
				}
			} elseif (isset($lineData['error'])) {
				$errorMsg = "Error in request '{$customId}'";
				if (isset($lineData['error']['message'])) {
					$errorMsg .= ': ' . $lineData['error']['message'];
				}
				trigger_error($errorMsg, E_USER_WARNING);
			}
		}

		return $outputMessages;
	}


	public function getError(): ?string
	{
		// Check for batch-level errors
		if (isset($this->batchData['errors']) && !empty($this->batchData['errors']['data'])) {
			$errorMessages = [];
			foreach ($this->batchData['errors']['data'] as $error) {
				if (isset($error['message'])) {
					$errorMessages[] = $error['message'];
				}
			}
			if (!empty($errorMessages)) {
				return 'Batch errors: ' . implode(', ', $errorMessages);
			}
		}

		// Check for batch-level errors based on request counts
		if (isset($this->batchData['request_counts'])) {
			$counts = $this->batchData['request_counts'];
			$errorInfo = [];

			if (isset($counts['failed']) && $counts['failed'] > 0) {
				$errorInfo[] = "{$counts['failed']} requests failed";
			}

			if (!empty($errorInfo)) {
				return 'Batch encountered issues: ' . implode(', ', $errorInfo);
			}
		}

		return null;
	}


	public function getCreatedAt(): ?\DateTimeImmutable
	{
		if (isset($this->batchData['created_at'])) {
			try {
				return new \DateTimeImmutable('@' . $this->batchData['created_at']);
			} catch (\Throwable) {
			}
		}
		return null;
	}


	public function getCompletedAt(): ?\DateTimeImmutable
	{
		foreach (['completed_at', 'failed_at', 'expired_at', 'cancelled_at'] as $field) {
			if (isset($this->batchData[$field])) {
				try {
					return new \DateTimeImmutable('@' . $this->batchData[$field]);
				} catch (\Throwable) {
				}
			}
		}
		return null;
	}


	public function getRawResult(): mixed
	{
		return $this->batchData;
	}


	/**
	 * Gets the unique identifier of the batch job.
	 */
	public function getId(): string
	{
		return $this->batchData['id'];
	}
}
