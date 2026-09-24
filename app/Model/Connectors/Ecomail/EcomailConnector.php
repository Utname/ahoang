<?php

namespace Ecomail;

class EcomailConnector
{
	use \Nette\SmartObject;


	/**
	 * @param string|null $apiKey
	 * @param string|null $listId
	 */
	public function __construct(protected ?string $apiKey = NULL, protected ?string $listId = NULL)
	{
	}


	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->apiKey && $this->listId;
	}

	/**
	 * @param string $email
	 * @return bool
	 */
	public function isSubscribed(string $email): bool
	{
		$ecomail = new \Ecomail($this->apiKey);
		$result = $ecomail->getSubscriber($this->listId, $email);

		if (!is_array($result)) {
			return FALSE;
		}

		if (!isset($result['subscriber']['status'])) {
			return FALSE;
		}

		return $result['subscriber']['status'] === 1;
	}


	/**
	 * @param string $email
	 * @param string $hash
	 * @return bool
	 */
	public function subscribe(string $email, string $hash): bool
	{
		$ecomail = new \Ecomail($this->apiKey);

		$result = $ecomail->addSubscriber($this->listId, [
			'subscriber_data' => [
				'email' => $email,
				'custom_fields' => [
					'shop_hash' => $hash
				],
			],
			'trigger_autoresponders' => TRUE,
			'skip_confirmation' => TRUE,
			'resubscribe' => TRUE
		]);

		if (isset($result['error']) && $result['error']) {
			return FALSE;
		}

		return TRUE;
	}


	/**
	 * @param string $email
	 * @return bool
	 */
	public function unsubscribe(string $email): bool
	{
		$ecomail = new \Ecomail($this->apiKey);

		$result = $ecomail->removeSubscriber($this->listId, [
			'email' => $email
		]);

		if (isset($result['error']) && $result['error']) {
			return FALSE;
		}

		return TRUE;
	}
}
