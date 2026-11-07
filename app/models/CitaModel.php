<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class CitaModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * Bloquea la fila del médico dentro de una transacción para serializar
     * reservas concurrentes del mismo profesional.
     */
    public function bloquearMedico(int $medicoId): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM medicos WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $medicoId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Citas activas (no canceladas) de un médico en una fecha.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activasDelDia(int $medicoId, string $fecha, ?int $excluirCitaId = null): array
    {
        $sql = "SELECT id, hora_inicio, hora_fin, origen, es_reconsulta
                FROM citas
                WHERE medico_id = :medico_id
                  AND fecha = :fecha
                  AND estado <> 'cancelada'";
        $parametros = [
            'medico_id' => $medicoId,
            'fecha' => $fecha,
        ];

        if ($excluirCitaId !== null && $excluirCitaId > 0) {
            $sql .= ' AND id <> :excluir_cita_id';
            $parametros['excluir_cita_id'] = $excluirCitaId;
        }

        $sql .= ' ORDER BY hora_inicio ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findRawById(int $id, bool $bloquear = false): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM citas WHERE id = :id'
            . ($bloquear ? ' FOR UPDATE' : '')
            . ' LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Crea una cita desde recepción, incluyendo reconsultas prioritarias.
     *
     * @param array{
     *     paciente_id: int,
     *     medico_id: int,
     *     especialidad_id: int,
     *     fecha: string,
     *     hora_inicio: string,
     *     hora_fin: string,
     *     motivo: ?string,
     *     es_prioritaria?: bool,
     *     es_reconsulta?: bool,
     *     reconsulta_tipo?: string|null,
     *     cita_origen_id?: int|null
     * } $datos
     * @return array<string, mixed>
     */
    public function crearRecepcion(array $datos): array
    {
        $origen = (string) ($datos['origen'] ?? 'recepcion');

        if (!in_array($origen, ['web', 'kiosco', 'recepcion'], true)) {
            $origen = 'recepcion';
        }

        $statement = $this->pdo->prepare(
            "INSERT INTO citas (
                 paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
                 estado, origen, es_prioritaria, es_reconsulta, reconsulta_tipo,
                 cita_origen_id, motivo
             ) VALUES (
                 :paciente_id, :medico_id, :especialidad_id, :fecha, :hora_inicio, :hora_fin,
                 'programada', CAST(:origen AS origen_cita), CAST(:es_prioritaria AS boolean),
                 CAST(:es_reconsulta AS boolean), CAST(:reconsulta_tipo AS tipo_reconsulta),
                 :cita_origen_id, :motivo
             )
             RETURNING id"
        );
        $statement->execute([
            'paciente_id' => $datos['paciente_id'],
            'medico_id' => $datos['medico_id'],
            'especialidad_id' => $datos['especialidad_id'],
            'fecha' => $datos['fecha'],
            'hora_inicio' => $datos['hora_inicio'],
            'hora_fin' => $datos['hora_fin'],
            'origen' => $origen,
            'es_prioritaria' => ($datos['es_prioritaria'] ?? false) ? 'true' : 'false',
            'es_reconsulta' => ($datos['es_reconsulta'] ?? false) ? 'true' : 'false',
            'reconsulta_tipo' => $datos['reconsulta_tipo'] ?? null,
            'cita_origen_id' => $datos['cita_origen_id'] ?? null,
            'motivo' => $datos['motivo'] ?? null,
        ]);

        return $this->findRawById((int) $statement->fetchColumn()) ?? [];
    }

    /**
     * Copia el estado de pago de una cita origen a la reprogramación: el
     * adelanto no se pierde, se transfiere a la nueva fecha.
     */
    public function aplicarCobroTransferido(int $id, array $origen): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE citas
             SET precio_total = :precio_total,
                 adelanto = :adelanto,
                 saldo = :saldo,
                 estado_pago = 'adelanto_pagado',
                 pago_adelanto_id = :pago_adelanto_id,
                 pago_expira_en = NULL
             WHERE id = :id"
        );
        $statement->execute([
            'id' => $id,
            'precio_total' => number_format((float) ($origen['precio_total'] ?? 0), 2, '.', ''),
            'adelanto' => number_format((float) ($origen['adelanto'] ?? 0), 2, '.', ''),
            'saldo' => number_format((float) ($origen['saldo'] ?? 0), 2, '.', ''),
            'pago_adelanto_id' => isset($origen['pago_adelanto_id']) && $origen['pago_adelanto_id'] !== null
                ? (int) $origen['pago_adelanto_id']
                : null,
        ]);
    }

    public function actualizarSlot(
        int $id,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?string $motivo = null
    ): void {
        $asignaciones = [
            'fecha = CAST(:fecha AS date)',
            'hora_inicio = CAST(:hora_inicio AS time)',
            'hora_fin = CAST(:hora_fin AS time)',
        ];
        $parametros = [
            'id' => $id,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
        ];

        if ($motivo !== null) {
            $asignaciones[] = 'motivo = :motivo';
            $parametros['motivo'] = $motivo;
        }

        $statement = $this->pdo->prepare(
            'UPDATE citas SET ' . implode(', ', $asignaciones) . ' WHERE id = :id'
        );
        $statement->execute($parametros);
    }

    /**
     * Fija el precio de la consulta y el adelanto de una cita recién creada.
     * El saldo se calcula aquí para que la cita siempre cuadre.
     */
    public function aplicarCobroInicial(
        int $id,
        float $precioTotal,
        float $adelanto,
        ?string $expiraEn = null
    ): void {
        $precioTotal = round(max(0.0, $precioTotal), 2);
        $adelanto = round(min($precioTotal, max(0.0, $adelanto)), 2);
        $saldo = round($precioTotal - $adelanto, 2);
        $cobra = $adelanto > 0;

        $statement = $this->pdo->prepare(
            "UPDATE citas
             SET precio_total = :precio_total,
                 adelanto = :adelanto,
                 saldo = :saldo,
                 estado_pago = CAST(:estado_pago AS estado_pago_cita),
                 pago_expira_en = CAST(:expira_en AS timestamptz)
             WHERE id = :id"
        );
        $statement->execute([
            'id' => $id,
            'precio_total' => number_format($precioTotal, 2, '.', ''),
            'adelanto' => number_format($adelanto, 2, '.', ''),
            'saldo' => number_format($saldo, 2, '.', ''),
            'estado_pago' => $cobra ? 'pendiente' : 'sin_pago',
            'expira_en' => $cobra ? $expiraEn : null,
        ]);
    }

    public function marcarAdelantoPagado(int $id, int $pagoId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE citas
             SET estado_pago = 'adelanto_pagado',
                 pago_adelanto_id = :pago_id,
                 pago_expira_en = NULL,
                 estado = CASE WHEN estado = 'programada' THEN 'confirmada' ELSE estado END
             WHERE id = :id"
        );
        $statement->execute(['id' => $id, 'pago_id' => $pagoId]);
    }

    public function marcarSaldoPagado(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE citas SET estado_pago = 'pagado', saldo = 0 WHERE id = :id"
        )->execute(['id' => $id]);
    }

    /**
     * Aplica un abono al saldo pendiente. Si el abono liquida la deuda, la
     * cita queda como pagada; si es parcial, conserva el estado y descuenta.
     */
    public function aplicarAbonoSaldo(int $id, float $monto): bool
    {
        $monto = number_format(max(0.0, round($monto, 2)), 2, '.', '');
        $statement = $this->pdo->prepare(
            "UPDATE citas
             SET saldo = GREATEST(0, ROUND(saldo - :monto_saldo, 2)),
                 estado_pago = CASE
                     WHEN ROUND(saldo - :monto_estado, 2) <= 0.01
                         THEN CAST('pagado' AS estado_pago_cita)
                     ELSE estado_pago
                 END
             WHERE id = :id
             RETURNING estado_pago"
        );
        $statement->execute([
            'id' => $id,
            'monto_saldo' => $monto,
            'monto_estado' => $monto,
        ]);

        return (string) $statement->fetchColumn() === 'pagado';
    }

    public function marcarTransferido(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE citas SET estado_pago = 'transferido', pago_expira_en = NULL WHERE id = :id"
        )->execute(['id' => $id]);
    }

    public function marcarPerdido(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE citas SET estado_pago = 'perdido', pago_expira_en = NULL WHERE id = :id"
        )->execute(['id' => $id]);
    }

    public function marcarReembolsoPendiente(int $id, string $codigo, float $monto): void
    {
        $this->pdo->prepare(
            "UPDATE citas
             SET estado_pago = 'reembolso_pendiente',
                 reembolso_codigo = :codigo,
                 reembolso_monto = :monto,
                 reembolso_solicitado_en = now(),
                 pago_expira_en = NULL
             WHERE id = :id"
        )->execute([
            'id' => $id,
            'codigo' => $codigo,
            'monto' => number_format(max(0.0, $monto), 2, '.', ''),
        ]);
    }

    public function marcarReembolsado(int $id, int $usuarioId): void
    {
        $this->pdo->prepare(
            "UPDATE citas
             SET estado_pago = 'reembolsado',
                 reembolso_entregado_en = now(),
                 reembolso_registrado_por = :usuario_id
             WHERE id = :id"
        )->execute(['id' => $id, 'usuario_id' => $usuarioId > 0 ? $usuarioId : null]);
    }

    public function marcarExpirado(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE citas SET estado_pago = 'expirado', pago_expira_en = NULL WHERE id = :id"
        )->execute(['id' => $id]);
    }

    /**
     * Citas con adelanto pendiente cuya ventana de pago ya venció.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendientesExpiradas(int $limite = 50): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, estado, paciente_id, medico_id
             FROM citas
             WHERE estado_pago = 'pendiente'
               AND pago_expira_en IS NOT NULL
               AND pago_expira_en < now()
               AND estado IN ('programada', 'confirmada')
             ORDER BY pago_expira_en ASC
             LIMIT " . max(1, min(500, $limite))
        );
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function reembolsosPendientes(): array
    {
        $statement = $this->pdo->query(
            "SELECT c.*, p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
                    p.ci AS paciente_ci, p.telefono AS paciente_telefono
             FROM citas c
             JOIN pacientes p ON p.id = c.paciente_id
             WHERE c.estado_pago = 'reembolso_pendiente'
             ORDER BY c.reembolso_solicitado_en ASC"
        );

        return $statement === false ? [] : $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function adelantosPendientes(): array
    {
        $statement = $this->pdo->query(
            "SELECT c.*, p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
                    p.ci AS paciente_ci, p.telefono AS paciente_telefono
             FROM citas c
             JOIN pacientes p ON p.id = c.paciente_id
             WHERE c.estado_pago = 'pendiente'
               AND c.estado IN ('programada', 'confirmada')
             ORDER BY c.fecha ASC, c.hora_inicio ASC"
        );

        return $statement === false ? [] : $statement->fetchAll();
    }

    /**
     * Citas con adelanto confirmado y saldo pendiente de cobro.
     *
     * @return array<int, array<string, mixed>>
     */
    public function saldosPendientes(): array
    {
        $statement = $this->pdo->query(
            "SELECT c.*, p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
                    p.ci AS paciente_ci, p.telefono AS paciente_telefono
             FROM citas c
             JOIN pacientes p ON p.id = c.paciente_id
             WHERE c.estado_pago = 'adelanto_pagado'
               AND c.saldo > 0
             ORDER BY c.fecha ASC, c.hora_inicio ASC"
        );

        return $statement === false ? [] : $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findRawByCodigo(string $codigo): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM citas WHERE codigo = :codigo LIMIT 1');
        $statement->execute(['codigo' => strtoupper(trim($codigo))]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function cambiarEstado(int $id, string $estado, ?string $motivoCancelacion = null): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE citas
             SET estado = CAST(:estado AS estado_cita),
                 motivo_cancelacion = :motivo_cancelacion
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'estado' => $estado,
            'motivo_cancelacion' => $motivoCancelacion,
        ]);
    }

    public function registrarHistorial(
        int $citaId,
        ?string $estadoAnterior,
        string $estadoNuevo,
        ?int $usuarioId = null,
        ?string $observacion = null
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO citas_estado_historial (
                 cita_id, estado_anterior, estado_nuevo, usuario_id, observacion
             ) VALUES (
                 :cita_id,
                 CAST(:estado_anterior AS estado_cita),
                 CAST(:estado_nuevo AS estado_cita),
                 :usuario_id,
                 :observacion
             )'
        );
        $statement->execute([
            'cita_id' => $citaId,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'usuario_id' => $usuarioId,
            'observacion' => $observacion,
        ]);
    }

    public function contarCanalEnFranja(
        int $medicoId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        string $canal
    ): int {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM citas
             WHERE medico_id = :medico_id
               AND fecha = :fecha
               AND estado <> 'cancelada'
               AND origen = CAST(:canal AS origen_cita)
               AND es_reconsulta = FALSE
               AND hora_inicio >= CAST(:hora_inicio AS time)
               AND hora_inicio < CAST(:hora_fin AS time)"
        );
        $statement->execute([
            'medico_id' => $medicoId,
            'fecha' => $fecha,
            'canal' => $canal,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, mixed>
     */
    public function crearWeb(
        int $pacienteId,
        int $medicoId,
        int $especialidadId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        string $motivo
    ): array {
        return $this->crearConOrigen(
            'web',
            $pacienteId,
            $medicoId,
            $especialidadId,
            $fecha,
            $horaInicio,
            $horaFin,
            $motivo
        );
    }

    /**
     * Reserva creada desde el kiosco (RFK-G).
     *
     * @return array<string, mixed>
     */
    public function crearKiosco(
        int $pacienteId,
        int $medicoId,
        int $especialidadId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        string $motivo
    ): array {
        return $this->crearConOrigen(
            'kiosco',
            $pacienteId,
            $medicoId,
            $especialidadId,
            $fecha,
            $horaInicio,
            $horaFin,
            $motivo
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function crearConOrigen(
        string $origen,
        int $pacienteId,
        int $medicoId,
        int $especialidadId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        string $motivo
    ): array {
        $statement = $this->pdo->prepare(
            "INSERT INTO citas (
                 paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
                 estado, origen, motivo
             ) VALUES (
                 :paciente_id, :medico_id, :especialidad_id, :fecha, :hora_inicio, :hora_fin,
                 'programada', CAST(:origen AS origen_cita), :motivo
             )
             RETURNING id, codigo, paciente_id, medico_id, especialidad_id, fecha,
                       hora_inicio, hora_fin, estado, origen, motivo,
                       precio_total, adelanto, saldo, estado_pago,
                       pago_adelanto_id, pago_expira_en, reembolso_codigo,
                       reembolso_monto, reembolso_solicitado_en"
        );
        $statement->execute([
            'paciente_id' => $pacienteId,
            'medico_id' => $medicoId,
            'especialidad_id' => $especialidadId,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'origen' => $origen,
            'motivo' => $motivo !== '' ? $motivo : null,
        ]);

        return $statement->fetch() ?: [];
    }

    /**
     * Listado administrativo de citas. El alcance por rol se aplica con
     * `medico_ids`/`paciente_id` desde el controlador.
     *
     * @param array<string, mixed> $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function listarAdmin(array $filtros, int $page, int $perPage): array
    {
        $parametros = [];
        $where = $this->whereAdmin($filtros, $parametros);
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM citas c
             JOIN pacientes p ON p.id = c.paciente_id
             JOIN medicos m ON m.id = c.medico_id
             JOIN especialidades e ON e.id = c.especialidad_id'
            . $whereSql
        );
        $count->execute($parametros);
        $total = (int) $count->fetchColumn();

        [$sort, $direction] = $this->ordenAdmin($filtros);
        $offset = ($page - 1) * $perPage;

        $sql = $this->selectAdminSql()
            . $whereSql
            . ' ORDER BY ' . $sort . ' ' . $direction
            . ', c.hora_inicio ' . $direction
            . ', c.id ' . $direction
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return [
            'data' => array_map([$this, 'normalizarCita'], $statement->fetchAll()),
            'total' => $total,
        ];
    }

    /**
     * Todas las citas que cumplen los filtros, sin paginar. Se usa para las
     * exportaciones CSV del módulo de reportes.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, array<string, mixed>>
     */
    public function exportarAdmin(array $filtros): array
    {
        $parametros = [];
        $where = $this->whereAdmin($filtros, $parametros);
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        [$sort, $direction] = $this->ordenAdmin($filtros);

        $sql = $this->selectAdminSql()
            . $whereSql
            . ' ORDER BY ' . $sort . ' ' . $direction
            . ', c.hora_inicio ' . $direction
            . ', c.id ' . $direction;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return array_map([$this, 'normalizarCita'], $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: string}
     */
    private function ordenAdmin(array $filtros): array
    {
        $sortMap = [
            'fecha' => 'c.fecha',
            'hora' => 'c.hora_inicio',
            'codigo' => 'c.codigo',
            'estado' => 'c.estado',
            'created_at' => 'c.created_at',
        ];
        $sort = $sortMap[(string) ($filtros['sort'] ?? 'fecha')] ?? 'c.fecha';
        $direction = strtoupper((string) ($filtros['direction'] ?? 'desc')) === 'ASC' ? 'ASC' : 'DESC';

        return [$sort, $direction];
    }

    /** @return array<string, mixed>|null */
    public function findAdminById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            $this->selectAdminSql() . ' WHERE c.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->normalizarCita($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function agenda(int $medicoId, string $desde, string $hasta, ?string $estado = null): array
    {
        $parametros = [];
        $where = $this->whereAdmin([
            'medico_id' => $medicoId,
            'desde' => $desde,
            'hasta' => $hasta,
            'estado' => $estado,
        ], $parametros);

        $statement = $this->pdo->prepare(
            $this->selectAdminSql()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY c.fecha ASC, c.hora_inicio ASC, c.id ASC'
        );
        $statement->execute($parametros);

        return array_map([$this, 'normalizarCita'], $statement->fetchAll());
    }

    /**
     * @return array{total: int, programadas: int, confirmadas: int, atendidas: int, canceladas: int, inasistencias: int}
     */
    public function contadoresAgenda(int $medicoId, string $desde, string $hasta): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE estado = 'programada') AS programadas,
                    COUNT(*) FILTER (WHERE estado = 'confirmada') AS confirmadas,
                    COUNT(*) FILTER (WHERE estado = 'atendida') AS atendidas,
                    COUNT(*) FILTER (WHERE estado = 'cancelada') AS canceladas,
                    COUNT(*) FILTER (WHERE estado = 'inasistencia') AS inasistencias
             FROM citas
             WHERE medico_id = :medico_id
               AND fecha BETWEEN CAST(:desde AS date) AND CAST(:hasta AS date)"
        );
        $statement->execute([
            'medico_id' => $medicoId,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);

        $row = $statement->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'programadas' => (int) ($row['programadas'] ?? 0),
            'confirmadas' => (int) ($row['confirmadas'] ?? 0),
            'atendidas' => (int) ($row['atendidas'] ?? 0),
            'canceladas' => (int) ($row['canceladas'] ?? 0),
            'inasistencias' => (int) ($row['inasistencias'] ?? 0),
        ];
    }

    /**
     * Historial de un paciente, incluyendo canceladas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historialPaciente(
        int $pacienteId,
        ?string $desde = null,
        ?string $hasta = null,
        ?string $estado = null
    ): array {
        $resultado = $this->listarAdmin([
            'paciente_id' => $pacienteId,
            'desde' => $desde,
            'hasta' => $hasta,
            'estado' => $estado,
            'sort' => 'fecha',
            'direction' => 'desc',
        ], 1, 200);

        return $resultado['data'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function historialEstados(int $citaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT h.id, h.cita_id, h.estado_anterior, h.estado_nuevo,
                    h.usuario_id, h.observacion, h.fecha,
                    u.username, u.email, u.rol
             FROM citas_estado_historial h
             LEFT JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.cita_id = :cita_id
             ORDER BY h.fecha DESC, h.id DESC'
        );
        $statement->execute(['cita_id' => $citaId]);

        return array_map([$this, 'normalizarHistorial'], $statement->fetchAll());
    }

    /**
     * Verifica si un paciente tiene alguna cita con alguno de los médicos
     * indicados. Se usa para autorizar el historial a médicos/recepción.
     *
     * @param array<int, int> $medicoIds
     */
    public function tieneCitaConPaciente(int $pacienteId, array $medicoIds): bool
    {
        $medicoIds = array_values(array_unique(array_filter(
            array_map('intval', $medicoIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($medicoIds === []) {
            return false;
        }

        $parametros = ['paciente_id' => $pacienteId];
        $placeholders = $this->placeholders($medicoIds, 'medico_cita', $parametros);

        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM citas
             WHERE paciente_id = :paciente_id
               AND medico_id IN (' . $placeholders . ')
             LIMIT 1'
        );
        $statement->execute($parametros);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $filtros
     * @param array<string, mixed> $parametros
     * @return array<int, string>
     */
    private function whereAdmin(array $filtros, array &$parametros): array
    {
        $where = [];

        if (($filtros['fecha'] ?? null) !== null) {
            $where[] = 'c.fecha = CAST(:fecha AS date)';
            $parametros['fecha'] = $filtros['fecha'];
        }

        if (($filtros['desde'] ?? null) !== null) {
            $where[] = 'c.fecha >= CAST(:desde AS date)';
            $parametros['desde'] = $filtros['desde'];
        }

        if (($filtros['hasta'] ?? null) !== null) {
            $where[] = 'c.fecha <= CAST(:hasta AS date)';
            $parametros['hasta'] = $filtros['hasta'];
        }

        if (($filtros['medico_id'] ?? null) !== null) {
            $where[] = 'c.medico_id = :medico_id';
            $parametros['medico_id'] = $filtros['medico_id'];
        }

        if (array_key_exists('medico_ids', $filtros) && $filtros['medico_ids'] !== null) {
            $ids = array_values(array_unique(array_filter(
                array_map('intval', (array) $filtros['medico_ids']),
                static fn (int $id): bool => $id > 0
            )));

            if ($ids === []) {
                $where[] = '1 = 0';
            } else {
                $placeholders = $this->placeholders($ids, 'scope_medico', $parametros);
                $where[] = 'c.medico_id IN (' . $placeholders . ')';
            }
        }

        if (($filtros['paciente_id'] ?? null) !== null) {
            $where[] = 'c.paciente_id = :paciente_id';
            $parametros['paciente_id'] = $filtros['paciente_id'];
        }

        if (($filtros['especialidad_id'] ?? null) !== null) {
            $where[] = 'c.especialidad_id = :especialidad_id';
            $parametros['especialidad_id'] = $filtros['especialidad_id'];
        }

        if (($filtros['estado'] ?? null) !== null) {
            $where[] = 'c.estado = CAST(:estado AS estado_cita)';
            $parametros['estado'] = $filtros['estado'];
        }

        if (($filtros['origen'] ?? null) !== null) {
            $where[] = 'c.origen = CAST(:origen AS origen_cita)';
            $parametros['origen'] = $filtros['origen'];
        }

        if (($filtros['q'] ?? '') !== '') {
            $where[] = '(c.codigo ILIKE :q
                         OR p.ci ILIKE :q
                         OR p.nombres ILIKE :q
                         OR p.apellidos ILIKE :q
                         OR m.nombres ILIKE :q
                         OR m.apellidos ILIKE :q)';
            $parametros['q'] = '%' . $filtros['q'] . '%';
        }

        return $where;
    }

    /** @param array<string, mixed> $parametros @param array<int, int> $ids */
    private function placeholders(array $ids, string $prefix, array &$parametros): string
    {
        $placeholders = [];

        foreach (array_values($ids) as $index => $id) {
            $name = $prefix . '_' . $index;
            $placeholders[] = ':' . $name;
            $parametros[$name] = $id;
        }

        return implode(', ', $placeholders);
    }

    private function selectAdminSql(): string
    {
        return "SELECT c.id, c.codigo, c.paciente_id, c.medico_id, c.especialidad_id,
                       c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.origen,
                       c.es_prioritaria, c.es_reconsulta, c.reconsulta_tipo,
                       c.cita_origen_id, c.motivo, c.motivo_cancelacion,
                       c.fecha_registro, c.created_at, c.updated_at,
                       c.precio_total, c.adelanto, c.saldo, c.estado_pago,
                       c.pago_adelanto_id, c.pago_expira_en,
                       c.reembolso_codigo, c.reembolso_monto,
                       c.reembolso_solicitado_en, c.reembolso_entregado_en,
                       p.codigo AS paciente_codigo, p.ci AS paciente_ci,
                       p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
                       p.telefono AS paciente_telefono, p.email AS paciente_email,
                       m.codigo AS medico_codigo, m.nombres AS medico_nombres,
                       m.apellidos AS medico_apellidos,
                       e.nombre AS especialidad_nombre
                FROM citas c
                JOIN pacientes p ON p.id = c.paciente_id
                JOIN medicos m ON m.id = c.medico_id
                JOIN especialidades e ON e.id = c.especialidad_id";
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizarCita(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['paciente_id'] = (int) $row['paciente_id'];
        $row['medico_id'] = (int) $row['medico_id'];
        $row['especialidad_id'] = (int) $row['especialidad_id'];
        $row['hora_inicio'] = substr((string) $row['hora_inicio'], 0, 5);
        $row['hora_fin'] = substr((string) $row['hora_fin'], 0, 5);
        $row['es_prioritaria'] = (bool) $row['es_prioritaria'];
        $row['es_reconsulta'] = (bool) $row['es_reconsulta'];
        $row['cita_origen_id'] = $row['cita_origen_id'] === null
            ? null
            : (int) $row['cita_origen_id'];
        $row['precio_total'] = (float) ($row['precio_total'] ?? 0);
        $row['adelanto'] = (float) ($row['adelanto'] ?? 0);
        $row['saldo'] = (float) ($row['saldo'] ?? 0);
        $row['pago_adelanto_id'] = isset($row['pago_adelanto_id']) && $row['pago_adelanto_id'] !== null
            ? (int) $row['pago_adelanto_id']
            : null;
        $row['reembolso_monto'] = (float) ($row['reembolso_monto'] ?? 0);
        $row['pago'] = [
            'precio_total' => $row['precio_total'],
            'adelanto' => $row['adelanto'],
            'saldo' => $row['saldo'],
            'estado' => (string) ($row['estado_pago'] ?? 'pendiente'),
            'pago_adelanto_id' => $row['pago_adelanto_id'],
            'expira_en' => $row['pago_expira_en'] ?? null,
            'reembolso_codigo' => $row['reembolso_codigo'] ?? null,
            'reembolso_monto' => $row['reembolso_monto'],
            'reembolso_solicitado_en' => $row['reembolso_solicitado_en'] ?? null,
            'reembolso_entregado_en' => $row['reembolso_entregado_en'] ?? null,
        ];

        $pacienteNombre = trim((string) $row['paciente_nombres'] . ' ' . (string) $row['paciente_apellidos']);
        $medicoNombre = trim((string) $row['medico_nombres'] . ' ' . (string) $row['medico_apellidos']);

        $row['paciente'] = [
            'id' => $row['paciente_id'],
            'codigo' => (string) $row['paciente_codigo'],
            'ci' => (string) $row['paciente_ci'],
            'nombres' => (string) $row['paciente_nombres'],
            'apellidos' => (string) $row['paciente_apellidos'],
            'nombre_completo' => $pacienteNombre,
            'telefono' => $row['paciente_telefono'],
            'email' => $row['paciente_email'],
        ];
        $row['medico'] = [
            'id' => $row['medico_id'],
            'codigo' => (string) $row['medico_codigo'],
            'nombres' => (string) $row['medico_nombres'],
            'apellidos' => (string) $row['medico_apellidos'],
            'nombre_completo' => $medicoNombre !== '' ? 'Dr. ' . $medicoNombre : '',
        ];
        $row['especialidad'] = [
            'id' => $row['especialidad_id'],
            'nombre' => (string) $row['especialidad_nombre'],
        ];

        unset(
            $row['paciente_codigo'],
            $row['paciente_ci'],
            $row['paciente_nombres'],
            $row['paciente_apellidos'],
            $row['paciente_telefono'],
            $row['paciente_email'],
            $row['medico_codigo'],
            $row['medico_nombres'],
            $row['medico_apellidos'],
            $row['especialidad_nombre']
        );

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizarHistorial(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['cita_id'] = (int) $row['cita_id'];
        $row['usuario_id'] = $row['usuario_id'] === null ? null : (int) $row['usuario_id'];
        $row['usuario'] = $row['usuario_id'] === null
            ? null
            : [
                'id' => $row['usuario_id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'rol' => $row['rol'],
            ];

        unset($row['username'], $row['email'], $row['rol']);

        return $row;
    }
}
