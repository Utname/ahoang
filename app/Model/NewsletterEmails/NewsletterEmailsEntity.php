<?php


class NewsletterEmailsEntity extends BaseEntity
{
	const VALIDATION_STATE_SUCCESS = 1;
	const VALIDATION_STATE_ERROR_EXISTS = 2;
	const VALIDATION_STATE_ERROR_COULD_NOT_INSERT = 3;
	const VALIDATION_STATE_ERROR_COULD_NOT_SEND_CONFRIMATION_MAIL = 4;

	/**
	 * @return LanguagesEntity|null
	 */
	public function getLang(): ?LanguagesEntity
	{
		if (!$this->language && $this->lang_id && $row = $this->getRow()->ref('languages', 'lang_id')) {
			$this->language = LanguagesRepository::getEntity($row);
		}

		return $this->language;
	}
}
