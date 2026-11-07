<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the "location" table. Written with the Schema API so it works on
 * SQLite, MySQL/MariaDB and PostgreSQL alike.
 */
final class Version20261107120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the location table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('location');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('provincia', Types::STRING, ['length' => 100]);
        $table->addColumn('poblacion', Types::STRING, ['length' => 150]);
        $table->addColumn('cod_postal', Types::STRING, ['length' => 5, 'fixed' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['cod_postal'], 'idx_location_cod_postal');
        $table->addUniqueIndex(['provincia', 'poblacion', 'cod_postal'], 'uniq_location');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('location');
    }
}
