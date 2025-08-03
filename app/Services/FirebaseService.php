<?php
namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected $messaging;

    public function __construct(Factory $factory)
    {
        $this->messaging = $factory->createMessaging();
    }

    public function sendNotification( $deviceToken,  $title,  $body)
    {
        $message = CloudMessage::withTarget('token', $deviceToken)
                               ->withNotification(Notification::create($title, $body));

        $this->messaging->send($message);
    }

    public function sendTopicNotification( $topic,  $title,  $body)
    {
        $message = CloudMessage::withTarget('topic', $topic)
                               ->withNotification(Notification::create($title, $body));

        $this->messaging->send($message);
    }
}
