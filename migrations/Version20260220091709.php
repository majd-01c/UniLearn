<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220091709 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE classe_contenu CHANGE is_hidden is_hidden TINYINT NOT NULL');
        $this->addSql('ALTER TABLE classe_course CHANGE is_hidden is_hidden TINYINT NOT NULL');
        $this->addSql('ALTER TABLE forum_reply CHANGE is_accepted is_accepted TINYINT NOT NULL');
        $this->addSql('ALTER TABLE job_application ADD score INT DEFAULT NULL, ADD score_breakdown JSON DEFAULT NULL, ADD scored_at DATETIME DEFAULT NULL, ADD extracted_data JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE job_offer ADD required_skills JSON DEFAULT NULL, ADD preferred_skills JSON DEFAULT NULL, ADD min_experience_years INT DEFAULT NULL, ADD min_education VARCHAR(50) DEFAULT NULL, ADD required_languages JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE classe_contenu CHANGE is_hidden is_hidden TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE classe_course CHANGE is_hidden is_hidden TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE forum_reply CHANGE is_accepted is_accepted TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE job_application DROP score, DROP score_breakdown, DROP scored_at, DROP extracted_data');
        $this->addSql('ALTER TABLE job_offer DROP required_skills, DROP preferred_skills, DROP min_experience_years, DROP min_education, DROP required_languages');
    }
}
