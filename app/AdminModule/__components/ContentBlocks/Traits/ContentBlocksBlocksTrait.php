<?php

use App\AdminModule\Presenters\AttachmentControl;
use App\AdminModule\Presenters\BaseModulePresenter;
use App\AdminModule\Presenters\FilesControl;
use App\AdminModule\Presenters\ImagesControl;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Forms\Controls\SelectBox;
use Nette\Utils\Finder;
use Nette\Utils\Image;
use Nette\Utils\Random;
use Nette\Utils\Strings;

trait ContentBlocksBlocksTrait
{

	/** @var string */
	protected string $imagesComponentTempDir = '';


	/**
	 * @param ContentSectionsEntity $section
	 * @return array
	 */
	public function getBlocks(ContentSectionsEntity $section): array
	{
		return $section->getBlocks();
	}


	/**
	 * @return array
	 */
	public function getBlockTypes(): array
	{
		return ContentBlocksDefinition::BLOCK_TYPES;
	}


	/**
	 * @param ContentBlocksEntity $block
	 * @return array|null
	 */
	public function getBlockDefinition(ContentBlocksEntity $block): ?array
	{
		return isset(ContentBlocksDefinition::BLOCK_TYPES[$block->types_id]) ? ContentBlocksDefinition::BLOCK_TYPES[$block->types_id] : NULL;
	}


	/**
	 * @return array
	 */
	public function getBlockTypesPairs(): array
	{
		$pairs = [];
		foreach ($this->getBlockTypes() as $blockKey => $block) {
			$pairs[$blockKey] = $this->getTranslator()->translate($block[\ContentBlocksDefinition::NAME]);
		}

		return $pairs;
	}


	/**
	 * @return Form
	 */
	protected function createComponentCreateBlockForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addHidden('id');
		$form->addHidden('sections_id');
		$form->addText('name', 'admin.terms.name');
		$form->addSelect('types_id', $this->translator->translate('admin.terms.type'), $this->getBlockTypesPairs())->setTranslator(NULL);

		$form->addSubmit('submit');

		$form->onSuccess[] = [$this, 'createBlockFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 */
	public function createBlockFormSubmitted(Form $form)
	{
		$values = $form->getHttpData();

		$newBlock = $this->blocksRepo->insert([
			'sections_id' => $values['sections_id'],
			'name' => isset($values['name']) ? $values['name'] : '',
			'types_id' => $values['types_id'],
			'data' => json_encode([])
		]);

		$this->content = $this->contentsRepo->findEntity($this->content->id);
		$this->getPresenter()->payload->openItem = [
			'list' => '.content-blocks-blocks',
			'editLink' => '.edit-trigger',
			'id' => $newBlock->id
		];
		$this->redrawControl('contentBlocks');
	}


	/**
	 * @param $sectionsId
	 */
	public function handleCreateBlock($sectionsId)
	{
		if (!$section = $this->sectionsRepo->findEntity($sectionsId)) {
			exit;
		}

		/** @var Form $form */
		$form = $this->getComponent('createBlockForm');

		$form->setDefaults([
			'sections_id' => $section->id,
			'types_id' => ContentBlocksDefinition::DEFAULT_CONTENT_BLOCK_ID
		], TRUE);

		$this->redrawModals(['createBlockForm']);
	}


	/**
	 * @return Form
	 */
	protected function createComponentCopyBlockForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);


		$form->addHidden('sections_id');

		$langPairs = [];
		foreach($GLOBALS['languages'] as $lang){
			$langPairs[$lang->id] = $lang->name;
		}

		$form->addSelect('languages_id', 'admin.terms.language', $langPairs);
		$tablesWithContents = $this->contentsRepo->getTablesWithContents();
		$tablePairs = $this->contentsRepo->getModuleNamePairsFromTables($tablesWithContents, 'admin.modules.');
		$form->addSelect('table_name', 'admin.terms.module', $tablePairs);
		$form->addSelect('items_id', 'admin.terms.item', [])->setPrompt('-');

		$form->addSubmit('submit');

