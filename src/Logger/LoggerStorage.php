<?php

namespace App\Logger;

class LoggerStorage
{
    private ?string $sessionId;
    private ?string $chatId;

    public function __construct()
    {
        $this->clear();
    }

    public function init(string $sessionId, string $chatId): void
    {
        $this->sessionId = $sessionId;
        $this->chatId = $chatId;
    }

    public function getContext(): string
    {
        if (!$this->sessionId) {
            return '';
        }

        return sprintf('%s-%s', $this->sessionId, $this->chatId);
    }

    public function clear(): void
    {
        $this->sessionId = null;
    }
}
