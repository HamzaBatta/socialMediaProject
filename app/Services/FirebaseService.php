<?php
namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Google\Auth\Credentials\ServiceAccountCredentials;

class FirebaseService
{
    protected $messaging;

    public function __construct(Factory $factory)
    {
        $this->messaging = $factory->createMessaging();
        $this->projectId = config('firebase.project_id');
        $this->client = new Client();
    }

    public function sendStructuredNotification($token,$title,$body,$route,$details = [],$image=null)
    {
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'route' => $route,
                    'details' => json_encode($details),
                ],
                'android' => [
                    'notification' => [
                        'sound' => 'default',
                    ]
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'mutable-content' => 1,
                            'sound' => 'default',
                        ]
                    ]
                ]
            ]
        ];

        if ($image) {
            $message['message']['notification']['image'] = $image;
        }

        try {
            $accessToken = $this->getAccessToken();
            $this->client->post('https://fcm.googleapis.com/v1/projects/' . $this->projectId . '/messages:send', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $message,
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null;
            Log::error('FCM Send Error', [
                'message' => $e->getMessage(),
                'response' => $response,
            ]);
        }
    }

    public function getAccessToken()
    {
        $credentialsPath = storage_path('app/firebase/prism-proj-firebase-adminsdk-fbsvc-caa0ff1501.json');

        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        $credentials = new ServiceAccountCredentials($scopes, $credentialsPath);

        $token = $credentials->fetchAuthToken();

        return $token['access_token'];
    }

    public function sendTopicNotification($topic, $title, $body, $route, $details = [], $image = null)
    {
        $message = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'route' => $route,
                    'details' => json_encode($details),
                ],
                'android' => [
                    'notification' => [
                        'sound' => 'default',
                    ]
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'mutable-content' => 1,
                            'sound' => 'default',
                        ]
                    ]
                ]
            ]
        ];

        if ($image) {
            $message['message']['notification']['image'] = $image;
        }

        try {
            $accessToken = $this->getAccessToken();
            $this->client->post(
                'https://fcm.googleapis.com/v1/projects/' . $this->projectId . '/messages:send',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $message,
                ]
            );
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null;
            Log::error('FCM Topic Send Error', [
                'message' => $e->getMessage(),
                'response' => $response,
            ]);
        }
    }


    public function subscribeToTopic($topic, $tokens)
    {
        $this->messaging->subscribeToTopic($topic, $tokens);
    }

    public function unsubscribeFromTopic($topic, $tokens)
    {
        $this->messaging->unsubscribeFromTopic($topic, $tokens);
    }
}
