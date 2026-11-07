<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LocationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A town (población) served by a postal code within a province.
 *
 * A postal code may cover several towns and a town may have several postal
 * codes, so each row is a unique (province, town, postal code) triple.
 */
#[ORM\Entity(repositoryClass: LocationRepository::class)]
#[ORM\Table(name: 'location')]
#[ORM\Index(name: 'idx_location_cod_postal', columns: ['cod_postal'])]
#[ORM\UniqueConstraint(name: 'uniq_location', columns: ['provincia', 'poblacion', 'cod_postal'])]
class Location implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 100)]
        private string $provincia,

        #[ORM\Column(length: 150)]
        private string $poblacion,

        /** Always 5 digits, keeping the leading zero (e.g. "01450"). */
        #[ORM\Column(name: 'cod_postal', length: 5, options: ['fixed' => true])]
        private string $codPostal,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvincia(): string
    {
        return $this->provincia;
    }

    public function getPoblacion(): string
    {
        return $this->poblacion;
    }

    public function getCodPostal(): string
    {
        return $this->codPostal;
    }

    /**
     * @return array{provincia: string, poblacion: string, cod_postal: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'provincia' => $this->provincia,
            'poblacion' => $this->poblacion,
            'cod_postal' => $this->codPostal,
        ];
    }
}
