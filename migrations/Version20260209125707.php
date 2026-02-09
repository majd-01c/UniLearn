<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209125707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE answer (id INT AUTO_INCREMENT NOT NULL, text_answer LONGTEXT DEFAULT NULL, is_correct TINYINT DEFAULT 0 NOT NULL, points_earned INT DEFAULT NULL, user_answer_id INT NOT NULL, question_id INT NOT NULL, selected_choice_id INT DEFAULT NULL, INDEX IDX_DADD4A25C1F9753A (selected_choice_id), INDEX IDX_DADD4A25AAD3C5E3 (user_answer_id), INDEX IDX_DADD4A251E27F6BF (question_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE build_program (id INT AUTO_INCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, image_url VARCHAR(255) DEFAULT NULL, level VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, program_id INT NOT NULL, INDEX IDX_BF780A083EB8070A (program_id), INDEX IDX_BF780A089AEACC13 (level), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE build_program_contenu (id INT AUTO_INCREMENT NOT NULL, build_program_course_id INT NOT NULL, contenu_id INT NOT NULL, INDEX IDX_FFF9B5D03BF11789 (build_program_course_id), INDEX IDX_FFF9B5D03C1CC488 (contenu_id), UNIQUE INDEX build_prog_contenu_unique (build_program_course_id, contenu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE build_program_course (id INT AUTO_INCREMENT NOT NULL, build_program_module_id INT NOT NULL, course_id INT NOT NULL, INDEX IDX_F16A10F9CD2F6B8A (build_program_module_id), INDEX IDX_F16A10F9591CC992 (course_id), UNIQUE INDEX build_prog_course_unique (build_program_module_id, course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE build_program_module (id INT AUTO_INCREMENT NOT NULL, build_program_id INT NOT NULL, module_id INT NOT NULL, INDEX IDX_EBD05968CC6385B3 (build_program_id), INDEX IDX_EBD05968AFC2B591 (module_id), UNIQUE INDEX build_prog_module_unique (build_program_id, module_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE choice (id INT AUTO_INCREMENT NOT NULL, choice_text LONGTEXT NOT NULL, is_correct TINYINT DEFAULT 0 NOT NULL, position INT NOT NULL, question_id INT NOT NULL, INDEX IDX_C1AB5A921E27F6BF (question_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE job_application (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT DEFAULT NULL, cv_file VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, offer_id INT NOT NULL, student_id INT NOT NULL, INDEX IDX_C737C68853C674EE (offer_id), INDEX IDX_C737C688CB944F1A (student_id), UNIQUE INDEX uniq_offer_student (offer_id, student_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE question (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, question_text LONGTEXT NOT NULL, points INT NOT NULL, position INT NOT NULL, explanation LONGTEXT DEFAULT NULL, quiz_id INT NOT NULL, INDEX IDX_B6F7494E853CD175 (quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quiz (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, passing_score INT DEFAULT NULL, time_limit INT DEFAULT NULL, shuffle_questions TINYINT DEFAULT 1 NOT NULL, shuffle_choices TINYINT DEFAULT 1 NOT NULL, show_correct_answers TINYINT DEFAULT 0 NOT NULL, contenu_id INT NOT NULL, UNIQUE INDEX UNIQ_A412FA923C1CC488 (contenu_id), INDEX IDX_A412FA923C1CC488 (contenu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_answer (id INT AUTO_INCREMENT NOT NULL, score INT DEFAULT NULL, total_points INT DEFAULT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, is_passed TINYINT DEFAULT 0 NOT NULL, user_id INT NOT NULL, quiz_id INT NOT NULL, INDEX IDX_BF8F5118A76ED395 (user_id), INDEX IDX_BF8F5118853CD175 (quiz_id), UNIQUE INDEX user_quiz_unique (user_id, quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE answer ADD CONSTRAINT FK_DADD4A25AAD3C5E3 FOREIGN KEY (user_answer_id) REFERENCES user_answer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE answer ADD CONSTRAINT FK_DADD4A251E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE answer ADD CONSTRAINT FK_DADD4A25C1F9753A FOREIGN KEY (selected_choice_id) REFERENCES choice (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE build_program ADD CONSTRAINT FK_BF780A083EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE build_program_contenu ADD CONSTRAINT FK_FFF9B5D03BF11789 FOREIGN KEY (build_program_course_id) REFERENCES build_program_course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE build_program_contenu ADD CONSTRAINT FK_FFF9B5D03C1CC488 FOREIGN KEY (contenu_id) REFERENCES contenu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE build_program_course ADD CONSTRAINT FK_F16A10F9CD2F6B8A FOREIGN KEY (build_program_module_id) REFERENCES build_program_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE build_program_course ADD CONSTRAINT FK_F16A10F9591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE build_program_module ADD CONSTRAINT FK_EBD05968CC6385B3 FOREIGN KEY (build_program_id) REFERENCES build_program (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE build_program_module ADD CONSTRAINT FK_EBD05968AFC2B591 FOREIGN KEY (module_id) REFERENCES module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE choice ADD CONSTRAINT FK_C1AB5A921E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_application ADD CONSTRAINT FK_C737C68853C674EE FOREIGN KEY (offer_id) REFERENCES job_offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_application ADD CONSTRAINT FK_C737C688CB944F1A FOREIGN KEY (student_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz ADD CONSTRAINT FK_A412FA923C1CC488 FOREIGN KEY (contenu_id) REFERENCES contenu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_answer ADD CONSTRAINT FK_BF8F5118A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_answer ADD CONSTRAINT FK_BF8F5118853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_offer ADD published_at DATETIME DEFAULT NULL, ADD expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_288A3A4EE0D4FDE1 ON job_offer (published_at)');
        $this->addSql('CREATE INDEX IDX_288A3A4EF9D83E2 ON job_offer (expires_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE answer DROP FOREIGN KEY FK_DADD4A25AAD3C5E3');
        $this->addSql('ALTER TABLE answer DROP FOREIGN KEY FK_DADD4A251E27F6BF');
        $this->addSql('ALTER TABLE answer DROP FOREIGN KEY FK_DADD4A25C1F9753A');
        $this->addSql('ALTER TABLE build_program DROP FOREIGN KEY FK_BF780A083EB8070A');
        $this->addSql('ALTER TABLE build_program_contenu DROP FOREIGN KEY FK_FFF9B5D03BF11789');
        $this->addSql('ALTER TABLE build_program_contenu DROP FOREIGN KEY FK_FFF9B5D03C1CC488');
        $this->addSql('ALTER TABLE build_program_course DROP FOREIGN KEY FK_F16A10F9CD2F6B8A');
        $this->addSql('ALTER TABLE build_program_course DROP FOREIGN KEY FK_F16A10F9591CC992');
        $this->addSql('ALTER TABLE build_program_module DROP FOREIGN KEY FK_EBD05968CC6385B3');
        $this->addSql('ALTER TABLE build_program_module DROP FOREIGN KEY FK_EBD05968AFC2B591');
        $this->addSql('ALTER TABLE choice DROP FOREIGN KEY FK_C1AB5A921E27F6BF');
        $this->addSql('ALTER TABLE job_application DROP FOREIGN KEY FK_C737C68853C674EE');
        $this->addSql('ALTER TABLE job_application DROP FOREIGN KEY FK_C737C688CB944F1A');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E853CD175');
        $this->addSql('ALTER TABLE quiz DROP FOREIGN KEY FK_A412FA923C1CC488');
        $this->addSql('ALTER TABLE user_answer DROP FOREIGN KEY FK_BF8F5118A76ED395');
        $this->addSql('ALTER TABLE user_answer DROP FOREIGN KEY FK_BF8F5118853CD175');
        $this->addSql('DROP TABLE answer');
        $this->addSql('DROP TABLE build_program');
        $this->addSql('DROP TABLE build_program_contenu');
        $this->addSql('DROP TABLE build_program_course');
        $this->addSql('DROP TABLE build_program_module');
        $this->addSql('DROP TABLE choice');
        $this->addSql('DROP TABLE job_application');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE quiz');
        $this->addSql('DROP TABLE user_answer');
        $this->addSql('DROP INDEX IDX_288A3A4EE0D4FDE1 ON job_offer');
        $this->addSql('DROP INDEX IDX_288A3A4EF9D83E2 ON job_offer');
        $this->addSql('ALTER TABLE job_offer DROP published_at, DROP expires_at');
    }
}
