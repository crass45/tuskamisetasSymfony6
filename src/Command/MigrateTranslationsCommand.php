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
    description: 'Importa las traducciones desde old_translations.csv de forma optimizada.',
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
        // Pide a PHP que no imponga un límite de memoria para este script
        ini_set('memory_limit', '-1');

        $io = new SymfonyStyle($input, $output);
        $io->title('Iniciando importación de traducciones desde CSV');

        // Desactivamos herramientas de depuración que consumen memoria
        $this->connection->getConfiguration()->setSQLLogger(null);
        gc_disable();

        $csvFilePath = $this->getApplication()->getKernel()->getProjectDir() . '/old_translations.csv';
        if (!file_exists($csvFilePath)) {
            $io->error("El archivo 'old_translations.csv' no se encuentra en la raíz del proyecto.");
            return Command::FAILURE;
        }

        try {
            $handle = fopen($csvFilePath, 'r');
            $this->connection->beginTransaction();

            $headers = fgetcsv($handle);
            if ($headers === false) {
                $io->warning('El archivo CSV está vacío.');
                return Command::SUCCESS;
            }

            $columnMap = array_flip($headers);

            // --- INICIO DE LA CORRECCIÓN DE LÓGICA ---
            // Columnas obligatorias para que una fila sea válida
            if (!isset($columnMap['translatable_id']) || !isset($columnMap['locale'])) {
                $io->error('El CSV debe contener al menos las columnas "translatable_id" y "locale".');
                return Command::FAILURE;
            }

            // Detectamos qué campos de traducción están realmente presentes en el CSV
            $availableFields = [];
            $potentialFields = ['titulo_seo', 'descripcion_seo', 'descripcion'];
            foreach ($potentialFields as $field) {
                if (isset($columnMap[$field])) {
                    $availableFields[] = $field;
                }
            }

            if (empty($availableFields)) {
                $io->warning('No se ha encontrado ninguna columna de traducción (titulo_seo, descripcion_seo, descripcion) en el CSV.');
                return Command::SUCCESS;
            }
            $io->info('Se importarán los siguientes campos: ' . implode(', ', $availableFields));
            // --- FIN DE LA CORRECCIÓN DE LÓGICA ---

            $io->progressStart();
            $rowCount = 0;
            $insertCount = 0;

            while (($data = fgetcsv($handle)) !== FALSE) {
                $rowCount++;
                if (count($data) !== count($headers)) {
                    // Este aviso es correcto, lo mantenemos
                    $io->warning(" Saltando línea #{$rowCount} por tener un número incorrecto de columnas.");
                    $io->progressAdvance();
                    continue;
                }

                $modeloId = $data[$columnMap['translatable_id']];
                $locale = $data[$columnMap['locale']];
                if (empty($modeloId) || empty($locale)) {
                    $io->progressAdvance();
                    continue;
                }

                // Iteramos solo sobre los campos que hemos detectado que existen
                foreach ($availableFields as $fieldName) {
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

                if ($rowCount % 500 === 0) {
                    $this->em->clear();
                    gc_collect_cycles();
                }

                $io->progressAdvance();
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
            gc_enable();
        }

        return Command::SUCCESS;
    }
}