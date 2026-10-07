<?php
spl_autoload_register(static function (string $class): void {
});

$apiAvailable = interface_exists(\PHPStan\Rules\Rule::class);

register_shutdown_function(static function () use ($apiAvailable): void {
	file_put_contents(getenv('PHPSTAN_STARTUP_PROBE'), json_encode([
		'restarted' => get_cfg_var('phpstan.restarted') !== false,
		'apiAvailable' => $apiAvailable,
		'customAutoloaders' => count($GLOBALS['__phpstanAutoloadFunctions'] ?? []),
	]));
});
