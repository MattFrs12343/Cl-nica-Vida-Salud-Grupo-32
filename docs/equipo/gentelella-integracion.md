# L-02 — Spike Gentelella v4 ↔ PHP

> Fecha: 2026-10-23
> Versión analizada: **Gentelella 4.2.0**
> Commit de referencia: `02936f9003baa8473aa39dadd4ce23b679961d53`
> Timebox: sesión de spike (lectura + prueba de una página)

## 1. Qué es Gentelella v4

- Es un template **Vite 8 + JavaScript vanilla**, **sin Bootstrap ni jQuery**.
- Las páginas de `production/*.html` son HTML estático que carga
  `/src/main-v4.js`; el shell (sidebar, topbar, tema, toasts, modales) se
  activa con JavaScript.
- El paquete publicado incluye `dist/` ya compilado:
  - `dist/assets/main-v4-*.css` (todo el CSS, variables y componentes),
  - `dist/js/*.js` (entrypoints y chunks de demo),
  - `dist/images/*` (iconos, favicons y placeholders).
- Los estilos usan variables CSS (`--primary`, `--sidebar-bg`, `--body-bg`,
  `--text`, etc.) y clases reutilizables (`.card`, `.btn`, `.table`, `.alert`,
  `.badge`, `.modal-*`, `.toast-*`, `.stat`, `.empty-state`, `.pagination`).
- El sidebar y el topbar del HTML de ejemplo son markup plano; el JS solo les
  agrega comportamiento (abrir/cerrar, tema, menú, búsqueda).

## 2. Opciones evaluadas

| Opción | Ventaja | Problema |
|---|---|---|
| Convertir `production/*.html` a PHP | Se ve igual que el demo | El demo trae 60 páginas y un sidebar ajeno; el JS espera el shell completo y los chunks de demo. Mucho ruido para nuestro dominio. |
| Compilar el proyecto completo con Vite | Pipeline oficial | Requiere `node_modules`, build de 60 entradas y chunks de demo; el shell sigue siendo JS-rendered. |
| **CSS compilado + shell PHP + JS propio** | Control total del HTML, sesión y menús; menos peso; sin dependencias JS | Hay que reimplementar sidebar móvil, tema, toasts y modales (poco código). |

## 3. Decisión

**Usar el CSS compilado de Gentelella 4.2.0 como base visual y renderizar el
shell con PHP.**

- Layout: `app/views/layouts/gentelella.php`.
- Partials: sidebar, topbar, flashes e iconos SVG.
- Assets: `public/assets/panel/css/gentelella.css` (CSS compilado) +
  `public/assets/panel/css/panel.css` (ajustes de la clínica).
- Imágenes: `public/assets/panel/images/`.
- Interacción: `public/assets/panel/js/panel.js` (propio), sin cargar
  `main-v4.js` ni los chunks de demo.
- Iconos: SVG inline (sprite en el layout), sin Font Awesome.

### Por qué no cargamos el JS de Gentelella

El JS de demo asume el shell completo, imports de páginas y datos de ejemplo.
Nuestro shell es PHP y solo necesita cinco comportamientos: sidebar móvil,
tema claro/oscuro, toasts, modales y `apiFetch()` con CSRF. Implementarlos
nos da control, evita errores de chunks y deja el panel liviano.

## 4. Prueba realizada

- Se copió el CSS compilado y las imágenes al panel.
- Se creó una página de panel (`/admin`) con el shell PHP y el CSS de
  Gentelella; la estructura usa las clases reales del template.
- Pendiente: verificación visual en navegador a 375/768/1280 px y ajuste de
  contraste/paleta (L-12/L-34).

## 5. Reproducibilidad (L-03)

El script `bin/build-panel-assets.ps1` reproduce los assets desde cero usando
el paquete npm publicado (que ya incluye `dist/`):

```powershell
powershell -ExecutionPolicy Bypass -File bin/build-panel-assets.ps1
```

Hace `npm pack gentelella@4.2.0`, extrae el paquete y copia:

- `dist/assets/main-v4-*.css` → `public/assets/panel/css/gentelella.css`
- `dist/images/*` → `public/assets/panel/images/*`

Si en el futuro se modifica el SCSS, se puede compilar desde la fuente con
`npm install && npm run build` dentro de `gentelella/` y apuntar el script a
`gentelella/dist`. Por ahora no se versiona `node_modules` ni `dist/`.

## 6. Riesgos y notas

- El CSS compilado tiene un nombre con hash; se copia como `gentelella.css`
  para que el layout no dependa del hash.
- El CSS referencia imágenes por ruta relativa en algunos casos; por eso se
  copia `dist/images` completa.
- La tipografía es Inter vía Google Fonts. Si el entorno es offline, cae a la
  fuente del sistema.
- Gentelella v4 no trae Chart.js; la decisión de gráficos queda en la duda 4 de
  `lucas-dudas.md`.
