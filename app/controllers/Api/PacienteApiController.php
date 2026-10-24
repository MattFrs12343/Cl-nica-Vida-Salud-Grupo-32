<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\CitaModel;
use App\Models\PacienteModel;
use App\Services\PacienteService;

final class PacienteApiController extends AdminApiController
{
    private const ESTADOS = ['activo', 'inactivo'];

    public function __construct(
        private ?PacienteService $pacientes = null,
        private ?PacienteModel $modelo = null,
        private ?CitaModel $citas = null
    ) {
        $this->pacientes ??= new PacienteService();
        $this->modelo ??= new PacienteModel();
        $this->citas ??= new CitaModel();
    }

    public function index(Request $request): Response
    {
        return $this->listar($request);
    }

    public function buscar(Request $request): Response
    {
        return $this->listar($request);
    }

    private function listar(Request $request): Response
    {
        $usuario = $this->usuarioActual();
        $estado = strtolower($this->queryText($request, 'estado'));

        if ($estado !== '' && !in_array($estado, self::ESTADOS, true)) {
            $this->validacion(['estado' => 'Usa activo o inactivo.']);
        }

        $paginacion = $this->pagination($request);
        $medicoIds = $this->medicoIdsDe($usuario);

        $resultado = $this->comoPropietario(
            fn (): array => $this->pacientes->listar([
                'q' => $this->queryText($request, 'q'),
                'estado' => $estado === '' ? null : $estado,
            ], $paginacion['page'], $paginacion['per_page'], $medicoIds)
        );

        return $this->listaJson(
            $resultado['data'],
            $resultado['total'],
            $paginacion['page'],
            $paginacion['per_page']
        );
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $paciente = $this->comoPropietario(fn (): ?array => $this->pacientes->obtener($id));

        if ($paciente === null) {
            throw new HttpException(404, 'Paciente no encontrado.', [], 'NOT_FOUND');
        }

        $this->autorizarPaciente($paciente);
        $paciente['citas'] = $this->comoPropietario(
            fn (): int => $this->modelo->contarCitas((int) $paciente['id'])
        );

        return json_ok($paciente);
    }

    public function store(Request $request): Response
    {
        $this->asegurarRoles('super_admin', 'admin', 'recepcionista', 'recepcionista_medico');

        $paciente = $this->comoPropietario(
            fn (): array => $this->pacientes->crear($request->body())
        );

        return json_ok($paciente, [], 'Paciente registrado.', 201);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): Response
    {
        $this->asegurarRoles('super_admin', 'admin', 'recepcionista', 'recepcionista_medico');

        $id = $this->idParam($params);
        $existente = $this->comoPropietario(fn (): ?array => $this->pacientes->obtener($id));

        if ($existente === null) {
            throw new HttpException(404, 'Paciente no encontrado.', [], 'NOT_FOUND');
        }

        $this->autorizarPaciente($existente, true);

        $paciente = $this->comoPropietario(
            fn (): array => $this->pacientes->actualizar($id, $request->body())
        );

        return json_ok($paciente, [], 'Paciente actualizado.');
    }

    /** @param array<string, string> $params */
    public function estado(Request $request, array $params): Response
    {
        $this->asegurarRoles('super_admin', 'admin');

        $id = $this->idParam($params);
        $estado = strtolower($this->limpiarTexto($request->input('estado', '')));
        $activo = $this->booleano($request->input('activo'));

        if ($estado === '' && $activo !== null) {
            $estado = $activo ? 'activo' : 'inactivo';
        }

        if (!in_array($estado, self::ESTADOS, true)) {
            $this->validacion(['estado' => 'Usa activo o inactivo.']);
        }

        $resultado = $this->comoPropietario(
            fn (): array => $this->pacientes->cambiarEstado($id, $estado)
        );

        $meta = ['citas_futuras' => $resultado['citas_futuras']];
        $mensaje = $estado === 'activo' ? 'Paciente activado.' : 'Paciente desactivado.';

        if ($estado === 'inactivo' && $resultado['citas_futuras'] > 0) {
            $mensaje .= ' Conserva ' . $resultado['citas_futuras']
                . ' cita(s) futura(s); no se podrán agendar nuevas mientras esté inactivo.';
        }

        return json_ok($resultado['paciente'], $meta, $mensaje);
    }

    public function perfil(Request $request): Response
    {
        $paciente = $this->pacienteDeSesion();

        return json_ok($paciente);
    }

    public function actualizarPerfil(Request $request): Response
    {
        $paciente = $this->pacienteDeSesion();
        $actualizado = $this->comoPropietario(
            fn (): array => $this->pacientes->actualizarPerfil((int) $paciente['id'], $request->body())
        );

        return json_ok($actualizado, [], 'Perfil actualizado.');
    }

    /**
     * @param array<string, mixed> $usuario
     * @return array<int, int>|null Null cuando el rol no tiene restricción.
     */
    private function medicoIdsDe(array $usuario): ?array
    {
        $rol = (string) ($usuario['rol'] ?? '');

        if (in_array($rol, ['super_admin', 'admin', 'recepcionista'], true)) {
            return null;
        }

        if ($rol === 'medico') {
            $medicoId = (int) ($usuario['medico_id'] ?? 0);

            return $medicoId > 0 ? [$medicoId] : [];
        }

        if ($rol === 'recepcionista_medico') {
            return array_values(array_unique(array_filter(
                array_map('intval', (array) ($usuario['medico_ids'] ?? [])),
                static fn (int $id): bool => $id > 0
            )));
        }

        throw new HttpException(403, 'No tienes permisos para esta sección.', [], 'FORBIDDEN');
    }

    /**
     * @param array<string, mixed> $paciente
     */
    private function autorizarPaciente(array $paciente, bool $escritura = false): void
    {
        $usuario = $this->usuarioActual();
        $rol = (string) ($usuario['rol'] ?? '');

        if (in_array($rol, ['super_admin', 'admin', 'recepcionista'], true)) {
            return;
        }

        if ($rol === 'paciente'
            && !$escritura
            && (int) ($paciente['id'] ?? 0) === (int) ($usuario['paciente_id'] ?? 0)) {
            return;
        }

        $medicoIds = $this->medicoIdsDe($usuario);

        if ($medicoIds !== null && $medicoIds !== []) {
            $tieneCita = $this->comoPropietario(
                fn (): bool => $this->citas->tieneCitaConPaciente((int) $paciente['id'], $medicoIds)
            );

            if ($tieneCita) {
                return;
            }
        }

        throw new HttpException(403, 'No tienes permisos para este paciente.', [], 'FORBIDDEN');
    }

    /** @return array<string, mixed> */
    private function pacienteDeSesion(): array
    {
        $usuario = $this->usuarioActual();

        if ((string) ($usuario['rol'] ?? '') !== 'paciente') {
            throw new HttpException(403, 'No tienes permisos para esta sección.', [], 'FORBIDDEN');
        }

        $paciente = $this->comoPropietario(
            fn (): ?array => $this->modelo->findByUsuarioId((int) ($usuario['id'] ?? 0))
        );

        if ($paciente === null) {
            throw new HttpException(
                404,
                'La cuenta no tiene un perfil de paciente asociado.',
                [],
                'NOT_FOUND'
            );
        }

        return $this->pacientes->normalizar($paciente);
    }
}
