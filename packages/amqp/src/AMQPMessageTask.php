<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\AMQP\Message\Result;
use Ody\Task\TaskInterface;
use PhpAmqpLib\Channel\AMQPChannel;
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
            $deliveryTag = $params['delivery_tag'];
            $channel = $params['channel'];

            // Create a new AMQPMessage instance since we can't serialize it
            $message = new AMQPMessage($messageBody);
            $message->setDeliveryInfo($deliveryTag, false, '', '');

            // Create an instance of the consumer
            $consumer = new $consumerClass();

            // Process message data
            $data = json_decode($messageBody, true) ?: [];

            // Call the consumer's method
            $result = $consumer->consumeMessage($data, $message);

            // Handle the result
            $this->handleResult($result, $deliveryTag, $channel);

            return [
                'success' => true,
                'result' => $result->value,
            ];
        } catch (\Throwable $e) {
            // Log the error
            error_log("Error processing AMQP message: " . $e->getMessage());

            // Always try to NACK the message so it doesn't get lost
            try {
                if (isset($channel) && isset($deliveryTag)) {
                    $channel->basic_nack($deliveryTag, false, true);
                }
            } catch (\Throwable $e) {
                // Ignore exceptions during error recovery
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle the result of message processing
     */
    private function handleResult(Result $result, int $deliveryTag, AMQPChannel $channel): void
    {
        switch ($result) {
            case Result::ACK:
                $channel->basic_ack($deliveryTag);
                break;

            case Result::NACK:
                $channel->basic_nack($deliveryTag);
                break;

            case Result::REQUEUE:
                $channel->basic_reject($deliveryTag, true);
                break;

            case Result::DROP:
                $channel->basic_reject($deliveryTag, false);
                break;
        }
    }
}