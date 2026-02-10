<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210124430 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document_request (id INT AUTO_INCREMENT NOT NULL, document_type VARCHAR(100) NOT NULL, additional_info LONGTEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, requested_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, document_path VARCHAR(255) DEFAULT NULL, student_id INT NOT NULL, INDEX IDX_9FF82943CB944F1A (student_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reclamation (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, status VARCHAR(50) NOT NULL, admin_response LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, student_id INT NOT NULL, related_course_id INT DEFAULT NULL, INDEX IDX_CE606404CB944F1A (student_id), INDEX IDX_CE606404F1DD9589 (related_course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE schedule (id INT AUTO_INCREMENT NOT NULL, day_of_week VARCHAR(20) NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, room VARCHAR(100) DEFAULT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, classe_id INT NOT NULL, course_id INT NOT NULL, teacher_id INT DEFAULT NULL, INDEX IDX_5A3811FB8F5EA509 (classe_id), INDEX IDX_5A3811FB591CC992 (course_id), INDEX IDX_5A3811FB41807E1D (teacher_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE document_request ADD CONSTRAINT FK_9FF82943CB944F1A FOREIGN KEY (student_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404CB944F1A FOREIGN KEY (student_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404F1DD9589 FOREIGN KEY (related_course_id) REFERENCES course (id)');
        $this->addSql('ALTER TABLE schedule ADD CONSTRAINT FK_5A3811FB8F5EA509 FOREIGN KEY (classe_id) REFERENCES classe (id)');
        $this->addSql('ALTER TABLE schedule ADD CONSTRAINT FK_5A3811FB591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('ALTER TABLE schedule ADD CONSTRAINT FK_5A3811FB41807E1D FOREIGN KEY (teacher_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE assessment ADD course_id INT NOT NULL');
        $this->addSql('ALTER TABLE assessment ADD CONSTRAINT FK_F7523D70591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('CREATE INDEX IDX_F7523D70591CC992 ON assessment (course_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document_request DROP FOREIGN KEY FK_9FF82943CB944F1A');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404CB944F1A');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404F1DD9589');
        $this->addSql('ALTER TABLE schedule DROP FOREIGN KEY FK_5A3811FB8F5EA509');
        $this->addSql('ALTER TABLE schedule DROP FOREIGN KEY FK_5A3811FB591CC992');
        $this->addSql('ALTER TABLE schedule DROP FOREIGN KEY FK_5A3811FB41807E1D');
        $this->addSql('DROP TABLE document_request');
        $this->addSql('DROP TABLE reclamation');
        $this->addSql('DROP TABLE schedule');
        $this->addSql('ALTER TABLE assessment DROP FOREIGN KEY FK_F7523D70591CC992');
        $this->addSql('DROP INDEX IDX_F7523D70591CC992 ON assessment');
        $this->addSql('ALTER TABLE assessment DROP course_id');
    }
}
