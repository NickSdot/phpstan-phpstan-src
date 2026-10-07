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
