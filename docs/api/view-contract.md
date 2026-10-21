# Contrato de vistas y sesión — Paneles de rol

> **Estado:** congelado para la Fase 0 (2026-10-21).
> **Dueño de las vistas:** Lucas.
> **Dueño del backend, sesión y helpers:** Matias.
> Este documento es la frontera entre ambos. Si algo no está aquí, se acuerda antes de implementarlo.

---

## 1. Sesión

El backend expone el usuario autenticado en `$_SESSION['usuario']` con esta forma:

```php
$_SESSION['usuario'] = [
    'id'          => 12,
    'rol'         => 'medico',      // super_admin|admin|recepcionista|recepcionista_medico|medico|paciente
    'nombre'      => 'Dra. Ana Pérez',
    'medico_id'   => 3,             // solo medico
    'medico_ids'  => [3, 5],        // solo recepcionista_medico
    'paciente_id' => null,          // solo paciente
];
```

Reglas:

- El backend redirige después del login según el rol.
- Un usuario sin sesión que entra a un panel recibe `302 → /login`.
- Un usuario con sesión pero sin permiso recibe `403`.
- El backend renueva el ID de sesión al iniciar sesión y rota el token CSRF.

Redirecciones por rol:

| Rol | Destino |
|---|---|
| `super_admin`, `admin` | `/admin` |
| `recepcionista`, `recepcionista_medico` | `/recepcion` |
| `medico` | `/medico` |
| `paciente` | `/portal` |

---

## 2. Helpers disponibles

Todos los helpers son globales y están cargados por `app/autoload.php`.

| Helper | Firma | Uso |
|---|---|---|
| `e()` | `e(mixed $value): string` | Escapa output. **Obligatorio** en toda variable. |
| `auth()` | `auth(): ?array` | Usuario de sesión o `null`. |
| `has_role()` | `has_role(string ...$roles): bool` | Verifica rol actual. |
| `csrf_token()` | `csrf_token(): string` | Token para meta tag o fetch. |
| `csrf_field()` | `csrf_field(): string` | Input oculto `_token`. |
| `csrf_verify()` | `csrf_verify(?string $token = null): bool` | Validación manual. |
| `flash()` | `flash(string $type, string $message): void` | Guarda mensaje para la siguiente pantalla. |
| `flashes()` | `flashes(): array` | Devuelve y limpia mensajes. |
| `old()` | `old(string $key, mixed $default = null): mixed` | Repoblar formularios. |
| `redirect()` | `redirect(string $url, int $status = 302): Response` | Redirección. |
| `url()` | `url(string $path = ''): string` | URL absoluta de la app. |
| `asset()` | `asset(string $path): string` | URL de `public/assets/`. |
| `view()` | `view(string $view, array $data = [], ?string $layout = null): string` | Renderiza una vista. |
| `json_ok()` | `json_ok(mixed $data = [], array $meta = [], ?string $message = null): Response` | Respuesta API de éxito. |
| `json_error()` | `json_error(string $code, string $message, array $fields = [], int $status = 422): Response` | Respuesta API de error. |
| `abort()` | `abort(int $status, string $message = ''): never` | Lanza error HTTP controlado. |

### 2.1 Uso de CSRF

En el layout:

```php
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
```

En un formulario HTML:

```php
<form method="post" action="<?= e(url('/api/pacientes')) ?>">
    <?= csrf_field() ?>
    ...
</form>
```

En `fetch`:

```js
const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const response = await fetch('/api/pacientes', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': token,
    },
    body: JSON.stringify(payload),
});
```

---

## 3. Contrato del layout

`app/views/layouts/gentelella.php` recibe:

| Variable | Tipo | Contenido |
|---|---|---|
| `$title` | `string` | Título de la pestaña. |
| `$active` | `string` | Clave del ítem activo del menú. |
| `$breadcrumbs` | `array<int, array{label: string, url?: string}>` | Migas de pan. |
| `$user` | `array` | Usuario de sesión. |
| `$csrfToken` | `string` | Token CSRF. |
| `$flashes` | `array<int, array{type: string, message: string}>` | Mensajes pendientes. |
| `$content` | `string` | HTML de la vista hija. |

Ejemplo de render desde `PanelController`:

```php
return $this->view('admin/usuarios/index', [
    'title' => 'Usuarios',
    'active' => 'usuarios',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => url('/admin')],
        ['label' => 'Usuarios'],
    ],
], 'layouts/gentelella');
```

El layout debe:

- Imprimir `$title`, `$breadcrumbs`, `$user['nombre']` y `$user['rol']`.
- Incluir `csrfToken` en un meta tag.
- Renderizar los flashes y limpiarlos.
- Mostrar el menú correspondiente al rol.
- Cargar los assets del panel con `asset('panel/...')`.
- Incluir el bloque `<?= $content ?>`.

---

## 4. Menú por rol

El menú se define en `app/views/layouts/menu.php` (propiedad de Lucas). Estas son las entradas acordadas:

### `super_admin` / `admin`

