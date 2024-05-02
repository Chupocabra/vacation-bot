<?php

namespace App\Service;

use App\Entity\Chat;
use App\Entity\Employee;
use App\Entity\Vacation;
use App\Repository\ChatRepository;
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
    public const MAX_VACATIONS = 3;

    public const COMMAND_MENU = 'menu';
    public const COMMAND_SET = 'set';
    public const COMMAND_SET_FIO = 'fio';
    public const COMMAND_DATE = 'date';
    public const COMMAND_LIST = 'list';
    public const COMMAND_CHANGE = 'change';

    public const CALLBACK_VACATIONS = 'vacations';
    public const CALLBACK_PAGINATION = 'pagination';
    public const CALLBACK_CHANGE_VACATION = 'change_vacation';
    public const CALLBACK_ADD_VACATION = 'add_vacation';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly TelegramApiService $telegramApi,
        private readonly string $groupId,
        EntityManagerInterface $em,
        LoggerInterface $logger,
    ) {
        parent::__construct($em, $logger);
    }

    public function getChat(int $chatId): ?Chat
    {
        /** @var ChatRepository $chatRepo */
        $chatRepo = $this->em->getRepository(Chat::class);

        return $chatRepo->findByChatId($chatId);
    }

    public function newChat(int $chatId): Chat
    {
        $this->logger->debug(sprintf('Created new chat %d', $chatId), ['tgChat' =>$chatId]);

        $chat = new Chat();
        $chat->setChatId($chatId);

        $this->em->persist($chat);
        $this->em->flush();

        return $chat;
    }

    public function saveStep(Chat $chat, ?string $step = null, ?string $context = null): void
    {
        $this->logger->debug(sprintf('Save step (%s) with context (%s)', $step, $context), ['tgChat' => $chat->getChatId()]);

        $chat->setStep($step);
        $chat->setContext($context);

        $this->em->persist($chat);
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

    public function sendWithMenu(int $chatId, string $translate, ?string $extraMessage = null): void
    {
        $messageText = $this->translator->trans($translate);

        if (null !== $extraMessage) {
            $messageText .= $extraMessage;
        }
        $this->logger->debug(sprintf('Send with menu message (%s)', $messageText), ['tgChat' => $chatId]);

        try {
            $keyboard = new ReplyKeyboardMarkup(
                [
                    [
                        $this->translator->trans('menu.set'),
                        $this->translator->trans('menu.list'),
                        $this->translator->trans('menu.change'),
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

    public function sendWithToMenu(int $chatId, string $translate): void
    {
        $messageText = $this->translator->trans($translate);
        $this->logger->debug(sprintf('Send with to menu message (%s)', $messageText), ['tgChat' => $chatId]);
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

    /**
     * @param int $chatId
     * @param Employee[] $employees
     * @param int $page
     * @param int $totalPage
     *
     * @return void
     */
    public function sendEmployees(int $chatId, array $employees, int $page = 1, int $totalPage = 1): void
    {
        $messageText = $this->translator->trans('message.employees');
        $this->logger->debug(sprintf('Send employees message page (%d/%d)', $page, $totalPage), ['tgChat' => $chatId]);

        $buttons = [];
        foreach ($employees as $employee) {
            $fullName = $employee->getFullName();

            if (isset($buttons[$fullName])) {
                $fullName = sprintf('%s (%s)', $fullName, $employee->getId());
            }

            $button = [
                'text' => $fullName,
                'callback_data' => json_encode([
                    'command' => self::CALLBACK_VACATIONS,
                    'data' => $employee->getId()
                ])
            ];
            $buttons[$fullName] = [$button];
        }

        $pagination = [];
        if ($page !== 1) {
            $pagination[] = [
                'text' => '<<',
                'callback_data' => json_encode([
                    'command' => self::CALLBACK_PAGINATION,
                    'data' => $page - 1
                ])
            ];
        }

        $pagination[] = [
            'text' => sprintf('%s/%s', $page, $totalPage),
            'callback_data' => json_encode([
                'command' => self::CALLBACK_PAGINATION,
                'data' => 1
            ])
        ];

        if ($totalPage > $page) {
            $pagination[] = [
                'text' => '>>',
                'callback_data' => json_encode([
                    'command' => self::CALLBACK_PAGINATION,
                    'data' => $page + 1
                ])
            ];
        }
        $buttons[] = $pagination;

        try {
            $keyboard = new InlineKeyboardMarkup(
                array_values($buttons),
            );

            $this->telegramApi->sendMessage($chatId, $messageText, null, false, null, $keyboard);
        } catch (Exception|InvalidArgumentException $e) {
            $this->logger->error(sprintf('List message error: %s', $e->getMessage()));
        }
    }

    /**
     * @param int $chatId
     * @param Vacation[] $vacations
     *
     * @return void
     */
    public function sendVacations(int $chatId, array $vacations): void
    {
        $messageText = $this->translator->trans('vacation.list');
        $this->logger->debug(sprintf('Send (%d) employee vacations', count($vacations)), ['tgChat' => $chatId]);

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

        if (count($buttons) < self::MAX_VACATIONS) {
            $buttons[] = [[
                'text' => $this->translator->trans('menu.add'),
                'callback_data' => json_encode([
                    'command' => self::CALLBACK_ADD_VACATION,
                    'data' => $vacations[0]->getEmployee()->getId()
                ])
            ]];
        }

        try {
            $keyboard = new InlineKeyboardMarkup(
                array_values($buttons),
            );

            $this->telegramApi->sendMessage($chatId, $messageText, null, false, null, $keyboard);
        } catch (Exception|InvalidArgumentException $e) {
            $this->logger->error(sprintf('List message error: %s', $e->getMessage()));
        }
    }

    public function sendToGroup(string $message): void
    {
        $messageText = $this->translator->trans('vacation.remind') . $message;
        $this->logger->debug(sprintf('Send message %s to group', $messageText), ['tgChat' => $this->groupId]);

        try {
            $this->telegramApi->sendMessage($this->groupId, $messageText);
        } catch (Exception|InvalidArgumentException $e) {
            $this->logger->error(sprintf('Send to group error: %s', $e->getMessage()));
        }
    }

    public function validationError(int $chatId): void
    {
        $this->logger->error('Validation error', ['tgChat' => $chatId]);

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
