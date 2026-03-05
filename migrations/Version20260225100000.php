<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add position field to course_contenu for drag and drop ordering
 */
final class Version20260225100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add position column to course_contenu table for ordering contenus';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course_contenu ADD position INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course_contenu DROP position');
    }
}
