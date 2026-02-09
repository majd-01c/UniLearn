<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260204223035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE assessment (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, contenu_id INT DEFAULT NULL, teacher_id INT NOT NULL, INDEX IDX_F7523D703C1CC488 (contenu_id), INDEX IDX_F7523D708CDE5729 (type), INDEX IDX_F7523D7041807E1D (teacher_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contenu (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, file_url VARCHAR(255) DEFAULT NULL, file_type VARCHAR(255) DEFAULT NULL, type VARCHAR(255) NOT NULL, published TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_89C2003F8CDE5729 (type), INDEX IDX_89C2003F683C6017 (published), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE course (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE course_contenu (id INT AUTO_INCREMENT NOT NULL, course_id INT NOT NULL, contenu_id INT NOT NULL, INDEX IDX_810105D3591CC992 (course_id), INDEX IDX_810105D33C1CC488 (contenu_id), UNIQUE INDEX course_contenu_unique (course_id, contenu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, type VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, start_at DATETIME NOT NULL, capacity INT DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INT NOT NULL, INDEX IDX_3BAE0AA7B03A8386 (created_by_id), INDEX IDX_3BAE0AA77B00651C (status), INDEX IDX_3BAE0AA7B75363F7 (start_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event_participation (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, event_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_8F0C52E371F7E88B (event_id), INDEX IDX_8F0C52E3A76ED395 (user_id), INDEX IDX_8F0C52E37B00651C (status), UNIQUE INDEX event_user_unique (event_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE general_chat_message (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(100) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, sender_id INT DEFAULT NULL, INDEX IDX_47897596F624B39D (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE grade (id INT AUTO_INCREMENT NOT NULL, score DOUBLE PRECISION NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, assessment_id INT NOT NULL, student_id INT NOT NULL, teacher_id INT NOT NULL, INDEX IDX_595AAE34DD3DD5F1 (assessment_id), INDEX IDX_595AAE3441807E1D (teacher_id), INDEX IDX_595AAE34CB944F1A (student_id), UNIQUE INDEX assessment_student_unique (assessment_id, student_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE job_offer (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, location VARCHAR(255) DEFAULT NULL, description LONGTEXT NOT NULL, requirements LONGTEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, partner_id INT NOT NULL, INDEX IDX_288A3A4E9393F8FE (partner_id), INDEX IDX_288A3A4E8CDE5729 (type), INDEX IDX_288A3A4E7B00651C (status), INDEX IDX_288A3A4E5E9E89CB (location), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE module (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, period_unit VARCHAR(255) NOT NULL, duration INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE module_course (id INT AUTO_INCREMENT NOT NULL, module_id INT NOT NULL, course_id INT NOT NULL, INDEX IDX_BC9D2F96AFC2B591 (module_id), INDEX IDX_BC9D2F96591CC992 (course_id), UNIQUE INDEX module_course_unique (module_id, course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE profile (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_8157AA0FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE program (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, published TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_92ED7784683C6017 (published), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE program_chat_message (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(100) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, program_id INT NOT NULL, sender_id INT DEFAULT NULL, INDEX IDX_B9160F31F624B39D (sender_id), INDEX IDX_B9160F313EB8070A (program_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE program_module (id INT AUTO_INCREMENT NOT NULL, program_id INT NOT NULL, module_id INT NOT NULL, INDEX IDX_586418723EB8070A (program_id), INDEX IDX_58641872AFC2B591 (module_id), UNIQUE INDEX program_module_unique (program_id, module_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reset_token (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(255) NOT NULL, expiry_date DATETIME NOT NULL, created_at DATETIME NOT NULL, used TINYINT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_D7C8DC195F37A13B (token), INDEX IDX_D7C8DC19A76ED395 (user_id), INDEX IDX_D7C8DC19F6DE1BC8 (expiry_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(50) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, is_active TINYINT NOT NULL, name VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, profile_pic VARCHAR(255) DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, skills JSON DEFAULT NULL, about LONGTEXT DEFAULT NULL, is_verified TINYINT NOT NULL, needs_verification TINYINT NOT NULL, email_verified_at DATETIME DEFAULT NULL, email_verification_code VARCHAR(100) DEFAULT NULL, code_expiry_date DATETIME DEFAULT NULL, must_change_password TINYINT NOT NULL, temp_password_generated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE assessment ADD CONSTRAINT FK_F7523D703C1CC488 FOREIGN KEY (contenu_id) REFERENCES contenu (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE assessment ADD CONSTRAINT FK_F7523D7041807E1D FOREIGN KEY (teacher_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE course_contenu ADD CONSTRAINT FK_810105D3591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE course_contenu ADD CONSTRAINT FK_810105D33C1CC488 FOREIGN KEY (contenu_id) REFERENCES contenu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_participation ADD CONSTRAINT FK_8F0C52E371F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_participation ADD CONSTRAINT FK_8F0C52E3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE general_chat_message ADD CONSTRAINT FK_47897596F624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE grade ADD CONSTRAINT FK_595AAE34DD3DD5F1 FOREIGN KEY (assessment_id) REFERENCES assessment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE grade ADD CONSTRAINT FK_595AAE34CB944F1A FOREIGN KEY (student_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE grade ADD CONSTRAINT FK_595AAE3441807E1D FOREIGN KEY (teacher_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_offer ADD CONSTRAINT FK_288A3A4E9393F8FE FOREIGN KEY (partner_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_course ADD CONSTRAINT FK_BC9D2F96AFC2B591 FOREIGN KEY (module_id) REFERENCES module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_course ADD CONSTRAINT FK_BC9D2F96591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT FK_8157AA0FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE program_chat_message ADD CONSTRAINT FK_B9160F313EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE program_chat_message ADD CONSTRAINT FK_B9160F31F624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE program_module ADD CONSTRAINT FK_586418723EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE program_module ADD CONSTRAINT FK_58641872AFC2B591 FOREIGN KEY (module_id) REFERENCES module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reset_token ADD CONSTRAINT FK_D7C8DC19A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assessment DROP FOREIGN KEY FK_F7523D703C1CC488');
        $this->addSql('ALTER TABLE assessment DROP FOREIGN KEY FK_F7523D7041807E1D');
        $this->addSql('ALTER TABLE course_contenu DROP FOREIGN KEY FK_810105D3591CC992');
        $this->addSql('ALTER TABLE course_contenu DROP FOREIGN KEY FK_810105D33C1CC488');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7B03A8386');
        $this->addSql('ALTER TABLE event_participation DROP FOREIGN KEY FK_8F0C52E371F7E88B');
        $this->addSql('ALTER TABLE event_participation DROP FOREIGN KEY FK_8F0C52E3A76ED395');
        $this->addSql('ALTER TABLE general_chat_message DROP FOREIGN KEY FK_47897596F624B39D');
        $this->addSql('ALTER TABLE grade DROP FOREIGN KEY FK_595AAE34DD3DD5F1');
        $this->addSql('ALTER TABLE grade DROP FOREIGN KEY FK_595AAE34CB944F1A');
        $this->addSql('ALTER TABLE grade DROP FOREIGN KEY FK_595AAE3441807E1D');
        $this->addSql('ALTER TABLE job_offer DROP FOREIGN KEY FK_288A3A4E9393F8FE');
        $this->addSql('ALTER TABLE module_course DROP FOREIGN KEY FK_BC9D2F96AFC2B591');
        $this->addSql('ALTER TABLE module_course DROP FOREIGN KEY FK_BC9D2F96591CC992');
        $this->addSql('ALTER TABLE profile DROP FOREIGN KEY FK_8157AA0FA76ED395');
        $this->addSql('ALTER TABLE program_chat_message DROP FOREIGN KEY FK_B9160F313EB8070A');
        $this->addSql('ALTER TABLE program_chat_message DROP FOREIGN KEY FK_B9160F31F624B39D');
        $this->addSql('ALTER TABLE program_module DROP FOREIGN KEY FK_586418723EB8070A');
        $this->addSql('ALTER TABLE program_module DROP FOREIGN KEY FK_58641872AFC2B591');
        $this->addSql('ALTER TABLE reset_token DROP FOREIGN KEY FK_D7C8DC19A76ED395');
        $this->addSql('DROP TABLE assessment');
        $this->addSql('DROP TABLE contenu');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE course_contenu');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE event_participation');
        $this->addSql('DROP TABLE general_chat_message');
        $this->addSql('DROP TABLE grade');
        $this->addSql('DROP TABLE job_offer');
        $this->addSql('DROP TABLE module');
        $this->addSql('DROP TABLE module_course');
        $this->addSql('DROP TABLE profile');
        $this->addSql('DROP TABLE program');
        $this->addSql('DROP TABLE program_chat_message');
        $this->addSql('DROP TABLE program_module');
        $this->addSql('DROP TABLE reset_token');
        $this->addSql('DROP TABLE `user`');
    }
}
