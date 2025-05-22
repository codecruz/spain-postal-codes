<?php

namespace App\Entity;

use App\Repository\LocationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocationRepository::class)]
class Location
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $provincia = null;

    #[ORM\Column(length: 255)]
    private ?string $poblacion = null;

    #[ORM\Column]
    private ?float $cod_postal = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvincia(): ?string
    {
        return $this->provincia;
    }

    public function setProvincia(string $provincia): static
    {
        $this->provincia = $provincia;

        return $this;
    }

    public function getPoblacion(): ?string
    {
        return $this->poblacion;
    }

    public function setPoblacion(string $poblacion): static
    {
        $this->poblacion = $poblacion;

        return $this;
    }

    public function getCodPostal(): ?int
    {
        return $this->cod_postal;
    }

    public function setCodPostal(int $cod_postal): static
    {
        $this->cod_postal = $cod_postal;

        return $this;
    }

}
