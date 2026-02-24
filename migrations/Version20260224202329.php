<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Rename forum_reply to forum_comment and add parent_id for nested comments
 */
final class Version20260224202329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename forum_reply to forum_comment and add parent_id column for nested replies';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Create new forum_comment table
        $this->addSql('CREATE TABLE forum_comment (
            id INT AUTO_INCREMENT NOT NULL, 
            content LONGTEXT NOT NULL, 
            is_teacher_response TINYINT(1) NOT NULL, 
            is_accepted TINYINT(1) NOT NULL, 
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            topic_id INT NOT NULL, 
            author_id INT NOT NULL, 
            parent_id INT DEFAULT NULL, 
            INDEX IDX_65B81F1D1F55203D (topic_id), 
            INDEX IDX_65B81F1DF675F31B (author_id), 
            INDEX IDX_65B81F1D727ACA70 (parent_id), 
            INDEX IDX_65B81F1D8B8E8428 (created_at), 
            INDEX IDX_65B81F1DE3B4804A (is_accepted), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Step 2: Add foreign key constraints
        $this->addSql('ALTER TABLE forum_comment ADD CONSTRAINT FK_65B81F1D1F55203D FOREIGN KEY (topic_id) REFERENCES forum_topic (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_comment ADD CONSTRAINT FK_65B81F1DF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_comment ADD CONSTRAINT FK_65B81F1D727ACA70 FOREIGN KEY (parent_id) REFERENCES forum_comment (id) ON DELETE CASCADE');
        
        // Step 3: Migrate existing data from forum_reply to forum_comment
        $this->addSql('INSERT INTO forum_comment (id, content, is_teacher_response, is_accepted, created_at, updated_at, topic_id, author_id, parent_id) 
                       SELECT id, content, is_teacher_response, is_accepted, created_at, updated_at, topic_id, author_id, NULL FROM forum_reply');
        
        // Step 4: Drop the old forum_reply table
        $this->addSql('ALTER TABLE forum_reply DROP FOREIGN KEY FK_E5DC60371F55203D');
        $this->addSql('ALTER TABLE forum_reply DROP FOREIGN KEY FK_E5DC6037F675F31B');
        $this->addSql('DROP TABLE forum_reply');
    }

    public function down(Schema $schema): void
    {
        // Step 1: Recreate forum_reply table
        $this->addSql('CREATE TABLE forum_reply (
            id INT AUTO_INCREMENT NOT NULL, 
            content LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, 
            is_teacher_response TINYINT(1) NOT NULL, 
            is_accepted TINYINT(1) NOT NULL, 
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            topic_id INT NOT NULL, 
            author_id INT NOT NULL, 
            INDEX IDX_E5DC60371F55203D (topic_id), 
            INDEX IDX_E5DC6037F675F31B (author_id), 
            INDEX IDX_E5DC60378B8E8428 (created_at), 
            INDEX IDX_E5DC6037E3B4804A (is_accepted), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('ALTER TABLE forum_reply ADD CONSTRAINT FK_E5DC60371F55203D FOREIGN KEY (topic_id) REFERENCES forum_topic (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_reply ADD CONSTRAINT FK_E5DC6037F675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        
        // Step 2: Migrate data back (only top-level comments, replies to comments will be lost)
        $this->addSql('INSERT INTO forum_reply (id, content, is_teacher_response, is_accepted, created_at, updated_at, topic_id, author_id) 
                       SELECT id, content, is_teacher_response, is_accepted, created_at, updated_at, topic_id, author_id FROM forum_comment WHERE parent_id IS NULL');
        
        // Step 3: Drop forum_comment table
        $this->addSql('ALTER TABLE forum_comment DROP FOREIGN KEY FK_65B81F1D1F55203D');
        $this->addSql('ALTER TABLE forum_comment DROP FOREIGN KEY FK_65B81F1DF675F31B');
        $this->addSql('ALTER TABLE forum_comment DROP FOREIGN KEY FK_65B81F1D727ACA70');
        $this->addSql('DROP TABLE forum_comment');
    }
}
