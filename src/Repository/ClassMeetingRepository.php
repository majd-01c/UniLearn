<?php

namespace App\Repository;

use App\Entity\ClassMeeting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClassMeeting>
 */
class ClassMeetingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClassMeeting::class);
    }

    /**
     * Find all meetings for a teacher classe
     */
    public function findByTeacherClasse(int $teacherClasseId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.teacherClasse = :teacherClasseId')
            ->setParameter('teacherClasseId', $teacherClasseId)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find live meetings for a classe
     */
    public function findLiveMeetingsForClasse(int $classeId): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.teacherClasse', 'tc')
            ->andWhere('tc.classe = :classeId')
            ->andWhere('m.status = :status')
            ->setParameter('classeId', $classeId)
            ->setParameter('status', ClassMeeting::STATUS_LIVE)
            ->orderBy('m.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active/scheduled meetings for a classe
     */
    public function findUpcomingMeetingsForClasse(int $classeId): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.teacherClasse', 'tc')
            ->andWhere('tc.classe = :classeId')
            ->andWhere('m.status IN (:statuses)')
            ->setParameter('classeId', $classeId)
            ->setParameter('statuses', [ClassMeeting::STATUS_SCHEDULED, ClassMeeting::STATUS_LIVE])
            ->orderBy('m.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find meetings by room code
     */
    public function findByRoomCode(string $roomCode): ?ClassMeeting
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.roomCode = :roomCode')
            ->setParameter('roomCode', $roomCode)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
