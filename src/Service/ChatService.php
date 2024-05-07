<?php

namespace App\Service;

use App\Entity\Employee;
use App\Entity\Vacation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use TelegramBot\Api\Exception;
use TelegramBot\Api\InvalidArgumentException;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;

class ChatService extends AbstractService
{
    // TODO maybe move to Enum?
    public const COMMAND_MENU = 'menu';
    public const COMMAND_SET = 'set';
    public const COMMAND_SET_FIO = 'fio';
    public const COMMAND_DATE = 'date';
    public const COMMAND_LIST = 'list';
    public const COMMAND_CHANGE = 'change';
    public const CALLBACK_CHANGE_VACATION = 'change_vacation';
    public const CALLBACK_ADD_VACATION = 'add_vacation';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly TelegramApiService $telegramApi,
        private readonly string $groupId,
        private readonly string $adminUrl,
        EntityManagerInterface $em,
        LoggerInterface $logger,
    ) {
        parent::__construct($em, $logger);
    }

    public function saveStep(Employee $chatter, ?string $step = null, ?string $context = null): void
    {
        $this->logger->debug(sprintf('Save step (%s) with context (%s)', $step, $context));

        $chatter->setStep($step);
        $chatter->setContext($context);

        $this->em->persist($chatter);
        $this->em->flush();
    }

    public function messageToCommand(string $message): string
    {
        $this->logger->debug(sprintf('Message (%s) to command', $message));

        $message = trim($message);
        if (str_starts_with($message, '/')) {
            return substr($message, 1);
        }

        $commands = [
            self::COMMAND_MENU, self::COMMAND_SET, self::COMMAND_LIST, self::COMMAND_SET_FIO, self::COMMAND_CHANGE
        ];

        foreach ($commands as $command) {
            if ($this->translator->trans('menu.' . $command) === $message) {
                return $command;
            }
        }

        return '';
    }

    public function sendWithMenu(Employee $chatter, string $translate, ?string $extraMessage = null): void
    {
        $messageText = $this->translator->trans($translate);

        if (null !== $extraMessage) {
            $messageText .= $extraMessage;
        }
        $this->logger->debug(sprintf('Send with menu message (%s)', $messageText));

        if ($chatter->getRole() === $chatter::ROLE_EMPLOYEE) {
            $buttons = [
                $this->translator->trans('menu.set'),
                $this->translator->trans('menu.change'),
            ];
        } else {
            $buttons = [
                $this->translator->trans('menu.set'),
                $this->translator->trans('menu.list'),
                $this->translator->trans('menu.change'),
            ];
        }

        try {
            $keyboard = new ReplyKeyboardMarkup(
                [
                    $buttons
                ],
                false,
                true,
            );
            $this->telegramApi->sendMessage($chatter->getChatId(), $messageText, null, false, null, $keyboard);
        } catch (Exception|InvalidArgumentException $e) {
            $this->logger->error(sprintf('Telegram API error: %s', $e->getMessage()));
        }
    }

    public function sendWithToMenu(int $chatId, string $translate): void
    {
        $messageText = $this->translator->trans($translate);
        $this->logger->debug(sprintf('Send with to menu message (%s)', $messageText));
        try {

            $keyboard = new ReplyKeyboardMarkup(
                [
                    [
                        $this->translator->trans('menu.menu'),
                    ]
                ],
                false,
                true,
            );
            $this->telegramApi->sendMessage($chatId, $messageText, null, false, null, $keyboard);

        } catch (Exception|InvalidArgumentException $e) {
            $this->logger->error(sprintf('Telegram API error: %s', $e->getMessage()));
        }
    }

    public function sendLinkToChangeVacations(int $chatId): void
    {
        $messageText = $this->adminUrl . '?token=' . $chatId;
        try {
            $this->telegramApi->sendMessage($chatId, $messageText);
        } catch (Exception|InvalidArgumentException $e) {
            $this->logger->error(sprintf('List message error: %s', $e->getMessage()));
        }
    }

    /**
     * @param Employee $chatter
     * @param Vacation[] $vacations
     *
     * @return void
     */
    public function sendVacations(Employee $chatter, array $vacations): void
    {
        $this->logger->debug(sprintf('Send (%d) employee vacations', count($vacations)));

        if (empty($vacations)) {
            $this->sendWithMenu($chatter, 'vacation.empty');

            return;
        }

        $employee = current($vacations)->getEmployee();
        $messageText = $employee->getFullName() . PHP_EOL;
        $messageText .= $this->translator->trans('vacation.list');

        $buttons = [];
        foreach ($vacations as $vacation) {
            $buttonText = sprintf(
                '%s - %s',
                $vacation->getStartDate()->format('d.m.Y'),
                $vacation->getEndDate()->format('d.m.Y')
            );

            $button = [
                'text' => $buttonText,
                'callback_data' => json_encode([
                    'command' => self::CALLBACK_CHANGE_VACATION,
                    'data' => $vacation->getId()
                ])
            ];

            $buttons[] = [$button];
        }

        if (count($buttons) < $employee::MAX_VACATIONS) {
            $buttons[] = [[
                'text' => $this->translator->trans('menu.add'),
                'callback_data' => json_encode([
                    'command' => self::CALLBACK_ADD_VACATION,
                    'data' => $employee->getId()
                ])
            ]];
        }

        try {
            $keyboard = new InlineKeyboardMarkup(
                array_values($buttons),
            );

            $this->telegramApi->sendMessage($chatter->getChatId(), $messageText, null, false, null, $keyboard);
        } catch (Exception|InvalidArgumentException $e) {
            $this->logger->error(sprintf('List message error: %s', $e->getMessage()));
        }
    }

    public function sendToGroup(string $message): void
    {
        $messageText = $this->translator->trans('vacation.remind') . $message;
        $this->logger->debug(sprintf('Send message %s to group', $messageText));

        try {
            $this->telegramApi->sendMessage($this->groupId, $messageText);
        } catch (Exception|InvalidArgumentException $e) {
            $this->logger->error(sprintf('Send to group error: %s', $e->getMessage()));
        }
    }

    public function validationError(int $chatId): void
    {
        $this->logger->error('Validation error');

        try {
            $messageText = $this->translator->trans('message.fail');
            $keyboard = new ReplyKeyboardMarkup(
                [
                    [
                        $this->translator->trans('menu.menu'),
                    ]
                ],
                false,
                true,
            );

            $this->telegramApi->sendMessage($chatId, $messageText, null, false, null, $keyboard);
        } catch (Exception|InvalidArgumentException $e) {
            $this->logger->error(sprintf('Validation message error: %s', $e->getMessage()));
        }
    }
}
