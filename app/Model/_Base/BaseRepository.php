<?php

declare(strict_types=1);

use \Nette\Database\Table\Selection;
use \Nette\Database\Table\ActiveRow;
use \Nette\Database\Table\GroupedSelection;
use \Nette\Database\IRow;
use \Nette\Utils\Finder;
use Nette\Utils\Strings;

abstract class BaseRepository
{
	use \Nette\SmartObject;

	/** @var string */
	protected $tableName = '';

	/** @var array */
	protected $columnNames = [];

	/** @var array */
	protected $orderRestrictions = [];

	/** @var null|int */
	protected $minOrder = NULL;

	/** @var null|int */
	protected $maxOrder = NULL;

	/** @var bool */
	protected $isML = FALSE;

	/** @var array  */
	public $cleanCacheTags = [];


	/**
	 * BaseRepository constructor.
	 * @param \Nette\Database\Explorer $db
	 */
	public function __construct(protected \Nette\Database\Explorer $db)
	{
		if ($this->tableName === '') {
			throw new Nette\InvalidStateException("Table name is not defined in ".$this->reflection->getName()."::\$tableName");
		}

		$this->columnNames = $this->getTableColumnNames($this->tableName);
		if ($this->isML) {
			$this->columnNames = array_merge($this->columnNames, $this->getTableColumnNames($this->tableName.'_mld'));
		}
	}


	/**
	 * @return string
	 */
	public function getTableName(): string
	{
		return $this->tableName;
	}


	/**
	 * @param string $tableName
	 * @return array
	 */
	public function getTableColumnNames(string $tableName): array
	{
		$columnNames = [];
		$columns = $this->db->getStructure()->getColumns($tableName);
		foreach ($columns as $column) {
			$columnNames[] = $column['name'];
		}

		return $columnNames;
	}


	/**
	 * @return array
	 */
	public function getColumnNames(): array
	{
		return $this->columnNames;
	}


	/**
	 * @param Selection $selection
	 * @param string $keyColumn
	 * @return array
	 */
	public static function getEntities(Selection $selection, string $keyColumn = 'id'): array
	{
		$entities = [];
		foreach ($selection as $row) {
			$entities[$row->$keyColumn ? $row->$keyColumn : $row->id] = self::getEntity($row);
		}

		return $entities;
	}


	/**
	 * @param Selection $selection
	 * @param string $keyColumn
	 * @return array
	 */
	public static function getGroupedEntitiesByKeyColumn(Selection $selection, string $keyColumn): array
	{
		$entities = [];
		foreach ($selection as $row) {
			$entities[$row->$keyColumn][] = self::getEntity($row);
		}

		return $entities;
	}


	/**
	 * @param ActiveRow $row
	 * @param GroupedSelection|NULL $related
	 * @return mixed
	 */
	public static function getEntity(ActiveRow $row, GroupedSelection $related = NULL)
	{
		$entityName = str_replace('Repository', 'Entity', get_called_class());

		return new $entityName($row, $related);
	}


	/**
	 * @return Selection
	 */
	protected function getTable(): Selection
	{
		return $this->db->table($this->tableName)->select($this->tableName.'.*');
	}


	/**
	 * @return Selection
	 */
	public function findAll(): Selection
	{
		return $this->getTable();
	}


	/**
	 * @param null $order
	 * @return Selection
	 */
	public function findAllActive($order = NULL): Selection
	{
		$selection = $this->findAll();
		if($this->isWithActiveState()){
			$selection->where('is_active');
		}
		if ($this->isWithActiveDate()) {
			$selection->where('(is_active_from <= ? OR is_active_from IS NULL)', date('Y-m-d'))
				->where('(is_active_to >= ? OR is_active_to IS NULL)', date('Y-m-d'));
		}

		if ($order) {
			$selection->order($order);
		}

		return $selection;
	}


