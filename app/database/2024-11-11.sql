CREATE TABLE `print_labels` (
	`id` int(11) NOT NULL,
	`datetime` timestamp NOT NULL DEFAULT current_timestamp(),
	`type_id` tinyint(1) NOT NULL DEFAULT 1,
	`admin_users_id` int(11) NOT NULL,
	`data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `print_labels`
	ADD PRIMARY KEY (`id`),
  ADD KEY `admin_users_id` (`admin_users_id`);

ALTER TABLE `print_labels`
	MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `print_labels`
	ADD CONSTRAINT `print_labels_ibfk_1` FOREIGN KEY (`admin_users_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;