<?php

use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Strings;

trait ContentBlocksSectionsTrait
{

	/**
	 * @return array
	 */
	public function getSections(): array
	{
		return $this->content ? $this->content->getSections() : [];
	}


	/**
	 * @return array
	 */
	public function getSectionTypes(): array
	{
		return ContentSectionsDefinition::SECTION_TYPES;
	}


	/**
	 * @param ContentSectionsEntity $section
	 * @return array|null
	 */
	public function getSectionDefinition(ContentSectionsEntity $section): ?array
	{
		return isset(ContentSectionsDefinition::SECTION_TYPES[$section->types_id]) ? ContentSectionsDefinition::SECTION_TYPES[$section->types_id] : NULL;
	}


	/**
	 * @return array
	 */
	public function getSectionTypesPairs(): array
	{
		$pairs = [];
		foreach ($this->getSectionTypes() as $sectionKey => $section) {
			$pairs[$sectionKey] = $this->getTranslator()->translate($section[\ContentSectionsDefinition::NAME]);
		}

		return $pairs;
	}


	/**
	 * @return Form
	 */
	protected function createComponentSectionForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addHidden('id');
		$form->addText('name', 'admin.terms.name');
		$form->addSelect('types_id', $this->translator->translate('admin.terms.type'), $this->getSectionTypesPairs())->setTranslator(NULL);

		$form->addSubmit('submit');

		$form->onSuccess[] = [$this, 'sectionFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 */
	public function sectionFormSubmitted(Form $form)
	{
		$values = $form->getHttpData();

		$set = [
			'name' => $values['name'],
			'types_id' => $values['types_id']
		];

		$data = [];
		foreach ($values as $key => $val) {
			if (Strings::startsWith($key, 'types_id-'.$values['types_id'].'-')) {
				$data[str_replace('types_id-'.$values['types_id'].'-', '', $key)] = $val;
			}
		}

		$set['data'] = json_encode($data, JSON_UNESCAPED_UNICODE);

		/** @var ContentSectionsEntity $section */
		if ($values['id'] && $section = $this->sectionsRepo->findEntity($values['id'])) {
			$section->setValues($set);
			$this->sectionsRepo->update($section);
		}
		else {
			$set['contents_id'] = $this->content->id;

			$newSection = $this->sectionsRepo->insert($set);

			$this->blocksRepo->insert([
				'sections_id' => $newSection->id,
				'types_id' => ContentBlocksDefinition::DEFAULT_CONTENT_BLOCK_ID,
				'pos_order' => 1,
				'data' => '[]'
			]);
		}

		$this->content = $this->contentsRepo->findEntity($this->content->id);

		$this->redrawControl('contentBlocks');
	}


	/**
	 *
	 */
	public function handleCreateSection()
	{
		/** @var Form $form */
		$form = $this->getComponent('sectionForm');

		$form->setDefaults([
			'types_id' => ContentSectionsDefinition::SECTION_TYPE_STANDARD
		], TRUE);

		$this->redrawModals(['sectionForm']);
	}


	/**
	 *
	 */
	public function handleEditSection($sectionsId)
	{
		/** @var ContentSectionsEntity $section */
		if (!$section = $this->sectionsRepo->findEntity($sectionsId)) {
			exit;
		}

		/** @var Form $form */
		$form = $this->getComponent('sectionForm');

		$form->setDefaults([
			'id' => $section->id,
			'name' => $section->name,
			'types_id' => $section->types_id
		], TRUE);

		$this->template->selectedSection = $section;

		$this->redrawModals(['sectionForm']);
	}


	/**
	 * @param $sectionsId
	 */
	public function handleRemoveSection($sectionsId)
	{
		/** @var ContentSectionsEntity $section */
		if (!$section = $this->sectionsRepo->findEntity($sectionsId)) {
			exit;
		}

		$this->sectionsRepo->delete($section->id);

		$this->redrawControl('contentBlocks');
	}


	/**
	 * @param $itemId
	 * @param $aboveId
	 */
	public function handleMoveSectionAbove($itemId, $aboveId)
	{
		$this->sectionsRepo->moveAbove($itemId, $aboveId);

		$this->redrawControl('contentBlocks');
	}


	/**
	 * @param $itemId
	 * @param $belowId
	 */
	public function handleMoveSectionBelow($itemId, $belowId)
	{
		$this->sectionsRepo->moveBelow($itemId, $belowId);

		$this->redrawControl('contentBlocks');
	}
}
