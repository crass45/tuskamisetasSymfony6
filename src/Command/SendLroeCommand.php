<?php

namespace App\Command;

use App\Entity\Factura;
use App\Entity\FacturaRectificativa;
use App\Repository\FacturaRectificativaRepository;
use App\Repository\FacturaRepository;
use App\Service\AeatClientService;
use App\Service\VerifactuService;
use Doctrine\ORM\EntityManagerInterface; // <-- Importar EntityManager
use josemmo\Verifactu\Models\Responses\ResponseStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:verifactu:send-lroe',
    description: 'Genera y envía todos los registros VeriFactu pendientes a la AEAT.',
)]
class SendLroeCommand extends Command
{
    public function __construct(
        private FacturaRepository $facturaRepo,
        private FacturaRectificativaRepository $rectificativaRepo, // <-- Repositorio añadido
        private VerifactuService $verifactuService,
        private AeatClientService $aeatClientService,
        private EntityManagerInterface $em // <-- Añadir EntityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // Ya no necesitamos argumentos de fecha
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'No enviar a la AEAT, solo mostrar los registros');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');

        $io->title("Generando LROE para todos los registros pendientes...");

        // 1. Encontrar todas las facturas y rectificativas pendientes
        $facturasPendientes = $this->facturaRepo->findPendingLroe();
        $rectificativasPendientes = $this->rectificativaRepo->findPendingLroe();

        $allInvoicesToSend = array_merge($facturasPendientes, $rectificativasPendientes);
        if (empty($allInvoicesToSend)) {
            $io->success('No hay facturas pendientes de enviar a la AEAT.');
            return Command::SUCCESS;
        }
        $io->info(count($allInvoicesToSend) . ' registros totales pendientes encontrados.');

        // 2. Ordenarlos cronológicamente (por fecha y luego por ID)
        usort($allInvoicesToSend, function ($a, $b) {
            if ($a->getFecha() == $b->getFecha()) {
                // Si la fecha es la misma, usamos el ID para desempatar
                $aId = ($a instanceof Factura) ? 'F'.$a->getId() : 'R'.$a->getId();
                $bId = ($b instanceof Factura) ? 'F'.$b->getId() : 'R'.$b->getId();
                return strcmp($aId, $bId);
            }
            return $a->getFecha() <=> $b->getFecha();
        });

        // 3. Reconstruir los objetos RegistrationRecord
        $recordsToSend = [];
        $io->progressStart(count($allInvoicesToSend));
        foreach ($allInvoicesToSend as $invoice) {
            $record = null;
            $previousRecordData = null;
            $currentTable = '';
            $currentId = $invoice->getId();

            if ($invoice instanceof Factura) {
                $currentTable = 'factura';
            } elseif ($invoice instanceof FacturaRectificativa) {
                $currentTable = 'factura_rectificativa';
            }

            // Buscamos el registro anterior al actual
            $previousRecordData = $this->facturaRepo->findPreviousVerifactuRecordData(
                $invoice->getFecha(),
                $currentId,
                $currentTable
            );

            // Creamos el registro correspondiente
            if ($invoice instanceof Factura) {
                $record = $this->verifactuService->createRegistrationRecord($invoice, $previousRecordData);
            } elseif ($invoice instanceof FacturaRectificativa) {
                $record = $this->verifactuService->createCreditNoteRecord($invoice, $previousRecordData);
            }

            if ($record) {
                $recordsToSend[] = $record;
            }
            $io->progressAdvance();
        }
        $io->progressFinish();
        $io->success('Todos los registros de facturación han sido reconstruidos.');

        // 4. Si es --dry-run, mostrar los datos y salir
        if ($isDryRun) {
            $io->section('Modo --dry-run activado. Registros que se enviarían:');
            print_r($recordsToSend);
            $io->note('No se ha enviado nada a la AEAT.');
            return Command::SUCCESS;
        }

        // 5. Enviar los registros a la AEAT
        $io->info('Enviando registros a la AEAT...');
        try {
            $response = $this->aeatClientService->sendRecords($recordsToSend);

            $io->section('Respuesta de la AEAT');
            $io->writeln(print_r($response, true));
//            $io->writeln($response->asXML());

            // --- CORRECCIÓN NUEVA: Accedemos a la propiedad pública '$status' ---
            if ($response->status === \josemmo\Verifactu\Models\Responses\ResponseStatus::Correct) {
                // --- FIN CORRECCIÓN NUEVA ---
                $io->success('LROE enviado y aceptado correctamente por la AEAT.');

                $io->info('Marcando registros como "enviados" en la base de datos...');
                foreach ($allInvoicesToSend as $invoice) {
                    $invoice->setVerifactuEnviadoAt(new \DateTimeImmutable());
                }
                $this->em->flush();
                $io->success('Base de datos actualizada.');

            } else {
                $io->warning('El LROE fue enviado pero la AEAT reportó errores. No se han marcado como enviados. Revisa la respuesta.');
            }

//            if ($response->getStatus() === \josemmo\Verifactu\Models\Responses\ResponseStatus::Correct) {
//                $io->success('LROE enviado y aceptado correctamente por la AEAT.');
//
//                // 6. Marcar las facturas como enviadas
//                $io->info('Marcando registros como "enviados" en la base de datos...');
//                foreach ($allInvoicesToSend as $invoice) {
//                    $invoice->setVerifactuEnviadoAt(new \DateTimeImmutable());
//                }
//                $this->em->flush();
//                $io->success('Base de datos actualizada.');
//
//            } else {
//                $io->warning('El LROE fue enviado pero la AEAT reportó errores. No se han marcado como enviados. Revisa la respuesta XML.');
//            }
        } catch (\Exception $e) {
            $io->error('Ha ocurrido un error durante el envío a la AEAT:');
            $io->writeln($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

