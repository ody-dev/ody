<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\Task\TaskInterface;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Task for processing AMQP messages
 */
class AMQPMessageTask implements TaskInterface
{
    /**
     * Handle the task
     */
    public function handle(array $params = []): array
    {
        try {
            // Extract parameters
            $consumerClass = $params['consumer_class'];
            $messageBody = $params['message_body'];
            $deliveryTag = $params['delivery_tag']; // Only store the tag, not the channel

            // Create a new AMQPMessage instance
            $message = new AMQPMessage($messageBody);
            $message->setDeliveryInfo($deliveryTag, false, '', '');

            // Create consumer instance
            $consumer = new $consumerClass();

            // Process message data
            $data = json_decode($messageBody, true) ?: [];

            // Call the consumer's method
            $result = $consumer->consumeMessage($data, $message);

            // Only return the result, don't interact with the channel
            return [
                'success' => true,
                'result' => $result->value,
                'delivery_tag' => $deliveryTag
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'delivery_tag' => $params['delivery_tag'] ?? null
            ];
        }
    }
}