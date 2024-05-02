<?php

namespace App\Service;

use App\Entity\Employee;
use App\Entity\Vacation;
use App\Repository\EmployeeRepository;
use App\Repository\VacationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class VacationService extends AbstractService
{
    private const DATES_DELIMITER = ' ';
    private TranslatorInterface $translator;

    public function __construct(EntityManagerInterface $em, LoggerInterface $logger, TranslatorInterface $translator)
    {
        parent::__construct($em, $logger);
        $this->translator = $translator;
    }

    public function addVacation(int $employeeId, string $message): bool
    {
        $this->logger->debug(sprintf('Add vacation to customer %s, %s', $employeeId, $message));

        /** @var EmployeeRepository $employeeRepo */
        $employeeRepo = $this->em->getRepository(Employee::class);
        $employee = $employeeRepo->find($employeeId);

        if (null === $employee) {
            $this->logger->error(sprintf('Employee %d not found', $employeeId));

            return false;
        }

        $vacation = new Vacation();

        $twoDates = trim($message);

        if (str_contains($message, self::DATES_DELIMITER)) {
            [$startDate, $endDate] = explode(self::DATES_DELIMITER, $twoDates);
            try {
                $startDate = new \DateTime($startDate);
                $endDate = new \DateTime($endDate);
            } catch (\Exception $e) {
                $this->logger->error(sprintf('Employee %d parsing date error: %s', $employeeId, $e->getMessage()));

                return false;
            }

            $vacation->setStartDate($startDate);
            $vacation->setEndDate($endDate);

            $employee->addVacation($vacation);

            $this->em->persist($vacation);
            $this->em->flush();
            $this->logger->debug('Vacation added');

            return true;
        }

        $this->logger->error(sprintf('There only one date in employee %d set vacation message', $employeeId));

        return false;
    }

    /**
     * @return Vacation[]
     */
    public function closestVacationsMessage(): array
    {
        $this->logger->debug('Find closest vacations');

        /** @var VacationRepository $vacationRepo */
        $vacationRepo = $this->em->getRepository(Vacation::class);
        return $vacationRepo->findClosest();
    }

    /**
     * @param Vacation[] $vacations
     * @return string
     */
    public function prepareVacationsForChat(array $vacations): string
    {
        $employeeCards = [];
        foreach ($vacations as $vacation) {
            $employee = $vacation->getEmployee();
            $fullName = $employee->getFullName();

            if (isset($employeeCards[$fullName])) {
                $fullName = sprintf('%s (%d)', $fullName, $employee->getId());
            }

            $employeeCards[$fullName] = sprintf(
                "\n- %s\n- %s - %s\n- %s %s\n",
                $fullName,
                $vacation->getStartDate()->format('d.m.Y'), $vacation->getEndDate()->format('d.m.Y'),
                $vacation->getEndDate()->diff($vacation->getStartDate())->d + 1,
                $this->translator->trans('message.days')
            );
        }

        return "\n" . implode('', $employeeCards);
    }

    /**
     * @param int $employeeId
     * @return Vacation[]
     */
    public function employeeVacations(int $employeeId): array
    {
        $this->logger->debug(sprintf('Find employee %d vacations', $employeeId));

        /** @var VacationRepository $vacationRepo */
        $vacationRepo = $this->em->getRepository(Vacation::class);

        return $vacationRepo->findByEmployeeId($employeeId);
    }

    public function changeVacation(int $vacationId, string $message): bool
    {
        $this->logger->info(sprintf('Change %s vacation %s', $vacationId, $message));

        $vacationRepo = $this->em->getRepository(Vacation::class);
        $vacation = $vacationRepo->find($vacationId);

        if (null === $vacation) {
            $this->logger->error(sprintf('Vacation %d not found', $vacationId));

            return false;
        }

        $twoDates = trim($message);
        if (str_contains($message, self::DATES_DELIMITER)) {
            [$startDate, $endDate] = explode(self::DATES_DELIMITER, $twoDates);
            try {
                $startDate = new \DateTime($startDate);
                $endDate = new \DateTime($endDate);
            } catch (\Exception $e) {
                $this->logger->error(sprintf('Vacation %d parsing date error: %s', $vacationId, $e->getMessage()));

                return false;
            }

            $vacation->setStartDate($startDate);
            $vacation->setEndDate($endDate);

            $this->em->persist($vacation);
            $this->em->flush();
            $this->logger->debug('Vacation added');

            return true;
        }

        $this->logger->error(sprintf('There only one date in change vacation %d message', $vacationId));

        return false;
    }
}
