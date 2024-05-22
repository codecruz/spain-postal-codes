<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Location;


#[Route('/api', name: 'api_')]
class LocationController extends AbstractController
{
    #[Route('/locations', name: 'location_index', methods: ['get'])]
    public function index(EntityManagerInterface $entityManager): JsonResponse
    {

        $locations = $entityManager
            ->getRepository(Location::class)
            ->findAll();

        $data = [];

        foreach ($locations as $location) {
            $data[] = [
                'id' => $location->getId(),
                'provincia' => $location->getProvincia(),
                'poblacion' => $location->getPoblacion(),
                'cod_postal' => $location->getCodPostal(),
            ];
        }

        return $this->json($data);
    }



    #[Route('/locations/{codPostal}', name: 'locations_show_by_codpostal', methods: ['GET'])]
    public function showByCodPostal(EntityManagerInterface $entityManager, string $codPostal): JsonResponse
    {
        $locations = $entityManager->getRepository(Location::class)->findBy(['cod_postal' => $codPostal]);
    
        if (!$locations) {
            return $this->json('No locations found for cod postal ' . $codPostal, 404);
        }
    
        $data = [];
    
        foreach ($locations as $location) {
            $data[] = [
                'id' => $location->getId(),
                'provincia' => $location->getProvincia(),
                'poblacion' => $location->getPoblacion(),
                'cod_postal' => $location->getCodPostal(),
            ];
        }
    
        return $this->json($data);
    }

}
