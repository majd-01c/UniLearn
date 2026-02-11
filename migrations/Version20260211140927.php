<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211140927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE student_classe (id INT AUTO_INCREMENT NOT NULL, enrolled_at DATETIME NOT NULL, is_active TINYINT NOT NULL, student_id INT NOT NULL, classe_id INT NOT NULL, INDEX IDX_1B16716CB944F1A (student_id), INDEX IDX_1B167168F5EA509 (classe_id), UNIQUE INDEX student_classe_unique (student_id, classe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE student_classe ADD CONSTRAINT FK_1B16716CB944F1A FOREIGN KEY (student_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_classe ADD CONSTRAINT FK_1B167168F5EA509 FOREIGN KEY (classe_id) REFERENCES classe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE classe ADD level VARCHAR(10) NOT NULL, ADD specialty VARCHAR(50) NOT NULL, DROP current_student_count');
        $this->addSql('CREATE INDEX IDX_8F87BF969AEACC13 ON classe (level)');
        $this->addSql('CREATE INDEX IDX_8F87BF96E066A6EC ON classe (specialty)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student_classe DROP FOREIGN KEY FK_1B16716CB944F1A');
        $this->addSql('ALTER TABLE student_classe DROP FOREIGN KEY FK_1B167168F5EA509');
        $this->addSql('DROP TABLE student_classe');
        $this->addSql('DROP INDEX IDX_8F87BF969AEACC13 ON classe');
        $this->addSql('DROP INDEX IDX_8F87BF96E066A6EC ON classe');
        $this->addSql('ALTER TABLE classe ADD current_student_count INT NOT NULL, DROP level, DROP specialty');
    }
}
