<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216091605 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teacher_classe ADD has_created_module TINYINT NOT NULL, ADD module_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE teacher_classe ADD CONSTRAINT FK_9A0C6D63AFC2B591 FOREIGN KEY (module_id) REFERENCES module (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_9A0C6D63AFC2B591 ON teacher_classe (module_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teacher_classe DROP FOREIGN KEY FK_9A0C6D63AFC2B591');
        $this->addSql('DROP INDEX IDX_9A0C6D63AFC2B591 ON teacher_classe');
        $this->addSql('ALTER TABLE teacher_classe DROP has_created_module, DROP module_id');
    }
}
