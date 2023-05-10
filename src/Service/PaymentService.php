<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use DateInterval;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

class PaymentService
{
    /**
     * @throws ORMException|Exception
     */
    private EntityManagerInterface $em;
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public function payment(User $user, Course $course): array
    {
        $this->em->getConnection()->beginTransaction();
        try {
            $user->setBalance($user->getBalance() - $course->getPrice());

            $transaction = new Transaction();
            $transaction->setCourse($course);
            $transaction->setAmount($course->getPrice() ?? 0.0);
            $transaction->setClient($user);
            $transaction->setStringType('payment');
            $currentData = new \DateTimeImmutable('now');
            $transaction->setCreatedAt($currentData);
            if ($course->getStringType() === "rent") {
                $transaction->setExpiresAt($currentData->add(new DateInterval('P7D')));
            }

            $this->em->persist($user);
            $this->em->persist($transaction);
            $this->em->flush();
            $this->em->getConnection()->commit();

            $json['success'] = true;
            $json['course_type'] = $course->getStringType();
            if ($course->getStringType() === 'rent') {
                $json['expires_at'] = date_format($transaction->getExpiresAt(), "Y-m-dTH:i:s");
            }
            return $json;
        } catch (ORMException|Exception $e) {
            $this->em->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function deposit(User $user, float $value): void
    {
        $this->em->getConnection()->beginTransaction();
        try {
            $user->setBalance($user->getBalance() + $value);

            $transaction = new Transaction();

            $transaction->setAmount($value);
            $transaction->setClient($user);
            $transaction->setStringType('deposit');
            $currentData = new \DateTimeImmutable('now');
            $transaction->setCreatedAt($currentData);

            $this->em->persist($user);
            $this->em->persist($transaction);
            $this->em->flush();
            $this->em->getConnection()->commit();
        } catch (Exception $e) {
            $this->em->getConnection()->rollBack();
            throw $e;
        }
    }
}
