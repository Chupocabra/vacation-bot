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

        $employee = $this->employeeService->findByChat($callback->getChat());

        if (null === $employee) {
            $employee = $this->employeeService->new($callback->getChat());
        }

        $command = $callback->getCommand();
        switch ($command) {
            case $this->chatService::CALLBACK_ADD_VACATION:
                $this->chatService->saveStep($employee, $this->chatService::CALLBACK_ADD_VACATION, $callback->getData());
                $this->chatService->sendWithMenu($employee, 'message.date');

                break;

            case $this->chatService::CALLBACK_CHANGE_VACATION:
                $this->chatService->saveStep($employee, $this->chatService::CALLBACK_CHANGE_VACATION, $callback->getData());
                $this->chatService->sendWithMenu($employee, 'message.date');

                break;

            case $this->chatService::CALLBACK_DEL_VACATION:
                $vacationMessage = $this->vacationService->deleteVacation($callback->getData());
                $this->chatService->sendWithMenu($employee, 'vacation.deleted', $vacationMessage);

                break;

            default:
                $this->chatService->validationError($employee->getChatId());
        }
    }
}
