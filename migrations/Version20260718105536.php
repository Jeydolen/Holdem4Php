<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260718105536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Need to do this in 3 steps, 1 add column 2 add default value, 3 enforce not null
        $this->addSql('ALTER TABLE variant ADD betting_type VARCHAR(255)');
        $this->addSql("UPDATE variant SET betting_type = 'NO_LIMIT' WHERE betting_type IS NULL");
        $this->addSql("ALTER TABLE variant ALTER COLUMN betting_type SET NOT NULL");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE variant DROP betting_type');
    }
}
