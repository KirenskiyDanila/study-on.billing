<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 *
 * @method Transaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findAll()
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function save(Transaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Transaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return Transaction[] Returns an array of Transaction objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Transaction
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    public function findWithFilter(
        User $user,
        int $type = null,
        string $code = null,
        bool $skipExpired = null
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.client = :user')
            ->setParameter('user', $user->getId());
        if ($type !== null) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }
        if ($code !== null) {
            $qb->innerJoin('t.course', 'c', 'WITH', 'c.id = t.course')
                ->andWhere('c.code = :code')
                ->setParameter('code', $code);
        }
        if ($skipExpired === true) {
            $qb->andWhere('t.expiresAt > :now OR t.expiresAt is null')
                ->setParameter('now', new \DateTime('now'));
        }
        return $qb
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findExpiringTransactions(User $user)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.client = :user')
            ->setParameter('user', $user->getId())
            ->andWhere('t.expiresAt > :now OR t.expiresAt is null')
            ->setParameter('now', new \DateTime('now'))
            ->andWhere('t.expiresAt < :tomorrow')
            ->setParameter('tomorrow', (new DateTime('now'))->modify("+1 days"))
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTransactionsForReport(Course $course)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.createdAt > :monthAgo')
            ->setParameter('monthAgo', (new DateTime('now'))->modify("-30 days"))
            ->innerJoin('t.course', 'c', 'WITH', 'c.id = t.course')
            ->andWhere('c.id = :id')
            ->setParameter('id', $course)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
