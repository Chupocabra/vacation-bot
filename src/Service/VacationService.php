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
    // space must be last
    private const DATES_DELIMITERS = ['-', ' '];
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

        foreach (self::DATES_DELIMITERS as $delimiter) {
            if (str_contains($message, $delimiter)) {
                $dates = explode($delimiter, $twoDates);
                $startDate = current($dates);
                $endDate = end($dates);
                try {
                    $startDate = new \DateTime(trim($startDate));
                    $endDate = new \DateTime(trim($endDate));
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
        }

        $this->logger->error(sprintf('There only one date in employee %d set vacation message', $employeeId));

        return false;
    }

    /**
     * @return Vacation[]
     */
    public function closestVacations(): array
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
                $vacation->getEndDate()->diff($vacation->getStartDate())->days + 1,
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

        foreach (self::DATES_DELIMITERS as $delimiter) {
            if (str_contains($message, $delimiter)) {
                $dates = explode($delimiter, $twoDates);
                $startDate = current($dates);
                $endDate = end($dates);
                try {
                    $startDate = new \DateTime(trim($startDate));
                    $endDate = new \DateTime(trim($endDate));
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
        }


        $this->logger->error(sprintf('There only one date in change vacation %d message', $vacationId));

        return false;
    }

    public function deleteVacation(int $vacationId): string
    {
        $vacationRepo = $this->em->getRepository(Vacation::class);
        $vacation = $vacationRepo->find($vacationId);

        if ($vacation) {
            $startDate = $vacation->getStartDate()->format('d.m.Y');
            $endDate = $vacation->getEndDate()->format('d.m.Y');

            $this->em->remove($vacation);
            $this->em->flush();

            return sprintf('%s-%s', $startDate, $endDate);
        }
        $this->logger->error(sprintf('Vacation %d not found', $vacationId));

        return '';
    }
}