	/**
	 * @param null $order
	 * @return array
	 */
	public function findAllActiveEntities($order = NULL): array
	{
		$selection = $this->findAllActive();
		if ($order) {
			$selection->order($order);
		}
		if ($selection->count()) {
			return self::getEntities($selection);
		}

		return [];
	}


	/**
	 * @param null $order
	 * @return array
	 */
	public function findAllEntities($order = NULL): array
	{
		$selection = $this->findAll();

		if ($order) {
			$selection->order($order);
		}

		return self::getEntities($selection);
	}


	/**
	 * @param array $by
	 * @return Selection
	 */
	public function findBy(array $by): Selection
	{
		return $this->findAll()->where($by);
	}


	/**
	 * @param array $by
	 * @return array
	 */
	public function findEntitiesBy(array $by): array
	{
		$selection = $this->findBy($by);
		if ($selection->count()) {
			return self::getEntities($selection);
		}

		return [];
	}


	/**
	 * @param array $by
	 * @return Selection
	 */
	public function findActiveBy(array $by): Selection
	{
		return $this->findAllActive()->where($by);
	}


	/**
	 * @param array $by
	 * @return array
	 */
	public function findActiveEntitiesBy(array $by): array
	{
		$selection = $this->findActiveBy($by);
		if ($selection->count()) {
			return self::getEntities($selection);
		}

		return [];
	}


	/**
	 * @param string $email
	 * @return Selection
	 */
	public function findByEmail(string $email): Selection
	{
		return $this->findBy(['email' => $email]);
	}


	/**
	 * @param array $by
	 * @return ActiveRow|null
	 */
	public function findOneBy(array $by): ?ActiveRow
	{
		if ($row = $this->findBy($by)->limit(1)->fetch()) {
			return $row;
		}

		return NULL;
	}


	/**
	 * @param string $email
	 * @return ActiveRow|null
	 */
	public function findOneByEmail(string $email): ?ActiveRow
	{
		return $this->findOneBy(['email' => $email]);
	}


	/**
	 * @param string $system
	 * @return ActiveRow|null
	 */
	public function findOneBySystem(string $system): ?ActiveRow
	{
		return $this->findOneBy(['system' => $system]);
	}


	/**
	 * @param $id
	 * @return ActiveRow|null
	 */
	public function find($id): ?ActiveRow
	{
		return $this->findOneBy(['id' => $id]);
	}


	/**
	 * @return BaseEntity|null
	 */
	public function findDefault(): ?BaseEntity
	{
		return $this->findEntityBy([
			'is_default' => TRUE
		]);
	}


	/**
	 * @param $id
	 * @return BaseEntity|null
	 */
	public function findEntity($id): ?BaseEntity
	{
		if ($row = $this->find($id)) {
			return self::getEntity($row);
		}

		return NULL;
	}


	/**
	 * @param array $by
	 * @return BaseEntity|null
	 */
	public function findEntityBy(array $by): ?BaseEntity
	{
		if ($row = $this->findBy($by)->limit(1)->fetch()) {
			return self::getEntity($row);
		}

		return NULL;
	}


	/**
	 * @param array $by
	 * @return BaseEntity|null
	 */
	public function findActiveEntityBy(array $by): ?BaseEntity
	{
		if ($row = $this->findActiveBy($by)->limit(1)->fetch()) {
			return self::getEntity($row);
		}

		return NULL;
	}


	/**
	 * @param string $email
	 * @return BaseEntity|null
	 */
	public function findEntityByEmail(string $email): ?BaseEntity
	{
		return $this->findEntityBy(['email' => $email]);
	}


	/**
	 * @param int $id
	 * @param string $hash
	 * @return BaseEntity|null
	 */
	public function findEntityByIdHash(int $id, string $hash): ?BaseEntity
	{
		return $this->findEntityBy(['id' => $id, 'hash' => $hash]);
	}


	/**
	 * @param string $system
	 * @return BaseEntity|null
	 */
	public function findEntityBySystem(string $system): ?BaseEntity
	{
		return $this->findEntityBy(['system' => $system]);
	}


