<?php

namespace App\AdminModule\Presenters;

use BlacklistEmailsRepository;
use BlacklistEmailsService;

class MailingQueuePresenter extends BaseModulePresenter
{
	/** @var \MailingQueueRepository @inject */
	public $repository;

    /** @var BlacklistEmailsService @inject */
    public $blacklistEmailsService;

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

        $this->template->blacklistEmails = $this->blacklistEmailsService->getBlacklistedEmails();

		$this['filters-form']->addText('subject', 'admin.terms.subject', 50, 255);
		$this['filters-form']->addText('email_from', 'admin.terms.sender', 50, 255);
		$this['filters-form']->addText('email_to', 'admin.terms.recipient', 50, 255);

		parent::actionDefault();
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
     * @param string $email
     */
    public function handleAddBlacklistEmail(string $email): void
    {
        $this->blacklistEmailsService->addEmail($email);

        $this->flashMessage($this->trans('admin.messages.successSave'), 'success');
        $this->redirect('this');
    }

    /**
     * @param string $email
     */
    public function handleRemoveBlacklistEmail(string $email): void
    {
        $this->blacklistEmailsService->removeEmail($email);

        $this->flashMessage($this->trans('admin.messages.successDelete'), 'success');
        $this->redirect('this');
    }


	/**
	 * @return void
	 * @throws \Nette\Application\AbortException
	 */
    public function handleDeleteAll(): void
    {
		if(!$this->aps->has('delete')){
			return;
		}
		$this->repository->findAll()->delete();

        $this->flashMessage($this->trans('admin.messages.successDelete'), 'success');
        $this->redirect('this');
    }


	/**
	 *
	 */
	public function beforeRender()
	{
		parent::beforeRender();

		$this->template->mailingQueueDetail = $this->mailingQueueDetail;
	}
}
