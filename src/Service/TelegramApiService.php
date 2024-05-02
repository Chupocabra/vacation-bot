<?php

namespace App\Service;

use TelegramBot\Api\BotApi;

class TelegramApiService extends BotApi
{
    public function __construct(string $token)
    {
        parent::__construct($token);
    }
}
