<?php
// src/Model/Filtros.php

namespace App\Model;

use App\Entity\Fabricante;
use App\Entity\Familia;
use App\Entity\Sonata\ClassificationCategory;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;

class Filtros
{
    private array $atributos = [];
    private array $colores = [];
    private ?Fabricante $fabricante = null;
    private ?Familia $familia = null;
    private ?ClassificationCategory $category = null;
    private string $orden = "";
    private ?string $busqueda = null;

    /**
     * Crea un objeto Filtros a partir de los parámetros de la URL.
     * Ahora recibe el EntityManager para poder buscar entidades.
     */
    public static function createFromRequest(Request $request, EntityManagerInterface $em): self
    {
        $filtros = new self();

        $filtros->setAtributos($request->query->all('atributos'));
        $filtros->setColores($request->query->all('colores'));
        $filtros->setOrden($request->query->get('orden', ''));
        $filtros->setBusqueda($request->query->get('q'));

        // Leemos el ID del fabricante de la URL
        $fabricanteId = $request->query->get('fabricante');
        if ($fabricanteId) {
            // Usamos el EntityManager para encontrar el objeto Fabricante completo
            $fabricanteObject = $em->getRepository(Fabricante::class)->find($fabricanteId);
            if ($fabricanteObject) {
                $filtros->setFabricante($fabricanteObject);
            }
        }

        return $filtros;
    }

    /**
     * Reemplaza el antiguo __sleep() para la serialización en PHP 8+.
     */
    public function __serialize(): array
    {
        return [
            'atributos' => $this->atributos,
            'colores' => $this->colores,
            'familia' => $this->familia,
            'fabricante' => $this->fabricante,
            'category' => $this->category,
            'orden' => $this->orden,
            'busqueda' => $this->busqueda,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->atributos = $data['atributos'] ?? [];
        $this->colores = $data['colores'] ?? [];
        $this->familia = $data['familia'] ?? null;
        $this->fabricante = $data['fabricante'] ?? null;
        $this->category = $data['category'] ?? null;
        $this->orden = $data['orden'] ?? '';
        $this->busqueda = $data['busqueda'] ?? null;
    }

    public function inicializaFiltros(): void
    {
        $this->atributos = [];
        $this->colores = [];
        $this->fabricante = null;
        $this->orden = "";
        $this->familia = null;
        $this->category = null;
        $this->busqueda = null;
    }

    // --- Métodos de Filtro (Toggle) ---

    public function filtroAtributo(string $atributo): void
    {
        if (!in_array($atributo, $this->atributos, true)) {
            $this->addAtributo($atributo);
        } else {
            $this->eliminaAtributo($atributo);
        }
    }

    public function filtroColor(string $color): void
    {
        if (!in_array($color, $this->colores, true)) {
            $this->addColor($color);
        } else {
            $this->eliminaColor($color);
        }
    }

    public function filtroFabricante(?Fabricante $fabricante): void
    {
        $this->fabricante = ($this->fabricante === $fabricante) ? null : $fabricante;
    }

    // --- Métodos de Gestión de Arrays ---

    private function addAtributo(string $atributo): void
    {
        if (!in_array($atributo, $this->atributos, true)) {
            $this->atributos[] = $atributo;
        }
    }

    private function eliminaAtributo(string $atributo): void
    {
        $key = array_search($atributo, $this->atributos, true);
        if ($key !== false) {
            unset($this->atributos[$key]);
            $this->atributos = array_values($this->atributos); // Re-indexar
        }
    }

    private function addColor(string $color): void
    {
        if (!in_array($color, $this->colores, true)) {
            $this->colores[] = $color;
        }
    }

    private function eliminaColor(string $color): void
    {
        $key = array_search($color, $this->colores, true);
        if ($key !== false) {
            unset($this->colores[$key]);
            $this->colores = array_values($this->colores); // Re-indexar
        }
    }

    // --- Métodos para URL ---

    public function getCadenaFiltro(): string
    {
        return implode(',', $this->atributos);
    }

    public function estableceCadena(?string $cadenaFiltros): void
    {
        $this->atributos = (!empty($cadenaFiltros)) ? explode(',', $cadenaFiltros) : [];
    }

    // --- Getters y Setters ---

    public function getAtributos(): array { return $this->atributos; }
    public function setAtributos(array $atributos): void { $this->atributos = $atributos; }
    public function getColores(): array { return $this->colores; }
    public function setColores(array $colores): void { $this->colores = $colores; }
    public function getFamilia(): ?Familia { return $this->familia; }
    public function setFamilia(?Familia $familia): void { $this->familia = $familia; }
    public function getFabricante(): ?Fabricante { return $this->fabricante; }
    public function setFabricante(?Fabricante $fabricante): void { $this->fabricante = $fabricante; }
    public function getCategory(): ?ClassificationCategory { return $this->category; }
    public function setCategory(?ClassificationCategory $category): void { $this->category = $category; }
    public function getOrden(): string { return $this->orden; }
    public function setOrden(string $orden): void { $this->orden = $orden; }
    public function getBusqueda(): ?string { return $this->busqueda; }
    public function setBusqueda(?string $busqueda): void { $this->busqueda = $busqueda; }
}

