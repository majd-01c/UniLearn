<?php

namespace App\Service\JobOffer;

use App\Entity\User;
use App\Repository\CustomSkillRepository;

/**
 * Provides predefined lists of skills, education levels, and languages
 * for the ATS system, plus partner-specific custom skills.
 */
class SkillsProvider
{
    public function __construct(
        private readonly CustomSkillRepository $customSkillRepository,
    ) {
    }
    /**
     * Get all skills organized by category
     */
    public function getSkillsByCategory(): array
    {
        return [
            'Langages de programmation' => [
                'PHP', 'Python', 'Java', 'JavaScript', 'TypeScript', 
                'C#', 'C++', 'C', 'Ruby', 'Go', 'Swift', 'Kotlin', 'Rust',
                'Scala', 'R', 'MATLAB', 'Perl', 'Shell/Bash',
            ],
            'Frontend' => [
                'HTML', 'CSS', 'React', 'Vue.js', 'Angular', 'Svelte',
                'Bootstrap', 'Tailwind CSS', 'jQuery', 'SASS/SCSS',
                'Next.js', 'Nuxt.js', 'Webpack', 'Vite',
            ],
            'Backend & Frameworks' => [
                'Symfony', 'Laravel', 'Django', 'Flask', 'Spring Boot',
                'Node.js', 'Express.js', 'NestJS', 'ASP.NET', 'Ruby on Rails',
                'FastAPI', 'Gin', 'Echo',
            ],
            'Base de données' => [
                'MySQL', 'PostgreSQL', 'MongoDB', 'SQLite', 'Redis',
                'Oracle', 'SQL Server', 'MariaDB', 'Elasticsearch',
                'Cassandra', 'Firebase', 'DynamoDB',
            ],
            'DevOps & Cloud' => [
                'Git', 'Docker', 'Kubernetes', 'Linux', 'AWS', 'Azure',
                'Google Cloud', 'CI/CD', 'Jenkins', 'GitLab CI', 'GitHub Actions',
                'Terraform', 'Ansible', 'Nginx', 'Apache',
            ],
            'Mobile' => [
                'Android', 'iOS', 'React Native', 'Flutter', 'Ionic',
                'Xamarin', 'Swift UI', 'Kotlin Multiplatform',
            ],
            'Data & IA' => [
                'Machine Learning', 'Deep Learning', 'TensorFlow', 'PyTorch',
                'Pandas', 'NumPy', 'Scikit-learn', 'Data Analysis',
                'Big Data', 'Spark', 'Hadoop', 'Power BI', 'Tableau',
            ],
            'Autres compétences' => [
                'API REST', 'GraphQL', 'Microservices', 'WebSocket',
                'Agile/Scrum', 'UML', 'Tests unitaires', 'TDD',
                'Clean Code', 'Design Patterns', 'SOLID',
                'Sécurité web', 'OAuth', 'JWT',
            ],
        ];
    }

    /**
     * Get flat list of all skills
     */
    public function getAllSkills(): array
    {
        $skills = [];
        foreach ($this->getSkillsByCategory() as $category => $categorySkills) {
            $skills = array_merge($skills, $categorySkills);
        }
        return $skills;
    }

    /**
     * Get education levels (ordered from lowest to highest)
     */
    public function getEducationLevels(): array
    {
        return [
            'bac' => 'Baccalauréat',
            'bac+2' => 'BTS / DUT (Bac+2)',
            'licence' => 'Licence (Bac+3)',
            'master' => 'Master (Bac+5)',
            'ingenieur' => 'Diplôme d\'Ingénieur',
            'doctorat' => 'Doctorat',
        ];
    }

    /**
     * Get education level weight for scoring
     */
    public function getEducationWeight(string $level): int
    {
        $weights = [
            'bac' => 1,
            'bac+2' => 2,
            'licence' => 3,
            'master' => 4,
            'ingenieur' => 4,
            'doctorat' => 5,
        ];
        
        return $weights[strtolower($level)] ?? 0;
    }

    /**
     * Get available languages
     */
    public function getLanguages(): array
    {
        return [
            'Français',
            'Anglais',
            'Arabe',
            'Allemand',
            'Espagnol',
            'Italien',
            'Chinois',
            'Japonais',
        ];
    }

    /**
     * Get experience year options
     */
    public function getExperienceYearOptions(): array
    {
        return [
            0 => 'Débutant (0 ans)',
            1 => '1 an',
            2 => '2 ans',
            3 => '3 ans',
            4 => '4 ans',
            5 => '5+ ans',
        ];
    }

    /**
     * Get all skills for a specific partner (predefined + custom)
     */
    public function getAllSkillsForPartner(?User $partner = null): array
    {
        $predefinedSkills = $this->getAllSkills();
        
        if (!$partner || !in_array('ROLE_BUSINESS_PARTNER', $partner->getRoles(), true)) {
            return $predefinedSkills;
        }

        $customSkills = $this->customSkillRepository->findByPartner($partner);
        $customSkillNames = array_map(fn($skill) => $skill->getName(), $customSkills);

        return array_merge($predefinedSkills, $customSkillNames);
    }

    /**
     * Get skills organized by category for a specific partner
     */
    public function getSkillsByCategoryForPartner(?User $partner = null): array
    {
        $predefinedSkillsByCategory = $this->getSkillsByCategory();
        
        if (!$partner || !in_array('ROLE_BUSINESS_PARTNER', $partner->getRoles(), true)) {
            return $predefinedSkillsByCategory;
        }

        // Get custom skills for the partner
        $customSkills = $this->customSkillRepository->getSkillsByCategoryForPartner($partner);
        
        // Merge custom skills into the predefined categories
        $mergedSkills = $predefinedSkillsByCategory;
        
        foreach ($customSkills as $category => $skills) {
            if (isset($mergedSkills[$category])) {
                $mergedSkills[$category] = array_merge($mergedSkills[$category], $skills);
            } else {
                $mergedSkills[$category] = $skills;
            }
        }

        return $mergedSkills;
    }

    /**
     * Check if a skill is a custom skill for a partner
     */
    public function isCustomSkillForPartner(string $skillName, User $partner): bool
    {
        if (!in_array('ROLE_BUSINESS_PARTNER', $partner->getRoles(), true)) {
            return false;
        }

        return $this->customSkillRepository->existsForPartner($partner, $skillName);
    }

    /**
     * Get partner custom skills only
     */
    public function getCustomSkillsForPartner(User $partner): array
    {
        if (!in_array('ROLE_BUSINESS_PARTNER', $partner->getRoles(), true)) {
            return [];
        }

        $customSkills = $this->customSkillRepository->findByPartner($partner);
        return array_map(fn($skill) => $skill->getName(), $customSkills);
    }
}
