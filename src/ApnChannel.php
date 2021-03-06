<?php

namespace Fruitcake\NotificationChannels\Apn;

use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use ZendService\Apple\Apns\Client\Message as Client;
use ZendService\Apple\Apns\Message as Packet;
use ZendService\Apple\Apns\Response\Message as Response;

class ApnChannel
{
    /** @var Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Send the notification to Apple Push Notification Service
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @return void
     *
     * @throws Exceptions\ConnectionFailed|Exceptions\SendingFailed
     */
    public function send($notifiable, Notification $notification)
    {
        if (!$this->openConnection()) {
            return;
        }

        $tokens = (array) $notifiable->routeNotificationFor('apn');
        if (!$tokens) {
            return;
        }

        $message = $notification->toApn($notifiable);
        if (!$message) {
            return;
        }

        foreach ($tokens as $token) {
            try {
                $packet = new Packet();
                $packet->setToken($token);
                $packet->setAlert($message->body);
                $packet->setBadge($message->badge);
                $packet->setCustom($message->data);

                $response = $this->client->send($packet);

                if($response->getCode() != Response::RESULT_OK) {
                    app()->make('events')->fire(
                        new NotificationFailed($notifiable, $notification, $this, [
                            'token' => $token,
                            'error' => $response->getCode()
                        ])
                    );
                }
            } catch (\Exception $e) {
                throw Exceptions\SendingFailed::create($e);
            }
        }

        $this->closeConnection();
    }

    /**
     * Try to open connection
     *
     * @return bool
     *
     * @throws Exceptions\ConnectionFailed
     */
    private function openConnection()
    {
        try {
            if (config('services.apn.sandbox')) {
                $this->client->open(Client::SANDBOX_URI, storage_path(config('services.apn.certificate_sandbox')));
            } else {
                $this->client->open(Client::PRODUCTION_URI, storage_path(config('services.apn.certificate')));
            }
            return true;
        } catch (\Exception $e) {
            throw Exceptions\ConnectionFailed::create($e);
        }
    }

    /**
     * Close connection
     *
     * @return void
     */
    private function closeConnection()
    {
        $this->client->close();
    }
}
