<?php

namespace App\FrontModule\Traits;

use Nette\Application\UI\Form;

trait BaseNewsletterTrait
{
	/** @var \NewsletterEmailsService @inject */
	public $newsletterEmailsService;


	/**
	 *
	 */
	protected function processNewsletter(): void
	{
		// newsletter confirmation
		if ($newsletterConfirmationRequestHash = $this->getParameter('ncr')) {
			if($newsletterEmail = $this->newsletterEmailsService->confirmSubscription(byHash: $newsletterConfirmationRequestHash)){
				$this->redirectUrlWithFlashMessage($this->getContent('newsletter', 'confirmation'), 'success', $this->getPage('home')?->full_url);
			}
			else{
				$this->redirectUrlWithFlashMessage($this->getContent('newsletter', 'invalid_link'), 'danger', $this->getPage('home')?->full_url);
			}
		}

		// newsletter remove
		if ($newsletterRemoveRequestHash = $this->getParameter('nrm')) {
			if($this->newsletterEmailsService->unsubscribe(byHash: $newsletterRemoveRequestHash)){
				$this->redirectUrlWithFlashMessage($this->getContent('newsletter', 'remove'), 'success', $this->getPage('home')?->full_url);
			}
			else{
				$this->redirectUrlWithFlashMessage($this->getContent('newsletter', 'invalid_link'), 'danger', $this->getPage('home')?->full_url);
			}
		}
	}


    /**
     * @return Form
     * @throws \Throwable
     */
	protected function createComponentNewsletterForm(): Form
	{
		$form = new Form();

		$form->addEmail('email', $this->getContent('newsletter', 'placeholder'))->setRequired($this->getContent('newsletter', 'fill_email'));
		$form->addSubmit('submit', $this->getContent('newsletter', 'button'));

		$form->onSuccess[] = [$this, 'processNewsletterForm'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @throws \Nette\Application\AbortException
	 */
	public function processNewsletterForm(Form $form): void
	{
		$values = $form->getValues();

		$state = $this->newsletterEmailsService->subscribe($values->email, $this->language->id);
		if($state == \NewsletterEmailsEntity::VALIDATION_STATE_SUCCESS){
			$form->reset();
			$this->redrawControlsWithFlashMessage($this->getContent('newsletter', 'success'), 'success', ['footerWrapper', 'newsletterForm']);
		}
		elseif($state == \NewsletterEmailsEntity::VALIDATION_STATE_ERROR_EXISTS){
			$this->redrawControlsWithFlashMessage($this->getContent('newsletter', 'email_exists'), 'danger', ['footerWrapper', 'newsletterForm']);
		}
		else{
			$this->redrawControlsWithFlashMessage($this->getContent('newsletter', 'error'), 'danger', ['footerWrapper', 'newsletterForm']);
		}
	}
}
