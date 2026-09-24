ALTER TABLE `products_mld` ADD FULLTEXT(`name`);


CREATE TABLE `admin_ip_addresses` (
	`id` int(11) NOT NULL,
	`ip_address` varchar(255) NOT NULL,
	`note` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `admin_ip_addresses`
	ADD PRIMARY KEY (`id`);

ALTER TABLE `admin_ip_addresses`
	MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;



CREATE TABLE `admin_users_branches_rel` (
	`admin_users_id` int(11) NOT NULL,
	`branches_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `admin_users_branches_rel`
	ADD PRIMARY KEY (`admin_users_id`,`branches_id`),
  ADD KEY `branches_id` (`branches_id`);

ALTER TABLE `admin_users_branches_rel`
	ADD CONSTRAINT `admin_users_branches_rel_ibfk_1` FOREIGN KEY (`admin_users_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admin_users_branches_rel_ibfk_2` FOREIGN KEY (`branches_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;


ALTER TABLE `admin_users` ADD `is_all_branches_permission` TINYINT(1) NOT NULL DEFAULT '1' AFTER `admin_roles_id`;