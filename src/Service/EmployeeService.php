<?php

namespace App\Service;

use App\Entity\Employee;
use App\Repository\EmployeeRepository;

class EmployeeService extends AbstractService
{
    private const CUSTOMERS_ON_PAGE = 3;
    public function save(string $message): int
    {
        $this->logger->debug('Save employee %s'. $message);
        $fullName = trim($message);

        $employee = new Employee();
        $employee->setFullName($fullName);

        $this->em->persist($employee);
        $this->em->flush();

        return $employee->getId();
    }

    public function total(): int
    {
        /** @var EmployeeRepository $employeeRepo */
        $employeeRepo = $this->em->getRepository(Employee::class);

        return ceil(count($employeeRepo->findAll()) / self::CUSTOMERS_ON_PAGE);
    }

    /**
     * @return Employee[]
     */
    public function list(int $page = 1): array
    {
        /** @var EmployeeRepository $employeeRepo */
        $employeeRepo = $this->em->getRepository(Employee::class);

        return $employeeRepo->getPageCustomers($page, self::CUSTOMERS_ON_PAGE);
    }
}
