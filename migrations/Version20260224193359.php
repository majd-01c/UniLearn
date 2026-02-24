<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224193359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE class_meeting (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, room_code VARCHAR(100) NOT NULL, status VARCHAR(20) NOT NULL, scheduled_at DATETIME DEFAULT NULL, started_at DATETIME DEFAULT NULL, ended_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, teacher_classe_id INT NOT NULL, INDEX IDX_306086C5F535E912 (teacher_classe_id), INDEX IDX_306086C57B00651C (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE class_meeting ADD CONSTRAINT FK_306086C5F535E912 FOREIGN KEY (teacher_classe_id) REFERENCES teacher_classe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_reply CHANGE is_accepted is_accepted TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE class_meeting DROP FOREIGN KEY FK_306086C5F535E912');
        $this->addSql('DROP TABLE class_meeting');
        $this->addSql('ALTER TABLE forum_reply CHANGE is_accepted is_accepted TINYINT DEFAULT 0 NOT NULL');
    }
}
