<?php

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class ContentsService
{
	use \Nette\SmartObject;

	/**
	 * @param Explorer $database
	 * @param ContentsRepository $contentsRepo
	 * @param ContentSectionsRepository $sectionsRepo
	 * @param ContentBlocksRepository $blocksRepo
	 */
	public function __construct(protected Explorer $database, protected ContentsRepository $contentsRepo, protected ContentSectionsRepository $sectionsRepo, protected ContentBlocksRepository $blocksRepo)
	{
	}


	/**
	 * @return ContentsEntity
	 */
	public function createDefaultContents(): ContentsEntity
	{
		//content
		/** @var ActiveRow $content */
		$content = $this->contentsRepo->insert(['types_id' => ContentsEntity::TYPE_SECTIONS_WRAPPER]);
		$content = ContentsRepository::getEntity($content);

		//section
		/** @var ActiveRow $section */
		$section = $this->sectionsRepo->insert([
			'contents_id' => $content->id,
			'types_id' => ContentSectionsDefinition::SECTION_TYPE_STANDARD,
			'pos_order' => 1,
			'name' => '',
			'data' => '[]'
		]);
		$section = ContentSectionsRepository::getEntity($section);

		//block
		$this->blocksRepo->insert([
			'sections_id' => $section->id,
			'types_id' => ContentBlocksDefinition::DEFAULT_CONTENT_BLOCK_ID,
			'pos_order' => 1,
			'data' => '[]'
		]);

		return $content;
	}


	/**
	 * @param int $contentsId
	 * @return ContentsEntity|null
	 */
	public function copyContent(int $contentsId): ?ContentsEntity
	{
		//contents===========================================
		//base
		$rowContent = $this->database->table($this->contentsRepo->getTableName())->select('*')->where('id', $contentsId)->limit(1)->fetch();
		$rowContentArray = $rowContent->toArray();
		unset($rowContentArray['id']);
		/** @var ActiveRow $newRowContent */
		$newRowContent = $this->database->table($this->contentsRepo->getTableName())->insert($rowContentArray);

		//mld
		$contentDataMld = [];
		/** @var ActiveRow $rowContentMld */
		foreach($this->database->table($this->contentsRepo->getTableName().'_mld')->select('*')->where('contents_id', $contentsId)->fetchAll() as $rowContentMld){
			$rowContentMldArray = $rowContentMld->toArray();
			$rowContentMldArray['contents_id'] = $newRowContent->id;
			$contentDataMld[] = $rowContentMldArray;
		}
		$this->database->table($this->contentsRepo->getTableName().'_mld')->insert($contentDataMld);

		//sections with blocks
		$sectionIds = $this->sectionsRepo->findAll()->where('contents_id', $contentsId)->fetchPairs(NULL, 'id');
		$this->copyContentSections($sectionIds, $newRowContent->id);

		return $this->contentsRepo->findEntity($newRowContent->id);
	}


	/**
	 * @param array $blockIds
	 * @param int $toSectionsId
	 * @return void
	 */
	public function copyContentBlocks(array $blockIds, int $toSectionsId): void
	{
		if(!empty($blockIds)){
			/** @var ActiveRow $rowBlock */
			foreach($this->blocksRepo->findAll()->where('id', $blockIds)->order('pos_order') as $rowBlock){
				$rowBlockArray = $rowBlock->toArray();
				$rowBlockArray['sections_id'] = $toSectionsId;
				unset($rowBlockArray['id']);
				unset($rowBlockArray['pos_order']);
				//insert via repository to recount pos order.
				$this->blocksRepo->insert($rowBlockArray);
			}
		}
	}


	/**
	 * @param array $sectionIds
	 * @param int $toContentsId
	 * @return void
	 */
	public function copyContentSections(array $sectionIds, int $toContentsId): void
	{
		if(!empty($sectionIds)){
			/** @var ActiveRow $rowBlock */
			foreach($this->sectionsRepo->findAll()->where('id', $sectionIds)->order('pos_order') as $rowSection){
				$rowSectionArray = $rowSection->toArray();
				$rowSectionArray['contents_id'] = $toContentsId;
				unset($rowSectionArray['id']);
				unset($rowSectionArray['pos_order']);
				//insert via repository to recount pos order.
				/** @var ActiveRow $newRowSection */
				$newRowSection = $this->sectionsRepo->insert($rowSectionArray);

				//blocks============================================
				$blockIds = $this->blocksRepo->findAll()->where('sections_id', $rowSection->id)->fetchPairs(NULL, 'id');
				$this->copyContentBlocks($blockIds, $newRowSection->id);
			}
		}

	}
}
