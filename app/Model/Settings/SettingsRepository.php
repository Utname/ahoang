<?php

class SettingsRepository extends BaseRepository
{
	protected $tableName = 'settings';
	public $cleanCacheTags = [\CacheTags::SETTINGS];
}
