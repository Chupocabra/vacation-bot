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

                break;

            case $this->chatService::COMMAND_SET_ALL:
                if ($employee->getRole() === $employee::ROLE_ADMIN) {
                    $this->chatService->saveStep($employee, $this->chatService::COMMAND_SET_FIO);
                    $this->chatService->sendWithToMenu($employee->getChatId(), 'message.fio');
                }

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
                $vacation = $this->vacationService->addVacation($context, $message->getText());
                if (null !== $vacation) {
                    $text = $this->vacationService->getFormattedVacationDates($vacation);
                    $this->chatService->sendWithMenu($employee, 'vacation.add', $text);
                    if ($vacationNumber >= $employee::MAX_VACATIONS) {
                        $this->chatService->saveStep($employee);
                    } else {
                        $this->chatService->saveStep($employee, $this->chatService::COMMAND_DATE, $context);
                        $this->chatService->sendWithToMenu($employee->getChatId(), 'message.date');
                    }

                    return;
                }
                $this->chatService->validationError($employee->getChatId());

                break;

            case $this->chatService::CALLBACK_CHANGE_VACATION:
                $vacation = $this->vacationService->changeVacation($context, $message->getText());
                if (null !== $vacation) {
                    $this->chatService->saveStep($employee);
                    $text = $this->vacationService->getFormattedVacationDates($vacation);
                    $this->chatService->sendWithMenu($employee, 'vacation.changed', $text);

                    return;
                }
                $this->chatService->validationError($employee->getChatId());

                break;

            case $this->chatService::CALLBACK_ADD_VACATION:
                $vacation = $this->vacationService->addVacation($context, $message->getText());
                if (null !== $vacation) {
                    $this->chatService->saveStep($employee);
                    $text = $this->vacationService->getFormattedVacationDates($vacation);
                    $this->chatService->sendWithMenu($employee, 'vacation.add', $text);

                    return;
                }
                $this->chatService->validationError($employee->getChatId());

                break;

            case $this->chatService::COMMAND_LIST:
                $this->chatService->saveStep($employee);
                $vacations = $this->vacationService->employeeVacations($employee->getId());
                $vacationsMessage = $this->vacationService->prepareVacations($employee, $vacations);
                $this->chatService->sendWithMenu($employee,'message.closest', $vacationsMessage);

                break;

            case $this->chatService::COMMAND_LIST_ALL:
                if ($employee->getRole() === $employee::ROLE_ADMIN) {
                    $this->chatService->saveStep($employee);
                    $closestVacations = $this->vacationService->closestVacations();
                    $closestVacationsMessage = $this->vacationService->prepareVacationsForAll($closestVacations);
                    $this->chatService->sendWithMenu($employee,'message.closest', $closestVacationsMessage);
                }

                break;

            case $this->chatService::COMMAND_CHANGE:
                $employeeVacations = $this->vacationService->employeeVacations($employee->getId());
                $this->chatService->sendVacations($employee, $employeeVacations);

                break;

            case $this->chatService::COMMAND_CHANGE_ALL:
                if ($employee->getRole() === $employee::ROLE_ADMIN) {
                    $this->chatService->sendLinkToChangeVacations($employee->getChatId());
                }

                break;

            default:
                $this->chatService->validationError($employee->getChatId());
        }
    }
}
