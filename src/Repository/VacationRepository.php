<?php

namespace App\Repository;

use App\Entity\Vacation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vacation>
 *
 * @method Vacation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Vacation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Vacation[]    findAll()
 * @method Vacation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VacationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vacation::class);
    }

    /**
     * @return Vacation[]
     */
    public function findClosest(): array
    {
        $nearDate = new \DateTime('+30 days');

        return $this->createQueryBuilder('v')
            ->andWhere('v.startDate < :nearDates')
            ->andWhere('v.startDate > :nowDate')
            ->setParameter('nearDates', $nearDate)
            ->setParameter('nowDate', new \DateTime())
            ->orderBy('v.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Vacation[]
     */
    public function findByEmployeeId(int $employeeId): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.employee = :employeeId')
            ->setParameter('employeeId', $employeeId)
            ->orderBy('v.startDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Vacation[]
     */
    public function findForRemind(): array
    {
        $firstRemind = new \DateTime('+7 days');
        $secondRemind = new \DateTime('+3 days');
        $thirdRemind = new \DateTime('+1 day');

        return $this->createQueryBuilder('v')
            ->where('v.startDate = :firstRemindDate')
            ->setParameter('firstRemindDate', $firstRemind->format('Y-m-d'))
            ->orWhere('v.startDate = :secondRemindDate')
            ->setParameter('secondRemindDate', $secondRemind->format('Y-m-d'))
            ->orWhere('v.startDate = :thirdRemindDate')
            ->setParameter('thirdRemindDate', $thirdRemind->format('Y-m-d'))
            ->orderBy('v.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
