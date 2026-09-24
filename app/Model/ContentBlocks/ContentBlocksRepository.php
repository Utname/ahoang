<?php

class ContentBlocksRepository extends BaseRepository
{
	protected $tableName = 'content_blocks';

	protected $orderRestrictions = ['sections_id'];
}
