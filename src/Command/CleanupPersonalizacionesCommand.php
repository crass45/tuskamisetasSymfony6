<?php

namespace App\Command;

use App\Entity\ModeloHasTecnicasEstampado;
use App\Entity\PedidoTrabajo;
use App\Entity\Personalizacion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ss:cleanup_personalizaciones',
    description: 'Elimina de forma segura las personalizaciones que no se usan en productos ni pedidos.'
)]
class CleanupPersonalizacionesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Limpieza de Personalizaciones Obsoletas');

        // 1. Obtener IDs de personalizaciones que están asignadas a algún MODELO (Catálogo actual)
        $io->text('Buscando personalizaciones en uso en el catálogo...');
        $usadasEnModelos = $this->em->getRepository(ModeloHasTecnicasEstampado::class)
            ->createQueryBuilder('mt')
            ->select('IDENTITY(mt.personalizacion) as id')
            ->distinct()
            ->getQuery()
            ->getScalarResult();

        $idsEnModelos = array_column($usadasEnModelos, 'id');

        // 2. Obtener IDs de personalizaciones que se usaron en PEDIDOS (Histórico de ventas)
        // ¡Importante para no romper pedidos antiguos!
        $io->text('Buscando personalizaciones usadas en pedidos históricos...');
        $usadasEnPedidos = $this->em->getRepository(PedidoTrabajo::class)
            ->createQueryBuilder('pt')
            ->select('IDENTITY(pt.personalizacion) as id')
            ->distinct()
            ->getQuery()
            ->getScalarResult();

        $idsEnPedidos = array_column($usadasEnPedidos, 'id');

        // 3. Fusionar listas para crear la "Lista Blanca" (No borrar)
        $idsEnUso = array_unique(array_merge($idsEnModelos, $idsEnPedidos));
        // Filtramos nulos por si acaso
        $idsEnUso = array_filter($idsEnUso);

        $io->note(sprintf('Se encontraron %d técnicas en uso (Modelos o Pedidos).', count($idsEnUso)));

        // 4. Buscar las personalizaciones que NO están en la lista blanca
        $qb = $this->em->getRepository(Personalizacion::class)->createQueryBuilder('p');

        if (!empty($idsEnUso)) {
            $qb->where($qb->expr()->notIn('p.codigo', ':ids'))
                ->setParameter('ids', $idsEnUso);
        }

        $aBorrar = $qb->getQuery()->getResult();
        $total = count($aBorrar);

        if ($total === 0) {
            $io->success('¡Todo limpio! No hay personalizaciones obsoletas para borrar.');
            return Command::SUCCESS;
        }

        // 5. Confirmación y Borrado
        $io->section("Se han encontrado $total personalizaciones HUÉRFANAS.");
        $io->text('Estas técnicas no están asignadas a ningún producto ni existen en pedidos anteriores.');

        if (!$io->confirm('¿Estás seguro de que quieres eliminarlas permanentemente?', false)) {
            $io->note('Operación cancelada.');
            return Command::SUCCESS;
        }

        $io->progressStart($total);

        foreach ($aBorrar as $personalizacion) {
            // Doctrine se encarga de borrar también los precios (orphanRemoval)
            $this->em->remove($personalizacion);
            $io->progressAdvance();
        }

        $this->em->flush(); // Ejecutar borrado en BBDD
        $io->progressFinish();

        $io->success("Limpieza completada. Se eliminaron $total técnicas.");

        return Command::SUCCESS;
    }
}