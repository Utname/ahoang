CREATE TABLE `nutrition_values` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`name` varchar(255) NOT NULL,
	`unit` varchar(32) NOT NULL DEFAULT '',
	`pos_order` float NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `product_nutrition_values` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`products_id` int(11) NOT NULL,
	`nutrition_values_id` int(11) NOT NULL,
	`value` varchar(64) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	UNIQUE KEY `products_nutrition` (`products_id`, `nutrition_values_id`),
	KEY `nutrition_values_id` (`nutrition_values_id`),
	CONSTRAINT `product_nutrition_values_ibfk_1` FOREIGN KEY (`products_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `product_nutrition_values_ibfk_2` FOREIGN KEY (`nutrition_values_id`) REFERENCES `nutrition_values` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

INSERT INTO `nutrition_values` (`name`, `unit`, `pos_order`) VALUES
	('Energetická / výhřevnost', 'kJ', 1),
	('Energetická / výhřevnost', 'kcal', 2),
	('Tuk', 'g', 3),
	('z toho: nasycené mastné kyseliny', 'g', 4),
	('Sacharidy', 'g', 5),
	('z toho cukr', 'g', 6),
	('Protein', 'g', 7),
	('Sůl', 'g', 8);
