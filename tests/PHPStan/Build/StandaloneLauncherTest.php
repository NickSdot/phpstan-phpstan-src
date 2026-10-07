<?php declare(strict_types = 1);

namespace PHPStan\Build;

use PHPStan\Compiler\StartupBuilder;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use function dirname;
use function file_get_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use const PHP_BINARY;

final class StandaloneLauncherTest extends TestCase
{

	public function testGeneratedLauncher(): void
	{
		$root = dirname(__DIR__, 3);
		$directory = tempnam(sys_get_temp_dir(), 'phpstan-startup-');
		self::assertNotFalse($directory);

		unlink($directory);
		mkdir($directory);

		try {
			$archive = $directory . '/phpstan.phar';
			$build = new Process([PHP_BINARY, '-d', 'phar.readonly=0', __DIR__ . '/data/startup-phar.php', $archive]);

			self::assertSame(0, $build->run(), $build->getErrorOutput());

			require_once $root . '/compiler/src/StartupBuilder.php';
			(new StartupBuilder())->build($directory . '/source', $directory, '_PHPStan_startup_test_Startup');

			$probe = $directory . '/probe';

			foreach ([
				'long version option' => ['--version'],
				'analysis command' => ['analyse', 'src'],
				'worker command' => ['worker'],
			] as $command => $args) {
				$process = new Process(
					[PHP_BINARY, $directory . '/phpstan', ...$args],
					$directory,
					['PHPSTAN_STARTUP_PROBE' => $probe],
				);

				self::assertSame(0, $process->run(), $command . ': ' . $process->getErrorOutput());
				self::assertSame("archive\n", $process->getOutput());

				self::assertFileExists($probe);
				self::assertSame('restart|' . $archive, file_get_contents($probe));

				unlink($probe);
			}
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
