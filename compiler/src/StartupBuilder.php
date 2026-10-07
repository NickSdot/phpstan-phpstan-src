<?php declare(strict_types = 1);

namespace PHPStan\Compiler;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;
use function array_merge;
use function chmod;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function hash_file;
use function is_dir;
use function json_encode;
use function mkdir;
use function preg_match;
use function str_replace;
use function str_starts_with;
use function substr;
use const JSON_THROW_ON_ERROR;

/** Builds the outer entrypoint from the compiler's already downgraded source. */
final class StartupBuilder
{

	public function build(string $sourceDirectory, string $destination, string $namespace): void
	{
		if (preg_match('/^_PHPStan_[a-zA-Z0-9_]+$/D', $namespace) !== 1) {
			throw new RuntimeException('Invalid startup namespace.');
		}

		$template = file_get_contents(__DIR__ . '/../build/phpstan.php.template');

		if ($template === false) {
			throw new RuntimeException('Could not read PHPStan entrypoint template.');
		}

		$entrypoint = $this->buildEntrypoint($sourceDirectory, $namespace, $template);

		if (!is_dir($destination) && !mkdir($destination, 0755, true)) {
			throw new RuntimeException('Could not create startup destination.');
		}

		if (file_put_contents($destination . '/phpstan', $entrypoint) === false) {
			throw new RuntimeException('Could not write PHPStan entrypoint.');
		}

		// Make launcher-only changes visible to the existing PHAR checksum gate.
		$builderHash = hash_file('sha256', __FILE__);

		if ($builderHash === false) {
			throw new RuntimeException('Could not hash startup builder.');
		}

		$manifest = json_encode([
			'entrypoint' => hash('sha256', str_replace($namespace, '_PHPStan_Startup', $entrypoint)),
			'builder' => $builderHash,
		], JSON_THROW_ON_ERROR);

		if (file_put_contents($destination . '/phpstan-startup.json', $manifest . "\n") === false) {
			throw new RuntimeException('Could not write startup build manifest.');
		}

		if (!chmod($destination . '/phpstan', 0755)) {
			throw new RuntimeException('Could not make PHPStan entrypoint executable.');
		}
	}

	private function buildEntrypoint(string $sourceDirectory, string $namespace, string $template): string
	{
		$parser = (new ParserFactory())->createForNewestSupportedVersion();
		$statements = [];

		foreach (['Turbo/TurboExtensionSelector', 'Turbo/TurboProcessRestarter'] as $class) {
			$code = file_get_contents($sourceDirectory . '/src/' . $class . '.php');

			if ($code === false) {
				throw new RuntimeException('Could not read startup source: ' . $class);
			}

			$nodes = $parser->parse($code);

			if ($nodes === null) {
				throw new RuntimeException('Empty startup source: ' . $class);
			}

			$resolver = new NodeTraverser(new NameResolver());
			$nodes = $resolver->traverse($nodes);

			$renamer = new NodeTraverser(new class ($namespace) extends NodeVisitorAbstract {

				public function __construct(private string $namespace)
				{
				}

				public function enterNode(Node $node): ?Node
				{
					if (!$node instanceof Name) {
						return null;
					}

					$name = $node->toString();
					if (!str_starts_with($name, 'PHPStan\\')) {
						return null;
					}

					$name = $this->namespace . substr($name, 7);
					if ($node instanceof Name\FullyQualified) {
						return new Name\FullyQualified($name, $node->getAttributes());
					}

					return new Name($name, $node->getAttributes());
				}

			});

			foreach ($renamer->traverse($nodes) as $node) {
				if ($node instanceof Declare_) {
					continue;
				}

				if (!$node instanceof Namespace_) {
					throw new RuntimeException('Unexpected statement outside startup namespace.');
				}

				$statements[] = $node;
			}
		}

		$entrypointNodes = $parser->parse(str_replace('STARTUP_NAMESPACE', $namespace, $template));

		if ($entrypointNodes === null) {
			throw new RuntimeException('Empty PHPStan entrypoint template.');
		}

		return "#!/usr/bin/env php\n<?php declare(strict_types = 1);\n\n"
			. (new Standard())->prettyPrint(array_merge($statements, $entrypointNodes)) . "\n";
	}

}
