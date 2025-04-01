<?php

namespace Ody\CQRS\Messaging;

class ServerCallback
{
    protected function onWorkerReload(Server $server, $eventDispatcher): void
    {
        // Existing reload logic...

        // Dispatch the code reloaded event
        $eventDispatcher->dispatch(new CodeReloaded());

        logger()->info('Worker reloaded, event dispatched');
    }
}