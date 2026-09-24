<?php

use \Nette\Utils\Strings;

class AdminPermissionsService
{
	use \Nette\SmartObject;

	/** @var AdminUsersEntity */
	protected $adminUser;

	/** @var string */
	protected $actualModuleId = '';

	/** @var array */
	protected $availableModules;


	/**
	 * AdminPermissionsService constructor.
	 * @param AdminPermissionsRepository $adminPermissionsRepo
	 * @param \Nette\Database\Explorer $db
	 */
	public function __construct(protected AdminPermissionsRepository $adminPermissionsRepo, protected \Nette\Database\Explorer $db)
	{
	}


	/**
	 * @param array|null $availableModules
	 */
	public function setAvailableModules(?array $availableModules): void
	{
		$this->availableModules = $availableModules;
	}


	/**
	 * @param AdminUsersEntity $adminUser
	 */
	public function setAdminUser(AdminUsersEntity $adminUser)
	{
		$this->adminUser = $adminUser;
	}


	/**
	 * @param string $moduleId
	 */
	public function setActualModuleId(string $moduleId)
	{
		$this->actualModuleId = $moduleId;
	}


	/**
	 * @param array $permissionsIds
	 * @return array
	 */
	protected function completePermissionsIds(array $permissionsIds): array
	{
		if (!$this->actualModuleId) {
			return $permissionsIds;
		}

		$fullPermissionsIds = [];
		foreach ($permissionsIds as $permissionsId) {
			if (!Strings::match($permissionsId, '~[A-Z]+[_A-Za-z]+~')) {
				$permissionsId = $this->actualModuleId.'_'.$permissionsId;
			}
			$fullPermissionsIds[] = $permissionsId;
		}

		return $fullPermissionsIds;
	}


	/**
	 * @param $permissionsIds
	 * @return bool
	 */
	public function has($permissionsIds): bool
	{
		if (is_string($permissionsIds)) {
			$permissionsIds = [$permissionsIds];
		}

		$permissionsIds = $this->completePermissionsIds($permissionsIds);
		$adminUsersPermissionsIds = $this->adminUser->getPermissionsIds();

		foreach ($permissionsIds as $permissionsId) {
			if (is_array($this->availableModules) && count($this->availableModules) && !in_array(explode('_', $permissionsId)[0], $this->availableModules)) {
				return FALSE;
			}

			if (!in_array($permissionsId, $adminUsersPermissionsIds)) {
				return FALSE;
			}
		}

		return TRUE;
	}


	/**
	 * @param array $permissionsIds
	 * @return bool
	 */
	public function hasAny(array $permissionsIds): bool
	{
		$permissionsIds = $this->completePermissionsIds($permissionsIds);
		$adminUsersPermissionsIds = $this->adminUser->getPermissionsIds();

		foreach ($permissionsIds as $permissionsId) {
			if (is_array($this->availableModules) && count($this->availableModules) && !in_array(explode('_', $permissionsId)[0], $this->availableModules)) {
				continue;
			}

			if (in_array($permissionsId, $adminUsersPermissionsIds)) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * @param array $modules
	 */
	public function updatePermissionsTable(array $modules)
	{
		$existsPermissions = $this->adminPermissionsRepo->findAll()->fetchPairs('id');

		$this->db->transaction(function() use ($modules, $existsPermissions) {
			foreach ($modules as $moduleId => $moduleInfo) {
				foreach ($moduleInfo['actions'] as $actionId => $actionName) {
					if (!isset($existsPermissions[$moduleId.'_'.$actionName])) {
						$this->adminPermissionsRepo->insert([
							'id' => $moduleId.'_'.$actionName,
							'name' => $actionName,
							'admin_modules_id' => $moduleId
						]);

						$existsPermissions[$moduleId.'_'.$actionName] = [
							'id' => $moduleId.'_'.$actionName,
							'name' => $actionName,
							'admin_modules_id' => $moduleId
						];
					}
					elseif ($existsPermissions[$moduleId.'_'.$actionName]['name'] != $actionName) {
						$this->adminPermissionsRepo->find($moduleId.'_'.$actionName)->update([
							'name' => $actionName
						]);
					}
				}
			}
		});
	}
}