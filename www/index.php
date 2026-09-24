<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

define('WWW_DIR', __DIR__);
define('APP_DIR', __DIR__.'/../app');
define('FS_DIR', __DIR__.'/files');
define('FS_TEMP', FS_DIR.'/temp');


\App\Bootstrap::boot()
	->createContainer()
	->getByType(Nette\Application\Application::class)
	->run();
