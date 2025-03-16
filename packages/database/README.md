# ody-database
This is a work in progress, the connection pool not stable at all! Highly experimental!

This package implements a custom database connection class that overrides the default Connection class
of Eloquent. Doing this enables Eloquent to run within a coroutine context and can connect over TCP sockets 
to a coroutine connection pool.

## Installation
```php
composer require ody/database
```

## Usage
### Register the package in ODY
Add `DatabaseServiceProvider` to `config/app.php`

```php
'providers' => [
    // ...
    \Ody\DB\ServiceProviders\DatabaseServiceProvider::class,
    // ...

]
```

### Edit config files

1. Add/edit the `config/database.php` & `config/pool.php` file.
2. When running a connection pool instance set `pool.enabled` to true and edit the host and port.
3. Run `php bin/connection_pool.php` to start a simple TCP server that fires up a MySQL connection pool.

To run Eloquent in standard mode, simply set `pool.enabled` to `false`. This is the advised method as the connection 
pool still are in an experimental stage.

## Using Eloquent

Refer to the Laravel documentation, most methods should work as we're used to. But since ODY is in active development,
issues may arise. Open an issue on GitHub and we'll look into it asap.

## Benchmarks

The current benchmarks are insanely good, 6k to 8k queries per second while firing off queries asynchronously, 10
coroutines with each performing 2000 queries. This was ran through the connection pool with standard settings.

`$this->userRepository->getAll();` eager loads relationships on top of the base `select * from users;` query.

```php
$n1 = async(function () { for ($k = 0 ; $k < 2000; $k++){ $this->userRepository->getAll(); } });
$n2 = async(function () { for ($k = 0 ; $k < 2000; $k++){ $this->userRepository->getAll(); } });
$n3 = async(function () { for ($k = 0 ; $k < 2000; $k++){ $this->userRepository->getAll(); } });
$n4 = async(function () { for ($k = 0 ; $k < 2000; $k++){ $this->userRepository->getAll(); } });
$n5 = async(function () { for ($k = 0 ; $k < 2000; $k++){ $this->userRepository->getAll(); } });
$n6 = async(function () { for ($k = 0 ; $k < 2000; $k++){ $this->userRepository->getAll(); } });
$n7 = async(function () { for ($k = 0 ; $k < 2000; $k++){ $this->userRepository->getAll(); } });
$n8 = async(function () { for ($k = 0 ; $k < 2000; $k++){ $this->userRepository->getAll(); } });
$n9 = async(function () { for ($k = 0 ; $k < 2000; $k++){ $this->userRepository->getAll(); } });
$n10 = async(function () { for ($k = 0 ; $k < 2000; $k++){ $this->userRepository->getAll(); } });

$n = \Ody\Futures\join([$n1, $n2, $n3, $n4, $n5, $n6, $n7, $n8, $n9, $n10]);

$n->await();
```

## Footnotes

Special thanks to:
* Taylor Otwell/Laravel for providing Eloquent
* allsilaevex (https://github.com/allsilaevex) for showing how to efficiently build a connection pool with Swoole