	/**
	 * @param array $values
	 * @return bool|int|ActiveRow
	 */
	public function simpleInsert(array $values)
	{
		$set = array_intersect_key($values, array_flip($this->getTableColumnNames($this->tableName)));

		return $this->getTable()->insert($set);
	}


	/**
	 * @param array
	 * @return IRow
	 */
	public function insert(array $values): IRow
	{
		if ($this->isSortable()) {
			$values['pos_order'] = $this->getMaxOrder() + 1;
		}

		$result = $this->simpleInsert($values);

		if ($this->isSortable()) {
			$this->recountOrder();
		}

		return $result;
	}


	/**
	 * @param BaseEntity $entity
	 * @return bool
	 */
	public function update(BaseEntity $entity): bool
	{
		if ($entity->id) {
			return $entity->getRow()->update($entity->getValues());
		}

		return FALSE;
	}


	/**
	 * @param array $values
	 * @param string|NULL $tableName
	 * @return void
	 */
	public function insertOrUpdate(array $values, string $tableName = NULL)
	{
		if (!$tableName) {
			$tableName = $this->tableName;
		}

		foreach ($values as $value) {
			$multidimensional = is_array($value);
			break;
		}

		$tableColumns = array_flip($this->getTableColumnNames($tableName));

		if ($multidimensional) {
			$rows = [];

			foreach ($values as $subValues) {
				$keys = [];
				$vals = [];
				$updatePars = [];

				$subValues = array_intersect_key($subValues, $tableColumns);

				foreach ($subValues as $key => $value) {
					$keys[] = $key;
					$vals[] = is_null($value) ? "NULL" : "'".$value."'";
					$updatePars[] = '`'.$key.'` = VALUES(`'.$key.'`)';
				}

				$rows[] = '('.implode(',', $vals).')';
			}
			$this->db->query('INSERT INTO `'.$tableName.'` (`'.implode('`,`', $keys).'`) VALUES '.implode(', ', $rows).' ON DUPLICATE KEY UPDATE '.implode(',', $updatePars));
		}
		else {
			$pairs = [];

			$values = array_intersect_key($values, $tableColumns);

			foreach ($values as $key => $value) {
				$pairs[] = "`$key` = ?";
			}

			$pairs = implode(', ', $pairs);
			$values = array_values($values);

			$this->db->queryArgs('INSERT INTO `'.$tableName.'` SET '.$pairs.' ON DUPLICATE KEY UPDATE '.$pairs, array_merge($values, $values));
		}
	}


	/**
	 * @param $id
	 * @return bool
	 */
	public function delete($id): bool
	{
		if ($row = $this->db->table($this->tableName)->get($id)) {
			$row->delete();
			if ($this->isSortable()) {
				$this->recountOrder();
			}

			return TRUE;
		}

		return FALSE;
	}


	/**
	 * @return bool
	 */
	public function isSortable()
	{
		return in_array('pos_order', $this->columnNames);
	}


	/**
	 * @param bool $refresh
	 * @return int
	 */
	public function getMaxOrder(bool $refresh = FALSE): int
	{
		if (!$this->maxOrder || $refresh) {
			$this->maxOrder = intval($this->db->table($this->tableName)
				->select('MAX(pos_order) AS pos_order')
				->limit(1)
				->fetch()->pos_order);
		}

		return $this->maxOrder;
	}


	/**
	 * @param bool $refresh
	 * @return int
	 */
	public function getMinOrder(bool $refresh = FALSE): int
	{
		if (!$this->minOrder || $refresh) {
			$this->minOrder = intval($this->db->table($this->tableName)
				->select('MIN(pos_order) AS pos_order')
				->limit(1)
				->fetch()->pos_order);
		}

		return $this->minOrder;
	}


