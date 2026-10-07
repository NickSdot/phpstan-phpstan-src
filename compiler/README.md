# PHAR Compiler for PHPStan

## Compile the PHAR

```bash
composer install
php bin/prepare
cd build
php ../box/vendor/bin/box compile --no-parallel
```

The compiled PHAR will be in `tmp/phpstan.phar`. The preparation step also emits
`tmp/phpstan` from `build/phpstan.php.template` and a dependency-free
`tmp/phpstan-startup.php` bundle from the downgraded startup sources. Package
these three files together: the outer entrypoint prepares PHP before opening
the archive. A startup build manifest is included in the PHAR so changes to
the entrypoint also trigger the existing release checksum check.

Please note that running the compiler will change the contents of `composer.json` file and `vendor` directory. Revert those changes after running it.
