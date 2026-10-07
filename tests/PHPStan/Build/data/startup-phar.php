<?php declare(strict_types = 1);

$phar = new Phar($argv[1]);
$phar->addFile(__DIR__ . '/startup-archive/src/Turbo/TurboExtensionSelector.php', 'src/Turbo/TurboExtensionSelector.php');

$phar->addFile(__DIR__ . '/startup-archive/src/Turbo/TurboProcessRestarter.php', 'src/Turbo/TurboProcessRestarter.php');

$phar->addFile(__DIR__ . '/startup-archive/bin/phpstan', 'bin/phpstan');

mkdir(dirname($argv[1]) . '/source/src/Turbo', 0755, true);

foreach (['Turbo/TurboExtensionSelector', 'Turbo/TurboProcessRestarter'] as $class) {
	file_put_contents(dirname($argv[1]) . '/source/src/' . $class . '.php', $phar['src/' . $class . '.php']->getContent());
}

$phar->setStub('<?php __HALT_COMPILER();');
