<?php
// src/Controller/Admin/ModeloCRUDController.php

namespace App\Controller\Admin;

use App\Entity\AreasTecnicasEstampado;
use App\Entity\Modelo;
use App\Entity\ModeloHasTecnicasEstampado;
use App\Entity\Personalizacion;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ModeloCRUDController extends CRUDController
{
    /**
     * Acción personalizada para aplicar el Pack Tuskamisetas
     */
    public function aplicarTecnicasTuskamisetasAction(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $modelo = $this->admin->getSubject();

        if (!$modelo) {
            throw $this->createNotFoundException(sprintf('No se encuentra el modelo con id: %s', $id));
        }

        // 1. DEFINICIÓN DE MEDIDAS Y REGLAS (La misma lógica que en el Importador)
        $definicionesTecnicas = [
            // --- SERIGRAFÍA (A1) ---
            'A1'   => ['w' => 36, 'h' => 42, 'mangas' => true, 'w_manga' => 11, 'h_manga' => 41, 'img_type' => 'std'],

            // --- TRANSFER / VINILO (P) ---
            'P1'   => ['w' => 12, 'h' => 12, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
            'P2'   => ['w' => 26, 'h' => 26, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],

            // --- TRANSFER (T) ---
            'T1'   => ['w' => 28, 'h' => 40, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
            'T2'   => ['w' => 20, 'h' => 28, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
            'T3'   => ['w' => 20, 'h' => 14, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
            'T4'   => ['w' => 10, 'h' => 10, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],

            // --- SUBLIMACIÓN (SU) ---
            'SU1'  => ['w' => 10, 'h' => 10, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
            'SU2'  => ['w' => 20, 'h' => 14, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
            'SU3'  => ['w' => 20, 'h' => 28, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],
            'SU4'  => ['w' => 28, 'h' => 40, 'mangas' => true, 'w_manga' => 10, 'h_manga' => 10, 'img_type' => 'std'],

            // --- IMPRESIÓN DIGITAL (DTG) - Sin mangas, imagenes específicas ---
            'DTG1' => ['w' => 10, 'h' => 10, 'mangas' => false, 'img_type' => 'dtg'],
            'DTG2' => ['w' => 20, 'h' => 14, 'mangas' => false, 'img_type' => 'dtg'],
            'DTG3' => ['w' => 20, 'h' => 30, 'mangas' => false, 'img_type' => 'dtg'],
            'DTG4' => ['w' => 30, 'h' => 40, 'mangas' => false, 'img_type' => 'dtg'],
            'DTG5' => ['w' => 40, 'h' => 50, 'mangas' => false, 'img_type' => 'dtg'],
        ];

        // 2. LIMPIEZA PREVIA (Borramos técnicas anteriores para evitar duplicados)
        // Opcional: Si quieres mantener las que ya tenía y solo añadir, comenta estas líneas.
        // Pero para "resetear" a Tuskamisetas, es mejor limpiar.
        $connection = $em->getConnection();
        $sqlClean = "DELETE a FROM areas_tecnicas_estampado a INNER JOIN modelo_tecnicas_estampado mt ON a.area_tecnica_id = mt.id WHERE mt.modelo_id = :modeloId";
        $connection->executeStatement($sqlClean, ['modeloId' => $modelo->getId()]);

        $sqlCleanRel = "DELETE FROM modelo_tecnicas_estampado WHERE modelo_id = :modeloId";
        $connection->executeStatement($sqlCleanRel, ['modeloId' => $modelo->getId()]);

        // 3. INSERTAR NUEVAS TÉCNICAS
        $count = 0;
        foreach ($definicionesTecnicas as $codigoTecnica => $specs) {
            $personalizacion = $em->getRepository(Personalizacion::class)->findOneBy(['codigo' => $codigoTecnica]);

            if (!$personalizacion) {
                continue; // Si la técnica no existe en BBDD, saltamos
            }

            // Crear Relación
            $relacion = new ModeloHasTecnicasEstampado();
            $relacion->setModelo($modelo);
            $relacion->setPersonalizacion($personalizacion);
            $relacion->setMaxcolores(8); // Estándar textil
            $em->persist($relacion);

            // --- A. ÁREAS DE CUERPO (Delantera / Trasera) ---
            $areasCuerpo = ['Delantera', 'Trasera'];
            foreach ($areasCuerpo as $nombreArea) {
                $suffixImg = ($specs['img_type'] === 'dtg') ? '_dtg.jpg' : '.jpg';
                $nombreImg = 'generica_' . strtolower($nombreArea) . $suffixImg;

                $area = new AreasTecnicasEstampado();
                $area->setTecnica($relacion);
                $area->setAreaname($nombreArea);
                $area->setAreawidth((string)$specs['w']);
                $area->setAreahight((string)$specs['h']);
                $area->setAreaimg("https://www.tuskamisetas.com/images/areas/" . $nombreImg);
                $area->setMaxcolores(8);
                $em->persist($area);
            }

            // --- B. ÁREAS DE MANGAS (Solo si aplica) ---
            if ($specs['mangas']) {
                $areasMangas = [
                    'Manga Izquierda' => 'generica_manga_izq.jpg',
                    'Manga Derecha'   => 'generica_manga_der.jpg'
                ];

                foreach ($areasMangas as $nombreArea => $imgFile) {
                    $wManga = $specs['w_manga'] ?? 10;
                    $hManga = $specs['h_manga'] ?? 10;

                    $area = new AreasTecnicasEstampado();
                    $area->setTecnica($relacion);
                    $area->setAreaname($nombreArea);
                    $area->setAreawidth((string)$wManga);
                    $area->setAreahight((string)$hManga);
                    $area->setAreaimg("https://www.tuskamisetas.com/images/areas/" . $imgFile);
                    $area->setMaxcolores(8);
                    $em->persist($area);
                }
            }
            $count++;
        }

        $em->flush();

        $this->addFlash('sonata_flash_success', sprintf('Se han aplicado %d técnicas Tuskamisetas (Pack Completo) al producto %s.', $count, $modelo->getReferencia()));

        return new RedirectResponse($this->admin->generateUrl('list', ['filter' => $this->admin->getFilterParameters()]));
    }
}