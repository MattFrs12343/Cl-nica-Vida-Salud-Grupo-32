<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\ConsultaModel;
use App\Models\DocumentoModel;
use App\Models\OrdenModel;
use App\Models\RecetaModel;
use App\Models\ResultadoModel;
use App\Services\ConsultaService;

/**
 * Consultas, recetas, documentos, órdenes y resultados (Spec 10 / M-31).
 *
 * Las lecturas (mostrar/imprimir) corren bajo el contexto RLS del usuario
 * autenticado: un médico o paciente que no sea dueño del recurso simplemente
 * no lo ve (la fila no existe para su sesión), por lo que se trata como 404.
 *
 * Las escrituras necesitan distinguir "no existe" de "no es tuyo" (SPEC 10:
 * "Cita no del médico" → 403), así que corren con `comoPropietario()`
 * (bypassa RLS) y verifican la propiedad explícitamente, igual que
 * `CitaService`/`CitaApiController`.
 */
final class ConsultaApiController extends AdminApiController
{
    private const TIPOS_DOCUMENTO = ['certificado', 'informe', 'constancia', 'interconsulta'];
    private const TIPOS_ORDEN = ['laboratorio', 'imagen', 'otro'];
    private const TIPOS_RESULTADO = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        private ?ConsultaService $consultaService = null,
        private ?ConsultaModel $consultas = null,
        private ?RecetaModel $recetas = null,
        private ?DocumentoModel $documentos = null,
        private ?OrdenModel $ordenes = null,
        private ?ResultadoModel $resultados = null
    ) {
        $this->consultaService ??= new ConsultaService();
        $this->consultas ??= new ConsultaModel();
        $this->recetas ??= new RecetaModel();
        $this->documentos ??= new DocumentoModel();
        $this->ordenes ??= new OrdenModel();
        $this->resultados ??= new ResultadoModel();
    }

    /** @param array<string, string> $params */
    public function crear(Request $request, array $params): Response
    {
        $usuario = $this->asegurarRoles('medico');
        $citaId = $this->idParam($params);
        $body = $request->body();

        $consulta = $this->comoPropietario(
            fn (): array => $this->consultaService->crear($citaId, $body, $usuario)
        );

        return json_ok($consulta, [], 'Consulta registrada.', 201);
    }

    /** @param array<string, string> $params */
    public function mostrar(Request $request, array $params): Response
    {
        $this->asegurarRoles('medico', 'paciente');
        $id = $this->idParam($params);
        $consulta = $this->consultas->porId($id);

        if ($consulta === null) {
            throw new HttpException(404, 'Consulta no encontrada.', [], 'NOT_FOUND');
        }

        return json_ok($consulta);
    }

    /** @param array<string, string> $params */
    public function crearReceta(Request $request, array $params): Response
    {
        $usuario = $this->asegurarRoles('medico');
        $consultaId = $this->idParam($params);
        $consulta = $this->consultaDe($consultaId, $usuario);

        $detalle = $this->detalleReceta($request->input('detalle'));

        $id = $this->recetas->crear(
            $consultaId,
            (int) $consulta['paciente_id'],
            (int) $consulta['medico_id'],
            $detalle
        );

        return json_ok($this->recetas->porId($id), [], 'Receta registrada.', 201);
    }

    /** @param array<string, string> $params */
    public function mostrarReceta(Request $request, array $params): Response
    {
        $this->asegurarRoles('medico', 'paciente');
        $id = $this->idParam($params);
        $receta = $this->recetas->porId($id);

        if ($receta === null) {
            throw new HttpException(404, 'Receta no encontrada.', [], 'NOT_FOUND');
        }

        return json_ok($receta);
    }

    /** @param array<string, string> $params */
    public function imprimirReceta(Request $request, array $params): Response
    {
        $this->asegurarRoles('medico', 'paciente');
        $id = $this->idParam($params);
        $receta = $this->recetas->porId($id);

        if ($receta === null) {
            throw new HttpException(404, 'Receta no encontrada.', [], 'NOT_FOUND');
        }

        return $this->view('pedidos/receta', ['receta' => $receta], 'layouts/imprimible');
    }

    /** @param array<string, string> $params */
    public function crearDocumento(Request $request, array $params): Response
    {
        $usuario = $this->asegurarRoles('medico');
        $consultaId = $this->idParam($params);
        $consulta = $this->consultaDe($consultaId, $usuario);

        $tipo = $this->tipoDocumento($request->input('tipo'));
        $contenido = $this->textoOpcional($request->input('contenido'));

        if ($contenido !== null && mb_strlen($contenido) > 4000) {
            $this->validacion(['contenido' => 'No puede superar 4000 caracteres.']);
        }

        $id = $this->documentos->crear(
            $consultaId,
            (int) $consulta['paciente_id'],
            (int) $consulta['medico_id'],
            $tipo,
            $contenido
        );

        return json_ok($this->documentos->porId($id), [], 'Documento registrado.', 201);
    }

    /** @param array<string, string> $params */
    public function imprimirDocumento(Request $request, array $params): Response
    {
        $this->asegurarRoles('medico', 'paciente');
        $id = $this->idParam($params);
        $documento = $this->documentos->porId($id);

        if ($documento === null) {
            throw new HttpException(404, 'Documento no encontrado.', [], 'NOT_FOUND');
        }

        return $this->view('pedidos/documento', ['documento' => $documento], 'layouts/imprimible');
    }

    /** @param array<string, string> $params */
    public function crearOrden(Request $request, array $params): Response
    {
        $usuario = $this->asegurarRoles('medico');
        $consultaId = $this->idParam($params);
        $consulta = $this->consultaDe($consultaId, $usuario);

        $tipo = $this->tipoOrden($request->input('tipo'));
        $detalle = $this->detalleOrden($request->input('detalle'));

        $id = $this->ordenes->crear(
            $consultaId,
            (int) $consulta['paciente_id'],
            (int) $consulta['medico_id'],
            $tipo,
            $detalle
        );

        return json_ok($this->ordenes->porId($id), [], 'Orden registrada.', 201);
    }

    /** @param array<string, string> $params */
    public function imprimirOrden(Request $request, array $params): Response
    {
        $this->asegurarRoles('medico', 'paciente');
        $id = $this->idParam($params);
        $orden = $this->ordenes->porId($id);

        if ($orden === null) {
            throw new HttpException(404, 'Orden no encontrada.', [], 'NOT_FOUND');
        }

        return $this->view('pedidos/orden', ['orden' => $orden], 'layouts/imprimible');
    }

    /** @param array<string, string> $params */
    public function crearResultado(Request $request, array $params): Response
    {
        $usuario = $this->asegurarRoles('medico');
        $ordenId = $this->idParam($params);
        $orden = $this->ordenDe($ordenId, $usuario);

        $texto = $this->textoOpcional($request->input('texto'));

        if ($texto !== null && mb_strlen($texto) > 4000) {
            $this->validacion(['texto' => 'No puede superar 4000 caracteres.']);
        }

        $archivo = $request->file('archivo') ?? $request->file('resultado');
        $rutaArchivo = null;

        if (is_array($archivo) && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $rutaArchivo = $this->guardarArchivoResultado($ordenId, $archivo);
        }

        if ($texto === null && $rutaArchivo === null) {
            $this->validacion(['archivo' => 'Adjunta un archivo o escribe un resultado.']);
        }

        $revisado = $this->booleano($request->input('revisado')) ?? false;

        $this->resultados->crear($ordenId, $rutaArchivo, $texto, $revisado);

        $actualizada = $this->ordenes->porId($ordenId);

        return json_ok($actualizada, [], 'Resultado registrado.', 201);
    }

    /** @return array<string, mixed> */
    /**
     * Busca la consulta bypasseando RLS (para distinguir "no existe" de "no
     * es tuya") y verifica explícitamente que sea del médico autenticado.
     *
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    private function consultaDe(int $consultaId, array $usuario): array
    {
        $consulta = $this->comoPropietario(fn (): ?array => $this->consultas->porId($consultaId));

        if ($consulta === null) {
            throw new HttpException(404, 'Consulta no encontrada.', [], 'NOT_FOUND');
        }

        if ((int) $consulta['medico_id'] !== (int) ($usuario['medico_id'] ?? 0)) {
            throw new HttpException(403, 'No tienes permisos para esta consulta.', [], 'FORBIDDEN');
        }

        return $consulta;
    }

    /**
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    private function ordenDe(int $ordenId, array $usuario): array
    {
        $orden = $this->comoPropietario(fn (): ?array => $this->ordenes->porId($ordenId));

        if ($orden === null) {
            throw new HttpException(404, 'Orden no encontrada.', [], 'NOT_FOUND');
        }

        if ((int) $orden['medico_id'] !== (int) ($usuario['medico_id'] ?? 0)) {
            throw new HttpException(403, 'No tienes permisos para esta orden.', [], 'FORBIDDEN');
        }

        return $orden;
    }

    /** @return array<int, array<string, mixed>> */
    private function detalleReceta(mixed $valor): array
    {
        if (!is_array($valor) || $valor === []) {
            $this->validacion(['detalle' => 'Agregue al menos un medicamento.']);
        }

        $detalle = [];

        foreach ($valor as $item) {
            if (!is_array($item)) {
                $this->validacion(['detalle' => 'Revisa los medicamentos ingresados.']);
            }

            $medicamento = $this->limpiarTexto($item['medicamento'] ?? '');

            if ($medicamento === '' || mb_strlen($medicamento) > 150) {
                $this->validacion(['detalle' => 'Cada medicamento debe tener un nombre válido.']);
            }

            $detalle[] = [
                'medicamento' => $medicamento,
                'dosis' => $this->campoCorto($item['dosis'] ?? null, 80, 'detalle'),
                'via' => $this->campoCorto($item['via'] ?? null, 50, 'detalle'),
                'frecuencia' => $this->campoCorto($item['frecuencia'] ?? null, 80, 'detalle'),
                'duracion' => $this->campoCorto($item['duracion'] ?? null, 50, 'detalle'),
                'instrucciones' => $this->campoCorto($item['instrucciones'] ?? null, 200, 'detalle'),
            ];
        }

        return $detalle;
    }

    /** @return array<int, array<string, mixed>> */
    private function detalleOrden(mixed $valor): array
    {
        if (!is_array($valor) || $valor === []) {
            $this->validacion(['detalle' => 'Agregue al menos un estudio.']);
        }

        $detalle = [];

        foreach ($valor as $item) {
            if (!is_array($item)) {
                $this->validacion(['detalle' => 'Revisa los estudios ingresados.']);
            }

            $estudio = $this->limpiarTexto($item['estudio'] ?? '');

            if ($estudio === '' || mb_strlen($estudio) > 150) {
                $this->validacion(['detalle' => 'Cada estudio debe tener un nombre válido.']);
            }

            $detalle[] = [
                'estudio' => $estudio,
                'observaciones' => $this->campoCorto($item['observaciones'] ?? null, 200, 'detalle'),
            ];
        }

        return $detalle;
    }

    private function campoCorto(mixed $valor, int $maximo, string $campo): ?string
    {
        $texto = $this->textoOpcional($valor);

        if ($texto !== null && mb_strlen($texto) > $maximo) {
            $this->validacion([$campo => 'Revisa la longitud de los campos del detalle.']);
        }

        return $texto;
    }

    private function tipoDocumento(mixed $valor): string
    {
        $tipo = is_scalar($valor) ? strtolower(trim((string) $valor)) : '';

        if (!in_array($tipo, self::TIPOS_DOCUMENTO, true)) {
            $this->validacion(['tipo' => 'Usa certificado, informe, constancia o interconsulta.']);
        }

        return $tipo;
    }

    private function tipoOrden(mixed $valor): string
    {
        $tipo = is_scalar($valor) ? strtolower(trim((string) $valor)) : '';

        if (!in_array($tipo, self::TIPOS_ORDEN, true)) {
            $this->validacion(['tipo' => 'Usa laboratorio, imagen u otro.']);
        }

        return $tipo;
    }

    /** @param array<string, mixed> $archivo */
    private function guardarArchivoResultado(int $ordenId, array $archivo): string
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->validacion(['archivo' => 'No se pudo recibir el archivo.']);
        }

        $tamano = (int) ($archivo['size'] ?? 0);

        if ($tamano <= 0 || $tamano > 5_242_880) {
            $this->validacion(['archivo' => 'El archivo no puede superar 5 MB.']);
        }

        $tmp = (string) ($archivo['tmp_name'] ?? '');

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $this->validacion(['archivo' => 'No se pudo recibir el archivo.']);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? false : finfo_file($finfo, $tmp);

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (!is_string($mime) || !isset(self::TIPOS_RESULTADO[$mime])) {
            $this->validacion(['archivo' => 'Formato de archivo no permitido.']);
        }

        $extension = self::TIPOS_RESULTADO[$mime];
        $directorio = public_path('uploads/resultados');

        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            throw new HttpException(500, 'No se pudo preparar la carpeta de resultados.', [], 'STORAGE_ERROR');
        }

        $nombre = 'orden-' . $ordenId . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destino = $directorio . DIRECTORY_SEPARATOR . $nombre;

        if (!move_uploaded_file($tmp, $destino)) {
            throw new HttpException(500, 'No se pudo guardar el archivo.', [], 'STORAGE_ERROR');
        }

        return 'uploads/resultados/' . $nombre;
    }
}
