<?php

namespace App\AdminModule\Traits;

use App\AdminModule\Presenters\ContentBlocksControl;
use BaseEntity;
use ContentBlocksDefinition;
use ContentSectionsDefinition;
use ContentSectionsRepository;
use ContentsEntity;
use ContentsRepository;
use Nette\Database\Table\ActiveRow;

trait BaseContentsTrait
{
	/** @var ContentsRepository @inject */
	public $contentsRepo;

	/** @var \ContentsService @inject */
	public $contentsService;

	/** @var ContentSectionsRepository @inject */
	public $sectionsRepo;

	/** @var \ContentBlocksRepository @inject */
	public $blocksRepo;


	/**
	 * @return ContentBlocksControl
	 */
	protected function createComponentContentBlocks(): ContentBlocksControl
	{
		$control = new ContentBlocksControl($this->contentsRepo, $this->contentsService, $this->sectionsRepo, $this->blocksRepo, $this->imagesService, $this->filesDataService, $this->database, $this->aps, $this->translator);
		if ($this->entity && in_array('contents_id', $this->repository->getColumnNames())) {
			if (!$content = $this->entity->getContent()) {
				$content = $this->contentsService->createDefaultContents();
				$this->entity->contents_id = $content->id;
				$this->repository->update($this->entity);
			}

			$control->setContent($content);
		}

		$control->setAction($this->getAction());

		return $control;
	}


	/**
	 * @param BaseEntity $copyFromEntity
	 * @param BaseEntity $copyToEntity
	 * @return BaseEntity
	 */
	public function copyContents(BaseEntity $copyFromEntity, BaseEntity $copyToEntity): BaseEntity
	{
		if($this->repository->containsColumn('contents_id')){
			if($this->repository::isMultilang()){
				$contentIdsByLanguageIds = $this->database->table($this->repository->getTableName().'_mld')
					->where($this->repository->getTableName().'_id', $copyFromEntity->id)
					->where('contents_id')
					->select('*')
					->fetchPairs('languages_id', 'contents_id');
				if(!empty($contentIdsByLanguageIds)){
					foreach($contentIdsByLanguageIds as $languageId => $contentId){
						$content = $this->contentsService->copyContent($contentId);
						if($content instanceof \ContentsEntity){
							$this->database->table($this->repository->getTableName().'_mld')
								->where('languages_id', $languageId)
								->where($this->repository->getTableName().'_id', $copyToEntity->id)
								->update([
									'contents_id' => $content->id
								]);
						}
						if($languageId == $GLOBALS['lang']){
							$copyToEntity->contents_id = $content->id;
						}
					}
				}
			}
			else{
				if(!is_null($copyFromEntity->contents_id)){
					$content = $this->contentsService->copyContent($copyFromEntity->contents_id);
					if($content instanceof \ContentsEntity){
						$copyToEntity->contents_id = $content->id;
						$this->repository->update($copyToEntity);
					}
				}
			}
		}
		return $copyToEntity;
	}
}
