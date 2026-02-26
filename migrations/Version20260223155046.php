<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223155046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE custom_skill (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, category VARCHAR(50) DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, partner_id INT NOT NULL, INDEX IDX_9D301C1E9393F8FE (partner_id), UNIQUE INDEX UNIQ_CUSTOM_SKILL_PARTNER_NAME (partner_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE custom_skill ADD CONSTRAINT FK_9D301C1E9393F8FE FOREIGN KEY (partner_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE custom_skill DROP FOREIGN KEY FK_9D301C1E9393F8FE');
        $this->addSql('DROP TABLE custom_skill');
    }
}
