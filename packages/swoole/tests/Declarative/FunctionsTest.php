<?php declare(strict_types=1);

namespace Ody\Swoole\Tests\Declarative;

use PHPUnit\Framework\TestCase;
use Swoole\Http\Request;
use Swoole\Http\Response;

//require_once __DIR__ . '/../../../vendor/swoole/ide-helper/output/swoole/namespace/Http/Request.php';
//require_once __DIR__ . '/../../../vendor/swoole/ide-helper/output/swoole/namespace/Http/Response.php';

class FunctionsTest extends TestCase
{
    public function testMiddleware()
    {
        $sum = [
            function (Request $request, Response $response, $value) {
                return $value ?? 0;
            },
            function (Request $request, Response $response, $value) {
                return $value + 1;
            },
            function (Request $request, Response $response, $value) {
                return $value + 2;
            },
        ];

        $middleware = \Ody\Swoole\Declarative\Swoole\middleware($sum);
        $this->assertSame(3, $middleware(new Request(), new Response()));

        $early_return = [
            function (Request $request, Response $response, $value) {
                return $request->get['foo'] ?? 'foobar';
            },
            function (Request $request, Response $response, $value) {
                if ($value === 'bar') {
                    return 'baz';
                }

                $response->header = 'quz';
                return null;
            },
            function (Request $request, Response $response, $value) {
                $response->header = $value;
            },
        ];

        $request = new Request();
        $request->get = ['foo' => 'bar'];
        $response = new Response();
        \Ody\Swoole\Declarative\Swoole\middleware($early_return)($request, $response);
        $this->assertSame('baz', $response->header);

        $request = new Request();
        $request->get = [];
        $response = new Response();
        \Ody\Swoole\Declarative\Swoole\middleware($early_return)($request, $response);
        $this->assertSame('quz', $response->header);
    }
}