| Clave | Etiqueta | Ruta |
|---|---|---|
| `dashboard` | Dashboard | `/admin` |
| `usuarios` | Usuarios | `/admin/usuarios` |
| `medicos` | Médicos | `/admin/medicos` |
| `especialidades` | Especialidades | `/admin/especialidades` |
| `horarios` | Horarios | `/admin/horarios` |
| `citas` | Citas | `/admin/citas` |
| `reportes` | Reportes | `/admin/reportes` |
| `auditoria` | Auditoría | `/admin/auditoria` |

### `recepcionista` / `recepcionista_medico`

| Clave | Etiqueta | Ruta |
|---|---|---|
| `dashboard` | Dashboard | `/recepcion` |
| `pacientes` | Pacientes | `/recepcion/pacientes` |
| `citas` | Citas | `/recepcion/citas` |
| `agenda` | Agenda | `/recepcion/agenda` |
| `reconsultas` | Reconsultas | `/recepcion/reconsultas` |
| `fichas` | Fichas | `/recepcion/fichas` |
| `pagos` | Pagos | `/recepcion/pagos` |

`recepcionista_medico` ve el mismo menú, pero el backend/RLS limita los datos a sus médicos asignados. El layout puede mostrar un badge "Mis médicos" si `medico_ids` está presente.

### `medico`

| Clave | Etiqueta | Ruta |
|---|---|---|
| `dashboard` | Dashboard | `/medico` |
| `agenda` | Agenda | `/medico/agenda` |
| `pacientes` | Pacientes | `/medico/pacientes` |
| `consultas` | Consultas | `/medico/consultas` |
| `historia` | Historia clínica | `/medico/historia` |
| `pedidos` | Recetas y órdenes | `/medico/pedidos` |
| `pagos` | Pagos | `/medico/pagos` |
| `qr` | Mi QR | `/medico/qr` |

### `paciente`

| Clave | Etiqueta | Ruta |
|---|---|---|
| `portal` | Inicio | `/portal` |
| `citas` | Mis citas | `/portal/mis-citas` |
| `historial` | Historial | `/portal/historial` |
| `perfil` | Mi perfil | `/portal/perfil` |

---

## 5. Rutas y vistas

| Ruta | Vista | Controlador |
|---|---|---|
| `/admin` | `admin/dashboard.php` | `PanelController@adminDashboard` |
| `/admin/usuarios` | `admin/usuarios/index.php` | `PanelController@adminUsuarios` |
| `/admin/medicos` | `admin/medicos/index.php` | `PanelController@adminMedicos` |
| `/admin/especialidades` | `admin/especialidades/index.php` | `PanelController@adminEspecialidades` |
| `/admin/horarios` | `admin/horarios/index.php` | `PanelController@adminHorarios` |
| `/admin/citas` | `admin/citas/index.php` | `PanelController@adminCitas` |
| `/admin/reportes` | `admin/reportes.php` | `PanelController@adminReportes` |
| `/admin/auditoria` | `admin/auditoria.php` | `PanelController@adminAuditoria` |
| `/recepcion` | `recepcion/dashboard.php` | `PanelController@recepcionDashboard` |
| `/recepcion/pacientes` | `recepcion/pacientes/index.php` | `PanelController@recepcionPacientes` |
| `/recepcion/citas` | `recepcion/citas/index.php` | `PanelController@recepcionCitas` |
| `/recepcion/agenda` | `recepcion/agenda.php` | `PanelController@recepcionAgenda` |
| `/recepcion/reconsultas` | `recepcion/reconsultas.php` | `PanelController@recepcionReconsultas` |
| `/recepcion/fichas` | `recepcion/fichas.php` | `PanelController@recepcionFichas` |
| `/recepcion/pagos` | `recepcion/pagos.php` | `PanelController@recepcionPagos` |
| `/medico` | `medico/dashboard.php` | `PanelController@medicoDashboard` |
| `/medico/agenda` | `medico/agenda.php` | `PanelController@medicoAgenda` |
| `/medico/pacientes` | `medico/pacientes/index.php` | `PanelController@medicoPacientes` |
| `/medico/consultas` | `medico/consultas/index.php` | `PanelController@medicoConsultas` |
| `/medico/historia` | `medico/historia.php` | `PanelController@medicoHistoria` |
| `/medico/pedidos` | `medico/pedidos.php` | `PanelController@medicoPedidos` |
| `/medico/pagos` | `medico/pagos.php` | `PanelController@medicoPagos` |
| `/medico/qr` | `medico/qr.php` | `PanelController@medicoQr` |
| `/portal` | `paciente/portal.php` | `PanelController@portalDashboard` |
| `/portal/mis-citas` | `paciente/mis-citas.php` | `PanelController@portalCitas` |
| `/portal/historial` | `paciente/historial.php` | `PanelController@portalHistorial` |
| `/portal/perfil` | `paciente/perfil.php` | `PanelController@portalPerfil` |

Los nombres de métodos son una propuesta; Lucas puede ajustarlos mientras mantenga las rutas y las vistas.

---

## 6. Responsabilidades de `PanelController`

**Sí puede:**

- Resolver la ruta de panel y renderizar la vista.
- Verificar sesión/rol con `has_role()` si el middleware no lo hizo.
- Pasar `title`, `active`, `breadcrumbs` y datos de sesión al layout.
- Consumir servicios de Matias si necesita datos iniciales para el HTML.
- Devolver `Response::html(View::render(...))`.

