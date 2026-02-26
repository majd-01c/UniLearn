<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225215934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE course_document (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, file_name VARCHAR(255) DEFAULT NULL, original_file_name VARCHAR(255) DEFAULT NULL, file_size INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_active TINYINT NOT NULL, classe_id INT NOT NULL, uploaded_by_id INT NOT NULL, INDEX IDX_71DDE720A2B28FE8 (uploaded_by_id), INDEX IDX_71DDE7208F5EA509 (classe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE course_document ADD CONSTRAINT FK_71DDE7208F5EA509 FOREIGN KEY (classe_id) REFERENCES classe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE course_document ADD CONSTRAINT FK_71DDE720A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assessment ADD max_score DOUBLE PRECISION NOT NULL, ADD classe_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE assessment ADD CONSTRAINT FK_F7523D708F5EA509 FOREIGN KEY (classe_id) REFERENCES classe (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_F7523D708F5EA509 ON assessment (classe_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course_document DROP FOREIGN KEY FK_71DDE7208F5EA509');
        $this->addSql('ALTER TABLE course_document DROP FOREIGN KEY FK_71DDE720A2B28FE8');
        $this->addSql('DROP TABLE course_document');
        $this->addSql('ALTER TABLE assessment DROP FOREIGN KEY FK_F7523D708F5EA509');
        $this->addSql('DROP INDEX IDX_F7523D708F5EA509 ON assessment');
        $this->addSql('ALTER TABLE assessment DROP max_score, DROP classe_id');
    }
}
