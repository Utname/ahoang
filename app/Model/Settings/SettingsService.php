<?php


class SettingsService
{
	use \Nette\SmartObject;

	/** @var array */
	protected $values;


	/**
	 * @param SettingsRepository $settingsRepo
	 * @param CacheService $cacheService
	 */
	public function __construct(protected SettingsRepository $settingsRepo, protected CacheService $cacheService)
	{
	}


	/**
	 * @param array $values
	 */
	public function setValues(array $values): void
	{
		foreach ($values as $id => $value) {
			if ($row = $this->settingsRepo->find($id)) {
				$row->update(['value' => $value]);
			}
			else {
				$this->settingsRepo->insert([
					'id' => $id,
					'value' => $value
				]);
			}
		}
		$this->cacheService->deleteByTags(\CacheTags::SETTINGS);
	}


	/**
	 * @param string $id
	 * @return string|null
	 */
	public function getValue(string $id): ?string
	{
		$this->loadValues();
		return $this->values[$id] ?? NULL;
	}


	/**
	 * @return array|null
	 */
	public function getValues(): ?array
	{
		$this->loadValues();
		return $this->values;
	}


	/**
	 * @return void
	 */
	public function loadValues(): void
	{
		if (is_null($this->values)) {
			try{
				$this->values = $this->cacheService->load(key: $this->settingsRepo->getTableName(), multilang: FALSE);
			}
			catch(Exception|Throwable $e){
				$this->values = NULL;
			}
			if (is_null($this->values)) {
				$this->values = $this->settingsRepo->findAll()->fetchPairs('id', 'value');
				$this->cacheService->save(key: $this->settingsRepo->getTableName(), data: $this->values, tags: [\CacheTags::SETTINGS], multilang: FALSE);
			}
		}
	}
}
