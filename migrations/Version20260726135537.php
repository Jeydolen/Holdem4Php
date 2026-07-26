<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260726135537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_f6298f46e7683c92');
        $this->addSql('CREATE INDEX IDX_F6298F46E7683C92 ON "table" (stake_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_F6298F46E7683C92');
        $this->addSql('CREATE UNIQUE INDEX uniq_f6298f46e7683c92 ON "table" (stake_id)');
    }
}
