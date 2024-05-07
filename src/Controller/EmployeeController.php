<?php

namespace App\Controller;

use App\Entity\Employee;
use App\Entity\Vacation;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmployeeController extends AbstractController
{
    private EntityManagerInterface $em;
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/list', name: 'employee_list')]
    public function list(Request $request, PaginatorInterface $paginator): Response
    {
        $employeeQueryBuilder = $this->em->getRepository(Employee::class)->createQueryBuilder('e')
            ->addOrderBy('e.fullName', 'ASC')
        ;

        $employees = $paginator->paginate(
            $employeeQueryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            25
        );

        $vacations = [];
        foreach ($employees as $employee) {
            $vacations[$employee->getId()] = $employee->getVacations();
        }

        return $this->render('employee/list.html.twig', [
            'token' => $request->query->getInt('token'),
            'employees' => $employees,
            'vacations' => $vacations,
        ]);
    }

    #[Route('/save', name: 'employee_save', methods: 'POST')]
    public function save(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $employeeRepo = $this->em->getRepository(Employee::class);
        $vacationRepo = $this->em->getRepository(Vacation::class);

        $employee = $employeeRepo->find(array_shift($data)['employee']);
        try {
            foreach ($data as $item) {
                $toDelete = true;
                $vacation = $vacationRepo->find($item['id']);
                if (null === $vacation) {
                    $vacation = new Vacation();
                    $toDelete = false;
                }

                if (!empty($item['start']) && !empty($item['end'])) {
                    $vacation->setStartDate(new \DateTime($item['start']));
                    $vacation->setEndDate(new \DateTime($item['end']));
                    $vacation->setEmployee($employee);

                    $this->em->persist($vacation);
                } else if ($toDelete) {
                    $this->em->remove($vacation);
                }
            }
        } catch (\Exception $e) {
            return $this->json([
                'message' => $e->getMessage()
            ], 400);
        }
        $this->em->flush();

        $result = [];
        if (null !== $employee) {
            foreach ($employee->getVacations() as $vacation) {
                $result[] = $vacation->getId();
            }
        }

        return $this->json($result);
    }
}
