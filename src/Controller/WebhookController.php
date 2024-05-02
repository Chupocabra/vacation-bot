<?php

namespace App\Controller;

use App\Messenger\Callback\Callback;
use App\Messenger\Message\Message;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    private MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    #[Route('/', name: 'app_message')]
    public function message(Request $request): JsonResponse
    {
        if (isset($request->toArray()['callback_query'])) {
            $this->bus->dispatch(new Callback($request->toArray()));
        }

        if (isset($request->toArray()['message'])) {
            $this->bus->dispatch(new Message($request->toArray()));
        }

        return new JsonResponse([]);
    }
}
