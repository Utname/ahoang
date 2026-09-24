<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;

class Bootstrap
{
	/**
	 * @return Configurator
	 */
	public static function boot(): Configurator
	{
		$configurator = new Configurator;
		$appDir = dirname(__DIR__);

		self::loadEnvFile($appDir.'/.env');

		// APP_ENV=production picks config/production.neon regardless of what
		// domain the request came in on — needed for hosts like Railway where
		// the domain is either an auto-generated *.up.railway.app subdomain
		// (changes if the service is renamed) or a custom domain that isn't
		// attached yet at first deploy, so matching a fixed config/<domain>.neon
		// filename the way the domains below do doesn't work. Existing
		// domain-matched deployments (fikkomarket.cz etc.) are untouched: this
		// branch only applies when APP_ENV is explicitly set to 'production'.
		$appEnv = getenv('APP_ENV') ?: '';
		$isProductionEnv = $appEnv === 'production';

		$domain = str_replace('www.', '', $_SERVER['HTTP_HOST'] ?? '');
		$domainConfigExists = $domain !== '' && file_exists($appDir.'/config/'.$domain.'.neon');

		// Debug mode (Tracy debug bar + full exception "blue screens" with stack
		// traces / source / server vars) used to be `$debugMode = true;`
		// unconditionally, regardless of environment — meaning it would have
		// been exposed to every visitor on every domain, including production,
		// the moment this was deployed anywhere. A domain now only gets debug
		// mode when it has no dedicated config/<domain>.neon of its own (falls
		// back to 02-local.neon — the PHP built-in dev server, or any other
		// unrecognised host) or its dedicated config file is a "loc"/"local"
		// one. This mirrors the convention www/.htaccess already uses for a
		// related decision (it only skips forcing HTTPS when "HTTP_HOST
		// contains 'local'"), so a domain is "local" here the same way it's
		// already "local" there. APP_ENV=production always forces it off,
		// since that branch is explicitly a real deployment, not a guess from
		// the domain name.
		$domainSegments = explode('.', $domain);
		$isLocalDomain = !$domainConfigExists || in_array('loc', $domainSegments, true) || in_array('local', $domainSegments, true);
		$debugMode = $isProductionEnv ? false : $isLocalDomain;

		$configurator->setDebugMode($debugMode);
		$configurator->enableTracy($appDir.'/log');

		$configurator->setTimeZone('Europe/Prague');
		$configurator->setTempDirectory($appDir.'/temp');

		$configurator->createRobotLoader()->addDirectory(__DIR__)->register();

		// Secrets that used to be hardcoded in plaintext in config/*.neon
		// (production DB password, SMTP password) now come from the process
		// environment instead, exposed to the neon files below as %dbUser%,
		// %dbPassword%, %smtpPassword%, %cronSecret% — see .env.example for the
		// full list and config/fikkomarket.cz.neon / 01-general.neon for where
		// they're consumed. .env itself (loaded by loadEnvFile() below) is a
		// local convenience for environments that can't set real process env
		// vars and must never be committed; on hosts that already export real
		// env vars (most PaaS/containers/systemd units), no .env file needs to
		// exist at all and this is a no-op.
		// dbHost/dbPort/dbName plus the MYSQLUSER/MYSQLPASSWORD fallback below
		// are for config/production.neon specifically: Railway's MySQL plugin
		// injects MYSQLHOST/MYSQLPORT/MYSQLDATABASE/MYSQLUSER/MYSQLPASSWORD
		// automatically into every service in the same project, so production
		// deploys don't need DB_USER/DB_PASSWORD set by hand at all — those two
		// stay reserved for the existing domain-matched configs, which is why
		// they're tried first, with the Railway names only as a fallback.
		$configurator->addDynamicParameters([
			'dbHost' => getenv('MYSQLHOST') ?: '127.0.0.1',
			'dbPort' => getenv('MYSQLPORT') ?: '3306',
			'dbName' => getenv('MYSQLDATABASE') ?: '',
			'dbUser' => getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: ''),
			'dbPassword' => getenv('DB_PASSWORD') ?: (getenv('MYSQLPASSWORD') ?: ''),
			'smtpPassword' => getenv('SMTP_PASSWORD') ?: '',
			'cronSecret' => getenv('CRON_SECRET') ?: '',
		]);

		$configurator->addConfig($appDir.'/config/01-general.neon');

		$configurator->addConfig($appDir.'/config/03-services.neon');
		$configurator->addConfig($appDir.'/config/04-images.neon');
		$configurator->addConfig($appDir.'/config/05-parameters.neon');
		$configurator->addConfig($appDir.'/config/06-modules.neon');
		$configurator->addConfig($appDir.'/config/07-menu-structure.neon');

		// specific config by domain (or by APP_ENV, see above)
		if ($isProductionEnv) {
			$configurator->addConfig($appDir.'/config/production.neon');
		} elseif ($domainConfigExists) {
			$configurator->addConfig($appDir.'/config/'.$domain.'.neon');
		}else{
            $configurator->addConfig($appDir.'/config/02-local.neon');
        }

		\FilesService::createDirIfNotExists(FS_DIR, 'images');
		\FilesService::createDirIfNotExists(FS_DIR, 'files');
		\FilesService::createDirIfNotExists(FS_DIR, 'favicon');
		\FilesService::createDirIfNotExists(FS_DIR, 'temp');
		\FilesService::createDirIfNotExists(WWW_DIR, 'media');
		\FilesService::createDirIfNotExists(WWW_DIR, 'xml');

		return $configurator;
	}


	/**
	 * Minimal dependency-free KEY=VALUE .env loader (no phpdotenv/symfony
	 * dotenv in composer.json, and adding one is more than this needs). Only
	 * fills in variables the real process environment doesn't already have,
	 * so actual env vars set by the host always win over the file.
	 */
	private static function loadEnvFile(string $path): void
	{
		if (!is_file($path)) {
			return;
		}

		foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			$line = trim($line);
			if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
				continue;
			}

			[$key, $value] = explode('=', $line, 2);
			$key = trim($key);
			$value = trim($value);
			if (strlen($value) >= 2 && (
				($value[0] === '"' && str_ends_with($value, '"'))
				|| ($value[0] === "'" && str_ends_with($value, "'"))
			)) {
				$value = substr($value, 1, -1);
			}

			if (getenv($key) === false) {
				putenv($key.'='.$value);
			}
		}
	}
}
