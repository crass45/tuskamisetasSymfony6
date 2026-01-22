<?php
// src/Repository/PlantillaRepository.php (este es el código base)

namespace App\Repository;

use App\Entity\Color;
use App\Entity\Modelo;
use App\Entity\Producto; // <-- CAMBIAR ESTO
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Producto> // <-- CAMBIAR ESTO
 */
class ProductoRepository extends ServiceEntityRepository // <-- CAMBIAR ESTO
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Producto::class); // <-- CAMBIAR ESTO
    }

    /**
     * Encuentra todos los productos para un modelo y color específicos,
     * y los devuelve ORDENADOS por talla.
     * @return Producto[]
     */
    public function findByModelAndColor(Modelo $modelo, Color $color): array
    {
        // 1. Obtenemos los productos de la base de datos
        $productos = $this->createQueryBuilder('p')
            ->andWhere('p.modelo = :modelo')
            ->andWhere('p.color = :color')
            ->andWhere('p.activo = true')
            ->setParameter('modelo', $modelo)
            ->setParameter('color', $color)
            ->getQuery()
            ->getResult();

        // 2. Definimos el orden correcto y completo de las tallas
        $tallasOrdenadas = [
            // BEBÉ (Meses)
            '104 (3-4)', '116 (5-6)', '128 (7-8)', '140 (9-11)', '152 (12-13)','164 (14-15)',
            '0-3', '3M', '03/06M', '3 MESES', '3-6', '6M', '6 MESES', '_0/6', '6-12', '06/12M', '9M', '9 MESES',
            '12M', '12 MESES', '_6/12', '12-18', '12;18', '12/18M', '18M', '18 MESES', '_12/18', '18-24',
            '18/23M', '_18/24', '24M',
            // NIÑO (Años)
            '1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24',
            '1/2','2/3','3/4','4/5','5/6','6/7','7/8','8/9','9/10','10/11','11/12',
            '1 AÑO', '90', '92 (1-2)', 'XS (90/1-2)', '1-2', '1/2 AÑOS', '1/2 (86-92)',
            '2 AÑOS', '2T (86/92/XS)', '2-3', '2-3 yrs', '02A',
            '3 AÑOS', '3T (98/S)', '3-4', '3/4 AÑOS', '3;4', '3/4 (98/104)', 'XS (3-4)', 'XS (3/4/104)',
            '4 AÑOS', '4T (104/M)', '4-5', '4/5', '4/6', '4/6 (110-120cm)', '04A', 'S (104/3-4)',
            '5-6', '5/6 AÑOS', '5/6 (110/116)', 'S (5/6)', 'S (116)', 'S (5-6, 116)', 'M (116/5-6)',
            '6 AÑOS', '6', '6-7', '6/7', '6-8', '6/8Y', 'S (6-7)',
            '7-8', '7/8 AÑOS', '7;8', '7-8 (122/128)', 'M (7-8)', 'M (128)', 'M (7-8, 128)', 'L (128/7-8)',
            '8 AÑOS', '8', '8-9', '8/9', '8-10', '8/10', 'M (8-9)',
            '9-10', '9/10 AÑOS', '9;11', '9-11', '9-10 (140)', 'L (9-10, 140)', 'XL (140/9-10)', 'L (9-10, 132)',
            '10 AÑOS', '10', '10-11', '10-12', '10/12', '10/12Y', 'L (10-11)',
            '11-12', '11/12 AÑOS', '11-13 (152)', '12-14', 'XL (11-12, 140)', '2XL (152/11-12)',
            '12 AÑOS', '12', '12-13', '13-14', '13/14 AÑOS', 'L (12-13)', 'XL (12-14)',
            '14 AÑOS', '14', '14-15', '14-16', '14A',
            '16 AÑOS', '16', '15/16A',
            'UNICA NIÑO', 'KID (31/34)',
            // ADULTO (Estándar)
            '3XS/2XS', '3XS', '2XS', '2XS (6)', 'XXS', 'XXS (6)',
            'XS', 'XS (8)', 'XS (3-4/104)', 'XS (34)', '8 (XS/34)',
            'S', 'S (10)', 'S (36)', '10 (S/36)',
            'M', 'M (12)', 'M (12/38)', '12 /M/38)',
            'L', 'L (14)', 'L (14/40)', '14 (L/40)',
            'XL', 'XL (16)', 'XL (16/42)', '16 (XL/42)',
            'XXL', '2XL', '2XL (18)', '2XL (18/44)',
            'XXXL', '3XL', '3XL (20)', '3XL (20/46)',
            'XXXXL', '4XL', '4XL (22)', '4XL (22/48)',
            'XXXXXL', '5XL', '5XL (24)',
            '6XL', '6XL (26)',
            // ADULTO (Combinadas)
            'XS/S', 'XS-S', 'XS/S (8/10)', 'S/M', 'S-M',
            'M/L', 'M-L', 'M/L (12/14)',
            'L/XL', 'L-XL', 'LXL',
            'XL/XXL', 'XL-XXL', 'XL/2XL', 'XL/2XL (16/18)',
            '2XL/3XL', 'XXL/XXXL',
            '3XL/4XL',
            // ADULTO (Numéricas)
            '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47', '48', '50', '52', '54', '56', '58', '60', '62', '64',
            // TALLA ÚNICA
            'UNICA', 'ONE SIZE', 'UNIQUE', 'ONE-SIZE', 'ST',
            // OTRAS
            'JR', 'SR', 'JUNIOR', 'ADULTO',
        ];

        // Añadimos tallas numéricas simples como fallback
        for ($i = 0; $i < 150; $i++) {
            $tallasOrdenadas[] = (string)$i;
        }

        // 3. Usamos 'usort' para ordenar el array de productos en PHP
        usort($productos, function (Producto $a, Producto $b) use ($tallasOrdenadas) {
            // Buscamos la posición de cada talla en nuestro array de ordenación
            $pos_a = array_search(strtoupper($a->getTalla()), $tallasOrdenadas);
            $pos_b = array_search(strtoupper($b->getTalla()), $tallasOrdenadas);

            // Si una talla no está en la lista, la ponemos al final.
            if ($pos_a === false) return 1;
            if ($pos_b === false) return -1;

            return $pos_a - $pos_b;
        });

        // 4. Devolvemos el array ya ordenado
        return $productos;
    }
}