<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209135737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE forum_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(50) DEFAULT NULL, position INT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_21BF9426462CE4F5 (position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE forum_reply (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, is_teacher_response TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, topic_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_E5DC60371F55203D (topic_id), INDEX IDX_E5DC6037F675F31B (author_id), INDEX IDX_E5DC60378B8E8428 (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE forum_topic (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, is_pinned TINYINT NOT NULL, view_count INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_activity_at DATETIME DEFAULT NULL, category_id INT NOT NULL, author_id INT NOT NULL, accepted_answer_id INT DEFAULT NULL, INDEX IDX_853478CC12469DE2 (category_id), INDEX IDX_853478CCF675F31B (author_id), UNIQUE INDEX UNIQ_853478CCEA36A5F2 (accepted_answer_id), INDEX IDX_853478CC7B00651C (status), INDEX IDX_853478CCB56E6838 (is_pinned), INDEX IDX_853478CC8B8E8428 (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE forum_reply ADD CONSTRAINT FK_E5DC60371F55203D FOREIGN KEY (topic_id) REFERENCES forum_topic (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_reply ADD CONSTRAINT FK_E5DC6037F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CC12469DE2 FOREIGN KEY (category_id) REFERENCES forum_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CCF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_topic ADD CONSTRAINT FK_853478CCEA36A5F2 FOREIGN KEY (accepted_answer_id) REFERENCES forum_reply (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forum_reply DROP FOREIGN KEY FK_E5DC60371F55203D');
        $this->addSql('ALTER TABLE forum_reply DROP FOREIGN KEY FK_E5DC6037F675F31B');
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY FK_853478CC12469DE2');
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY FK_853478CCF675F31B');
        $this->addSql('ALTER TABLE forum_topic DROP FOREIGN KEY FK_853478CCEA36A5F2');
        $this->addSql('DROP TABLE forum_category');
        $this->addSql('DROP TABLE forum_reply');
        $this->addSql('DROP TABLE forum_topic');
    }
}
