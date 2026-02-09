<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205221726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE classe (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, image_url VARCHAR(255) DEFAULT NULL, status VARCHAR(20) DEFAULT \'inactive\' NOT NULL, program_id INT NOT NULL, INDEX IDX_8F87BF963EB8070A (program_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE classe_contenu (id INT AUTO_INCREMENT NOT NULL, classe_course_id INT NOT NULL, contenu_id INT NOT NULL, INDEX IDX_F25B605E684889B5 (classe_course_id), INDEX IDX_F25B605E3C1CC488 (contenu_id), UNIQUE INDEX classe_contenu_unique (classe_course_id, contenu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE classe_course (id INT AUTO_INCREMENT NOT NULL, classe_module_id INT NOT NULL, course_id INT NOT NULL, INDEX IDX_3ED5A0739E96F5B6 (classe_module_id), INDEX IDX_3ED5A073591CC992 (course_id), UNIQUE INDEX classe_course_unique (classe_module_id, course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE classe_module (id INT AUTO_INCREMENT NOT NULL, classe_id INT NOT NULL, module_id INT NOT NULL, INDEX IDX_246FE9E28F5EA509 (classe_id), INDEX IDX_246FE9E2AFC2B591 (module_id), UNIQUE INDEX classe_module_unique (classe_id, module_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE classe ADD CONSTRAINT FK_8F87BF963EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE classe_contenu ADD CONSTRAINT FK_F25B605E684889B5 FOREIGN KEY (classe_course_id) REFERENCES classe_course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE classe_contenu ADD CONSTRAINT FK_F25B605E3C1CC488 FOREIGN KEY (contenu_id) REFERENCES contenu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE classe_course ADD CONSTRAINT FK_3ED5A0739E96F5B6 FOREIGN KEY (classe_module_id) REFERENCES classe_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE classe_course ADD CONSTRAINT FK_3ED5A073591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE classe_module ADD CONSTRAINT FK_246FE9E28F5EA509 FOREIGN KEY (classe_id) REFERENCES classe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE classe_module ADD CONSTRAINT FK_246FE9E2AFC2B591 FOREIGN KEY (module_id) REFERENCES module (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE classe DROP FOREIGN KEY FK_8F87BF963EB8070A');
        $this->addSql('ALTER TABLE classe_contenu DROP FOREIGN KEY FK_F25B605E684889B5');
        $this->addSql('ALTER TABLE classe_contenu DROP FOREIGN KEY FK_F25B605E3C1CC488');
        $this->addSql('ALTER TABLE classe_course DROP FOREIGN KEY FK_3ED5A0739E96F5B6');
        $this->addSql('ALTER TABLE classe_course DROP FOREIGN KEY FK_3ED5A073591CC992');
        $this->addSql('ALTER TABLE classe_module DROP FOREIGN KEY FK_246FE9E28F5EA509');
        $this->addSql('ALTER TABLE classe_module DROP FOREIGN KEY FK_246FE9E2AFC2B591');
        $this->addSql('DROP TABLE classe');
        $this->addSql('DROP TABLE classe_contenu');
        $this->addSql('DROP TABLE classe_course');
        $this->addSql('DROP TABLE classe_module');
    }
}
