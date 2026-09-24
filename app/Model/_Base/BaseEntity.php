<?php

declare(strict_types=1);

use \Nette\Database\Table\ActiveRow;
use \Nette\Database\Table\GroupedSelection;
use \Nette\Utils\Strings;
#[\AllowDynamicProperties]
abstract class BaseEntity
{
	/** @var ActiveRow */
	protected $row;

	/** @var ActiveRow */
	protected $rowML;

	/** @var array */
	protected $columns = [];

	/** @var string */
	protected $tableName = '';

	/** @var GlobalContainer */
	protected $globalContainer;

	/** @var LanguagesEntity */
	protected $language = NULL;

	/** @var ContentsEntity */
	protected $content;


	/**
	 * BaseEntity constructor.
	 * @param ActiveRow $row
	 * @param GroupedSelection|NULL $related
	 */
	public function __construct(ActiveRow $row, GroupedSelection $related = NULL)
	{
		if (isset($GLOBALS['container'])) {
			$this->globalContainer = $GLOBALS['container'];
		}

		$this->setRow($row, $related);
	}


	/**
	 * @param string|null $string
	 * @return string|null
	 */
	public static function prepareHtml(?string $string): ?string
	{
		if(!is_null($string)){
			$string = str_replace('<table ', '<div class="table-responsive"><table class="table table-striped"', $string);
			$string = str_replace('</table>', '</table></div>', $string);
			$string = str_replace('<ul>', '<ul class="ul-styled">', $string);
			$string = str_replace('<ol>', '<ol class="ol-styled">', $string);
		}
		return $string;
	}


	/**
	 *
	 */
	protected function prepare()
	{
	}


	/**
	 * @param ActiveRow $row
	 * @param GroupedSelection $related
	 */
	public function setRow(ActiveRow $row, GroupedSelection $related = NULL)
	{
		$values = $row->toArray();

		if ($related) {
			$relatedItems = Strings::endsWith($related->getName(), '_mld') ? $related->where('languages_id', $GLOBALS['lang']) : $related;

			foreach ($relatedItems as $relatedItem) {
				foreach ($relatedItem->toArray() as $key => $val) {
					$values[$key] = $val;
				}
			}
		}

		foreach ($values as $key => $val) {
			$this->$key = $val;
		}

		$this->tableName = $row->getTable()->getName();

		$isML = Strings::endsWith($this->tableName, '_mld');

		$this->row = $isML ? $row->ref(Strings::substring($this->tableName, 0, -4)) : $row;
		$this->rowML = $isML ? $row : (isset($relatedItem) ? $relatedItem : NULL);
		$this->columns = array_keys($values);
		$this->prepare();
	}


	/**
	 * @return string
	 */
	public function getTableName(): string
	{
		return $this->tableName;
	}


	/**
	 * @return ActiveRow
	 */
	public function getRow(): ActiveRow
	{
		return $this->row;
	}


	/**
	 * @return ActiveRow
	 */
	public function getRowML(): ActiveRow
	{
		return $this->rowML;
	}


	/**
	 * @return array
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}


	/**
	 * @param string $column
	 * @param $value
	 */
	public function setValue(string $column, $value)
	{
		if (in_array($column, $this->columns)) {
			$this->$column = $value;
		}
	}


	/**
	 * @param array $pairs
	 */
	public function setValues(array $pairs)
	{
		foreach ($pairs as $column => $value) {
			$this->setValue($column, $value);
		}
	}


	/**
	 * @param bool $normalize
	 * @return array
	 */
	public function getValues(bool $normalize = FALSE): array
	{
		$values = [];
		foreach ($this->columns as $column) {
			$value = $this->$column;
			if ($normalize) {
				if ($column == 'date' || Strings::startsWith($column, 'date_') || Strings::endsWith($column, '_date')) {
					$clone = $value;
					$value = $clone ? $clone->format('d.m.Y') : '';
					$values[$column.'_day'] = $clone ? $clone->format('j') : NULL;
					$values[$column.'_month'] = $clone ? $clone->format('n') : NULL;
					$values[$column.'_year'] = $clone ? $clone->format('Y') : NULL;
				}
				elseif ($column == 'time' || Strings::startsWith($column, 'time_') || Strings::endsWith($column, '_time')) {
					$value = $value ? $value instanceof \Nette\Utils\DateTime ? $value->format('H:i') : $value : '';
				}
				elseif (Strings::endsWith($column, '_ids')) {
					$value = $value ? explode(',', $value) : [];
				}
			}
			$values[$column] = $value;
		}

		return $values;
	}


