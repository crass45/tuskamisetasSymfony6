<?php
// src/Command/FixSonataRolesCommand.php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


//UPDATE app_group
//SET roles = '[]'  -- '[]' es el array JSON vacío
//WHERE roles = 'a:0:{}';


use App\Entity\Sonata\User as UserEntity;

class FixUserRolesCommand extends Command
{
    protected static $defaultName = 'app:fix-sonata-roles';

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Convierte los roles serializados de PHP de los usuarios de Sonata a formato JSON.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connection = $this->entityManager->getConnection();

        $tableName = 'user__user';

        // Obtenemos todos los usuarios con roles serializados
        $stmt = $connection->prepare("SELECT id, roles FROM $tableName WHERE roles LIKE 'a:%'");
        $results = $stmt->executeQuery()->fetchAllAssociative();

        if (empty($results)) {
            $io->info('No se encontraron usuarios con roles en formato serializado. ¡Todo correcto!');
            return Command::SUCCESS;
        }

        $io->progressStart(count($results));
        $updatedCount = 0;

        foreach ($results as $row) {
            $rawRoles = $row['roles'];
            $userId = $row['id'];

            // Usamos unserialize() para convertir la cadena en un array de PHP
            $unserializedRoles = @unserialize($rawRoles);

            if ($unserializedRoles !== false) {
                // Convertimos el array de PHP a un string JSON
                $jsonRoles = json_encode(array_values($unserializedRoles));

                // Actualizamos la fila directamente con SQL para máxima eficiencia
                $connection->executeStatement(
                    "UPDATE $tableName SET roles = :roles WHERE id = :id",
                    ['roles' => $jsonRoles, 'id' => $userId]
                );
                $updatedCount++;
            }
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success("$updatedCount usuarios de Sonata actualizados correctamente.");

        return Command::SUCCESS;
    }
}