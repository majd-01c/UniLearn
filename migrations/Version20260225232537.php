<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225232537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add forum_comment_reaction table for like/dislike functionality on forum comments';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE forum_comment_reaction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, comment_id INT NOT NULL, INDEX IDX_39A05B18A76ED395 (user_id), INDEX IDX_39A05B18F8697D13 (comment_id), UNIQUE INDEX user_comment_reaction_unique (user_id, comment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE forum_comment_reaction ADD CONSTRAINT FK_39A05B18A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_comment_reaction ADD CONSTRAINT FK_39A05B18F8697D13 FOREIGN KEY (comment_id) REFERENCES forum_comment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forum_comment_reaction DROP FOREIGN KEY FK_39A05B18A76ED395');
        $this->addSql('ALTER TABLE forum_comment_reaction DROP FOREIGN KEY FK_39A05B18F8697D13');
        $this->addSql('DROP TABLE forum_comment_reaction');
    }
}
