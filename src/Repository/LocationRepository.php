<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /**
     * @param string|null $provincia canonical province name
     * @param string|null $search    case-insensitive substring of the town name
     *
     * @return array{items: list<Location>, total: int}
     */
    public function paginate(?string $provincia, ?string $search, int $page, int $limit): array
    {
        $total = (int) $this->filtered($provincia, $search)
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Location> $items */
        $items = $this->filtered($provincia, $search)
            ->orderBy('l.codPostal', 'ASC')
            ->addOrderBy('l.poblacion', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<Location>
     */
    public function findByCodPostal(string $codPostal): array
    {
        return $this->findBy(['codPostal' => $codPostal], ['poblacion' => 'ASC']);
    }

    /**
     * Number of locations and distinct postal codes per province name.
     *
     * @return array<string, array{locations: int, postal_codes: int}>
     */
    public function countByProvince(): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.provincia AS provincia', 'COUNT(l.id) AS locations', 'COUNT(DISTINCT l.codPostal) AS postal_codes')
            ->groupBy('l.provincia')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['provincia']] = [
                'locations' => (int) $row['locations'],
                'postal_codes' => (int) $row['postal_codes'],
            ];
        }

        return $counts;
    }

    private function filtered(?string $provincia, ?string $search): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l');

        if (null !== $provincia) {
            $qb->andWhere('l.provincia = :provincia')->setParameter('provincia', $provincia);
        }

        if (null !== $search) {
            // "!" as escape character is portable across SQLite, MySQL and PostgreSQL.
            $escaped = strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']);
            $qb->andWhere("LOWER(l.poblacion) LIKE :search ESCAPE '!'")
                ->setParameter('search', '%'.$escaped.'%');
        }

        return $qb;
    }
}
