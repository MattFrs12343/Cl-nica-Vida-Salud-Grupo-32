<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\EspecialidadModel;
use App\Models\MedicoModel;

final class PublicController extends Controller
{
    public function __construct(
        private ?EspecialidadModel $especialidades = null,
        private ?MedicoModel $medicos = null
    ) {
        $this->especialidades ??= new EspecialidadModel();
        $this->medicos ??= new MedicoModel();
    }

    public function index(Request $request): Response
    {
        return Response::html(View::render('landing/index', [
            'title' => 'Clínica Vida Salud — Agenda tu cita médica',
            'description' => 'Especialidades, médicos y reserva de citas online de la Clínica Vida Salud. Atención cercana y profesional.',
            'canonical' => url('/'),
            'bodyClass' => 'landing',
            'especialidades' => $this->especialidades->listActivas(),
            'medicos' => $this->medicos->listActivos(),
        ], 'layouts/base'));
    }

    public function reservar(Request $request): Response
    {
        $usuario = auth();

        if ($usuario === null) {
            flash('error', 'Inicia sesión como paciente para reservar tu cita.');

            return redirect('/login');
        }

        if ((string) ($usuario['rol'] ?? '') !== 'paciente') {
            flash('error', 'Solo los pacientes pueden reservar desde la landing.');
            return redirect(role_home((string) $usuario['rol']));
        }

        return Response::html(View::render('landing/reservar', [
            'title' => 'Reservar cita — Clínica Vida Salud',
            'description' => 'Elige especialidad, médico, fecha y horario para tu cita.',
            'canonical' => url('/reservar'),
            'bodyClass' => 'landing landing-reserva',
            'especialidades' => $this->especialidades->listActivas(),
            'usuario' => $usuario,
        ], 'layouts/base'));
    }

    public function kiosco(Request $request): Response
    {
        return Response::html(View::render('landing/kiosco', [
            'title' => 'Kiosco de fichas — Clínica Vida Salud',
            'description' => 'Conoce cómo sacar tu ficha de atención en el kiosco presencial de la clínica.',
            'canonical' => url('/kiosco'),
            'bodyClass' => 'landing landing-kiosco',
        ], 'layouts/base'));
    }
}
