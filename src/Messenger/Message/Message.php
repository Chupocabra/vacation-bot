<?php

namespace App\Messenger\Message;

use App\Messenger\MessageInterface;

class Message implements MessageInterface
{
    private int $id;
    private string $chat;
    private string $text;

    public function __construct(array $payload)
    {
        $this->id = $payload['message']['message_id'];
        $this->chat = $payload['message']['chat']['id'];
        $this->text = $payload['message']['text'] ?? 'menu';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getChat(): string
    {
        return $this->chat;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
