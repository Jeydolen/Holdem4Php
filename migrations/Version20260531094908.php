<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531094908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE phase DROP CONSTRAINT fk_b1bdd6cb6bd4bc94');
        $this->addSql('DROP INDEX idx_b1bdd6cb6bd4bc94');

        $this->addSql('ALTER TABLE card DROP CONSTRAINT fk_161498d33b69a9af');
        $this->addSql('DROP INDEX idx_161498d33b69a9af');
        $this->addSql('ALTER TABLE card DROP CONSTRAINT card_pkey');
        $this->addSql('ALTER TABLE card DROP variant_id');
        $this->addSql('ALTER TABLE card RENAME COLUMN id TO card_id');
        $this->addSql('ALTER TABLE card ADD PRIMARY KEY (card_id)');

        $this->addSql('ALTER TABLE phase DROP CONSTRAINT phase_pkey');
        $this->addSql('ALTER TABLE phase DROP table_rules_id');
        $this->addSql('ALTER TABLE phase RENAME COLUMN id TO phase_id');
        $this->addSql('ALTER TABLE phase ADD PRIMARY KEY (phase_id)');

        $this->addSql('ALTER TABLE variant DROP CONSTRAINT variant_pkey');
        $this->addSql('ALTER TABLE variant ADD name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE variant RENAME COLUMN id TO variant_id');
        $this->addSql('ALTER TABLE variant ADD PRIMARY KEY (variant_id)');

        $this->addSql('CREATE TABLE variant_cards (variant_id INT NOT NULL, card_id INT NOT NULL, PRIMARY KEY (variant_id, card_id))');
        $this->addSql('CREATE INDEX IDX_8448C7A03B69A9AF ON variant_cards (variant_id)');
        $this->addSql('CREATE INDEX IDX_8448C7A04ACC9A20 ON variant_cards (card_id)');
        $this->addSql('ALTER TABLE variant_cards ADD CONSTRAINT FK_8448C7A03B69A9AF FOREIGN KEY (variant_id) REFERENCES variant (variant_id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE variant_cards ADD CONSTRAINT FK_8448C7A04ACC9A20 FOREIGN KEY (card_id) REFERENCES card (card_id) NOT DEFERRABLE');

        $this->addSql('CREATE TABLE variant_phases (variant_id INT NOT NULL, phase_id INT NOT NULL, PRIMARY KEY (variant_id, phase_id))');
        $this->addSql('CREATE INDEX IDX_253CE333B69A9AF ON variant_phases (variant_id)');
        $this->addSql('CREATE INDEX IDX_253CE3399091188 ON variant_phases (phase_id)');
        $this->addSql('ALTER TABLE variant_phases ADD CONSTRAINT FK_253CE333B69A9AF FOREIGN KEY (variant_id) REFERENCES variant (variant_id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE variant_phases ADD CONSTRAINT FK_253CE3399091188 FOREIGN KEY (phase_id) REFERENCES phase (phase_id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE variant_cards DROP CONSTRAINT FK_8448C7A03B69A9AF');
        $this->addSql('ALTER TABLE variant_cards DROP CONSTRAINT FK_8448C7A04ACC9A20');
        $this->addSql('ALTER TABLE variant_phases DROP CONSTRAINT FK_253CE333B69A9AF');
        $this->addSql('ALTER TABLE variant_phases DROP CONSTRAINT FK_253CE3399091188');
        $this->addSql('DROP TABLE variant_cards');
        $this->addSql('DROP TABLE variant_phases');
        $this->addSql('ALTER TABLE card DROP CONSTRAINT card_pkey');
        $this->addSql('ALTER TABLE card ADD variant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card RENAME COLUMN card_id TO id');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT fk_161498d33b69a9af FOREIGN KEY (variant_id) REFERENCES variant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_161498d33b69a9af ON card (variant_id)');
        $this->addSql('ALTER TABLE card ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE phase DROP CONSTRAINT phase_pkey');
        $this->addSql('ALTER TABLE phase ADD table_rules_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE phase RENAME COLUMN phase_id TO id');
        $this->addSql('ALTER TABLE phase ADD CONSTRAINT fk_b1bdd6cb6bd4bc94 FOREIGN KEY (table_rules_id) REFERENCES variant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_b1bdd6cb6bd4bc94 ON phase (table_rules_id)');
        $this->addSql('ALTER TABLE phase ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE variant DROP CONSTRAINT variant_pkey');
        $this->addSql('ALTER TABLE variant DROP name');
        $this->addSql('ALTER TABLE variant RENAME COLUMN variant_id TO id');
        $this->addSql('ALTER TABLE variant ADD PRIMARY KEY (id)');
    }
}
