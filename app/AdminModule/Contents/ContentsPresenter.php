<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Traits\ContentParametersTrait;
use Nette\Application\UI\Form;

class ContentsPresenter extends BaseModulePresenter
{
	use ContentParametersTrait;

	/** @var \ContentsRepository @inject */
	public $repository;

	/** @var \ContentsService @inject */
	public $service;

	/** @var string[]  */
	protected $moduleSortingOptions = [
		'name' => 'name',
		'name__desc' => 'name DESC',
	];

	/** @var array */
	protected $moduleBulkActions = [
		BulkActionsControl::ACTION_DELETE,
	];

	/** @var array */
	protected $parameterTypesPairs = [];


	/**
	 * @throws \ReflectionException
	 */
	protected function startup()
	{
		parent::startup();

		$this->parameterTypesPairs = [
			\ContentParametersEntity::TYPE_TEXT => $this->trans('admin.contentParameterTypes.text'),
			\ContentParametersEntity::TYPE_WSW => $this->trans('admin.contentParameterTypes.wsw'),
			\ContentParametersEntity::TYPE_IMG => $this->trans('admin.contentParameterTypes.image'),
			\ContentParametersEntity::TYPE_DATE => $this->trans('admin.contentParameterTypes.date'),
			\ContentParametersEntity::TYPE_COLOR => $this->trans('admin.contentParameterTypes.color'),
			\ContentParametersEntity::TYPE_ICON => $this->trans('admin.contentParameterTypes.icon'),
			\ContentParametersEntity::TYPE_BOOL => $this->trans('admin.contentParameterTypes.bool')
		];

		$this->template->parameterTypesPairs = $this->parameterTypesPairs;
	}


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.name');

		parent::actionDefault();
	}


	/**
	 *
	 */
	public function applyFilters()
	{
		$this->selection->where('contents.types_id', \ContentsEntity::TYPE_PARAMETERS_WRAPPER);
		parent::applyFilters();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);
		$form->addText('system', 'admin.terms.system');

		$this->setParametersFormFields($form);

		return parent::setFormFields($form);
	}


	/**
	 *
	 */
	protected function setParametersFormFields(Form $form): void
	{
		/** @var \ContentsEntity $content */
		if (!$content = $this->entity) {
			return;
		}

		/** @var \ContentParametersEntity $parameter */
		foreach ($content->getParameters() as $parameter) {
			$key = ($parameter->types_id === \ContentParametersEntity::TYPE_IMG ? 'image' : 'data').'_'.$parameter->id;

			if ($form->getComponent($key, FALSE)) {
				continue;
			}

			if ($parameter->types_id === \ContentParametersEntity::TYPE_WSW) {
				$form->addTextArea($key, $parameter->name)
					->setTranslator(NULL)
					->setDefaultValue($parameter->data);
			}
			elseif ($parameter->types_id === \ContentParametersEntity::TYPE_IMG) {
				$form->addUpload($key, $parameter->name)->setTranslator(NULL);
			}
			elseif ($parameter->types_id === \ContentParametersEntity::TYPE_IMG) {
				$form->addUpload($key, $parameter->name)->setTranslator(NULL);
			}
			elseif ($parameter->types_id === \ContentParametersEntity::TYPE_BOOL) {
				$form->addSelect($key, $parameter->name, $this->global['bool'])->setDefaultValue($parameter->data)->setTranslator(NULL);
			}
			else {
				$form->addText($key, $parameter->name)->setTranslator(NULL)->setDefaultValue($parameter->data);
			}
		}
	}


	/**
	 * @param array $values
	 * @return \BaseEntity
	 */
	protected function processCreateForm(array $values): \BaseEntity
	{
		$values['types_id'] = \ContentsEntity::TYPE_PARAMETERS_WRAPPER;
		return parent::processCreateForm($values);
	}

	/**
	 * @param \BaseEntity $entity
	 * @param Form $form
	 * @throws \Exception
	 */
	protected function afterEntitySave(\BaseEntity $entity, Form $form): void
	{
		parent::afterEntitySave($entity, $form);

		/** @var \ContentsEntity $content */
		if (!$content = $entity) {
			return;
		}

		$values = self::getEditFormValues($form, 'edit');

		/** @var \ContentParametersEntity $parameter */
		foreach ($content->getParameters() as $parameter) {
			$key = ($parameter->types_id === \ContentParametersEntity::TYPE_IMG ? 'image' : 'data').'_'.$parameter->id;

			if (!isset($values[$key])) {
				continue;
			}

			$parameter->data = $values[$key];

			$this->contentParametersRepo->update($parameter);
		}
	}
}
