<?php

/**
 * Idempotent schema changes, safe to run on every start (docker/entrypoint.sh) and
 * on MySQL as well as MariaDB (no "ADD COLUMN IF NOT EXISTS").
 * Connection: same environment variables as bin/sync-print-templates.php.
 */

const COLUMNS = [
	// "Admin - Produkty v2": Rozmrazeno is a product flag, shared by all its labels
	['products', 'rozmrazeno', 'TINYINT(1) NOT NULL DEFAULT 0'],
	// "Cena za" unit printed next to the price: Kč, Kč/ks, Kč/balení, Kč/kg, Kč/100 g, Kč/l
	['products', 'price_unit', "VARCHAR(20) NOT NULL DEFAULT 'Kč'"],
	// per-label Složení override (✎ Upravit); NULL = use the product's ingredients
	['product_labels', 'ingredients', 'TEXT NULL'],
	// per-label "Cena za" override; NULL = products.price_unit
	['product_labels', 'price_unit', 'VARCHAR(20) NULL'],
	// Výkup lahví: voucher number prefix of the branch ("DUB" → DUB-V261008-007)
	['branches', 'vykup_code', 'VARCHAR(6) NULL'],
];

// product search by EAN (Produkty list): without an index every search scanned the table
const INDEXES = [
	['products', 'products_code_ean', 'code_ean'],
	['product_codes', 'product_codes_value', 'value'],
	// GlobalContainer::getImageData() looks images up by name
	['files_data', 'files_data_type_file', 'types_id, file'],
];

