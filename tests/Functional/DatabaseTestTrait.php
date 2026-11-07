<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Location;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Recreates the schema in the SQLite test database before each test.
 */
trait DatabaseTestTrait
{
    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function resetDatabase(): void
    {
        $em = $this->entityManager();
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        $tool = new SchemaTool($em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    /**
     * @param list<array{string, string, string}> $rows [provincia, poblacion, cod_postal]
     */
    protected function loadLocations(array $rows): void
    {
        $em = $this->entityManager();
        foreach ($rows as [$provincia, $poblacion, $codPostal]) {
            $em->persist(new Location($provincia, $poblacion, $codPostal));
        }
        $em->flush();
        $em->clear();
    }

    protected function countLocations(): int
    {
        // Plain DBAL query: it also works after a failed transaction closed the entity manager.
        return (int) $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM location');
    }
}
