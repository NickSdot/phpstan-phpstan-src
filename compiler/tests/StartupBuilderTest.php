<?php declare(strict_types = 1);

namespace PHPStan\Compiler;

use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use function copy;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function str_replace;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use const PHP_BINARY;

final class StartupBuilderTest extends TestCase
{

	public function testDowngradedStartupBundleWithoutAutoloading(): void
	{
		$root = dirname(__DIR__, 2);
		require_once $root . '/compiler/src/StartupBuilder.php';

		$directory = tempnam(sys_get_temp_dir(), 'phpstan-startup-build-');
		self::assertNotFalse($directory);

		unlink($directory);
		mkdir($directory);

		try {
			foreach (['Turbo/TurboExtensionSelector', 'Turbo/TurboProcessRestarter'] as $class) {
				$destination = $directory . '/src/' . $class . '.php';

				if (!is_dir(dirname($destination))) {
					mkdir(dirname($destination), 0755, true);
				}

				copy($root . '/src/' . $class . '.php', $destination);
			}

			copy(__DIR__ . '/data/startup-downgrade.php.template', $directory . '/downgrade.php');
			$downgrade = new Process(
				[PHP_BINARY, $root . '/compiler/vendor/bin/simple-downgrade', 'downgrade', '-c', 'downgrade.php', '7.4'],
				$directory,
			);

			self::assertSame(0, $downgrade->run(), $downgrade->getErrorOutput());

			$builder = new StartupBuilder();
			$builder->build($directory, $directory . '/package', '_PHPStan_test_Startup');

			// Both artifacts retain PHP 7.4 syntax after namespace isolation.
			$bundle = file_get_contents($directory . '/package/phpstan-startup.php');
			self::assertNotFalse($bundle);

			$parser = (new ParserFactory())->createForVersion(PhpVersion::fromString('7.4'));
			self::assertNotNull($parser->parse($bundle));

			$entrypoint = file_get_contents($directory . '/package/phpstan');
			self::assertNotFalse($entrypoint);
			self::assertNotNull($parser->parse($entrypoint));

			// A build namespace change must not churn the release checksum.
			$manifest = file_get_contents($directory . '/package/phpstan-startup.json');
			$builder->build($directory, $directory . '/other-build', '_PHPStan_other_Startup');

			self::assertSame($manifest, file_get_contents($directory . '/other-build/phpstan-startup.json'));

			// Run without Composer or any classes held by PHPUnit.
			copy(__DIR__ . '/data/startup-bundle-probe.php.template', $directory . '/probe.php');
			$probe = new Process([PHP_BINARY, $directory . '/probe.php'], $directory);

			self::assertSame(0, $probe->run(), $probe->getErrorOutput());
			self::assertSame('ok', $probe->getOutput());

			// A source change must still trigger the release checksum gate.
			$source = file_get_contents($directory . '/src/Turbo/TurboExtensionSelector.php');
			self::assertNotFalse($source);

			file_put_contents($directory . '/src/Turbo/TurboExtensionSelector.php', str_replace('80300', '80400', $source));
			$builder->build($directory, $directory . '/changed-build', '_PHPStan_test_Startup');

			self::assertNotSame($manifest, file_get_contents($directory . '/changed-build/phpstan-startup.json'));
		} finally {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST,
			);

			foreach ($files as $file) {
				if ($file->isDir()) {
					rmdir($file->getPathname());
				} else {
					unlink($file->getPathname());
				}
			}

			rmdir($directory);
		}
	}

}
