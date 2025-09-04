<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExchangeRate;
use App\Enum\CurrencyPair;
use App\Repository\Interface\ExchangeRateRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExchangeRate>
 */
class ExchangeRateRepository extends ServiceEntityRepository implements ExchangeRateRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeRate::class);
    }

    /**
     * @return ExchangeRate[]
     */
    public function findLast24Hours(CurrencyPair $pair): array
    {
        $yesterday = new \DateTimeImmutable('-24 hours');

        return $this->createQueryBuilder('er')
            ->where('er.pair = :pair')
            ->andWhere('er.createdAt >= :yesterday')
            ->setParameter('pair', $pair)
            ->setParameter('yesterday', $yesterday)
            ->orderBy('er.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ExchangeRate[]
     */
    public function findByDate(CurrencyPair $pair, \DateTimeInterface $date): array
    {
        $startOfDay = (clone $date)->setTime(0, 0, 0);
        $endOfDay = (clone $date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('er')
            ->where('er.pair = :pair')
            ->andWhere('er.createdAt >= :start')
            ->andWhere('er.createdAt <= :end')
            ->setParameter('pair', $pair)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->orderBy('er.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(ExchangeRate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Clean up old records (older than 30 days)
     */
    public function cleanupOldRecords(): int
    {
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');

        return $this->createQueryBuilder('er')
            ->delete()
            ->where('er.createdAt < :thirtyDaysAgo')
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->getQuery()
            ->execute();
    }
}
