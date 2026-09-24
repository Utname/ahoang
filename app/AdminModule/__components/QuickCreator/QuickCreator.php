<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\Html;

class QuickCreator extends \Nette\Application\UI\Control
{
	const DEFAULT_FIRST_INPUT_CLASS = 'form-control form-control-bigger';
	const DEFAULT_INPUT_CLASS = 'form-control';
	const DEFAULT_PARENT_CLASS = 'form-group col-lg-12';

	/** @var callable|null */
	protected $customCreateCallback;

	/** @var callable|null */
	protected $onCreateCallback;

	/** @var bool  */
	protected $showFlashes = FALSE;

	/** @var string|null  */
	protected $modalTitle = NULL;

	/** @var string|null  */
	protected $buttonText = NULL;

	/** @var Html */
	protected $customButtonHtml = NULL;

	/** @var array  */
	protected $defaultValues = []; //default form values or values that are not included in form - f.e. is_active default TRUE...

	/** @var array  */
	protected $inputParentHtmlAttributes = [];

	/** @var array  */
	protected $requiredPermissions = [];

	/**
	 * @param \AdminPermissionsService $adminPermissionsService
	 * @param \BaseRepository $repository
	 * @param Translator $translator
	 */
	public function __construct(protected \AdminPermissionsService $adminPermissionsService, protected Translator $translator, protected \BaseRepository $repository)
	{
	}

	/**
	 *
	 */
	public function render()
	{
		if(!$this->hasRequiredPermissions()){
			return;
		}

		$this->template->inputParentHtmlAttributes = $this->inputParentHtmlAttributes;
		$this->template->setFile(__DIR__.'/QuickCreatorModal.latte');
		$this->template->render();
	}


	/**
	 *
	 */
	public function renderButton()
	{
		if(!$this->hasRequiredPermissions()){
			return;
		}

		if($this->getCustomButtonHtml() instanceof Html){
			$button = $this->getCustomButtonHtml();
			$button->setAttribute('class', $button->getAttribute('class').' ajax-modal');
			$button->setAttribute('data-target', '#modal-quickCreator-'.$this->getName());
			$button->setAttribute('href', $this->link('prepareModal!'));
			$this->template->customButtonHtml = $button;
		}

		$this->template->setFile(__DIR__.'/QuickCreatorButton.latte');
		$this->template->render();
	}


	/**
	 * @return bool
	 */
	public function hasRequiredPermissions(): bool
	{
		if(count($this->getRequiredPermissions()) && !$this->adminPermissionsService->hasAny($this->getRequiredPermissions())){
			return FALSE;
		}
		return TRUE;
	}


	/**
	 * @return Form
	 */
	protected function createComponentForm(): Form
	{
		$form = new Form();
		$form->addSubmit('submit');
		$form->onSuccess[] = [$this, 'formSubmitted'];
		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 * @throws \Nette\Application\AbortException
	 */
	public function formSubmitted(Form $form)
	{
		$values = $this->getDefaultValues();
		foreach(BaseModulePresenter::prepareFormValues($form->getValues(TRUE), $form->getHttpData(), 'edit') as $key => $val){
			$values[$key] = $val;
		}

		/** @var BasePresenter $presenter */
		$presenter = $this->getPresenter();

		$entity = NULL;

		if ($this->getCustomCreateCallback()) {
			$callback = $this->getCustomCreateCallback();
			$entity = $callback($values);
		}
		else{
			if ($data = $this->repository->insert($values)) {
				$entity = $this->repository->findEntity($data->id);
				if($this->isShowFlashes()){
					$presenter->flashMessage($this->translator->translate('admin.messages.successSave'), 'success');
				}
			}
			else {
				if($this->isShowFlashes()){
					$presenter->flashMessage($this->translator->translate('admin.messages.successSave'), 'success');
				}
			}
		}

		if ($this->getOnCreateCallback()) {
			$callback = $this->getOnCreateCallback();
			$callback($entity);
		}


		if($this->isShowFlashes()){
			$presenter->redrawControl('flashMessages');
		}

	}


	/**
	 * @return void
	 */
	public function handlePrepareModal(): void
	{
		$defaults = [];
		$defaultValues = $this->getDefaultValues();
		if(!empty($defaultValues)){
			foreach($this['form']->getComponents() as $key => $component){
				if(isset($defaultValues[$key])){
					$defaults[$key] = $defaultValues[$key];
				}
			}
		}

		$this['form']->setDefaults($defaults, TRUE);
		$this->template->getLatte()->addProvider('formsStack', [$this['form']]);
		$this->redrawControl('form');
	}

	/**
	 * @return Form
	 */
	public function getForm(): Form
	{
		return $this['form'];
	}

	/**
	 * @param string $input
	 * @param string $attribute
	 * @param string $value
	 * @return $this
	 */
	public function setInputParentHtmlAttribute(string $input, string $attribute, string $value): self
	{
		$this->inputParentHtmlAttributes[$input][$attribute] = $value;

		return $this;
	}


	//GETTERS & SETTERS

	/**
	 * @return callable|null
	 */
	public function getCustomCreateCallback(): ?callable
	{
		return $this->customCreateCallback;
	}


	/**
	 * @param callable|null $customCreateCallback
	 */
	public function setCustomCreateCallback(?callable $customCreateCallback): void
	{
		$this->customCreateCallback = $customCreateCallback;
	}


	/**
	 * @return callable|null
	 */
	public function getOnCreateCallback(): ?callable
	{
		return $this->onCreateCallback;
	}


	/**
	 * @param callable|null $onCreateCallback
	 */
	public function setOnCreateCallback(?callable $onCreateCallback): void
	{
		$this->onCreateCallback = $onCreateCallback;
	}

	/**
	 * @return bool
	 */
	public function isShowFlashes(): bool
	{
		return $this->showFlashes;
	}


	/**
	 * @param bool $showFlashes
	 */
	public function setShowFlashes(bool $showFlashes): void
	{
		$this->showFlashes = $showFlashes;
	}


	/**
	 * @return string|null
	 */
	public function getModalTitle(): ?string
	{
		return $this->modalTitle;
	}


	/**
	 * @param string|null $modalTitle
	 */
	public function setModalTitle(?string $modalTitle): void
	{
		$this->modalTitle = $modalTitle;
	}


	/**
	 * @return string|null
	 */
	public function getButtonText(): ?string
	{
		return $this->buttonText;
	}


	/**
	 * @param string|null $buttonText
	 */
	public function setButtonText(?string $buttonText): void
	{
		$this->buttonText = $buttonText;
	}


	/**
	 * @return array
	 */
	public function getDefaultValues(): array
	{
		return $this->defaultValues;
	}


	/**
	 * @param array $defaultValues
	 */
	public function setDefaultValues(array $defaultValues): void
	{
		$this->defaultValues = $defaultValues;
	}


	/**
	 * @return Html
	 */
	public function getCustomButtonHtml(): ?Html
	{
		return $this->customButtonHtml;
	}


	/**
	 * @param Html $customButtonHtml
	 */
	public function setCustomButtonHtml(?Html $customButtonHtml): void
	{
		$this->customButtonHtml = $customButtonHtml;
	}


	/**
	 * @return array
	 */
	public function getRequiredPermissions(): array
	{
		return $this->requiredPermissions;
	}


	/**
	 * @param array $requiredPermissions
	 */
	public function setRequiredPermissions(array $requiredPermissions): void
	{
		$this->requiredPermissions = $requiredPermissions;
	}



}
