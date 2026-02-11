<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211122312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contenu ADD file_size INT DEFAULT NULL, CHANGE file_url file_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE job_application ADD updated_at DATETIME DEFAULT NULL, CHANGE cv_file cv_file_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE profile ADD updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contenu DROP file_size, CHANGE file_name file_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE job_application DROP updated_at, CHANGE cv_file_name cv_file VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE profile DROP updated_at');
    }
}
