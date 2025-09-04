<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250902131942_InitialSchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial schema for cryptocurrency exchange rates API';
    }

    public function up(Schema $schema): void
    {
        // Create exchange_rates table
        $this->addSql('CREATE TABLE exchange_rates (
            id INT AUTO_INCREMENT NOT NULL,
            pair VARCHAR(10) NOT NULL,
            rate NUMERIC(20, 8) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add indexes for optimal query performance
        $this->addSql('CREATE INDEX idx_pair_created ON exchange_rates (pair, created_at)');
        $this->addSql('CREATE INDEX idx_pair_created_desc ON exchange_rates (pair, created_at DESC)');

        // Add constraint to ensure only valid currency pairs
        $this->addSql('ALTER TABLE exchange_rates ADD CONSTRAINT CHK_pair_enum
            CHECK (pair IN (\'EUR/BTC\', \'EUR/ETH\', \'EUR/LTC\'))');

        // Add constraint to ensure rate is positive
        $this->addSql('ALTER TABLE exchange_rates ADD CONSTRAINT CHK_rate_positive
            CHECK (rate > 0)');
    }

    public function down(Schema $schema): void
    {
        // Drop constraints first
        $this->addSql('ALTER TABLE exchange_rates DROP CONSTRAINT CHK_rate_positive');
        $this->addSql('ALTER TABLE exchange_rates DROP CONSTRAINT CHK_pair_enum');

        // Drop indexes
        $this->addSql('DROP INDEX idx_pair_created_desc ON exchange_rates');
        $this->addSql('DROP INDEX idx_pair_created ON exchange_rates');

        // Drop table
        $this->addSql('DROP TABLE exchange_rates');
    }
}
