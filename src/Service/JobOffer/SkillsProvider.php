<?php

namespace App\Service\JobOffer;

/**
 * Provides predefined lists of skills, education levels, and languages
 * for the ATS system.
 */
class SkillsProvider
{
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
}
