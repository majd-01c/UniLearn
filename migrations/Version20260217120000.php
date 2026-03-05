<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add avatar_filename and avatar_updated_at columns to profile table.
 */
final class Version20260217120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add avatar_filename and avatar_updated_at to profile table for generated avatars';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD avatar_filename VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE profile ADD avatar_updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP avatar_filename');
        $this->addSql('ALTER TABLE profile DROP avatar_updated_at');
    }
}