**No puede:**

- Escribir SQL ni consultas directas.
- Implementar reglas de negocio (cupos, estados, pagos).
- Crear endpoints de API.
- Modificar migraciones.

Si una vista necesita datos, se usan los endpoints de `docs/api/contract.md` vía `fetch` o un servicio existente.

---

## 7. JS base del panel

Ruta acordada: `public/assets/panel/js/app.js`.

Debe exponer, como mínimo:

```js
export async function apiFetch(url, options = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const headers = {
        Accept: 'application/json',
        'X-CSRF-Token': token,
        ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
        ...(options.headers ?? {}),
    };

    const response = await fetch(url, { ...options, headers });
    const payload = await response.json().catch(() => null);

    if (response.status === 401) {
        window.location.href = '/login';
        throw new Error('Sesión expirada');
    }

    if (!response.ok) {
        const error = new Error(payload?.error?.message ?? 'Error inesperado');
        error.status = response.status;
        error.fields = payload?.error?.fields ?? {};
        throw error;
    }

    return payload;
}
```

También debe incluir helpers de:

- `toast(type, message)` — éxito/error.
- `openModal(id)` / `closeModal(id)`.
- `confirmAction(message)` — confirmación antes de mutar.
- `setLoading(button, isLoading)`.
- `renderFieldErrors(form, fields)` — pinta errores `422`.

Reglas:

- No usar jQuery.
- No hardcodear la URL base; usar rutas relativas (`/api/...`).
- Manejar `401`, `403` (incluye CSRF), `409` y `422` con mensajes claros.
- No mostrar stack traces.

---

## 8. Estados de UI obligatorios

Cada vista con datos debe tener:

| Estado | Comportamiento |
|---|---|
| Cargando | Skeleton o spinner; deshabilitar acciones. |
| Vacío | Mensaje claro + acción sugerida (ej. "Registrar primer paciente"). |
| Error | Mensaje + botón "Reintentar"; no dejar la pantalla en blanco. |
| Sin permisos | Página 403 con enlace al inicio del rol. |
| Sesión expirada | Redirección a `/login` con aviso. |

---

## 9. Assets y Gentelella

- Fuente: `gentelella/` (v4, Vite + vanilla JS).
- Salida del panel: `public/assets/panel/**`.
- Lucas es el único que edita `gentelella/` y ejecuta su build.
- El layout PHP puede usar el CSS/JS compilado de Gentelella, pero **el sidebar/topbar los renderiza PHP**, no el `shellInjectionPlugin` de Vite.
- Evitar incluir `data-shell="admin"` en las páginas PHP para no duplicar el shell.
- Paleta de la clínica: azul `#0A6EBD`, teal `#0F9D8A`, fondos claros.
- Tipografía: Inter/Poppins.
- Iconografía: la de Gentelella o Font Awesome, según lo que ya incluya el build.

---

## 10. Vistas de autenticación

Lucas implementa:

- `auth/login.php`
- `auth/recuperar.php`
- `auth/reset.php`
- `auth/activar.php`

Contrato:

| Vista | Endpoint | Campos |
|---|---|---|
| Login | `POST /auth/login` | `identificador`, `password` |
| Recuperar | `POST /auth/recuperar` | `email` |
| Reset | `POST /auth/reset` | `token`, `password`, `password_confirmation` |
| Activar | `POST /auth/activar` | `ci`, `telefono`, `password`, `password_confirmation`, `email` (opcional) |

Todas muestran errores de validación y mensajes de éxito/error sin recargar la página cuando sea posible.

---

## 11. Convenciones de nombres

- Vistas: `snake_case` en carpetas, `index.php` para listados.
- Variables de vista: `camelCase` (`$paciente`, `$citas`, `$csrfToken`).
- Claves de menú: `snake_case`.
- Endpoints: `snake_case` en query params; rutas en plural.
- Estados de cita: `programada`, `confirmada`, `atendida`, `cancelada`, `inasistencia`.
- Métodos de pago: `efectivo`, `visa`, `mastercard`.

---

## 12. Flujo de trabajo con mocks

1. Lucas toma el JSON de `docs/api/mocks/`.
2. Lo usa en su capa de datos local (objeto JS o archivo estático).
3. Construye la vista con estados de carga/vacío/error.
4. Cuando Matias publica el endpoint, cambia la fuente por `apiFetch('/api/...')`.
5. Si el contrato cambia, se actualiza `docs/api/contract.md` y el mock.

---

## 13. Criterios de aceptación de una vista

- [ ] Renderiza con el layout y el menú correcto para su rol.
- [ ] No contiene SQL ni lógica de negocio.
- [ ] Usa `e()` en todo output dinámico.
- [ ] Usa CSRF en formularios y mutaciones.
- [ ] Tiene estados de carga, vacío y error.
- [ ] Es responsive en 375, 768 y 1280 px.
- [ ] No tiene errores en consola.
- [ ] Los permisos se validan también en el servidor.
- [ ] Incluye captura en el PR.