	/**
	 * @param string $column
	 * @return mixed
	 */
	public function getValue(string $column): mixed
	{
		return isset($this->$column) ? $this->$column : NULL;
	}


	/**
	 * @param string $column
	 * @return array
	 */
	public function getImages(string $column = 'images'): array
	{
		$images = [];
		if(!isset($this->$column)){
			return $images;
		}
		if ($this->$column) {
			foreach (explode(';', $this->$column) as $jsonFile) {
				$file = json_decode($jsonFile);

				if ($file->file && ImagesService::imageExists($file->file)) {
					$images[] = $file;
				}
			}
		}

		return $images;
	}


	/**
	 * @param int $index
	 * @return stdClass|null
	 */
	public function getImage(int $index = 0): ?stdClass
	{
		$images = array_values($this->getImages());

		return (count($images)) ? $images[$index] : NULL;
	}


	/**
	 * @param string $column
	 * @return array
	 */
	public function getFiles(string $column = 'files'): array
	{
		$files = [];
		if(!isset($this->$column)){
			return $files;
		}
		if ($this->$column) {
			foreach (explode(';', $this->$column) as $jsonFile) {
				if (!trim($jsonFile)) {
					continue;
				}

				$file = json_decode($jsonFile);

				if ($file->file && FilesService::fileExists($file->file)) {
					$file->extension = pathinfo($file->file, PATHINFO_EXTENSION);
					$file->formattedFileSize = FilesService::getFormattedFileSize($file->file);
					$files[] = $file;
				}
			}
		}

		return $files;
	}


	/**
	 * @return ContentsEntity|null
	 */
	public function getContent(): ?ContentsEntity
	{
		if (!in_array('contents_id', $this->columns)) {
			return NULL;
		}

		$thisRow = $this->getRowML() ?: $this->getRow();

		if (!$this->content && $this->contents_id && $row = $thisRow->ref('contents', 'contents_id')) {
			$this->content = ContentsRepository::getEntity($row);
		}

		return $this->content;
	}


	/**
	 * @return string
	 */
	public function getContentText(): string
	{
		if ($content = $this->getContent()) {
			foreach ($content->getSections() as $section) {
				foreach ($section->getBlocks() as $block) {
					if ($block->types_id == \Content\Definition::BLOCK_TYPE_TEXT) {
						return $block->getValue('text');
					}
				}
			}
		}

		return '';
	}


	/**
	 * @return string
	 */
	public function getOffsetName(): string
	{
		return ($this->pos_level ? str_repeat('- - - ', $this->pos_level - 1) : '').$this->name;
	}


	/**
	 * @return LanguagesEntity|null
	 */
	public function getLanguage(): ?LanguagesEntity
	{
		if (!$this->language && $this->languages_id && $row = $this->getRowML()->ref('languages')) {
			$this->language = LanguagesRepository::getEntity($row);
		}

		return $this->language;
	}


	/**
	 * @return bool
	 */
	public function canBeDeleted(): bool
	{
		return TRUE;
	}


	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return isset($this->title) && $this->title ? $this->title : $this->name;
	}


	/**
	 * @return string
	 */
	public function getMetaTitle(): string
	{
		return isset($this->meta_title) && $this->meta_title ? $this->meta_title : $this->getTitle();
	}


	/**
	 * @return string
	 */
	public function getMetaDescription(): string
	{
		return isset($this->meta_desc) && $this->meta_desc ? strip_tags($this->meta_desc ?: '') : '';
	}


	/**
	 * @return string
	 */
	public function getMetaImage(): string
	{
		return isset($this->meta_image) && $this->meta_image ? $this->meta_image : (isset($this->image) && $this->image ? $this->image : '');
	}


	/**
	 * @return string
	 */
	public function getBreadcrumbs(): string
	{
		return isset($this->breadcrumb) && $this->breadcrumb ? $this->breadcrumb : $this->name;
	}
}
