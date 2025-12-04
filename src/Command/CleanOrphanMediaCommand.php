<?php

namespace App\Command;

use App\Entity\Sonata\Media;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\MediaBundle\Model\MediaManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:media:clean-orphans',
    description: 'Elimina los medios de Sonata que no están asociados a ninguna entidad del sistema.',
)]
class CleanOrphanMediaCommand extends Command
{
    private EntityManagerInterface $em;
    private MediaManagerInterface $mediaManager;

    public function __construct(
        EntityManagerInterface $em,
        MediaManagerInterface  $mediaManager
    )
    {
        parent::__construct();
        $this->em = $em;
        $this->mediaManager = $mediaManager;
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Si se activa, solo muestra qué se borraría sin borrar nada.')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Límite de archivos a borrar por ejecución', 500);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $limit = (int)$input->getOption('limit');

        $io->title('Limpieza de Medios Huérfanos');

        if ($isDryRun) {
            $io->warning('MODO SIMULACIÓN (DRY-RUN): No se borrará nada.');
        }

        // 1. Obtener IDs de Medios EN USO
        // Tienes que añadir aquí TODAS las entidades y campos que relacionan con Media
        $usedMediaIds = $this->getUsedMediaIds();
        $io->text(sprintf('Se han encontrado %d medios en uso legítimo.', count($usedMediaIds)));

        // 2. Obtener TODOS los IDs de Medios existentes
        // Filtramos por contexto si quieres ser precavido (ej: solo 'default' o 'producto')
        // Aquí cogemos todos.
        $allMediaIds = $this->em->createQueryBuilder()
            ->select('m.id')
            ->from(Media::class, 'm')
            ->getQuery()
            ->getSingleColumnResult();

        $io->text(sprintf('Total de medios en el sistema: %d', count($allMediaIds)));

        // 3. Calcular la diferencia (Huérfanos)
        $orphanIds = array_diff($allMediaIds, $usedMediaIds);
        $totalOrphans = count($orphanIds);

        if ($totalOrphans === 0) {
            $io->success('¡Enhorabuena! No hay medios huérfanos.');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Se han detectado %d medios huérfanos.', $totalOrphans));

        // 4. Procesar borrado (por lotes para no saturar memoria)
        $count = 0;
        $deleted = 0;

        foreach ($orphanIds as $id) {
            if ($count >= $limit) {
                $io->text("Se ha alcanzado el límite de $limit operaciones. Ejecuta de nuevo para continuar.");
                break;
            }

            $media = $this->mediaManager->find($id);

            if ($media) {
                $name = $media->getName();
                $context = $media->getContext();

                if ($isDryRun) {
                    $io->text(" [DRY-RUN] Se borraría: ID $id - $name ($context)");
                } else {
                    try {
                        // El manager se encarga de borrar archivos y BBDD
                        $this->mediaManager->delete($media);
                        $io->text(" [OK] Borrado: ID $id - $name");
                        $deleted++;
                    } catch (\Exception $e) {
                        $io->error("Error borrando ID $id: " . $e->getMessage());
                    }
                }
            }

            $count++;
        }

        if (!$isDryRun) {
            $this->em->flush(); // Asegurar cambios en BBDD
        }

        $io->success(sprintf('Proceso finalizado. %s %d medios.', $isDryRun ? 'Se habrían borrado' : 'Borrados', $deleted));

        return Command::SUCCESS;
    }

    /**
     * Recopila todos los IDs de media que están siendo usados en las tablas del proyecto.
     */
    private function getUsedMediaIds(): array
    {
        $ids = [];

        // Lista de [Clase Entidad, Campo de Relación]
        // ¡AÑADE AQUÍ CUALQUIER OTRA RELACIÓN QUE TENGAS!
        $relations = [
            ['App\Entity\Producto', 'imagen'],
            ['App\Entity\Modelo', 'imagen'],
            ['App\Entity\Modelo', 'drawing'],
            ['App\Entity\Modelo', 'fichaTecnica'],
            ['App\Entity\Sonata\ClassificationCategory', 'imagen'],
            ['App\Entity\Fabricante', 'imagen'],
            ['App\Entity\Proveedor', 'imagen'],
            ['App\Entity\Empresa', 'logo'], // Si Empresa tiene logo como Media
            ['App\Entity\BannerHome', 'imagen'], // Si el blog usa Media
            ['App\Entity\PedidoTrabajo', 'montaje'],
            ['App\Entity\PedidoTrabajo', 'arteFin'],
            ['App\Entity\PedidoTrabajo', 'imagenOriginal'],
            ['App\Entity\EmpresaHasMedia', 'media'],
        ];

        foreach ($relations as $relation) {
            [$entityClass, $field] = $relation;

            // Comprobamos si la clase existe para evitar errores si borraste alguna
            if (!class_exists($entityClass)) continue;

            try {
                $results = $this->em->createQueryBuilder()
                    ->select("IDENTITY(e.$field)") // IDENTITY obtiene solo la ID foránea
                    ->from($entityClass, 'e')
                    ->where("e.$field IS NOT NULL")
                    ->distinct()
                    ->getQuery()
                    ->getSingleColumnResult();

                // Fusionamos los IDs encontrados
                $ids = array_merge($ids, $results);
            } catch (\Exception $e) {
                // Ignoramos errores si el campo no existe
            }
        }

        // También hay que buscar en las galerías (sonata_media_gallery_has_media)
        // Sonata gestiona esto internamente, pero los Items de galería apuntan al Media.
        try {
            $galleryResults = $this->em->createQueryBuilder()
                ->select('IDENTITY(i.media)')
                ->from('App\Entity\Sonata\GalleryItem', 'i')
                ->where('i.media IS NOT NULL')
                ->distinct()
                ->getQuery()
                ->getSingleColumnResult();
            $ids = array_merge($ids, $galleryResults);
        } catch (\Exception $e) {
        }

        // Devolvemos IDs únicos y limpios
        return array_unique(array_map('intval', $ids));
    }
}