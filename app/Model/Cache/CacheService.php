<?php

use Nette\Caching\Cache;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class CacheService
{
	use \Nette\SmartObject;

	/** @var Cache */
	protected $cache;

	/**
	 * CacheService constructor.
	 */
	public function __construct(\Nette\Caching\Storage $storage)
	{
		$this->cache = new Cache($storage);
	}


	/**
	 * @param mixed $cacheTags
	 * @return bool
	 */
	public function deleteByTags(mixed $cacheTags): bool
	{
		if(is_array($cacheTags) && count($cacheTags)){
			$this->getCache()->clean([
				Cache::TAGS => $cacheTags
			]);
			return TRUE;
		}
		elseif(is_string($cacheTags) || is_numeric($cacheTags)){
			$this->getCache()->clean([
				Cache::TAGS => [$cacheTags]
			]);
			return TRUE;
		}
		return FALSE;
	}


	/**
	 * @param string $key
	 * @param bool $multilang
	 * @return void
	 */
	public function deleteByKey(string $key, bool $multilang = TRUE)
	{
		$this->getCache()->remove($this->prepareKey($key, $multilang));
	}


	/**
	 * @param string $key
	 * @param mixed $data
	 * @param array $tags
	 * @param mixed $expire
	 * @param bool $multilang
	 * @return mixed
	 */
	public function save(string $key, mixed $data, array $tags, mixed $expire = '24 hours', bool $multilang = TRUE): mixed
	{
		return $this->getCache()->save($this->prepareKey($key, $multilang), $data, [
			Cache::TAGS => $tags,
			Cache::EXPIRE => $expire
		]);
	}


	/**
	 * @param string $key
	 * @param Selection $selection
	 * @param array $tags
	 * @param string|NULL $saveByKey
	 * @param mixed $expire
	 * @param bool $multilang
	 * @return mixed
	 */
	public function saveDatabaseSelection(string $key, Selection $selection, array $tags, string $saveByKey = NULL, mixed $expire = '24 hours', bool $multilang = TRUE): mixed
	{
		$data = [];
		foreach($selection->fetchAll() as $fetchKey => $activeRow){
			$activeRowData = (object)$activeRow->toArray();
			$data[is_null($saveByKey) ? $fetchKey : $activeRowData->{$saveByKey}] = $activeRowData;
		}
		return $this->save($key, $data, $tags, $expire, $multilang);
	}


	/**
	 * @param string $key
	 * @param bool $multilang
	 * @return mixed
	 * @throws Throwable
	 */
	public function load(string $key, bool $multilang = TRUE): mixed
	{
		return $this->getCache()->load($this->prepareKey($key, $multilang));
	}


	/**
	 * @param string $key
	 * @param bool $multilang
	 * @return string
	 */
	public function prepareKey(string $key, bool $multilang = TRUE): string
	{
		$cacheKeyParts = [
			$key
		];

		if($multilang){
			$cacheKeyParts[] = $GLOBALS['lang'];
		}

		return implode('__', $cacheKeyParts);
	}


	/**
	 * @return Cache
	 */
	public function getCache(): Cache
	{
		return $this->cache;
	}



}
