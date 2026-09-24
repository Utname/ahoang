<?php

namespace App\AdminModule\Presenters;

use BlacklistEmailsRepository;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;

class BlacklistEmailsPresenter extends BaseModulePresenter
{
	/** @var BlacklistEmailsRepository @inject */
	public $repository;


    /** @var string[]  */
    protected $moduleSortingOptions = [
        'email' => 'email',
        'email__desc' => 'email DESC',
        'date_insert' => 'date_insert',
        'date_insert__desc' => 'date_insert DESC',
    ];

	/**
	 *
	 */
	public function actionDefault()
	{

		$this['filters-form']->addText('email', 'admin.terms.email', 50, 255);
        $this['filters-form']->addText('date_insert', 'admin.terms.date', 50, 255);

		parent::actionDefault();
	}


    /**
     * @param Form $form
     * @return Form
     */
    protected function setFormFields(Form $form): Form
    {
        $form->addText('email', 'admin.terms.email')->setRequired(TRUE)->addRule([$this, 'validateUniqueEmail'], $this->trans('admin.terms.emailAlreadyExists'));

        return parent::setFormFields($form);
    }


    public function validateUniqueEmail(TextInput $emailControl)
    {
        $email = $emailControl->getValue();

        if ($this->repository->findEntityBy(['email' => $email])) {
            $this->flashMessage($this->trans('admin.terms.emailAlreadyExists'), 'danger');

            return false;
        } else {
            return true;
        }
    }
}