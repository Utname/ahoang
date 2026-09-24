<?php


class AdminRolesEntity extends BaseEntity
{
	/**
	 * @return array
	 */
	public function getPermissionsIds(): array
	{
		return $this->getRow()
			->related('admin_roles_admin_permissions_rel', 'admin_roles_id')
			->fetchPairs(NULL, 'admin_permissions_id');
	}
}