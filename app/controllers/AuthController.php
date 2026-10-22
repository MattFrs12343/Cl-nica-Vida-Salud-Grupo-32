<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function __construct(private ?AuthService $auth = null)
    {
        $this->auth ??= new AuthService();
    }

    public function showLogin(Request $request): Response
    {
        return $this->authView('auth/login', 'Iniciar sesión');
    }

    public function showRecuperar(Request $request): Response
    {
        return $this->authView('auth/recuperar', 'Recuperar contraseña');
    }

    public function showReset(Request $request): Response
    {
        return $this->authView('auth/reset', 'Nueva contraseña', [
            'token' => (string) $request->query('token', ''),
        ]);
    }

    public function showActivar(Request $request): Response
    {
        return $this->authView('auth/activar', 'Activar cuenta');
    }

    public function login(Request $request): Response
    {
        $identificador = (string) $request->input('identificador', '');
        $password = (string) $request->input('password', '');

        try {
            $usuario = $this->auth->login($identificador, $password);
        } catch (HttpException $exception) {
            return $this->respondError($request, $exception, '/login');
        }

        Session::regenerate();
        Session::rotateCsrf();
        Session::set('usuario', $usuario);

        try {
            DB::setContext($usuario, $request->ip());
        } catch (\Throwable) {
            // El contexto se reintenta en el siguiente request; no bloquea el login.
        }

        $redirect = role_home((string) $usuario['rol']);

        if ($request->wantsJson()) {
            return json_ok([
                'usuario' => $usuario,
                'redirect' => $redirect,
            ]);
        }

        flash('success', 'Bienvenido/a, ' . $usuario['nombre'] . '.');

        return redirect($redirect);
    }

    public function logout(Request $request): Response
    {
        Session::destroy();

        try {
            DB::clearContext();
        } catch (\Throwable) {
            // La conexión se cierra al terminar el request.
        }

        if ($request->wantsJson()) {
            return json_ok([], [], 'Sesión cerrada.');
        }

        return redirect('/login');
    }

    public function recuperar(Request $request): Response
    {
        $this->auth->solicitarReset((string) $request->input('email', ''));

        $mensaje = 'Si el correo existe, recibirás instrucciones.';

        if ($request->wantsJson()) {
            return json_ok([], [], $mensaje);
        }

        flash('success', $mensaje);

        return redirect('/login');
    }

    public function reset(Request $request): Response
    {
        try {
            $this->auth->resetPassword(
                (string) $request->input('token', ''),
                (string) $request->input('password', ''),
                (string) $request->input('password_confirmation', '')
            );
        } catch (HttpException $exception) {
            return $this->respondError($request, $exception, '/reset');
        }

        $mensaje = 'Contraseña actualizada. Ya puedes iniciar sesión.';

        if ($request->wantsJson()) {
            return json_ok(['redirect' => '/login'], [], $mensaje);
        }

        flash('success', $mensaje);

        return redirect('/login');
    }

    public function activar(Request $request): Response
    {
        try {
            $data = $this->auth->activarPaciente(
                (string) $request->input('ci', ''),
                (string) $request->input('telefono', ''),
                (string) $request->input('password', ''),
                (string) $request->input('password_confirmation', ''),
                (string) $request->input('email', '')
            );
        } catch (HttpException $exception) {
            return $this->respondError($request, $exception, '/activar');
        }

        $mensaje = 'Cuenta activada. Ya puedes iniciar sesión.';

        if ($request->wantsJson()) {
            return json_ok($data, [], $mensaje);
        }

        flash('success', $mensaje);

        return redirect('/login');
    }

    /** @param array<string, mixed> $data */
    private function authView(string $view, string $title, array $data = []): Response
    {
        if (!is_file(app_path('views/' . $view . '.php'))) {
            return json_error(
                'VIEW_NOT_AVAILABLE',
                'La vista ' . $view . ' aún no está disponible.',
                [],
                501
            );
        }

        return Response::html(View::render($view, array_merge([
            'title' => $title,
            'csrfToken' => csrf_token(),
            'csrf_token' => csrf_token(),
        ], $data)));
    }

    private function respondError(Request $request, HttpException $exception, string $fallback): Response
    {
        if ($request->wantsJson()) {
            return json_error(
                $exception->errorCode() ?? 'ERROR',
                $exception->getMessage(),
                $exception->fields(),
                $exception->statusCode()
            );
        }

        Session::setOld($request->body());
        flash('error', $exception->getMessage());

        return redirect($fallback);
    }
}
