<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240427104440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat (id INT AUTO_INCREMENT NOT NULL, chat_id VARCHAR(255) NOT NULL, step VARCHAR(255) DEFAULT NULL, context VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_659DF2AA1A9A7125 (chat_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE employee (id INT AUTO_INCREMENT NOT NULL, full_name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE vacation (id INT AUTO_INCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, employee_id INT NOT NULL, INDEX IDX_E3DADF758C03F15C (employee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('ALTER TABLE vacation ADD CONSTRAINT FK_E3DADF758C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE vacation DROP FOREIGN KEY FK_E3DADF758C03F15C');
        $this->addSql('DROP TABLE chat');
        $this->addSql('DROP TABLE employee');
        $this->addSql('DROP TABLE vacation');
    }
}
