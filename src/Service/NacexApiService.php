<?php

namespace App\Service;

use App\Entity\Pedido;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NacexApiService
{
    public function __construct(
        private HttpClientInterface $client, // Volvemos a usar HttpClient
        private EntityManagerInterface $entityManager,
        private string $nacexUser,
        private string $nacexPass,
        private string $nacexUrl, // Esta será la URL base, ej: https://pda.nacex.com/nacex_ws/ws
        private string $nacexDelCli,
        private string $nacexNumCli
    ) {}

    /**
     * Documenta un envío y actualiza el objeto Pedido en la base de datos.
     * @return array ['error' => bool, 'message' => string]
     * El servicio 26 era el que utilizamos para un bulto y el 08 es el 19 horas que se utiliza para mas bultos
     */
    public function documentarEnvio(Pedido $pedido, int $bultos = 1, string $codigoServicio = '26'): array
    {
        $direccionEnvio = $pedido->getDireccion() ?? $pedido->getContacto()->getDireccionFacturacion();
        if (!$direccionEnvio) {
            return ['error' => true, 'message' => 'El pedido no tiene dirección de envío.'];
        }

        if($bultos>1)
        {
            $codigoServicio = '08';
        }

        // Construimos el string de datos para el método GET, como en la documentación
        $dataString = http_build_query([
            'del_cli' => $this->nacexDelCli, 'num_cli' => $this->nacexNumCli,
            'tip_ser' => $codigoServicio, 'tip_cob' => 'O', 'ref_cli' => $pedido->getId(),
            'tip_env' => 2, 'bul' => $bultos, 'kil' => 1,
            'nom_ent' => $this->codifica($direccionEnvio->getNombre() ?: ($pedido->getContacto()->getNombre() . ' ' . $pedido->getContacto()->getApellidos())),
            'dir_ent' => $this->codifica($direccionEnvio->getDir()),
            'pais_ent' => 'ES', 'cp_ent' => $direccionEnvio->getCp(),
            'pob_ent' => $this->codifica($direccionEnvio->getPoblacion()),
            'tel_ent' => $direccionEnvio->getTelefonoMovil() ?: $pedido->getContacto()->getTelefonoMovil(),
            'obs1' => $this->codifica($pedido->getObservaciones() ?? ''),
            'seguimiento' => 'S', // Pedimos explícitamente la URL de seguimiento
        ], '', '|');

        try {
            // Construimos la URL final para la petición GET
            $url = sprintf(
                '%s?method=putExpedicion&data=%s&user=%s&pass=%s',
                $this->nacexUrl,
                $dataString,
                $this->nacexUser,
                $this->nacexPass
            );

            $response = $this->client->request('GET', $url);
            $content = $response->getContent();
            $respuestas = explode("|", $content);

            if (count($respuestas) < 13 || empty($respuestas[0])) { // La respuesta GET tiene 14 campos
                throw new \Exception('Respuesta inesperada de la API: ' . $content);
            }

            // Según la documentación, la URL de seguimiento es el último parámetro
            $trackingUrl = str_replace('amp;', '', end($respuestas));

            //obtenemos también la idReferencia que es el número de albarán para luego obtener las etiquetas_
            $idReferencia = $respuestas[0];
            //https://www.nacex.es/seguimientoDetalle.do?agencia_origen=3003&numero_albaran=10768217&numero_referencia=374904507
//            https://www.nacex.com//seguimientoDetalle.do?agencia_origen=3003&numero_albaran=11051915&externo=N&numero_referencia=374904507

            $trackingUrl = $trackingUrl."&".$idReferencia;

            if (filter_var($trackingUrl, FILTER_VALIDATE_URL) === false) {
                throw new \Exception('La API no devolvió una URL de seguimiento válida.');
            }

            $pedido->setAgenciaEnvio('nacex');
            $pedido->setBultos($bultos);
            $pedido->setSeguimientoEnvio($trackingUrl);

            $this->entityManager->flush();

            return ['error' => false, 'message' => 'Envío documentado en Nacex correctamente.'];

        } catch (\Exception $e) {
            return ['error' => true, 'message' => 'Error en la comunicación con Nacex (GET): ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene las etiquetas para una expedición de Nacex.
     */
    public function getEtiquetas(Pedido $pedido): array
    {
        // ==================== INICIO DE LA CORRECCIÓN ====================
        $trackingUrl = $pedido->getSeguimientoEnvio();
        $codExp = null;

        if ($trackingUrl) {
            // 1. Intentamos extraer el 'numero_referencia' de la URL guardada.
            parse_str(parse_url($trackingUrl, PHP_URL_QUERY), $queryParams);
            $codExp = $queryParams['numero_referencia'] ?? null;
        }

        // 2. Si no lo encontramos en la URL (caso de los envíos antiguos), usamos el ID del pedido como respaldo.
//        if (!$codExp) {
//            $codExp = $pedido->getNombre();
////            $codExp="452673015 ";
//        }
        var_dump($codExp);

        if (!$codExp) {
            return ['error' => true, 'message' => 'No se pudo determinar el código de expedición para este pedido.', 'etiquetas' => []];
        }

        $bultos = $pedido->getBultos() > 0 ? $pedido->getBultos() : 1;
        $dataString = "codExp={$codExp}|bultoIni=1|bultoFin={$bultos}|modelo=IMAGEN_B";
        // ===================== FIN DE LA CORRECCIÓN ======================

        try {
            $url = sprintf(
                '%s?method=getEtiquetaBultos&data=%s&user=%s&pass=%s',
                $this->nacexUrl,
                $dataString,
                $this->nacexUser,
                $this->nacexPass
            );

            $response = $this->client->request('GET', $url);
            $content = $response->getContent();
            var_dump($content);
            $etiquetasBase64 = explode('|', $content);

            if (empty($etiquetasBase64[0])) {
                throw new \Exception('La API no devolvió ninguna etiqueta.');
            }

            return ['error' => false, 'message' => 'Etiquetas obtenidas.', 'etiquetas' => $etiquetasBase64];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => 'Error al obtener etiquetas de Nacex (GET): ' . $e->getMessage(), 'etiquetas' => []];
        }
    }

    private function codifica(?string $cadena): string
    {
        if($cadena === null) {
            return '';
        }
        if (empty($cadena)) return '';
        $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕºª';
        $modificadas = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRroa';
        $mapping = array_combine(preg_split('/(?<!^)(?!$)/u', $originales), preg_split('/(?<!^)(?!$)/u', $modificadas));
        return urlencode(strtr($cadena, $mapping));
    }
}