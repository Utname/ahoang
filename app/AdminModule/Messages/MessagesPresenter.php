<?php

namespace App\AdminModule\Presenters;

use BlacklistEmailsRepository;
use BlacklistEmailsService;
use Nette\Application\UI\Form;

class MessagesPresenter extends BaseModulePresenter
{
	/** @var \MailingQueueRepository @inject */
	public $repository;

	/** @var \EmailTemplatesRepository @inject */
	public $emailTemplatesRepo;

	/** @var \MailingService @inject */
	public $mailingService;

	/** @var string */
	protected $orderBy = 'created_at DESC, id DESC';

	/** @var string[]  */
	protected $moduleSortingOptions = [
		'date__desc' => 'created_at DESC, id DESC',
		'date' => 'created_at, id',
		'subject' => 'subject',
		'subject__desc' => 'subject DESC',
		'email_from' => 'email_from',
		'email_from__desc' => 'email_from DESC',
		'email_to' => 'email_to',
		'email_to__desc' => 'email_to DESC',
	];

	/** @var \MailingQueueEntity */
	protected $mailingQueueDetail;


	/**
	 *
	 */
	public function actionDefault()
	{

	    $printBody = $this->getParameter('printBody');

		if ($printBody && $item = $this->repository->findEntity($printBody)) {
			echo $item->body;
			exit;
		}

		$this['filters-form']->addText('subject', 'admin.terms.subject', 50, 255);
		$this['filters-form']->addText('email_from', 'admin.terms.sender', 50, 255);
		$this['filters-form']->addText('email_to', 'admin.terms.recipient', 50, 255);

		$this['filters']->setCustomFilters(function($selection, $filters) {
			$selection->where([
				'email_templates_id' => \EmailTemplatesEntity::CONTACT_FORM_ID,
				'email_to NOT LIKE ?' => '%@fikkomarket.cz'
			]);
		});

		parent::actionDefault();
	}


	/**
	 *
	 */
	public function beforeRender()
	{
		parent::beforeRender();

		$this->template->mailingQueueDetail = $this->mailingQueueDetail;
	}


	/**
	 * @param int $itemsId
	 */
	public function handleMailingQueueDetail(int $itemsId): void
	{
		$this->mailingQueueDetail = $this->repository->findEntity($itemsId);

		$this->redrawControl('modalWrapper');
		$this->redrawControl('mailingQueueDetail');
	}


	/**
	 * @return Form
	 */
	protected function createComponentMailingQueueReplyForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addHidden('id_item');
		$form->addText('email', 'admin.terms.email', 50, 255)
			->setRequired('required')
			->addRule(Form::EMAIL, 'invalid_email_address_format');
		$form->addText('email_reply', 'admin.terms.addressee', 50, 255)
			->addCondition(Form::FILLED)
			->addRule(Form::EMAIL, 'invalid_email_address_format');
		$form->addText('subject', 'admin.terms.subject', 50, 255)
			->setRequired('required');
		$form->addTextArea('text', 'admin.terms.text')
			->setRequired('required');

		$form->addSubmit('submit', 'save');

		$form->onSuccess[] = [$this, 'mailingQueueReplyFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 */
	public function mailingQueueReplyFormSubmitted(Form $form)
	{
		$values = $form->getValues();

		if ($values->id_item && $item = $this->repository->findEntity($values->id_item)) {
			if ($this->mailingService->send($values->subject, $values->text, [$values->email_reply], $values->email, [], TRUE)) {
				$item->is_reply = TRUE;
				$this->repository->update($item);
				$this->flashMessage($this->translator->translate('admin.messages.successMailingQueue'), 'success');
			}
		}

		$this->redirect('this');
	}


	public function handleMailingQueueReply(int $itemsId)
	{
		if ($itemsId && $item = $this->repository->findEntity($itemsId)) {
			$emailTemplate = $this->emailTemplatesRepo->findEntity(\EmailTemplatesEntity::CONTACT_FORM_ID);
			$this['mailingQueueReplyForm']->setDefaults([
				'id_item' => $item->id,
				'email' => $emailTemplate ? $emailTemplate->email_from : '',
				'email_reply' => $item->email_to,
				'subject' => 'Re: '.$item->subject,
				'text' => preg_replace('/<td(.*?)>/', '<td $1><p></p><p><strong>Původní zpráva:</strong></p>', $item->body, 1)
			]);
		}

		$this->redrawControl('modalWrapper');
		$this->redrawControl('mailing_queue_reply');
	}
}
