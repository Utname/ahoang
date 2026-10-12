<?php

/**
 * Copies the print template sources from app/Model/PrintTemplates/templates/*.latte
 * into print_templates.html_source. Labels are rendered from the database copy,
 * so an edited .latte file has no effect until it is synced.
 *
 * Usage: php bin/sync-print-templates.php [template ...]
 *   php bin/sync-print-templates.php            all templates below
 *   php bin/sync-print-templates.php vel2 a4    only these (file names without .latte)
 *
 * Connection: MYSQLHOST / MYSQLPORT / MYSQLDATABASE / MYSQLUSER / MYSQLPASSWORD
 * (set on Railway; run there with `railway ssh -s web -- php bin/sync-print-templates.php`),
 * otherwise the local defaults 127.0.0.1:3306 website root/toor.
 */

// template file => product_label_type_id (ProductLabelsEntity::TYPE_*)
const TEMPLATES = [
	'a4-discount' => 1,
	'a5-discount' => 2,
	'vel1' => 3,
	'vel2' => 4,
	'vel3' => 5,
	'vel2b-akce' => 6,
	'vel2-akce' => 7,
	'pecivo' => 8,
	'fruit' => 9,
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
$update = $pdo->prepare("UPDATE print_templates SET html_source = ? WHERE product_label_type_id = ? AND variant = 'always' AND is_default = 1");

foreach (TEMPLATES as $file => $typeId) {
	if ($only && !in_array($file, $only, TRUE)) {
		continue;
	}
	$path = __DIR__ . '/../app/Model/PrintTemplates/templates/' . $file . '.latte';
	$source = file_get_contents($path);
	if ($source === FALSE) {
		fwrite(STDERR, "Missing $path\n");
		exit(1);
	}
	$update->execute([$source, $typeId]);
	printf("%-12s -> type %d: %d row(s) updated, %d bytes\n", $file, $typeId, $update->rowCount(), strlen($source));
}
