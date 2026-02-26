<?php

namespace App\Repository;

use App\Entity\CustomSkill;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomSkill>
 */
class CustomSkillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomSkill::class);
    }

    /**
     * Find all custom skills for a partner
     */
    public function findByPartner(User $partner): array
    {
        return $this->createQueryBuilder('cs')
            ->andWhere('cs.partner = :partner')
            ->setParameter('partner', $partner)
            ->orderBy('cs.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search skills by name for a partner with optional limit
     */
    public function searchByPartner(User $partner, string $query = '', int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('cs')
            ->andWhere('cs.partner = :partner')
            ->setParameter('partner', $partner)
            ->orderBy('cs.name', 'ASC')
            ->setMaxResults($limit);

        if (!empty($query)) {
            $qb->andWhere('cs.name LIKE :query')
                ->setParameter('query', '%' . trim($query) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Check if a skill name already exists for a partner
     */
    public function existsForPartner(User $partner, string $skillName): bool
    {
        return $this->createQueryBuilder('cs')
            ->select('COUNT(cs.id)')
            ->andWhere('cs.partner = :partner')
            ->andWhere('LOWER(cs.name) = LOWER(:name)')
            ->setParameter('partner', $partner)
            ->setParameter('name', trim($skillName))
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Find custom skill by name for a partner
     */
    public function findByPartnerAndName(User $partner, string $skillName): ?CustomSkill
    {
        return $this->createQueryBuilder('cs')
            ->andWhere('cs.partner = :partner')
            ->andWhere('LOWER(cs.name) = LOWER(:name)')
            ->setParameter('partner', $partner)
            ->setParameter('name', trim($skillName))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get skills grouped by category for a partner
     */
    public function getSkillsByCategoryForPartner(User $partner): array
    {
        $skills = $this->findByPartner($partner);
        $grouped = [];

        foreach ($skills as $skill) {
            $category = $skill->getCategory() ?? 'Mes compétences personnalisées';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $skill->getName();
        }

        return $grouped;
    }

    /**
     * Delete skill by partner and skill name
     */
    public function deleteByPartnerAndName(User $partner, string $skillName): bool
    {
        $skill = $this->findByPartnerAndName($partner, $skillName);
        
        if (!$skill) {
            return false;
        }

        $this->getEntityManager()->remove($skill);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Create a new skill for a partner
     */
    public function createForPartner(User $partner, string $skillName, ?string $category = null, ?string $description = null): CustomSkill
    {
        $skill = new CustomSkill();
        $skill->setPartner($partner);
        $skill->setName($skillName);
        $skill->setCategory($category);
        $skill->setDescription($description);

        $this->getEntityManager()->persist($skill);
        $this->getEntityManager()->flush();

        return $skill;
    }
}