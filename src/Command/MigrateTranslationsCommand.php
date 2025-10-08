<?php

namespace App\Command;

use App\Entity\Modelo;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-translations',
    description: 'Importa las traducciones desde old_translations.csv a la tabla ext_translations.',
)]
class MigrateTranslationsCommand extends Command
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Iniciando importación de traducciones desde CSV (modo optimizado)');

        // Desactivamos el logger de SQL para que no consuma memoria
        $this->connection->getConfiguration()->setSQLLogger(null);
        gc_disable(); // Desactivamos el garbage collector para gestionarlo manualmente

        $csvFilePath = $this->getApplication()->getKernel()->getProjectDir() . '/old_translations.csv';
        if (!file_exists($csvFilePath)) {
            $io->error("El archivo 'old_translations.csv' no se encuentra en la raíz del proyecto.");
            return Command::FAILURE;
        }

        try {
            $io->text("Abriendo el archivo CSV: " . $csvFilePath);
            $handle = fopen($csvFilePath, 'r');

            // Contamos el total de líneas para la barra de progreso
            $totalRows = count(file($csvFilePath)) - 1; // -1 para no contar la cabecera
            if ($totalRows <= 0) {
                $io->warning('El archivo CSV está vacío o solo contiene la cabecera.');
                return Command::SUCCESS;
            }

            $this->connection->beginTransaction();
            $insertCount = 0;
            $rowCount = 0;

            $headers = fgetcsv($handle);
            $columnMap = array_flip($headers);
            // ... (verificación de columnas que ya teníamos)

            // --- INICIO DE LA CORRECCIÓN ---
            // 1. Iniciamos la barra de progreso con el total de filas.
            $io->progressStart($totalRows);
            // --- FIN DE LA CORRECCIÓN ---

            while (($data = fgetcsv($handle)) !== FALSE) {
                $rowCount++;
                if (count($data) !== count($headers)) {
                    $io->warning("Saltando línea #{$rowCount} por tener un número incorrecto de columnas.");
                    continue;
                }

                $modeloId = $data[$columnMap['translatable_id']];
                $locale = $data[$columnMap['locale']];
                if (empty($modeloId) || empty($locale)) continue;

                $fieldsToMigrate = ['titulo_seo', 'descripcion_seo', 'descripcion'];
                foreach ($fieldsToMigrate as $fieldName) {
                    $content = $data[$columnMap[$fieldName]];
                    if (!empty($content)) {
                        $this->connection->insert('ext_translations', [
                            'locale' => $locale, 'object_class' => Modelo::class,
                            'field' => $fieldName, 'foreign_key' => $modeloId,
                            'content' => $content
                        ]);
                        $insertCount++;
                    }
                }

                // Limpiamos la memoria cada 500 filas
                if ($rowCount % 500 === 0) {
                    $this->em->clear();
                    gc_collect_cycles();
                }

                // --- INICIO DE LA CORRECCIÓN ---
                // 2. Avanzamos la barra de progreso en cada iteración.
                $io->progressAdvance();
                // --- FIN DE LA CORRECCIÓN ---
            }
            fclose($handle);

            $this->connection->commit();
            $io->progressFinish();
            $io->success("¡Migración completada! Se han importado {$insertCount} campos traducidos.");

        } catch (\Exception $e) {
            $this->connection->rollBack();
            $io->error('Ha ocurrido un error: ' . $e->getMessage());
            return Command::FAILURE;
        } finally {
            gc_enable(); // Reactivamos el recolector de basura
        }

        return Command::SUCCESS;
    }
}