<?php

namespace App\Service;

use App\Entity\Employee;
use App\Repository\EmployeeRepository;

class EmployeeService extends AbstractService
{
    public function findByChat(int $chatId): ?Employee
    {
        /** @var EmployeeRepository $employeeRepo */
        $employeeRepo = $this->em->getRepository(Employee::class);

        return $employeeRepo->findByChatId($chatId);
    }

    public function findById(int $id): ?Employee
    {
        /** @var EmployeeRepository $employeeRepo */
        $employeeRepo = $this->em->getRepository(Employee::class);

        return $employeeRepo->find($id);
    }

    public function new(int $chatId): Employee
    {
        $this->logger->debug(sprintf('Created new employee %d', $chatId));

        $chat = new Employee();
        $chat->setChatId($chatId);

        $this->em->persist($chat);
        $this->em->flush();

        return $chat;
    }

    public function save(Employee $chatter, string $message): int
    {
        $this->logger->debug('Save employee %s'. $message);
        $fullName = trim($message);

        if ($chatter->getRole() === $chatter::ROLE_ADMIN) {
            $employee = new Employee();
            $employee->setFullName($fullName);
        } else {
            $employee = $chatter;
        }

        $employee->setFullName($fullName);


        $this->em->persist($employee);
        $this->em->flush();

        return $employee->getId();
    }
}
