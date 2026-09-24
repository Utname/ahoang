<?php

class ContentSectionsRepository extends BaseRepository
{
	protected $tableName = 'content_sections';

	protected $orderRestrictions = ['contents_id'];
}