// Výkup lahví (design_handoff_fikko/Vykup lahvi.dc.html, vykup-lahvi/README-TICH-HOP.md)
const TABLES = [
	'vykup_staff' => "CREATE TABLE vykup_staff (
		id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		branches_id INT NOT NULL,
		name VARCHAR(60) NOT NULL,
		active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		UNIQUE KEY uq_branch_name (branches_id, name)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	'vykup_items' => "CREATE TABLE vykup_items (
		id VARCHAR(20) PRIMARY KEY,
		name_cs VARCHAR(80) NOT NULL,
		name_vi VARCHAR(80) NOT NULL,
		ean8 CHAR(8) NOT NULL UNIQUE,
		price_kc SMALLINT UNSIGNED NOT NULL,
		sort TINYINT NOT NULL DEFAULT 0,
		active TINYINT(1) NOT NULL DEFAULT 1
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	'vykup_vouchers' => "CREATE TABLE vykup_vouchers (
		id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		branches_id INT NOT NULL,
		issue_date DATE NOT NULL,
		day_seq SMALLINT UNSIGNED NOT NULL,
		voucher_no VARCHAR(24) NOT NULL,
		vykup_staff_id INT UNSIGNED NOT NULL,
		admin_users_id INT NULL,
		total_kc INT UNSIGNED NOT NULL,
		status ENUM('valid','void') NOT NULL DEFAULT 'valid',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		voided_at DATETIME NULL,
		voided_by INT NULL,
		UNIQUE KEY uq_no (voucher_no),
		UNIQUE KEY uq_day_seq (branches_id, issue_date, day_seq),
		KEY ix_branch_date (branches_id, issue_date)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	'vykup_voucher_lines' => "CREATE TABLE vykup_voucher_lines (
		vykup_vouchers_id INT UNSIGNED NOT NULL,
		vykup_items_id VARCHAR(20) NOT NULL,
		qty MEDIUMINT UNSIGNED NOT NULL,
		price_kc SMALLINT UNSIGNED NOT NULL,
		PRIMARY KEY (vykup_vouchers_id, vykup_items_id),
		CONSTRAINT fk_vykup_line_voucher FOREIGN KEY (vykup_vouchers_id) REFERENCES vykup_vouchers(id),
		CONSTRAINT fk_vykup_line_item FOREIGN KEY (vykup_items_id) REFERENCES vykup_items(id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	'vykup_reprints' => "CREATE TABLE vykup_reprints (
		id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		vykup_vouchers_id INT UNSIGNED NOT NULL,
		vykup_staff_id INT UNSIGNED NULL,
		admin_users_id INT NULL,
		printed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		KEY ix_voucher (vykup_vouchers_id),
		CONSTRAINT fk_vykup_reprint_voucher FOREIGN KEY (vykup_vouchers_id) REFERENCES vykup_vouchers(id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	'vykup_daily_close' => "CREATE TABLE vykup_daily_close (
		branches_id INT NOT NULL,
		close_date DATE NOT NULL,
		app_lahev INT NOT NULL, app_prepravka INT NOT NULL, app_kc INT NOT NULL,
		pos_lahev INT NOT NULL, pos_prepravka INT NOT NULL, pos_kc INT NOT NULL,
		ok TINYINT(1) NOT NULL,
		admin_users_id INT NULL,
		closed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (branches_id, close_date)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

// rows that must exist (INSERT IGNORE: an edited row is left alone)
const ROWS = [
	// bottle / crate: EAN-8 printed on the voucher = code set up in GoKasa (check digit included)
	"INSERT IGNORE INTO vykup_items (id, name_cs, name_vi, ean8, price_kc, sort) VALUES
		('lahev', 'Vrácená láhev', 'Chai bia 0,5 l', '00000819', 3, 1),
		('prepravka', 'Vrácená přepravka', 'Két bia', '00000802', 100, 2)",
	// voucher header (editable in Výkup lahví › Pracovníci)
	"INSERT IGNORE INTO settings (id, value) VALUES ('vykup_company', 'Fikko market s.r.o.'), ('vykup_ico', '')",
	// admin module, permissions, granted to the Administrátor role
	"INSERT IGNORE INTO admin_modules (id, created_at, name) VALUES ('Vykup', NOW(), 'Výkup lahví')",
	"INSERT IGNORE INTO admin_permissions (id, created_at, name, admin_modules_id) VALUES
		('Vykup_list', NOW(), 'list', 'Vykup'), ('Vykup_edit', NOW(), 'edit', 'Vykup')",
	"INSERT IGNORE INTO admin_roles_admin_permissions_rel (admin_roles_id, admin_permissions_id)
		SELECT 1, id FROM admin_permissions WHERE id IN ('Vykup_list', 'Vykup_edit')
		AND EXISTS (SELECT 1 FROM admin_roles WHERE id = 1)
		AND NOT EXISTS (SELECT 1 FROM admin_roles_admin_permissions_rel WHERE admin_roles_id = 1 AND admin_permissions_id = admin_permissions.id)",
];

$env = static fn(string $name, string $default): string => getenv($name) !== FALSE && getenv($name) !== '' ? getenv($name) : $default;
$database = $env('MYSQLDATABASE', 'website');
$pdo = new PDO(
	sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env('MYSQLHOST', '127.0.0.1'), $env('MYSQLPORT', '3306'), $database),
	$env('MYSQLUSER', 'root'),
	$env('MYSQLPASSWORD', 'toor'),
	[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
$tableExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');

foreach (COLUMNS as [$table, $column, $definition]) {
	$exists->execute([$database, $table, $column]);
	if ((int)$exists->fetchColumn() > 0) {
		printf("%s.%s exists\n", $table, $column);
		continue;
	}
	$pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
	printf("%s.%s added\n", $table, $column);
}

$indexExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?');
foreach (INDEXES as [$table, $index, $columns]) {
	$indexExists->execute([$database, $table, $index]);
	if ((int)$indexExists->fetchColumn() > 0) {
		printf("index %s.%s exists
", $table, $index);
		continue;
	}
	$pdo->exec(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (%s)', $table, $index, $columns));
	printf("index %s.%s added
", $table, $index);
}

foreach (TABLES as $table => $sql) {
	$tableExists->execute([$database, $table]);
	if ((int)$tableExists->fetchColumn() > 0) {
		printf("table %s exists\n", $table);
		continue;
	}
	$pdo->exec($sql);
	printf("table %s created\n", $table);
}

foreach (ROWS as $sql) {
	$pdo->exec($sql);
}

// voucher prefix per branch: first 3 letters of the branch name ("Dubí" → DUB),
// with the branch id added when two branches would share it (Most, Žatec, Chomutov)
$branches = $pdo->query("SELECT b.id, b.vykup_code, COALESCE(m.name, '') name FROM branches b LEFT JOIN branches_mld m ON m.branches_id = b.id AND m.languages_id = 'cs' ORDER BY b.id")->fetchAll(PDO::FETCH_ASSOC);
$base = [];
foreach ($branches as $b) {
	$ascii = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $b['name'])));
	$base[$b['id']] = substr($ascii ?: 'B', 0, 3);
}
$counts = array_count_values($base);
$set = $pdo->prepare('UPDATE branches SET vykup_code = ? WHERE id = ? AND (vykup_code IS NULL OR vykup_code = \'\')');
foreach ($branches as $b) {
	if ((string)$b['vykup_code'] === '') {
		$code = $counts[$base[$b['id']]] > 1 ? substr($base[$b['id']], 0, 3) . $b['id'] : $base[$b['id']];
		$set->execute([substr($code, 0, 6), $b['id']]);
	}
}
echo "vykup rows ok\n";