		$form->onSuccess[] = [$this, 'copyBlockFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 */
	public function copyBlockFormSubmitted(Form $form)
	{
		$values = $form->getHttpData();

		$blockIds = [];
		foreach($values as $key => $val){
			if(Strings::startsWith($key, 'block__')){
				[$nothing, $blockId] = explode('__', $key);
				$blockIds[] = $blockId;
			}
		}
		$this->contentsService->copyContentBlocks($blockIds, $values['sections_id']);

		$this->redrawControl('contentBlocks');
	}


	/**
	 * @param $sectionsId
	 * @param $languagesId
	 * @param $tableName
	 * @param $itemId
	 * @return void
	 */
	public function setCopyBlockFormValues($sectionsId, $languagesId, $tableName, $itemId = NULL)
	{
		if (!$section = $this->sectionsRepo->findEntity($sectionsId)) {
			return;
		}

		/** @var Form $form */
		$form = $this->getComponent('copyBlockForm');


		//load items
		$content = NULL;
		$itemPairs = [];
		if(!is_null($tableName)){
			$baseTableName = str_replace('_mld', '', $tableName);
			$selection = NULL;
			if(Strings::contains($tableName, '_mld')){
				$selection = $this->database->table($baseTableName.'_mld')
					->select('*, '.$baseTableName.'.*')
					->where($baseTableName.'_mld.languages_id = ?', $languagesId);
			}
			else{
				$selection = $this->database->table($baseTableName)->select($baseTableName.'.*');
			}

			$isMultilevel = $this->database->query('SHOW COLUMNS FROM '.$baseTableName.' LIKE ?;', 'pos_level')->fetch();
			if($isMultilevel){
				$itemPairs = $selection
					->select('id, CONCAT(REPEAT(" - - - ", pos_level - 1), name) AS name')
					->where('pos_level > ?', 0)
					->order('pos_left')
					->fetchPairs('id', 'name');
			}
			else{
				$itemPairs = $selection
					->order('name')
					->fetchPairs('id', 'name');
			}

			if(is_null($itemId) && !empty($itemPairs)){
				$itemId = array_key_first($itemPairs);
			}
			if(!is_null($itemId)){
				$selection = $this->database->table($tableName)->select('contents_id')->where($baseTableName.'_id', $itemId);
				if(Strings::contains($tableName, '_mld')){
					$selection->where('languages_id', $languagesId);
				}
				$rowWithContentsId = $selection->limit(1)->fetch();
				if($rowWithContentsId){
					$content = $this->contentsRepo->findEntity($rowWithContentsId->contents_id);
				}

			}
		}

		/** @var SelectBox $itemsIdInput */
		$itemsIdInput = $form['items_id'];
		$itemsIdInput->setItems($itemPairs);


		$form->setDefaults([
			'sections_id' => $section->id,
			'languages_id' => $languagesId,
			'table_name' => $tableName,
			'items_id' => isset($itemPairs[$itemId]) ? $itemId : NULL
		], TRUE);

		$this->template->content = $content;
	}


	/**
	 * @param $sectionsId
	 */
	public function handleCopyBlock($sectionsId)
	{
		/** @var BaseModulePresenter $presenter */
		$presenter = $this->getPresenter();
		$this->setCopyBlockFormValues($sectionsId, $GLOBALS['lang'], $presenter->repository->getContentsTableName(), $this->getPresenter()->getParameter('id'));
		$this->redrawModals(['copyBlockForm']);
	}


	/**
	 * @param $sectionsId
	 * @param $languagesId
	 * @param $tableName
	 * @param $itemId
	 * @return void
	 */
	public function handleCopyBlockSetParameters($sectionsId, $languagesId, $tableName, $itemId)
	{
		$this->setCopyBlockFormValues($sectionsId, $languagesId, $tableName, $itemId ?: NULL);
		$this->redrawModals(['copyBlockForm']);
	}


	/**
	 * @return Form
	 */
	protected function createComponentEditBlockForm(): Form
	{
		$form = new Form();
		$form->addHidden('id');

		$form->addSubmit('submit');

		$form->onSuccess[] = [$this, 'editBlockFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 * @throws \Nette\Utils\ImageException
	 * @throws \Nette\Utils\UnknownImageFileException
	 */
	public function editBlockFormSubmitted(Form $form)
	{
		$values = $form->getHttpData();

		/** @var ContentBlocksEntity $block */
		if (!$values['id'] || !$block = $this->blocksRepo->findEntity($values['id'])) {
			exit;
		}

		foreach ($values as $key => $value) {
			if (Strings::startsWith($key, 'types_id-0-')) {
				$values[str_replace('types_id-0-', '', $key)] = $value;
				unset($values[$key]);
			}
		}

		$data = json_decode($block->data, TRUE);
		if ($blockDefinition = $this->getBlockDefinition($block)) {
			foreach ($blockDefinition['parameters'] as $parameterKey => $parameter) {
				if ($parameter[\ContentBlocksDefinition::TYPE] == ContentBlocksDefinition::PARAMETER_TYPE_IMAGE) {
					$val = isset($values[$parameterKey]) ? $values[$parameterKey] : NULL;

					if (!$val) {
						continue;
					}

					if($filename = ImagesService::saveImageUpload($val)){
						$data[$parameterKey] = $filename;
					}
					else{
						unset($data[$parameterKey]);
					}
				}
				elseif ($parameter[\ContentBlocksDefinition::TYPE] == ContentBlocksDefinition::PARAMETER_TYPE_FILE) {
					$val = isset($values[$parameterKey]) ? $values[$parameterKey] : NULL;

					if (!$val) {
						continue;
					}

					if($filename = FilesService::saveFileUpload($val)){
						$data[$parameterKey] = $filename;
					}
					else{
						unset($data[$parameterKey]);
					}
				}
				elseif ($parameter[\ContentBlocksDefinition::TYPE] == ContentBlocksDefinition::PARAMETER_TYPE_IMAGES) {
					if (isset($values['images'])) {
						$data['images'] = $values['images'];
					}

					// new images
					$tempDirectory = FS_TEMP.'/block-'.$block->id.'-'.$parameterKey;
					$images = [];
					if (file_exists($tempDirectory)) {
						/**
						 * @var int $key
						 * @var \SplFileInfo $file
						 */
						foreach (Finder::findFiles('*')->in($tempDirectory) as $key => $file) {
							if ($fileObj = ImagesService::saveImage($file)) {
								$images[] = json_encode($fileObj);
							}
							unlink($tempDirectory.'/'.$file->getFilename());
						}
						rmdir($tempDirectory);
					}

					if (count($images)) {
						$data['images'] .= ($data['images'] ? '; ' : '').implode('; ', $images);
					}
				}
				elseif ($parameter[\ContentBlocksDefinition::TYPE] == ContentBlocksDefinition::PARAMETER_TYPE_FILES) {
					if (isset($values['files'])) {
						$data['files'] = $values['files'];
					}

					// new files
					$tempDirectory = FS_TEMP.'/block-'.$block->id.'-'.$parameterKey;
					$files = [];
					if (file_exists($tempDirectory)) {
						foreach (Finder::findFiles('*')->in($tempDirectory) as $key => $file) {
							if ($fileObj = FilesService::saveFile($tempDirectory, $file)) {
								$files[] = json_encode($fileObj);
							}

							unlink($tempDirectory.'/'.$file->getFilename());
						}
						rmdir($tempDirectory);
					}

					if (count($files)) {
						$data['files'] .= ($data['files'] ? '; ' : '').implode('; ', $files);
					}
				}
				elseif ($parameter[\ContentBlocksDefinition::TYPE] == ContentBlocksDefinition::PARAMETER_TYPE_MULTISELECT) {
					$data[$parameterKey] = [];
					if(isset($values[$parameterKey]) && is_array($values[$parameterKey])){
						foreach($values[$parameterKey] as $optionKey => $optionVal){
							if((is_numeric($optionVal) && intval($optionVal) != 0) || !is_numeric($optionVal)){
								$data[$parameterKey][] = $optionVal;
							}
						}
					}
				}
				elseif ($parameter[\ContentBlocksDefinition::TYPE] == ContentBlocksDefinition::PARAMETER_TYPE_CHECKBOX) {
					$data[$parameterKey] = [];
					if(isset($values[$parameterKey]) && is_array($values[$parameterKey])){
						foreach($values[$parameterKey] as $optionKey => $optionVal){
							if(((is_numeric($optionKey) && intval($optionKey) != 0) || !is_numeric($optionKey)) && $optionVal == 'on'){
								$data[$parameterKey][] = $optionKey;
							}
						}
					}
				}
				elseif ($parameter[\ContentBlocksDefinition::TYPE] == ContentBlocksDefinition::PARAMETER_TYPE_MULTIPLICATOR) {
					$data[$parameterKey] = [];
					foreach($values as $key => $multiValues){
						if(Strings::startsWith($key, $parameterKey.'__')){
							if(is_array($multiValues) && count($multiValues) > 1){
								[$nothing, $column] = explode('__', $key);
								foreach($multiValues as $valueKey => $value){
									//skip first (multiplicator)
									if($valueKey != 0){
										$data[$parameterKey][$valueKey][$column] = $value;
									}
								}
							}
						}
					}
					//unset empty
					foreach($data[$parameterKey] as $valuesKey => $multiValues){
						$hasValues = FALSE;
						foreach($multiValues as $key => $val){
							if($val){
								$hasValues = TRUE;
							}
						}
						if(!$hasValues){
							unset($data[$parameterKey][$valuesKey]);
						}
					}
				}
				elseif (isset($values[$parameterKey])) {
					$data[$parameterKey] = $values[$parameterKey];
				}
			}
		}

        foreach($data as $key => $value){
            if(Strings::startsWith($key, 'slug')){
                $block->slug = $value;

            }

            if(Strings::startsWith($key, 'namePage')){
                $block->name_page = $value;
            }
        }

		$block->data = json_encode($data, JSON_UNESCAPED_UNICODE);
		$this->blocksRepo->update($block);

		$this->content = $this->contentsRepo->findEntity($this->content->id);

		$this->redrawControl('contentBlocks');
	}


    /**
     * @param $itemId
     */
    public function handleSetActiveMenu($itemId)
    {
        /** @var ContentBlocksEntity $block */
        if (!$block = $this->blocksRepo->findEntity($itemId)) {
            exit;
        }

        $block->is_in_menu = !$block->is_in_menu;

        if($block->is_in_menu && !$block->slug){
            $blockDefinition = $this->getBlockDefinition($block);
            $data = json_decode($block->data, TRUE);

            $block->name_page = $data['namePage'] = $this->getTranslator()->translate($blockDefinition[\ContentBlocksDefinition::NAME]);
            $block->slug = $data['slug'] = Strings::webalize($block->name_page);

            $block->data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        $this->blocksRepo->update($block);

        $this->redrawControl('contentBlocks');
        $this->redrawControl('contentBlocksSortingArea');
        $this->redrawControl('contentBlocksSorting');
    }


	/**
	 *
	 */
	public function handleEditBlock($blocksId)
	{
		/** @var ContentBlocksEntity $block */
		if (!$block = $this->blocksRepo->findEntity($blocksId)) {
			exit;
		}

		/** @var Form $form */
		$form = $this->getComponent('editBlockForm');

		$form->setDefaults([
			'id' => $block->id
		], TRUE);

		$this->template->selectedBlock = $block;
		$this->template->blockType = $this->getBlockDefinition($block);

		$this->redrawModals(['editBlockForm']);
	}


	/**
	 * @param $blocksId
	 */
	public function handleRemoveBlock($blocksId)
	{
		if (!$block = $this->blocksRepo->findEntity($blocksId)) {
			exit;
		}

		$this->blocksRepo->delete($block->id);

		$this->redrawControl('contentBlocks');
	}


	/**
	 * @param ContentBlocksEntity $block
	 * @param string $parametersKey
	 * @return ImagesControl
	 */
	public function getImagesControl(ContentBlocksEntity $block, string $parametersKey): ImagesControl
	{
		/** @var ImagesControl $control */
		$control = $this['images'];
		$control->setTempDir('block-'.$block->id.'-'.$parametersKey);
		$control->setImages($block->getContentImages($parametersKey));
		return $control;
	}


	/**
	 * @return ImagesControl
	 */
	public function createComponentImages(): ImagesControl
	{
		return new ImagesControl($this->imagesService, $this->filesDataService);
	}


	/**
	 * @param ContentBlocksEntity $block
	 * @param string $parametersKey
	 * @return FilesControl
	 */
	public function getFilesControl(ContentBlocksEntity $block, string $parametersKey): FilesControl
	{
		/** @var FilesControl $control */
		$control = $this['files'];
		$control->setTempDir('block-'.$block->id.'-'.$parametersKey);
		$control->setFiles($block->getContentFiles($parametersKey));

		return $control;
	}


	/**
	 * @return FilesControl
	 */
	public function createComponentFiles(): FilesControl
	{
		return new FilesControl($this->filesDataService);
	}


	/**
	 * @return AttachmentControl
	 */
	protected function createComponentAttachment(): AttachmentControl
	{
		return new AttachmentControl($this->translator, $this->imagesService, $this->filesDataService, $this->blocksRepo);
	}

	/**
	 * @param $blocksId
	 * @param $parametersKey
	 * @param $value
	 */
	public function handleSetValue($blocksId, $parametersKey, $value)
	{
		/** @var ActiveRow $block */
		if (!$block = $this->blocksRepo->findEntity($blocksId)) {
			exit;
		}

		$block->setValue($parametersKey, $value);
		$this->blocksRepo->update($block);

		$this->handleEditBlock($block->id);
	}


	/**
	 * @param $itemId
	 */
	public function handleSetActiveState($itemId)
	{
		/** @var ContentBlocksEntity $block */
		if (!$block = $this->blocksRepo->findEntity($itemId)) {
			exit;
		}

		$block->is_active = !$block->is_active;
		$this->blocksRepo->update($block);

		$this->redrawControl('contentBlocks');
	}


	/**
	 * @param $itemId
	 * @param $aboveId
	 * @param $sectionId
	 */
	public function handleMoveBlockAbove($itemId, $aboveId, $sectionId)
	{
		/** @var ContentBlocksEntity $block */
		if (!$block = $this->blocksRepo->findEntity($itemId)) {
			exit;
		}

		if ($sectionId != $block->sections_id) {
			$block->sections_id = $sectionId;
			$this->blocksRepo->update($block);
		}

		$this->blocksRepo->moveAbove($itemId, $aboveId);

		$this->redrawControl('contentBlocks');
	}


	/**
	 * @param $itemId
	 * @param $belowId
	 * @param $sectionId
	 */
	public function handleMoveBlockBelow($itemId, $belowId, $sectionId)
	{
		/** @var ContentBlocksEntity $block */
		if (!$block = $this->blocksRepo->findEntity($itemId)) {
			exit;
		}

		if ($sectionId != $block->sections_id) {
			$block->sections_id = $sectionId;
			$this->blocksRepo->update($block);
		}

		$this->blocksRepo->moveBelow($itemId, $belowId);

		$this->redrawControl('contentBlocks');
	}


	/**
	 * @param $itemId
	 * @param $sectionId
	 */
	public function handleMoveBlockToSection($itemId, $sectionId)
	{
		/** @var ContentBlocksEntity $block */
		if (!$block = $this->blocksRepo->findEntity($itemId)) {
			exit;
		}

		if ($sectionId != $block->sections_id) {
			$block->sections_id = $sectionId;
			$this->blocksRepo->update($block);
		}

		$this->redrawControl('contentBlocks');
	}


	/**
	 * @param array $parameter
	 * @return array
	 */
	public function getInputOptions(array $parameter): array
	{
		$options = [];
		if(isset($parameter[\ContentBlocksDefinition::OPTIONS]) && !empty($parameter[\ContentBlocksDefinition::OPTIONS])){
			foreach($parameter[\ContentBlocksDefinition::OPTIONS] as $key => $val){
				$options[$key] = $this->getTranslator()->translate($val);
			}
			$options = $parameter[\ContentBlocksDefinition::OPTIONS];
		}
		else{
			if(isset($parameter[\ContentBlocksDefinition::OPTIONS__DB_PAIRS]) && $parameter[\ContentBlocksDefinition::OPTIONS__DB_PAIRS]){
				$parts = explode('__', $parameter[\ContentBlocksDefinition::OPTIONS__DB_PAIRS]);
				if(count($parts) == 3){
					[$table, $byColumn, $column] = $parts;
					 $order = $column;
				}
				if(count($parts) == 4){
					[$table, $byColumn, $column, $order] = $parts;
				}

				if(isset($parameter[\ContentBlocksDefinition::OPTIONS__DB_QUERY]) && $parameter[\ContentBlocksDefinition::OPTIONS__DB_QUERY]){
					$items =  $this->database->query($parameter[\ContentBlocksDefinition::OPTIONS__DB_QUERY])->fetchPairs($byColumn, $column);
				}
				else{
					if(Strings::endsWith($table, '_mld')){
                        $baseTableName = str_replace('_mld', '', $table);
                        $isMultilevel = $this->database->query('SHOW COLUMNS FROM '.$baseTableName.' LIKE ?;', 'pos_level')->fetch();

                        $baseTable = str_replace('_mld', '', $table);
                        $rows = $this->database->table($table)
							->select('*, '.$baseTable.'.*')
							->where($table.'.languages_id = ?', $GLOBALS['lang'])
							->order($order);

                        if($isMultilevel){
                            $rows->where("pos_level > 0");
                        }

                        $items = $rows->fetchPairs($byColumn, $column);
					}
					else{
						$items = $this->database->table($table)->select($table.'.*')
							->order($order)
							->fetchPairs($byColumn, $column);
					}
				}

				if(!empty($items)){
					if(in_array($parameter[\ContentBlocksDefinition::TYPE], [\ContentBlocksDefinition::PARAMETER_TYPE_SELECT, \ContentBlocksDefinition::PARAMETER_TYPE_MULTISELECT])){
						$options[0] = '-';
					}
					foreach($items as $optionKey => $optionName){
						$options[$optionKey] = $optionName;
					}
				}
			}
		}

		return $options;
	}


	/**
	 * @param ContentBlocksEntity $blocksEntity
	 * @param int $number
	 * @param array $blockDefinition
	 * @return string
	 */
	public function getFormattedBlockName(ContentBlocksEntity $blocksEntity, int $number, array $blockDefinition): string
	{
		$mainNameParts = [];
		$nameParts = [];
		$mainNameParts[] = '#'.$number;
		$mainNameParts[] = $this->getTranslator()->translate($blockDefinition[\ContentBlocksDefinition::NAME]);


		$parameter = 'image_pos';
		$val = $blocksEntity->getValue($parameter);
		$imageVal = $blocksEntity->getValue('image');
		if ($val && $imageVal && isset($blockDefinition[\ContentBlocksDefinition::PARAMETERS][$parameter][\ContentBlocksDefinition::OPTIONS][$val]) && $blockDefinition[\ContentBlocksDefinition::PARAMETERS][$parameter][\ContentBlocksDefinition::OPTIONS][$val]){
			$valName = $blockDefinition[\ContentBlocksDefinition::PARAMETERS][$parameter][\ContentBlocksDefinition::OPTIONS][$val];
			$nameParts[] = '<small>'.Strings::lower($this->translator->translate('admin.terms.image')).' '.Strings::lower($this->translator->translate($valName)).'</small>';
		}

		$parameter = 'columns';
		$val = $blocksEntity->getValue($parameter);
		if ($val && isset($blockDefinition[\ContentBlocksDefinition::PARAMETERS][$parameter][\ContentBlocksDefinition::OPTIONS][$val]) && $blockDefinition[\ContentBlocksDefinition::PARAMETERS][$parameter][\ContentBlocksDefinition::OPTIONS][$val]){
			$valName = $blockDefinition[\ContentBlocksDefinition::PARAMETERS][$parameter][\ContentBlocksDefinition::OPTIONS][$val];
			$nameParts[] = '<small>'.$valName.' '.Strings::lower($this->translator->translate('admin.terms.columuns')).'</small>';
		}

		return implode(' - ', $mainNameParts).'<br>'.implode(', ', $nameParts);
	}


	/**
	 * @param int $typesId
	 * @return string
	 */
	public function getParameterLatteIncludeName(int $typesId): string
	{
		if($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_TEXT){
			return 'PARAMETER_TYPE_TEXT';
		}
		elseif($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_TEXTAREA){
			return 'PARAMETER_TYPE_TEXTAREA';
		}
		elseif($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_WSW){
			return 'PARAMETER_TYPE_WSW';
		}
		elseif($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_IMAGE){
			return 'PARAMETER_TYPE_IMAGE';
		}
		elseif($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_IMAGES){
			return 'PARAMETER_TYPE_IMAGES';
		}
		elseif($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_FILES){
			return 'PARAMETER_TYPE_FILES';
		}
		elseif($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_BOOLEAN){
			return 'PARAMETER_TYPE_BOOLEAN';
		}
		elseif($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_SELECT){
			return 'PARAMETER_TYPE_SELECT';
		}
		elseif($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_FILE){
			return 'PARAMETER_TYPE_FILE';
		}
		elseif($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_CLEARFIX){
			return 'PARAMETER_TYPE_CLEARFIX';
		}
		elseif($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_CHECKBOX){
			return 'PARAMETER_TYPE_CHECKBOX';
		}
		elseif($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_MULTIPLICATOR){
			return 'PARAMETER_TYPE_MULTIPLICATOR';
		}
		elseif($typesId == \ContentBlocksDefinition::PARAMETER_TYPE_MULTISELECT){
			return 'PARAMETER_TYPE_MULTISELECT';
		}
		else{
			return '';
		}
	}
}
