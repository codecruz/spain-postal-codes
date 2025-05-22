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
    public function showByCodPostal(string $codPostal): JsonResponse
    {
        $csvFile = __DIR__ . '/../../data/es_cp.csv'; // Ruta al CSV

        if (!file_exists($csvFile)) {
            return $this->json('Archivo CSV no encontrado', 500);
        }

        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            return $this->json('No se pudo abrir el archivo CSV', 500);
        }

        $header = fgetcsv($handle, 0, ';'); // Leer cabecera con separador correcto
        $data = [];

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowData = array_combine($header, $row);
            if (isset($rowData['cod_postal']) && $rowData['cod_postal'] === $codPostal) {
                $data[] = [
                    'provincia' => $rowData['provincia'],
                    'poblacion' => $rowData['poblacion'],
                    'cod_postal' => $rowData['cod_postal'],
                ];
            }
        }
        fclose($handle);

        if (empty($data)) {
            return $this->json('No locations found for cod postal ' . $codPostal, 404);
        }

        return $this->json($data);
    }

    #[Route('/migrate-csv', name: 'locations_migrate_csv', methods: ['POST', 'GET'])]
    public function migrateCsvToDb(EntityManagerInterface $entityManager): JsonResponse
    {
        $csvFile = __DIR__ . '/../../data/es_cp.csv';

        if (!file_exists($csvFile)) {
            return $this->json('Archivo CSV no encontrado', 500);
        }

        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            return $this->json('No se pudo abrir el archivo CSV', 500);
        }

        $header = fgetcsv($handle, 0, ';');
        $count = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowData = array_combine($header, $row);
            if (!$rowData || !isset($rowData['provincia'], $rowData['poblacion'], $rowData['cod_postal'])) {
                continue;
            }
            // Evitar duplicados por cod_postal y poblacion
            $existing = $entityManager->getRepository(Location::class)->findOneBy([
                'cod_postal' => $rowData['cod_postal'],
                'poblacion' => $rowData['poblacion'],
            ]);
            if ($existing) {
                continue;
            }

            $location = new Location();
            $location->setProvincia($rowData['provincia']);
            $location->setPoblacion($rowData['poblacion']);
            $location->setCodPostal($rowData['cod_postal']);

            $entityManager->persist($location);
            $count++;
        }
        fclose($handle);

        $entityManager->flush();

        return $this->json(['migrated' => $count]);
    }

}
