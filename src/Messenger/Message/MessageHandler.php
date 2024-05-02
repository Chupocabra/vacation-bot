<?php

namespace App\Messenger\Message;

use App\Entity\Chat;
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
        $this->logger->debug('Message: ' . print_r($message, true));

        $chat = $this->chatService->getChat($message->getChat());

        if (null === $chat) {
            $chat = $this->chatService->newChat($message->getChat());
        }

        $command = $this->chatService->messageToCommand($message->getText());
        if ($this->chatService::COMMAND_MENU !== $command && null !== $chat->getStep()) {
            $this->logger->debug('nextStep: ' . $chat->getStep());
            $this->nextStep($message, $chat);

            return;
        }

        switch ($command) {
            case $this->chatService::COMMAND_MENU:
                $this->chatService->saveStep($chat);
                $this->chatService->sendWithMenu($chat->getChatId(), 'message.start');

                break;

            case $this->chatService::COMMAND_SET:
                $this->chatService->saveStep($chat, $this->chatService::COMMAND_SET_FIO);
                $this->chatService->sendWithToMenu($chat->getChatId(), 'message.fio');

                break;

            case $this->chatService::COMMAND_LIST:
                $this->chatService->saveStep($chat);
                $closestVacations = $this->vacationService->closestVacationsMessage();
                $closestVacationsMessage = $this->vacationService->prepareVacationsForChat($closestVacations);
                $this->chatService->sendWithMenu($chat->getChatId(),'message.closest', $closestVacationsMessage);

                break;

            case $this->chatService::COMMAND_CHANGE:
                $this->chatService->saveStep($chat, null, 1);
                $employeesList = $this->employeeService->list();
                $total = $this->employeeService->total();
                $this->chatService->sendEmployees($chat->getChatId(), $employeesList, 1, $total);

                break;

            default:
                $this->chatService->validationError($chat->getChatId());
        }
    }

    private function nextStep(Message $message, Chat $chat): void
    {
        $step = $chat->getStep();
        $context = $chat->getContext();

        $stage = '';
        if (str_contains($chat->getStep(), '-')) {
            [$step, $stage] = explode('-', $chat->getStep());
        }
        $this->logger->info(sprintf('Step handling: step %s, stage %s, context %s', $step, $stage, $context), ['chatter' => $chat->getId()]);

        switch ($step) {
            case $this->chatService::COMMAND_SET_FIO:
                $this->logger->debug('Create employee');

                if (empty($message->getText())) {
                    $this->chatService->validationError($chat->getChatId());

                    return;
                }

                $context = $this->employeeService->save($message->getText());
                $step = $this->chatService::COMMAND_DATE . '-' . 1;
                $this->chatService->saveStep($chat, $step, $context);
                $this->chatService->sendWithToMenu($chat->getChatId(), 'message.date');

                break;

            case $this->chatService::COMMAND_DATE:
                if ($stage >= $this->chatService::MAX_VACATIONS) {
                    $this->chatService->saveStep($chat);
                    $this->chatService->sendWithMenu($chat->getChatId(), 'message.start');

                    return;
                }

                if (!isset($context)) {
                    $this->chatService->validationError($chat->getChatId());

                    return;
                }

                ++$stage;
                if ($this->vacationService->addVacation($context, $message->getText())) {
                    $step = $this->chatService::COMMAND_DATE . '-' . $stage;
                    $this->chatService->saveStep($chat, $step, $context);
                    $this->chatService->sendWithToMenu($chat->getChatId(), 'message.date');

                    return;
                }
                $this->chatService->validationError($chat->getChatId());

                break;

            case $this->chatService::CALLBACK_CHANGE_VACATION:
                if ($this->vacationService->changeVacation($context, $message->getText())) {
                    $this->chatService->saveStep($chat);
                    $this->chatService->sendWithMenu($message->getChat(), 'vacation.changed');

                    return;
                }
                $this->chatService->validationError($chat->getChatId());

                break;

            case $this->chatService::CALLBACK_ADD_VACATION:
                if ($this->vacationService->addVacation($context, $message->getText())) {
                    $this->chatService->saveStep($chat);
                    $this->chatService->sendWithMenu($message->getChat(), 'vacation.added');

                    return;
                }
                $this->chatService->validationError($chat->getChatId());

                break;

            default:
                $this->chatService->validationError($chat->getChatId());
        }
    }
}
