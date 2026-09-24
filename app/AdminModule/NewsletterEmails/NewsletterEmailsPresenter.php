<?php

namespace App\AdminModule\Presenters;

use BaseEntity;
use Ecomail\EcomailConnector;
use Nette\Application\UI\Form;
use Nette\Utils\Random;
use Nette\Utils\Strings;
use Nette\Utils\Validators;

class NewsletterEmailsPresenter extends BaseModulePresenter
{
	/** @var \NewsletterEmailsRepository @inject */
	public $repository;

	/** @var \NewsletterEmailsService @inject */
	public $service;

	/** @var \BlacklistEmailsService @inject */
	public $blacklistEmailsService;

	/** @var string[]  */
	protected $moduleSortingOptions = [
		'email' => 'email',
		'email__desc' => 'email DESC',
		'created_at' => 'created_at',
		'created_at__desc' => 'created_at DESC',
		'confirmed_at' => 'confirmed_at',
		'confirmed_at__desc' => 'confirmed_at DESC',
	];

	/** @var \LanguagesRepository @inject */
	public $languagesRepo;

	/** @var array */
	protected $languagesPairs = [];

	/** @var \EmailTemplatesRepository @inject */
	public $emailTemplatesRepo;

	/** @var array */
	protected $emailTemplatesPairs = [];

	/** @var array|null */
	protected $bulkActionEmailTemplateByLanguages = NULL;

	/** @var \MailingService @inject */
	public $mailingService;

	/** @var EcomailConnector */
	public $ecomailConnector;

	/** @var array */
	protected $moduleBulkActions = [
		BulkActionsControl::ACTION_SUBSCRIBE_TO_ECOMAIL,
		BulkActionsControl::ACTION_SEND_NEWSLETTER,
		BulkActionsControl::ACTION_DELETE,
	];

	/** @var SettingsControl @inject */
	public $settingsControl;

	/**de
	 * @throws \ReflectionException
	 */
	protected function startup()
	{
		parent::startup();

		$this->languagesPairs = $this->languagesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
		$this->emailTemplatesPairs = $this->emailTemplatesRepo->findAll()->order('name')->fetchPairs('id', 'name');

		$this->template->blacklistEmails = $this->blacklistEmailsService->getBlacklistedEmails();

		$this->ecomailConnector = new EcomailConnector($this->settings->ecomail_api_key ?? NULL, $this->settings->ecomail_list_id ?? NULL);
	}


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('email', 'admin.terms.email');
		$this['filters-form']->addSelect('lang_id', 'admin.terms.language', $this->languagesPairs)->setPrompt('-');

		$this->getBulkActionsControl()
			->setBulkActionPermissionId(BulkActionsControl::ACTION_SEND_NEWSLETTER, 'edit')
			->setBulkActionData(BulkActionsControl::ACTION_SEND_NEWSLETTER, $this->emailTemplatesPairs)
			->addBulkActionSubmit(BulkActionsControl::ACTION_SEND_NEWSLETTER, BulkActionsControl::SUBMIT_STATE_SET)
			->setBulkActionCallback(BulkActionsControl::ACTION_SEND_NEWSLETTER, function(BaseEntity $item, mixed $value, int $stateId) {
				if($value){
					//load email template
					if(is_null($this->bulkActionEmailTemplateByLanguages)){
						$currentLang = $GLOBALS['lang'];
						/** @var \LanguagesEntity $language */
						foreach($this->languages as $language){
							$GLOBALS['lang'] = $language->id;
							$this->bulkActionEmailTemplateByLanguages[$language->id] = $this->emailTemplatesRepo->findEntity($value);
						}
						$GLOBALS['lang'] = $currentLang;
					}
					if(!is_null($item->confirmed_at) && Validators::isEmail($item->email) && isset($this->bulkActionEmailTemplateByLanguages[$item->lang_id])){
						$this->mailingService
							->sendTemplate(emailTemplatesEntity: $this->bulkActionEmailTemplateByLanguages[$item->lang_id], vars: [], recipients: [$item->email], sendImmediately: FALSE);
					}
				}

			});


