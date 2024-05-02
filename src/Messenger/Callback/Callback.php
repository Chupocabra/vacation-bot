<?php

namespace App\Messenger\Callback;

use App\Messenger\MessageInterface;

class Callback implements MessageInterface
{
    private int $id;
    private int $chat;
    private string $command;
    private string $data;

    public function __construct(array $payload)
    {
        $this->id = $payload['callback_query']['id'];
        $this->chat = $payload['callback_query']['message']['chat']['id'];
        $this->command = json_decode($payload['callback_query']['data'], true)['command'];
        $this->data = json_decode($payload['callback_query']['data'], true)['data'];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getChat(): int
    {
        return $this->chat;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getData(): string
    {
        return $this->data;
    }
}
