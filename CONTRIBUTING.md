# Contributing

## Local Setup

```bash
composer install
```

## Run Tests

```bash
composer test
composer validate --strict
```

## Emulate CI Lanes

Laravel 10:

```bash
composer update --no-interaction --prefer-dist --with-all-dependencies --prefer-stable laravel/framework:^10.0 orchestra/testbench:^8.0
vendor/bin/phpunit
```

Laravel 10 lowest dependencies:

```bash
composer update --no-interaction --prefer-dist --with-all-dependencies --prefer-lowest --prefer-stable laravel/framework:^10.0 orchestra/testbench:^8.0
vendor/bin/phpunit
```

Laravel 11:

```bash
composer update --no-interaction --prefer-dist --with-all-dependencies --prefer-stable laravel/framework:^11.0 orchestra/testbench:^9.0
vendor/bin/phpunit
```

Laravel 12:

```bash
composer update --no-interaction --prefer-dist --with-all-dependencies --prefer-stable laravel/framework:^12.0 orchestra/testbench:^10.0
vendor/bin/phpunit
```

Laravel 13 provisional lane:

```bash
composer update --no-interaction --prefer-dist --with-all-dependencies --prefer-stable laravel/framework:^13.0 orchestra/testbench:^11.0
vendor/bin/phpunit
```

Laravel 13 remains a provisional compatibility target until the stable framework release can be validated end-to-end.
