<?php declare(strict_types = 1);

$phar = new Phar($argv[1]);
$phar['src/Turbo/TurboExtensionSelector.php'] = <<<'CODE'
<?php declare(strict_types = 1);

namespace PHPStan\Turbo;

final class TurboExtensionSelector
{

	public static function probe(): string
	{
		return \PHPStan\Turbo\TurboProcessRestarter::RESTARTED_INI;
	}
}
CODE;

$phar['src/Turbo/TurboProcessRestarter.php'] = <<<'CODE'
<?php declare(strict_types = 1);

namespace PHPStan\Turbo;

final class TurboProcessRestarter
{

	public const RESTARTED_INI = 'restart';

	public static function restartIfSuitable(array $argv, ?string $pharPath = null): void
	{
		file_put_contents(getenv('PHPSTAN_STARTUP_PROBE'), \PHPStan\Turbo\TurboExtensionSelector::probe() . '|' . $pharPath);
	}
}
CODE;

$phar['bin/phpstan'] = <<<'CODE'
<?php namespace _PHPStan_startup_test;
// Loading these after the isolated copies also checks for redeclarations.
require __DIR__ . '/../src/Turbo/TurboExtensionSelector.php';
require __DIR__ . '/../src/Turbo/TurboProcessRestarter.php';

if (!defined('__PHPSTAN_RESTART_CHECKED__')) {
	throw new RuntimeException('The archive would attempt to restart again.');
}

echo "archive\n";
CODE;

mkdir(dirname($argv[1]) . '/source/src/Turbo', 0755, true);
foreach (['Turbo/TurboExtensionSelector', 'Turbo/TurboProcessRestarter'] as $class) {
	file_put_contents(dirname($argv[1]) . '/source/src/' . $class . '.php', $phar['src/' . $class . '.php']->getContent());
}
$phar->setStub('<?php __HALT_COMPILER();');
