<?php
/*
 * This file is part of ODY framework
 *
 * @link https://ody.dev
 * @documentation https://ody.dev/docs
 * @license https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Foundation;

use Ody\Foundation\Http\RequestCallback;
use Ody\Swoole\Coroutine\ContextManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Coroutine;
use Swoole\Http\Request as SwRequest;
use Swoole\Http\Response as SwResponse;
use Swoole\Http\Server as SwServer;

class HttpServer
{
    private static ?Application $app = null;

    /**
     * @param SwServer $server
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function start(SwServer $server): void
    {
        // static::$app = // App
        if (self::$app === null) {
            // Get existing application instance
            self::$app = Bootstrap::init();

            // Ensure the application is bootstrapped
            if (!self::$app->isBootstrapped()) {
                self::$app->bootstrap();
            }

            error_log("HttpServer::start() initialized application");
        } else {
            error_log("HttpServer::start() using existing application instance");
        }

        // static::$app->bind(SwServer::class, $server); // Bind server instance to container

        $server->start();
    }

    /**
     * @param SwRequest $request
     * @param SwResponse $response
     * @return void
     */
    public static function onRequest(SwRequest $request, SwResponse $response): void
    {
        Coroutine::create(function() use ($request, $response) {
            static::setContext($request);

            error_log("HttpServer::onRequest() handling request: " .
                $request->server['request_method'] . ' ' .
                $request->server['request_uri']);


            (new RequestCallback(
                static::$app
            ))->handle($request, $response);
        });
    }

    /**
     * @param SwRequest $request
     * @return void
     */
    private static function setContext(SwRequest $request): void
    {
        ContextManager::set('_GET', (array)$request->get);
        ContextManager::set('_GET', (array)$request->get);
        ContextManager::set('_POST', (array)$request->post);
        ContextManager::set('_FILES', (array)$request->files);
        ContextManager::set('_COOKIE', (array)$request->cookie);
        ContextManager::set('_SERVER', (array)$request->server);
    }
}