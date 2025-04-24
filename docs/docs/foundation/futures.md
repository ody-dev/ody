# Futures

## Introduction

Futures refer to a programming concept used for handling asynchronous operations. A future represents
a value that may not yet be available but will be resolved at some point in the future. It allows developers to write
non-blocking code, improving efficiency, especially in concurrent or parallel programming.
ODY made a custom implementation of futures using coroutines. We used a well known `async/await` approach.

## Usage

### Async / await

Creates and awaits for asynchronous computations in an alternative style than Swoole's coroutines.

```php
$future = Futures\async(fn() => 1);
$result = $future->await(); // 1
```

Futures are lazy, they only run when you call `await`.

### Join

Joins a list of Futures into a single Future that awaits for a list of results.

```php
$slow_rand = function (): int {
    Co::sleep(3);
    return rand(1, 100);
};
$n1 = async($slow_rand);
$n2 = async($slow_rand);
$n3 = async($slow_rand);
$n = \Ody\Swoole\Futures\join([$n1, $n2, $n3]);
print_r($n->await());
```

This takes 3 seconds, not 9, as Futures run concurrently! (Order isn't guaranteed)

### Race

Returns the result of the first finished Future.

```php
$site1 = async(function () {
    $client = new Client('www.google.com', 443, true);
    $client->get('/');
    return $client->body;
});
$site2 = async(function () {
    $client = new Client('www.swoole.co.uk', 443, true);
    $client->get('/');
    return $client->body;
});
$site3 = async(function () {
    $client = new Client('ody.dev', 443, true);
    $client->get('/');
    return $client->body;
});
$first_to_load = select([$site1, $site2, $site3]);
echo $first_to_load->await();
```

### Async map

Maps an array into a list of Futures where each item runs concurrently.

```php
$list = [1, 2, 3];
$multiply = fn(int $a) => fn(int $b) => $a * $b;
$double = $multiply(2);
$doubles = \Ody\Swoole\Futures\join(async_map($list, $double))->await();
print_r($doubles);
```

### Then

Sequences a series of steps for a Future, is the serial analog for join:

```php
$future = async(fn() => 2)
    ->then(fn(int $i) => async(fn() => $i + 3))
    ->then(fn(int $i) => async(fn() => $i * 4))
    ->then(fn(int $i) => async(fn() => $i - 5));
echo $future->await(); // 15
```

### Stream

Streams values/events from `sink` to `listen` with operations in between.

```php
function main()
{
    $stream = Futures\stream()
        ->map(fn($val) => $val + 1)
        ->filter(fn($val) => $val % 2 === 0)
        ->map(fn($val) => $val * 2)
        ->listen(fn($val) => print("$val\n"));
    foreach (range(0, 9) as $n) {
        $stream->sink($n);
    }
}
Co\run('Acme\main');
```