<?php declare(strict_types = 1);

namespace PHPStan\Compiler;

use PHPStan\Compiler\Console\PrepareCommand;
use PHPStan\Compiler\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use Symfony\Component\Process\Process;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function rmdir;
use function str_replace;
use function uniqid;
use function unlink;
use function var_export;
use const PHP_BINARY;

final class PreloadScriptTest extends TestCase
{

	public function testOnlyDependenciesArePreloaded(): void
	{
		$root = dirname(__DIR__, 2);
		require_once $root . '/compiler/src/Filesystem/Filesystem.php';
		require_once $root . '/compiler/src/Console/PrepareCommand.php';
		$directory = $root . '/tmp/startup-preload-' . uniqid();

		foreach (['src', 'vendor/nikic/php-parser/lib/PhpParser', 'vendor/phpstan/phpdoc-parser/src'] as $path) {
			mkdir($directory . '/' . $path, 0755, true);
		}
		try {
			foreach ([
				'src/Analysis.php' => 'analysis;',
				'vendor/nikic/php-parser/lib/PhpParser/Parser.php' => 'parser;',
				'vendor/phpstan/phpdoc-parser/src/Parser.php' => 'phpdoc;',
			] as $path => $marker) {
				file_put_contents($directory . '/' . $path, '<?php file_put_contents(getenv("PHPSTAN_PRELOAD_PROBE"), ' . var_export($marker, true) . ', FILE_APPEND);');
			}

			$command = new PrepareCommand($this->createStub(Filesystem::class), $directory);
			(new ReflectionMethod($command, 'buildPreloadScript'))->invoke($command);

			$preload = file_get_contents($directory . '/preload.php');
			self::assertNotFalse($preload);

			// Production builds use the repository root as buildDir. Resolve the
			// generated paths from there for this isolated fixture too.
			file_put_contents($directory . '/preload.php', str_replace('__DIR__', var_export($root, true), $preload));
			$analysisPreload = file_get_contents($directory . '/preload-analysis.php');
			self::assertNotFalse($analysisPreload);
			file_put_contents($directory . '/preload-analysis.php', str_replace('__DIR__', var_export($root, true), $analysisPreload));

			foreach ([
				[['phpstan', '--version'], 'parser;phpdoc;'],
				[['phpstan', 'help', 'analyse'], 'parser;phpdoc;'],
				[['phpstan', 'analyse', '--help'], 'parser;phpdoc;'],
				[['phpstan', 'analyse'], 'parser;phpdoc;'],
				[['phpstan', 'analyse', '--', '--help'], 'parser;phpdoc;'],
			] as [$args, $expected]) {
				$probe = $directory . '/probe';
				$script = '$_SERVER["argv"] = ' . var_export($args, true) . '; require ' . var_export($directory . '/preload.php', true) . ';';
				$process = new Process([PHP_BINARY, '-r', $script], $directory, ['PHPSTAN_PRELOAD_PROBE' => $probe]);

				self::assertSame(0, $process->run(), $process->getErrorOutput());
				self::assertSame($expected, file_get_contents($probe));

				unlink($probe);
			}

			// Own analysis classes are preloaded once when workers need them.
			$script = 'require ' . var_export($directory . '/preload-analysis.php', true) . '; require_once ' . var_export($directory . '/preload-analysis.php', true) . ';';
			$process = new Process([PHP_BINARY, '-r', $script], $directory, ['PHPSTAN_PRELOAD_PROBE' => $directory . '/probe']);
			self::assertSame(0, $process->run(), $process->getErrorOutput());
			self::assertSame('analysis;', file_get_contents($directory . '/probe'));
		} finally {
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
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
