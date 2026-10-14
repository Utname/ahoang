<?php

namespace App\AdminModule\Traits;

use Nette\Application\LinkGenerator;
use Nette\Utils\Html;
use Nette\Utils\Strings;

trait BaseTemplatesTrait
{
	/** @var LinkGenerator @inject */
	public $linkGenerator;

	/**
	 * @return array|string[]
	 */
	public function formatLayoutTemplateFiles(): array
	{
		$layout = $this->layout ?: 'layout';

		return [
			APP_DIR."/AdminModule/@$layout.latte"
		];
	}


	/**
	 * Formats view template file names.
	 */
	public function formatTemplateFiles(): array
	{
		$dir = dirname(static::getReflection()->getFileName());
		$dir = is_dir("$dir/templates") ? $dir : dirname($dir);

		return [
			"$dir/templates/$this->view.latte"
		];
	}

	/** @var \PrintListsService @inject */
	public $printListsService;


	/**
	 * @return void
	 */
	public function beforeRender()
	{
		parent::beforeRender();
		$this->template->addFunction('renderMenu', function() {
			$menuGenerator = new \MenuGenerator($this->linkGenerator);
			$menuGenerator->setNameSpace('Admin'); //for link generator
			$menuGenerator->setMenuStructure($this->container->getParameters()['menuStructure']);
			$menuGenerator->setIconClassPrefix('menu-icon');
			$menuGenerator->setTranslator($this->translator);
			$menuGenerator->setAdminPermissionsService($this->aps);
			$menuGenerator->setBadges($this->menuBadges());
			return $menuGenerator->generate()->toHtml();
		});
	}


	/**
	 * Counts next to menu items: lists sent from the phone and waiting to be printed in
	 * the admin's branch (the one chosen in Tisk z mobilu, else the admin's default).
	 * @return array<string, int>
	 */
	protected function menuBadges(): array
	{
		try {
			if (!$this->aps->has('PrintLists_list')) {
				return [];
			}
			$branchesId = (int)($this->getSession('printLists')->branch ?? 0) ?: (int)($this->adminUser->default_branches_id ?? 0);
			$branchesId = $branchesId ?: $this->printListsService->firstBranchId($this->adminUser->is_all_branches_permission ? NULL : $this->adminUser->getBranchesIds());

			return $branchesId ? ['PrintLists' => $this->printListsService->waitingCount($branchesId)] : [];
		}
		catch (\Throwable $e) {
			return [];
		}
	}

}
