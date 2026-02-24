<?php

namespace App\Controller\Partner;

use App\Entity\CustomSkill;
use App\Repository\CustomSkillRepository;
use App\Service\JobOffer\SkillsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/partner/skills')]
#[IsGranted('ROLE_BUSINESS_PARTNER')]
class PartnerSkillController extends AbstractController
{
    public function __construct(
        private readonly CustomSkillRepository $customSkillRepository,
        private readonly SkillsProvider $skillsProvider,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Search skills (both predefined and custom) for autocomplete
     */
    #[Route('/search', name: 'app_partner_skill_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->query->get('q', ''));
        $limit = min(20, max(5, (int) $request->query->get('limit', 20)));

        /** @var \App\Entity\User $partner */
        $partner = $this->getUser();

        $suggestions = [];

        // Get predefined skills from SkillsProvider
        $allSkills = $this->skillsProvider->getAllSkills();
        
        // Filter by query if provided
        if (!empty($query)) {
            $allSkills = array_filter($allSkills, function($skill) use ($query) {
                return stripos($skill, $query) !== false;
            });
        }

        // Add predefined skills to suggestions
        foreach (array_slice($allSkills, 0, $limit) as $skill) {
            $suggestions[] = [
                'value' => $skill,
                'label' => $skill,
                'type' => 'predefined',
                'category' => $this->getSkillCategory($skill)
            ];
        }

        // Get custom skills for the current partner
        $customSkills = $this->customSkillRepository->searchByPartner($partner, $query, $limit);
        
        foreach ($customSkills as $customSkill) {
            $suggestions[] = [
                'value' => $customSkill->getName(),
                'label' => $customSkill->getName(),
                'type' => 'custom',
                'category' => $customSkill->getCategory() ?? 'Mes compétences',
                'description' => $customSkill->getDescription(),
                'id' => $customSkill->getId(),
            ];
        }

        // Sort by relevance (exact matches first, then partial matches)
        if (!empty($query)) {
            usort($suggestions, function($a, $b) use ($query) {
                $aExact = strcasecmp($a['value'], $query) === 0;
                $bExact = strcasecmp($b['value'], $query) === 0;
                
                if ($aExact && !$bExact) return -1;
                if (!$aExact && $bExact) return 1;
                
                return strcasecmp($a['value'], $b['value']);
            });
        }

        return new JsonResponse([
            'suggestions' => array_slice($suggestions, 0, $limit),
            'total' => count($suggestions),
        ]);
    }

    /**
     * Add a new custom skill for the partner
     */
    #[Route('/add', name: 'app_partner_skill_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $partner */
        $partner = $this->getUser();
        
        $data = json_decode($request->getContent(), true);
        $skillName = trim($data['name'] ?? '');
        $category = trim($data['category'] ?? '');
        $description = trim($data['description'] ?? '');

        if (empty($skillName)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le nom de la compétence est requis.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if skill name already exists (predefined or custom)
        if (in_array($skillName, $this->skillsProvider->getAllSkills())) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cette compétence existe déjà dans la liste prédéfinie.'
            ], Response::HTTP_CONFLICT);
        }

        if ($this->customSkillRepository->existsForPartner($partner, $skillName)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cette compétence existe déjà dans vos compétences personnalisées.'
            ], Response::HTTP_CONFLICT);
        }

        try {
            $customSkill = new CustomSkill();
            $customSkill->setPartner($partner);
            $customSkill->setName($skillName);
            $customSkill->setCategory(!empty($category) ? $category : null);
            $customSkill->setDescription(!empty($description) ? $description : null);

            // Validate the entity
            $violations = $this->validator->validate($customSkill);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = $violation->getMessage();
                }
                
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreurs de validation : ' . implode(', ', $errors)
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->customSkillRepository->createForPartner(
                $partner,
                $skillName,
                !empty($category) ? $category : null,
                !empty($description) ? $description : null
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Compétence ajoutée avec succès.',
                'skill' => [
                    'value' => $skillName,
                    'label' => $skillName,
                    'type' => 'custom',
                    'category' => $category ?: 'Mes compétences',
                    'description' => $description,
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout de la compétence : ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a custom skill for the partner
     */
    #[Route('/delete', name: 'app_partner_skill_delete', methods: ['DELETE'])]
    public function delete(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $partner */
        $partner = $this->getUser();
        
        $data = json_decode($request->getContent(), true);
        $skillName = trim($data['name'] ?? '');

        if (empty($skillName)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le nom de la compétence est requis.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if it's a predefined skill (cannot be deleted)
        if (in_array($skillName, $this->skillsProvider->getAllSkills())) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Les compétences prédéfinies ne peuvent pas être supprimées.'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $deleted = $this->customSkillRepository->deleteByPartnerAndName($partner, $skillName);
            
            if (!$deleted) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Compétence introuvable.'
                ], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Compétence supprimée avec succès.'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all skills for the partner (predefined + custom)
     */
    #[Route('/list', name: 'app_partner_skill_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var \App\Entity\User $partner */
        $partner = $this->getUser();

        // Get predefined skills by category
        $predefinedSkills = $this->skillsProvider->getSkillsByCategory();
        
        // Get custom skills for the partner
        $customSkillsByCategory = $this->customSkillRepository->getSkillsByCategoryForPartner($partner);

        // Merge both arrays
        $allSkillsByCategory = [];

        // Add predefined skills
        foreach ($predefinedSkills as $category => $skills) {
            $allSkillsByCategory[$category] = [];
            foreach ($skills as $skill) {
                $allSkillsByCategory[$category][] = [
                    'value' => $skill,
                    'label' => $skill,
                    'type' => 'predefined',
                ];
            }
        }

        // Add custom skills
        foreach ($customSkillsByCategory as $category => $skills) {
            if (!isset($allSkillsByCategory[$category])) {
                $allSkillsByCategory[$category] = [];
            }
            foreach ($skills as $skill) {
                $allSkillsByCategory[$category][] = [
                    'value' => $skill,
                    'label' => $skill,
                    'type' => 'custom',
                ];
            }
        }

        return new JsonResponse([
            'skillsByCategory' => $allSkillsByCategory,
            'customSkillsCount' => count($this->customSkillRepository->findByPartner($partner)),
        ]);
    }

    /**
     * Get the category for a predefined skill
     */
    private function getSkillCategory(string $skill): string
    {
        $skillsByCategory = $this->skillsProvider->getSkillsByCategory();
        
        foreach ($skillsByCategory as $category => $skills) {
            if (in_array($skill, $skills)) {
                return $category;
            }
        }

        return 'Autres';
    }
}