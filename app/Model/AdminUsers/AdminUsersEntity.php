<?php


class AdminUsersEntity extends BaseEntity
{
	/** @var AdminRolesEntity */
	protected $adminRole;

	/** @var array */
	protected $permissionsIds = NULL;


	/**
	 * @return AdminRolesEntity|null
	 */
	public function getRole(): ?AdminRolesEntity
	{
		if (!$this->adminRole && $this->admin_roles_id && $row = $this->getRow()->ref('admin_roles')) {
			$this->adminRole = AdminRolesRepository::getEntity($row);
		}

		return $this->adminRole;
	}


	/**
	 * @return array
	 */
	public function getPermissionsIds(): array
	{
		if (is_null($this->permissionsIds)) {
			$this->permissionsIds = $this->getRole()?->getPermissionsIds();
		}

		return $this->permissionsIds;
	}


	/**
	 * @return array
	 */
	public function getBranchesIds(): array
	{
		return $this->getRow()->related('admin_users_branches_rel', 'admin_users_id')->fetchPairs(NULL, 'branches_id');
	}
}