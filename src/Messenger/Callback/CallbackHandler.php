<?php

namespace App\Messenger\Callback;

use App\Service\ChatService;
use App\Service\EmployeeService;
use App\Service\VacationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CallbackHandler
{
    private LoggerInterface $logger;
    private ChatService $chatService;
    private EmployeeService $employeeService;
    private VacationService $vacationService;

    public function __construct(ChatService $chatService, EmployeeService $employeeService, VacationService $vacationService, LoggerInterface $logger)
    {
        $this->chatService = $chatService;
        $this->employeeService = $employeeService;
        $this->vacationService = $vacationService;
        $this->logger = $logger;
    }

    public function __invoke(Callback $callback): void
    {
        $this->logger->debug('Callback: ' . print_r($callback, true));

        $chat = $this->chatService->getChat($callback->getChat());

        if (null === $chat) {
            $chat = $this->chatService->newChat($callback->getChat());
        }

        $command = $callback->getCommand();
        switch ($command) {
            case $this->chatService::CALLBACK_VACATIONS:
                $this->chatService->saveStep($chat, $this->chatService::CALLBACK_VACATIONS, $callback->getData());
                $employeeVacations = $this->vacationService->employeeVacations($callback->getData());
                $this->chatService->sendVacations($chat->getChatId(), $employeeVacations);

                break;

            case $this->chatService::CALLBACK_ADD_VACATION:
                $this->chatService->saveStep($chat, $this->chatService::CALLBACK_ADD_VACATION, $callback->getData());
                $this->chatService->sendWithToMenu($callback->getChat(), 'message.date');

                break;

            case $this->chatService::CALLBACK_CHANGE_VACATION:
                $this->chatService->saveStep($chat, $this->chatService::CALLBACK_CHANGE_VACATION, $callback->getData());
                $this->chatService->sendWithToMenu($callback->getChat(), 'message.date');

                break;

            case $this->chatService::CALLBACK_PAGINATION:
                $this->chatService->saveStep($chat, null, $callback->getData());
                $employeesList = $this->employeeService->list($callback->getData());
                $total = $this->employeeService->total();
                $this->chatService->sendEmployees($chat->getChatId(), $employeesList, $callback->getData(), $total);

                break;

            default:
                $this->chatService->validationError($chat->getChatId());
        }
    }
}
