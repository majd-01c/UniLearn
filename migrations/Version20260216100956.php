<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add is_accepted field to forum_reply and migrate existing accepted answers
 */
final class Version20260216100956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_accepted field to forum_reply for multiple accepted answers support';
    }

    public function up(Schema $schema): void
    {
        // Add is_accepted column with default value of 0
        $this->addSql('ALTER TABLE forum_reply ADD is_accepted TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('CREATE INDEX IDX_E5DC6037E3B4804A ON forum_reply (is_accepted)');
        
        // Migrate existing accepted answers from forum_topic.accepted_answer_id
        $this->addSql('UPDATE forum_reply fr 
            INNER JOIN forum_topic ft ON ft.accepted_answer_id = fr.id 
            SET fr.is_accepted = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_E5DC6037E3B4804A ON forum_reply');
        $this->addSql('ALTER TABLE forum_reply DROP is_accepted');
    }
}
