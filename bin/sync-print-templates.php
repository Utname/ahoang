<?php

/**
 * Copies the print template sources from app/Model/PrintTemplates/templates/*.latte
 * into print_templates.html_source. Labels are rendered from the database copy,
 * so an edited .latte file has no effect until it is synced. A label type without
 * a template row gets one.
 *
 * Usage: php bin/sync-print-templates.php [template ...]
 *   php bin/sync-print-templates.php            all templates below
 *   php bin/sync-print-templates.php vel2 a4-discount    only these (file names without .latte)
 *
 * Connection: MYSQLHOST / MYSQLPORT / MYSQLDATABASE / MYSQLUSER / MYSQLPASSWORD
 * (set on Railway, where docker/entrypoint.sh runs this on every start),
 * otherwise the local defaults 127.0.0.1:3306 website root/toor.
 */

// template file => label types it serves: [product_label_type_id, row name, width mm, height mm]
// (ProductLabelsEntity::TYPE_*; an Akce type renders the same file in its Akce layout)
const TEMPLATES = [
	'a4-discount' => [[1, 'Fikko Cena A4', 210, 297], [10, 'Fikko Cena A4 Akce', 210, 297]],
	'a5-discount' => [[2, 'Fikko Cena A5', 210, 148.5], [11, 'Fikko Cena A5 Akce', 210, 148.5]],
	'vel1' => [[3, 'Fikko Cena Vel.1', 164, 41]],
	// one layout for the vel. 2 family (handoff 19.10.2026): the height comes from the row,
	// 167mm wide rows add the Složení column
	'vel2' => [[4, 'Fikko Cena Vel.2', 133, 41], [7, 'Fikko Cena Vel. 2 Akce', 133, 38], [15, 'Fikko Cena Vel.2 Složení', 167, 38], [16, 'Fikko Cena Vel.2B Složení', 167, 52]],
	'vel3' => [[5, 'Fikko Cena Vel.3', 210, 56], [12, 'Fikko Cena Vel.3 Akce', 210, 56]],
	'vel2b-akce' => [[6, 'Fikko Cena Vel.2B Akce', 133, 52], [14, 'Fikko Cena Vel.2 B', 133, 52]],
	'pecivo' => [[8, 'Fikko Cena – tem dải', 150, 41]],
	'fruit' => [[9, 'Fikko Cena Ovoce', 50, 35]],
];

$only = array_slice($argv, 1);
$unknown = array_diff($only, array_keys(TEMPLATES));
if ($unknown) {
	fwrite(STDERR, 'Unknown template: ' . implode(', ', $unknown) . "\nKnown: " . implode(', ', array_keys(TEMPLATES)) . "\n");
	exit(1);
}

$env = static fn(string $name, string $default): string => getenv($name) !== FALSE && getenv($name) !== '' ? getenv($name) : $default;
$pdo = new PDO(
	sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env('MYSQLHOST', '127.0.0.1'), $env('MYSQLPORT', '3306'), $env('MYSQLDATABASE', 'website')),
	$env('MYSQLUSER', 'root'),
	$env('MYSQLPASSWORD', 'toor'),
	[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$where = "product_label_type_id = ? AND variant = 'always' AND is_default = 1";
$find = $pdo->prepare("SELECT COUNT(*) FROM print_templates WHERE $where");
// the size follows the design too (Vel. 3 went from 182 to 210 mm wide)
$update = $pdo->prepare("UPDATE print_templates SET html_source = ?, width_mm = ?, height_mm = ? WHERE $where");
$insert = $pdo->prepare("INSERT INTO print_templates (name, product_label_type_id, width_mm, height_mm, variant, html_source, is_active, is_default) VALUES (?, ?, ?, ?, 'always', ?, 1, 1)");

foreach (TEMPLATES as $file => $types) {
	if ($only && !in_array($file, $only, TRUE)) {
		continue;
	}
	$path = __DIR__ . '/../app/Model/PrintTemplates/templates/' . $file . '.latte';
	$source = @file_get_contents($path);
	if ($source === FALSE) {
		fwrite(STDERR, "Missing $path\n");
		exit(1);
	}
	foreach ($types as [$typeId, $name, $width, $height]) {
		$find->execute([$typeId]);
		if ((int)$find->fetchColumn() === 0) {
			$insert->execute([$name, $typeId, $width, $height, $source]);
			printf("%-14s -> type %2d: row created, %d bytes\n", $file, $typeId, strlen($source));
			continue;
		}
		$update->execute([$source, $width, $height, $typeId]);
		printf("%-14s -> type %2d: %d row(s) updated, %d bytes\n", $file, $typeId, $update->rowCount(), strlen($source));
	}
}
