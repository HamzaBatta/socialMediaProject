<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class EventPublisher
{
    protected $connection;
    protected $channel;
    protected $exchange = 'app_events';

    public function __construct()
    {
        $this->connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
        $this->channel = $this->connection->channel();

        // Declare a fanout exchange (broadcast to all bound queues)
        $this->channel->exchange_declare($this->exchange, 'fanout', false, true, false);
    }

    public function publishEvent(string $eventType, array $data): void
    {
        $payload = json_encode([
            'event_type' => $eventType,
            'data' => $data
        ]);

        $message = new AMQPMessage($payload, [
            'content_type' => 'application/json',
            'delivery_mode' => 2 // make message persistent
        ]);

        $this->channel->basic_publish($message, $this->exchange);
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
