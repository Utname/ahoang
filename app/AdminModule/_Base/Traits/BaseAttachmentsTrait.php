<?php

namespace App\AdminModule\Traits;

use App\AdminModule\Presenters\AttachmentControl;

trait BaseAttachmentsTrait
{

	/**
	 * @return AttachmentControl
	 */
	protected function createComponentAttachment(): AttachmentControl
	{
		$control = new AttachmentControl($this->translator, $this->imagesService, $this->filesDataService, $this->repository);
		$control->setAction($this->getAction());
		return $control;
	}
}