	/**
	 *
	 */
	public function recountOrder()
	{

		if (count($this->orderRestrictions)) {

			$mysqlVariables = [];
			$mysqlConditions = [];
			$mysqlReset = [];
			foreach ($this->orderRestrictions as $orderRestriction) {
				$mysqlVariables[] = '@'.$orderRestriction.'PrevId = 0';
				$mysqlConditions[] = '@'.$orderRestriction.'PrevId != '.$orderRestriction;
				$mysqlReset[] = '(@'.$orderRestriction.'PrevId := '.$orderRestriction.')*0';
			}

			$this->db->query("SET ".implode(", ", $mysqlVariables).", @order := 0");
			$this->db->query("UPDATE `{$this->tableName}` SET 
									pos_order =
 										IF( ".implode(" OR ", $mysqlConditions).",
										    ( @order := 1 ) + (".implode(" + ", $mysqlReset)."),
										    ( @order := @order + 1)
										  )
										 
										 ORDER BY ".implode(", ", $this->orderRestrictions).", pos_order");
		}
		else {
			$this->db->query("SET @order = 0");
			$this->db->query("UPDATE `{$this->tableName}` SET pos_order = @order := @order + 1 ORDER BY pos_order");
		}
	}


	/**
	 * @param $id
	 */
	public function moveUp($id)
	{
		if ($row = $this->find($id)) {
			$entity = $this->getEntity($row);
			$entity->setValue('pos_order', $entity->pos_order - 1.5);
			$this->update($entity);
		}

		$this->recountOrder();
	}


	/**
	 * @param $id
	 */
	public function moveDown($id)
	{
		if ($row = $this->find($id)) {
			$entity = $this->getEntity($row);
			$entity->setValue('pos_order', $entity->pos_order + 1.5);
			$this->update($entity);
		}

		$this->recountOrder();
	}


	/**
	 * @param $id
	 * @param $aboveId
	 */
	public function moveAbove($id, $aboveId)
	{
		if (($row = $this->find($id)) && ($aboveRow = $this->find($aboveId))) {
			$entity = $this->getEntity($row);
			$aboveEntity = $this->getEntity($aboveRow);
			$entity->setValue('pos_order', $aboveEntity->pos_order - 0.5);
			$this->update($entity);
		}

		$this->recountOrder();
	}


	/**
	 * @param $id
	 * @param $belowId
	 */
	public function moveBelow($id, $belowId)
	{
		if (($row = $this->find($id)) && ($belowRow = $this->find($belowId))) {
			$entity = $this->getEntity($row);
			$belowEntity = $this->getEntity($belowRow);
			$entity->setValue('pos_order', $belowEntity->pos_order + 0.5);
			$this->update($entity);
		}

		$this->recountOrder();
	}


	/**
	 * @param string $columnName
	 * @return bool
	 */
	public function containsColumn(string $columnName): bool
	{
		return in_array($columnName, $this->columnNames);
	}


	/**
	 * @return bool
	 */
	public function isMultilevel(): bool
	{
		return $this->containsColumn('pos_level');
	}


    /**
     * @return bool
     */
    public function isWithSystem(): bool
    {
        return $this->containsColumn('system');
    }


    /**
	 * @return bool
	 */
	public function isWithImages()
	{
		return $this->containsColumn('images');
	}


	/**
	 * @return bool
	 */
	public function isWithFiles()
	{
		return $this->containsColumn('files');
	}


	/**
	 * @return bool
	 */
	public function isWithActiveState()
	{
		return $this->containsColumn('is_active');
	}


	/**
	 * @return bool
	 */
	public function isWithActiveDate()
	{
		return $this->containsColumn('is_active_from') && $this->containsColumn('is_active_to');
	}


	/**
	 * @return bool
	 */
	public function isWithMetaImage()
	{
		return $this->containsColumn('meta_image');
	}


	/**
	 * @return bool
	 */
	public function isWithMetaTitle()
	{
		return $this->containsColumn('meta_title');
	}


	/**
	 * @return bool
	 */
	public function isWithMetaDesc()
	{
		return $this->containsColumn('meta_desc');
	}


	/**
	 * @param BaseEntity $entity
	 * @param string $tempFolder
	 * @throws \Nette\Utils\UnknownImageFileException
	 */
	public function addImages(BaseEntity $entity, string $tempFolder)
	{
		$tempDirectory = FS_TEMP.'/'.$tempFolder;
		$images = [];

		if ($tempFolder && file_exists($tempDirectory)) {
			/** @var SplFileInfo $file */
			foreach (Finder::findFiles('*')->in($tempDirectory) as $file) {
				$fileName = $file->getFilename();

				if ($fileObj = ImagesService::saveImage($file)) {
					$images[] = json_encode($fileObj);
				}

				unlink($tempDirectory.'/'.$fileName);
			}

			rmdir($tempDirectory);
		}

		if (count($images)) {
			$entity->images .= ($entity->images ? '; ' : '').implode('; ', $images);
			$this->update($entity);
		}
	}


	/**
	 * @param BaseEntity $entity
	 * @param string $tempFolder
	 */
	public function addFiles(BaseEntity $entity, string $tempFolder)
	{
		$tempDirectory = FS_TEMP.'/'.$tempFolder;
		$files = [];

		if ($tempFolder && file_exists($tempDirectory)) {
			foreach (Finder::findFiles('*')->in($tempDirectory) as $key => $file) {

				if ($fileObj = FilesService::saveFile($tempDirectory, $file)) {
					$files[] = json_encode($fileObj);
				}

				unlink($tempDirectory.'/'.$file->getFilename());
			}
			rmdir($tempDirectory);
		}

		if (count($files)) {
			$entity->files .= ($entity->files ? '; ' : '').implode('; ', $files);
			$this->update($entity);
		}
	}


	/**
	 * @param BaseEntity $entity
	 * @param int $id
	 */
	public function deleteImage(BaseEntity $entity, int $id)
	{
		$image = $this->db->table('images')
			->where('rel_table', $this->tableName)
			->where('rel_id', $entity->id)
			->where('id', $id)
			->limit(1)
			->fetch();

		$fileDirectory = FS_DIR.'/images/'.$entity->images_directory;

		if ($entity->images_directory && file_exists($fileDirectory.'/'.$image->filename)) {
			unlink($fileDirectory.'/'.$image->filename);
		}

		$image->delete();

		$this->recountImagesOrder($entity);
	}


	/**
	 * @param int $relId
	 * @return int
	 */
	public function getImagesMaxPosOrder(int $relId): int
	{
		return intval($this->db->table('images')
			->select('MAX(pos_order) AS pos_order')
			->where('rel_table', $this->tableName)
			->where('rel_id', $relId)
			->limit(1)
			->fetch()->pos_order);
	}


	/**
	 * @param BaseEntity $entity
	 */
	public function recountImagesOrder(BaseEntity $entity)
	{
		$order = 1;
		$files = [];

		$rows = $this->db->table('images')
			->where('rel_table', $this->tableName)
			->where('rel_id', $entity->id)
			->order('pos_order');

		foreach ($rows as $item) {
			$item->update(['pos_order' => $order++]);
			$files[] = $item->filename;
		}

		$entity->images_files = implode('; ', $files);
		$this->update($entity);
	}


	/**
	 * @return bool
	 */
	public static function isMultilang(): bool
	{
		$extendClass = get_parent_class(get_called_class());

		return Strings::endsWith($extendClass, 'ML');
	}

	/**
	 * @return array
	 */
	public function getOrderRestrictions(): array
	{
		return $this->orderRestrictions;
	}


	/**
	 * @return string|null
	 */
	public function getContentsTableName(): ?string
	{
		$contentsTableName = NULL;

		$columnNames = $this->getTableColumnNames($this->tableName);
		if(in_array('contents_id', $columnNames)){
			$contentsTableName = $this->tableName;
		}
		if ($this->isML) {
			$columnNames = $this->getTableColumnNames($this->tableName.'_mld');
			if(in_array('contents_id', $columnNames)){
				$contentsTableName = $this->tableName.'_mld';
			}
		}
		return $contentsTableName;
	}
}
