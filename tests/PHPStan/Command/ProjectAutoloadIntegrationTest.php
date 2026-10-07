<?php declare(strict_types = 1);

namespace PHPStan\Command;

use JsonException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use function copy;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_file;
use function json_decode;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

final class ProjectAutoloadIntegrationTest extends TestCase
{

	/** @throws JsonException */
	public function testProjectAutoloadingCanUsePHPStanApi(): void
	{
		$directory = tempnam(sys_get_temp_dir(), 'phpstan-startup-');
		self::assertNotFalse($directory);

		unlink($directory);
		mkdir($directory . '/vendor', 0755, true);

		file_put_contents($directory . '/composer.json', '{}');
		copy(__DIR__ . '/data/startup-project-autoload.php', $directory . '/vendor/autoload.php');

		try {
			$probe = $directory . '/probe';
			$process = new Process(
				[PHP_BINARY, '-d', 'opcache.enable_cli=0', dirname(__DIR__, 3) . '/bin/phpstan', '--version'],
				$directory,
				['PHPSTAN_STARTUP_PROBE' => $probe, 'BLACKFIRE_AGENT_SOCKET' => 'test'],
			);

			self::assertSame(0, $process->run(), $process->getErrorOutput());

			$contents = file_get_contents($probe);
			self::assertNotFalse($contents);
			self::assertSame([
				'restarted' => false,
				'apiAvailable' => true,
				'customAutoloaders' => 1,
			], json_decode($contents, true, flags: JSON_THROW_ON_ERROR));
		} finally {
			unlink($directory . '/vendor/autoload.php');
			rmdir($directory . '/vendor');
			unlink($directory . '/composer.json');

			if (is_file($directory . '/probe')) {
				unlink($directory . '/probe');
			}

			rmdir($directory);
		}
	}

}
