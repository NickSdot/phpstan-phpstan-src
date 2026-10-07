<?php declare(strict_types = 1);

namespace PHPStan\Turbo;

final class TurboExtensionSelector
{

	public static function probe(): string
	{
		return \PHPStan\Turbo\TurboProcessRestarter::RESTARTED_INI;
	}
}
