<?php

namespace App\Command;

use App\Entity\Vacation;
use App\Repository\VacationRepository;
use App\Service\ChatService;
use App\Service\VacationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VacationRemindCommand extends Command
{
    protected static $defaultName = 'bot:vacation:remind';
    protected static $defaultDescription = 'Remind about vacation';

    private EntityManagerInterface $em;
    private ChatService $chatService;
    private VacationService $vacationService;

    public function __construct(EntityManagerInterface $em, ChatService $chatService, VacationService $vacationService)
    {
        $this->em = $em;
        $this->chatService = $chatService;
        $this->vacationService = $vacationService;

        parent::__construct('bot:vacation:remind');
    }

    protected function configure(): void
    {
        $this->setDescription('Remind about vacation');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var VacationRepository $vacationRepo */
        $vacationRepo = $this->em->getRepository(Vacation::class);
        $vacations = $vacationRepo->findForRemind();

        if (empty($vacations)) {
            $output->writeln('Not found vacations to remind');

            return Command::SUCCESS;
        }

        $remindVacationsMessage = $this->vacationService->prepareVacationsForChat($vacations);
        $this->chatService->sendToGroup($remindVacationsMessage);

        return Command::SUCCESS;
    }
}
