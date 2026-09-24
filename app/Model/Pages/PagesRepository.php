<?php

use Nette\Database\IRow;

class PagesRepository extends BaseTreeRepositoryML
{
	protected $tableName = 'pages';


	/**
	 * @param BaseEntity $entity
	 * @return string
	 */
	public function getUrl(BaseEntity $entity): string
	{
		if ($entity->link) {
			return $entity->link;
		}

		if ($entity->getLanguage()->is_default && $entity->system === 'home') {
			return '/';
		}

		$parts = [];
		foreach ($this->getParents($entity) as $parent) {
			$parts[] = $parent->slug;
		}
		$parts[] = $entity->slug;

		return ($GLOBALS['container']->isMultilang() ? ('/'.$entity->languages_id) : '').'/'.implode('/', $parts);
	}


}
