<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216103621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY `FK_853478CCEA36A5F2`');
        $this->addSql('DROP INDEX UNIQ_853478CCEA36A5F2 ON forum_topic');
        $this->addSql('ALTER TABLE forum_topic DROP accepted_answer_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forum_topic ADD accepted_answer_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT `FK_853478CCEA36A5F2` FOREIGN KEY (accepted_answer_id) REFERENCES forum_reply (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_853478CCEA36A5F2 ON forum_topic (accepted_answer_id)');
    }
}
