<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260226012148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE forum_ai_suggestion (id INT AUTO_INCREMENT NOT NULL, question_hash VARCHAR(64) NOT NULL, question LONGTEXT NOT NULL, suggestions JSON NOT NULL, ai_response LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, usage_count INT NOT NULL, INDEX IDX_5426AB8A8F568F57 (question_hash), INDEX IDX_5426AB8A8B8E8428 (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE forum_ai_suggestion');
    }
}
