- packages/cqrs/src/Messaging/AsyncCommandProducer.php

Consider injecting the Container class here, similar to how it's done in AMQPBootstrap and AMQPManager. This would allow
the producer to resolve dependencies using the container, which could be useful for more complex producer logic.

- packages/cqrs/src/Messaging/AsyncCommandConsumer.php

Consider injecting the Container class here, similar to how it's done in AMQPBootstrap and AMQPManager. This would
allow the consumer to resolve dependencies using the container, which could be useful for more complex consumer logic.

- packages/cqrs/src/Messaging/AMQPMessageBroker.php

```php
   private function getProducerClassForChannel(string $channel): string
    {
        // Convert channel name to producer class name
        // Example: 'order-processing' -> 'App\\Producers\\OrderProcessingProducer'
        $parts = explode('-', $channel);
        $className = '';

        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        return "App\\Producers\\{$className}Producer";
    }
```

The logic to derive the producer class name from the channel assumes a specific naming convention
(App\Producers\{$className}Producer). This might be too rigid. Consider making the producer class namespace configurable
or providing a more flexible mapping mechanism. What if the user wants to use a different namespace or naming
convention?