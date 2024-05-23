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

    public function addVacation(int $employeeId, string $message): ?Vacation
    {
        $this->logger->debug(sprintf('Add vacation to customer %s, %s', $employeeId, $message));

        /** @var EmployeeRepository $employeeRepo */
        $employeeRepo = $this->em->getRepository(Employee::class);
        $employee = $employeeRepo->find($employeeId);

        if (null === $employee) {
            $this->logger->error(sprintf('Employee %d not found', $employeeId));

            return null;
        }

        $vacation = new Vacation();

        if ($this->validateMessageToDate($employee->getId(), $vacation, $message, trim($message))) {
            $employee->addVacation($vacation);
            $this->em->persist($vacation);
            $this->em->flush();
            $this->logger->debug('Vacation added');

            return $vacation;
        }

        return null;
    }

    private function validateMessageToDate(int $employeeId, Vacation $vacation, string $message, string $twoDates): bool
    {
        foreach (self::DATES_DELIMITERS as $delimiter) {
            if (str_contains($message, $delimiter)) {
                $dates = explode($delimiter, $twoDates);
                $startDate = current($dates);
                $endDate = end($dates);
                try {
                    $startDate = new \DateTime(trim($startDate));
                    $endDate = new \DateTime(trim($endDate));

                    if ($endDate->diff($startDate)->days < 1) {
                        $this->logger->error('EndDate less then startDate');

                        return false;
                    }
                } catch (\Exception $e) {
                    $this->logger->error(sprintf('Employee %d parsing date error: %s', $employeeId, $e->getMessage()));

                    return false;
                }

                $vacation->setStartDate($startDate);
                $vacation->setEndDate($endDate);

                return true;
            }
        }

        $this->logger->error(sprintf('There only one date in employee %d set vacation message', $employeeId));

        return false;
    }

    public function getFormattedVacationDates(Vacation $vacation): string
    {
        return sprintf('%s - %s', $vacation->getStartDate()->format('d.m.Y'), $vacation->getEndDate()->format('d.m.Y'));
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
    public function prepareVacations(Employee $employee, array $vacations): string
    {
        $message =  $employee->getFullName();
        if (empty($vacations)) {
            $message .= PHP_EOL . $this->translator->trans('vacation.empty');
        }
        foreach ($vacations as $vacation) {
            $message .= sprintf(
                "\n- %s - %s\n- %s %s\n",
                $vacation->getStartDate()->format('d.m.Y'), $vacation->getEndDate()->format('d.m.Y'),
                $vacation->getEndDate()->diff($vacation->getStartDate())->days + 1,
                $this->translator->trans('message.days')
            );
        }

        return PHP_EOL . $message;
    }

    /**
     * @param Vacation[] $vacations
     * @return string
     */
    public function prepareVacationsForAll(array $vacations): string
    {
        $employeeCards = [];
        foreach ($vacations as $vacation) {
            $employee = $vacation->getEmployee();
            $fullName = $employee->getFullName();

            $employeeCards[] = sprintf(
                "\n- %s\n- %s\n- %s %s\n",
                $fullName,
                $this->getFormattedVacationDates($vacation),
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

    public function changeVacation(int $vacationId, string $message): ?Vacation
    {
        $this->logger->info(sprintf('Change %s vacation %s', $vacationId, $message));

        $vacationRepo = $this->em->getRepository(Vacation::class);
        $vacation = $vacationRepo->find($vacationId);

        if (null === $vacation) {
            $this->logger->error(sprintf('Vacation %d not found', $vacationId));

            return null;
        }

        if ($this->validateMessageToDate($vacation->getEmployee()->getId(), $vacation, $message, trim($message))) {
            $this->em->persist($vacation);
            $this->em->flush();
            $this->logger->debug('Vacation added');

            return $vacation;
        }
        $this->logger->info(sprintf('Vacation %s not changed', $vacationId));

        return null;
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
