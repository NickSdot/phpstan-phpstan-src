<?php declare(strict_types = 1);

require __DIR__ . '/package/phpstan-startup.php';

$_SERVER['BLACKFIRE_AGENT_SOCKET'] = 'test';
\_PHPStan_test_Startup\Turbo\TurboProcessRestarter::restartIfSuitable(['phpstan', 'analyse']);

if (\_PHPStan_test_Startup\Turbo\TurboExtensionSelector::findExtension(__DIR__ . '/phpstan.phar') !== null) {
	exit(2);
}

echo 'ok';