		if($this->ecomailConnector->isActive()){
			$this->getBulkActionsControl()
				->setBulkActionPermissionId(BulkActionsControl::ACTION_SUBSCRIBE_TO_ECOMAIL, 'edit')
				->addBulkActionSubmit(BulkActionsControl::ACTION_SUBSCRIBE_TO_ECOMAIL, BulkActionsControl::SUBMIT_STATE_SET)
				->setBulkActionCallback(BulkActionsControl::ACTION_SUBSCRIBE_TO_ECOMAIL, function(BaseEntity $item) {
					$this->ecomailConnector->subscribe($item->email, $item->hash);
				});
		}




		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addEmail('email', 'admin.terms.email')->setRequired(TRUE);
		$form->addSelect('lang_id', $this->trans('admin.terms.language'), $this->languagesPairs)->setTranslator(NULL);

		return parent::setFormFields($form);
	}


	/**
	 * @param BaseEntity $entity
	 * @param Form $form
	 */
	protected function afterEntitySave(BaseEntity $entity, Form $form)
	{
		parent::afterEntitySave($entity, $form);

		if (!$entity->hash) {
			$entity->hash = Random::generate(32);
			$this->repository->update($entity);
		}
	}


	/**
	 * @param BaseEntity $entity
	 * @param Form $form
	 * @return void
	 */
	public function afterEntityCreate(BaseEntity $entity, Form $form)
	{
		parent::afterEntityCreate($entity, $form);
		$this->service->sendConfirmationRequest($entity);
	}


	/**
	 * @param BaseEntity $copyFromEntity
	 * @param BaseEntity $entity
	 * @param Form $form
	 * @return void
	 */
	public function afterEntityCopy(BaseEntity $copyFromEntity, BaseEntity $entity, Form $form)
	{
		parent::afterEntityCopy($copyFromEntity, $entity, $form);
		$this->service->sendConfirmationRequest($entity);
	}


	/**
	 * @param int $itemsId
	 * @return void
	 * @throws \Nette\Application\AbortException
	 */
	public function handleSendConfirmationRequest(int $itemsId): void
	{
		/** @var \NewsletterEmailsEntity $newsletterEmail */
		if (!$newsletterEmail = $this->repository->findEntity($itemsId)) {
			$this->flashMessage($this->trans('admin.messages.error'), 'error');
			$this->redirect('this');
		}

		$this->service->sendConfirmationRequest($newsletterEmail);

		$this->flashMessage($this->trans('admin.messages.success'), 'success');
		$this->redirect('this');
	}


	/**
	 * @param string $email
	 * @throws \Nette\Application\AbortException
	 */
	public function handleAddBlacklistEmail(string $email): void
	{
		$this->blacklistEmailsService->addEmail($email);

		$this->flashMessage($this->trans('admin.messages.successSave'), 'success');
		$this->redirect('this');
	}


	/**
	 * @param string $email
	 * @throws \Nette\Application\AbortException
	 */
	public function handleRemoveBlacklistEmail(string $email): void
	{
		$this->blacklistEmailsService->removeEmail($email);

		$this->flashMessage($this->trans('admin.messages.successDelete'), 'success');
		$this->redirect('this');
	}


	/**
	 * @param $id
	 * @return void
	 * @throws \Nette\Application\AbortException
	 */
	public function actionDelete($id)
	{
		if($this->ecomailConnector->isActive() && $item = $this->repository->find($id)){
			$this->ecomailConnector->unsubscribe($item->email);
		}
		parent::actionDelete($id);

	}

	/**
	 * @param $email
	 * @return bool
	 */
	public function isEcomailSubscribed($email): bool
	{
		return $this->ecomailConnector->isActive() && $this->ecomailConnector->isSubscribed($email);
	}

	/**
	 * @return SettingsControl
	 */
	public function createComponentSettings(): SettingsControl
	{
		return $this->settingsControl;
	}
}
