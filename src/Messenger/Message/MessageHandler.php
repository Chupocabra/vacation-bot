<?php

namespace App\Messenger\Message;

use App\Entity\Employee;
use App\Service\ChatService;
use App\Service\EmployeeService;
use App\Service\VacationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class MessageHandler
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

    public function __invoke(Message $message): void
    {
        $this->logger->debug('Message: ' . json_encode($message));

        $employee = $this->employeeService->findByChat($message->getChat());
        if (null === $employee) {
            $employee = $this->employeeService->new($message->getChat());
        }

        $command = $this->chatService->messageToCommand($message->getText());
        $step = $employee->getStep();
        $context = $employee->getContext();
        if ($this->chatService::COMMAND_MENU !== $command && null !== $employee->getStep()) {
            $command = $step;
        }

        $this->logger->info(sprintf('Message handling: step %s, context %s', $step, $context), ['chat' => $employee->getChatId()]);

        switch ($command) {
            case $this->chatService::COMMAND_MENU:
                $this->chatService->saveStep($employee);
                $this->chatService->sendWithMenu($employee, 'message.start');

                break;

            case $this->chatService::COMMAND_SET:
                $this->commandSet($employee);

                break;

            case $this->chatService::COMMAND_SET_FIO:
                $context = $this->employeeService->save($employee, $message->getText());
                $this->chatService->saveStep($employee, $this->chatService::COMMAND_DATE, $context);
                $this->chatService->sendWithToMenu($employee->getChatId(), 'message.date');

                break;

            case $this->chatService::COMMAND_DATE:
                if (!isset($context)) {
                    $this->chatService->validationError($employee->getChatId());

                    return;
                }

                $curEmployee = $this->employeeService->findById($context);
                if (null === $curEmployee) {
                    $this->chatService->validationError($employee->getChatId());

                    return;
                }

                $vacationNumber = count($curEmployee->getVacations());
                if ($vacationNumber >= $employee::MAX_VACATIONS) {
                    $this->chatService->saveStep($employee);
                    $this->chatService->sendWithMenu($employee, 'message.start');

                    return;
                }

                $vacationNumber++;
                if ($this->vacationService->addVacation($context, $message->getText())) {
                    if ($vacationNumber >= $employee::MAX_VACATIONS) {
                        $this->chatService->saveStep($employee);
                        $this->chatService->sendWithMenu($employee, 'vacation.add');
                    } else {
                        $this->chatService->saveStep($employee, $this->chatService::COMMAND_DATE, $context);
                        $this->chatService->sendWithToMenu($employee->getChatId(), 'message.date');
                    }

                    return;
                }
                $this->chatService->validationError($employee->getChatId());

                break;

            case $this->chatService::CALLBACK_CHANGE_VACATION:
                if ($this->vacationService->changeVacation($context, $message->getText())) {
                    $this->chatService->saveStep($employee);
                    $this->chatService->sendWithMenu($employee, 'vacation.changed');

                    return;
                }
                $this->chatService->validationError($employee->getChatId());

                break;

            case $this->chatService::CALLBACK_ADD_VACATION:
                if ($this->vacationService->addVacation($context, $message->getText())) {
                    $this->chatService->saveStep($employee);
                    $this->chatService->sendWithMenu($employee, 'vacation.add');

                    return;
                }
                $this->chatService->validationError($employee->getChatId());

                break;

            case $this->chatService::COMMAND_LIST:
                if ($employee->getRole() !== $employee::ROLE_EMPLOYEE) {
                    $this->chatService->saveStep($employee);
                    $closestVacations = $this->vacationService->closestVacationsMessage();
                    $closestVacationsMessage = $this->vacationService->prepareVacationsForChat($closestVacations);
                    $this->chatService->sendWithMenu($employee,'message.closest', $closestVacationsMessage);
                }

                break;

            case $this->chatService::COMMAND_CHANGE:
                if ($employee->getRole() !== $employee::ROLE_EMPLOYEE) {
                    $this->chatService->sendLinkToChangeVacations($employee->getChatId());

                    return;
                }
                $employeeVacations = $this->vacationService->employeeVacations($employee->getId());
                $this->chatService->sendVacations($employee, $employeeVacations);

                break;

            default:
                $this->chatService->validationError($employee->getChatId());
        }
    }

    private function commandSet(Employee $employee): void
    {
        if ($employee->getRole() === $employee::ROLE_EMPLOYEE) {
            if (null !== $employee->getFullName()) {
                $vacationNumber = count($employee->getVacations());
                if ($vacationNumber >= $employee::MAX_VACATIONS) {
                    $this->chatService->saveStep($employee);
                    $this->chatService->sendWithMenu($employee, 'vacation.full');
                } else {
                    $this->chatService->saveStep($employee, $this->chatService::COMMAND_DATE, $employee->getId());
                    $this->chatService->sendWithToMenu($employee->getChatId(), 'message.date');
                }
            } else {
                $this->chatService->saveStep($employee, $this->chatService::COMMAND_SET_FIO);
                $this->chatService->sendWithToMenu($employee->getChatId(), 'message.fio');
            }
        } else {
            $this->chatService->saveStep($employee, $this->chatService::COMMAND_SET_FIO);
            $this->chatService->sendWithToMenu($employee->getChatId(), 'message.fio');
        }
    }
}
