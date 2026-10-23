<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\KioskTokenService;

final class KioscoApiController extends Controller
{
    public function __construct(private ?KioskTokenService $tokens = null)
    {
        $this->tokens ??= new KioskTokenService();
    }

    public function sesion(Request $request): Response
    {
        $deviceKey = $request->body()['device_key'] ?? null;

        if (!is_string($deviceKey) || trim($deviceKey) === '') {
            throw new HttpException(
                422,
                'Revisa los datos enviados.',
                [],
                'VALIDATION_ERROR',
                ['device_key' => 'Ingresa la clave del dispositivo.']
            );
        }

        if (!$this->tokens->deviceKeyValid(trim($deviceKey))) {
            throw new HttpException(
                401,
                'La clave del dispositivo no es válida.',
                [],
                'UNAUTHENTICATED'
            );
        }

        return json_ok($this->tokens->issue())
            ->withHeader('Cache-Control', 'no-store');
    }
}
