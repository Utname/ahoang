<?php

use \Nette\Utils\Finder;

class AdminModulesService
{
	use \Nette\SmartObject;

	/**
	 * AdminModulesService constructor.
	 * @param AdminModulesRepository $adminModulesRepo
	 * @param \Nette\Database\Explorer $db
	 */
	public function __construct(protected AdminModulesRepository $adminModulesRepo, protected \Nette\Database\Explorer $db)
	{
	}


	/**
	 * @param array $modules
	 */
	public function updateModulesTable(array $modules)
	{
		$existsModules = $this->adminModulesRepo->findAll()->fetchPairs('id', 'name');

		$this->db->transaction(function() use ($modules, $existsModules) {
			foreach ($modules as $id => $info) {
				if (!isset($existsModules[$id])) {
					$this->adminModulesRepo->insert([
						'id' => $id,
						'name' => $info['name']
					]);

					$existsModules[$id] = $info['name'];
				}
				elseif ($existsModules[$id] != $info['name']) {
					$this->adminModulesRepo->find($id)->update([
						'name' => $info['name']
					]);
				}
			}
		});
	}


	/**
	 * @return array
	 */
	public static function loadModules(\Nette\Localization\Translator $translator): array
	{
		$modules = [];

		foreach (Finder::findFiles('*Presenter.php')->from(APP_DIR.'/AdminModule') as $file) {
			$moduleId = str_replace('Presenter.php', '', $file->getBasename());
			$modulePresenterName = 'App\\AdminModule\\Presenters\\'.$moduleId.'Presenter';
			$class = new ReflectionClass($modulePresenterName);

			if (!class_exists($modulePresenterName) || $class->isAbstract()) {
				continue;
			}

			$modulePresenter = new $modulePresenterName();

			if (!method_exists($modulePresenter, 'getModuleName')) {
				continue;
			}

			if (!$moduleNameId = $modulePresenter->getModuleName()) {
				continue;
			}

			$moduleName = $translator->translate('admin.modules.'.$moduleNameId);

			if ($moduleName == 'modules.'.$moduleNameId) {
				continue;
			}

			$modules[$moduleId] = [
				'name' => $moduleName,
				'actions' => $modulePresenter->getModuleActions()
			];
		}

		return $modules;
	}
}
