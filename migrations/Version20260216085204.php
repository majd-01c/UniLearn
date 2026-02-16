<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216085204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE teacher_classe (id INT AUTO_INCREMENT NOT NULL, assigned_at DATETIME NOT NULL, is_active TINYINT NOT NULL, teacher_id INT NOT NULL, classe_id INT NOT NULL, INDEX IDX_9A0C6D6341807E1D (teacher_id), INDEX IDX_9A0C6D638F5EA509 (classe_id), UNIQUE INDEX teacher_classe_unique (teacher_id, classe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE teacher_classe ADD CONSTRAINT FK_9A0C6D6341807E1D FOREIGN KEY (teacher_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teacher_classe ADD CONSTRAINT FK_9A0C6D638F5EA509 FOREIGN KEY (classe_id) REFERENCES classe (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teacher_classe DROP FOREIGN KEY FK_9A0C6D6341807E1D');
        $this->addSql('ALTER TABLE teacher_classe DROP FOREIGN KEY FK_9A0C6D638F5EA509');
        $this->addSql('DROP TABLE teacher_classe');
    }
}
