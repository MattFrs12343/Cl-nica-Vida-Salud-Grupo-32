# SPEC 99 — Modelo de Datos Consolidado y RLS

> Documento de referencia de la base de datos (PostgreSQL 16) y la seguridad a nivel de fila.
> Consumido por todas las specs por módulo.

---

## 1. Convenciones

- **PK:** `id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY`.
- **Códigos visibles:** `codigo` único (`PAC-`, `MED-`, `CM-`, `FIC-`, `RSV-`, `DOC-`, `ORD-`).
- **Tiempos:** `created_at`, `updated_at TIMESTAMPTZ DEFAULT now()`.
- **Borrado lógico:** `estado` (`activo`/`inactivo`) o `activo BOOLEAN`.
- **Enums** nativos para estado de cita, origen, rol, sexo, acción, tipo de pedido.
- **JSONB** para `signos_vitales` y `datos_antes`/`datos_despues`.

## 2. Enums

```sql
CREATE TYPE rol_usuario AS ENUM
  ('super_admin','admin','recepcionista','recepcionista_medico','medico','paciente');
CREATE TYPE estado_paciente AS ENUM ('activo','inactivo');
CREATE TYPE sexo AS ENUM ('masculino','femenino');
CREATE TYPE sexo_permitido AS ENUM ('ambos','femenino','masculino');
CREATE TYPE estado_cita AS ENUM
  ('programada','confirmada','atendida','cancelada','inasistencia');
CREATE TYPE origen_cita AS ENUM ('web','kiosco','recepcion');
CREATE TYPE tipo_reconsulta AS ENUM ('ausencia_medico','seguimiento');
CREATE TYPE canal_ficha AS ENUM ('web','kiosco');
CREATE TYPE accion_auditoria AS ENUM ('INSERT','UPDATE','DELETE');
CREATE TYPE tipo_documento AS ENUM
  ('certificado','informe','constancia','interconsulta');
CREATE TYPE tipo_orden AS ENUM ('laboratorio','imagen','otro');
CREATE TYPE metodo_pago AS ENUM ('efectivo','visa','mastercard');

## 3. Esquema completo (DDL de referencia)

### 3.1 Seguridad
```sql
CREATE TABLE usuarios (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  username VARCHAR(60) UNIQUE,
  email VARCHAR(120) UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol rol_usuario NOT NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  intentos_fallidos SMALLINT NOT NULL DEFAULT 0,
  bloqueado BOOLEAN NOT NULL DEFAULT FALSE,
  token_reset VARCHAR(128),
  token_reset_expira TIMESTAMPTZ,
  ultimo_acceso TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ
);

CREATE TABLE auditoria (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  usuario_id INTEGER REFERENCES usuarios(id),
  ip INET,
  tabla_afectada VARCHAR(60) NOT NULL,
  accion accion_auditoria NOT NULL,
  registro_id BIGINT,
  datos_antes JSONB,
  datos_despues JSONB,
  fecha TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_auditoria_tabla ON auditoria(tabla_afectada);
CREATE INDEX ix_auditoria_fecha ON auditoria(fecha);
CREATE INDEX ix_auditoria_usuario ON auditoria(usuario_id);
```

### 3.2 Pacientes
```sql
CREATE TABLE pacientes (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  usuario_id INTEGER REFERENCES usuarios(id),
  ci VARCHAR(20) NOT NULL UNIQUE,
  nombres VARCHAR(100) NOT NULL,
  apellidos VARCHAR(100) NOT NULL,
  fecha_nacimiento DATE NOT NULL,
  sexo sexo NOT NULL,
  telefono VARCHAR(20),
  email VARCHAR(120),
  direccion VARCHAR(200),
  tipo_sangre VARCHAR(5),
  alergias TEXT,
  enfermedades_cronicas TEXT,
  medicamentos TEXT,
  antecedentes TEXT,
  emergencia_nombre VARCHAR(100),
  emergencia_parentesco VARCHAR(50),
  emergencia_telefono VARCHAR(20),
  estado estado_paciente NOT NULL DEFAULT 'activo',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ
);
```

### 3.3 Catálogos médicos
```sql
CREATE TABLE especialidades (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  descripcion TEXT,
  sexo_permitido sexo_permitido NOT NULL DEFAULT 'ambos',
  edad_min SMALLINT,
  edad_max SMALLINT,
  destacada BOOLEAN NOT NULL DEFAULT FALSE,
  icono VARCHAR(50),
  estado estado_paciente NOT NULL DEFAULT 'activo',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ
);
CREATE UNIQUE INDEX ux_especialidad_nombre ON especialidades (lower(nombre));

CREATE TABLE medicos (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  usuario_id INTEGER REFERENCES usuarios(id),
  registro_profesional VARCHAR(50) NOT NULL UNIQUE,
  nombres VARCHAR(100) NOT NULL,
  apellidos VARCHAR(100) NOT NULL,
  telefono VARCHAR(20),
  email VARCHAR(120),
  duracion_cita_min SMALLINT NOT NULL DEFAULT 30,
  qr_imagen VARCHAR(255),
  estado estado_paciente NOT NULL DEFAULT 'activo',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ
);

CREATE TABLE medico_especialidad (
  medico_id INTEGER NOT NULL REFERENCES medicos(id),
  especialidad_id INTEGER NOT NULL REFERENCES especialidades(id),
  PRIMARY KEY (medico_id, especialidad_id)
);

CREATE TABLE recepcionista_medico (
  usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
  medico_id INTEGER NOT NULL REFERENCES medicos(id),
  PRIMARY KEY (usuario_id, medico_id)
);
```

### 3.4 Horarios y cupos
```sql
CREATE TABLE horarios (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  medico_id INTEGER NOT NULL REFERENCES medicos(id),
  dia_semana SMALLINT NOT NULL CHECK (dia_semana BETWEEN 0 AND 6),
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  cupos_web SMALLINT NOT NULL DEFAULT 0,
  cupos_kiosco SMALLINT NOT NULL DEFAULT 0,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ,
  CHECK (hora_fin > hora_inicio)
);

CREATE TABLE bloqueos (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  medico_id INTEGER NOT NULL REFERENCES medicos(id),
  fecha DATE NOT NULL,
  hora_inicio TIME,
  hora_fin TIME,
  motivo VARCHAR(120),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 3.5 Citas y fichas
```sql
CREATE TABLE citas (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  paciente_id INTEGER NOT NULL REFERENCES pacientes(id),
  medico_id INTEGER NOT NULL REFERENCES medicos(id),
  especialidad_id INTEGER NOT NULL REFERENCES especialidades(id),
  fecha DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  estado estado_cita NOT NULL DEFAULT 'programada',
  origen origen_cita NOT NULL,
  es_prioritaria BOOLEAN NOT NULL DEFAULT FALSE,
  es_reconsulta BOOLEAN NOT NULL DEFAULT FALSE,
  reconsulta_tipo tipo_reconsulta,
  cita_origen_id INTEGER REFERENCES citas(id),
  motivo VARCHAR(200),
  motivo_cancelacion VARCHAR(200),
  fecha_registro TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ
);
CREATE UNIQUE INDEX ux_cita_no_superpone
  ON citas (medico_id, fecha, hora_inicio)
  WHERE estado <> 'cancelada';
CREATE INDEX ix_citas_fecha ON citas(fecha);
CREATE INDEX ix_citas_paciente ON citas(paciente_id);
CREATE INDEX ix_citas_medico ON citas(medico_id);

CREATE TABLE citas_estado_historial (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  cita_id INTEGER NOT NULL REFERENCES citas(id),
  estado_anterior estado_cita,
  estado_nuevo estado_cita NOT NULL,
  usuario_id INTEGER REFERENCES usuarios(id),
  observacion VARCHAR(200),
  fecha TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE fichas (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  cita_id INTEGER NOT NULL UNIQUE REFERENCES citas(id),
  canal canal_ficha NOT NULL,
  codigo_acceso VARCHAR(4) NOT NULL,
  verificacion_biometrica BOOLEAN NOT NULL DEFAULT FALSE,
  emitida_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 3.6 Historia clínica
```sql
CREATE TABLE consultas (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  cita_id INTEGER NOT NULL REFERENCES citas(id),
  paciente_id INTEGER NOT NULL REFERENCES pacientes(id),
  medico_id INTEGER NOT NULL REFERENCES medicos(id),
  motivo VARCHAR(200),
  anamnesis TEXT,
  signos_vitales JSONB,
  examen_fisico TEXT,
  diagnostico TEXT,
  tratamiento TEXT,
  indicaciones TEXT,
  fecha TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE recetas (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  codigo_acceso VARCHAR(4) NOT NULL,
  consulta_id INTEGER NOT NULL REFERENCES consultas(id),
  paciente_id INTEGER NOT NULL REFERENCES pacientes(id),
  medico_id INTEGER NOT NULL REFERENCES medicos(id),
  enviado_whatsapp BOOLEAN NOT NULL DEFAULT FALSE,
  fecha TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE receta_detalle (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  receta_id INTEGER NOT NULL REFERENCES recetas(id),
  medicamento VARCHAR(150) NOT NULL,
  dosis VARCHAR(80),
  via VARCHAR(50),
  frecuencia VARCHAR(80),
  duracion VARCHAR(50),
  instrucciones VARCHAR(200)
);

CREATE TABLE documentos (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  codigo_acceso VARCHAR(4) NOT NULL,
  consulta_id INTEGER NOT NULL REFERENCES consultas(id),
  paciente_id INTEGER NOT NULL REFERENCES pacientes(id),
  medico_id INTEGER NOT NULL REFERENCES medicos(id),
  tipo tipo_documento NOT NULL,
  contenido TEXT,
  enviado_whatsapp BOOLEAN NOT NULL DEFAULT FALSE,
  fecha TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE ordenes (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  codigo_acceso VARCHAR(4) NOT NULL,
  consulta_id INTEGER NOT NULL REFERENCES consultas(id),
  paciente_id INTEGER NOT NULL REFERENCES pacientes(id),
  medico_id INTEGER NOT NULL REFERENCES medicos(id),
  tipo tipo_orden NOT NULL,
  enviado_whatsapp BOOLEAN NOT NULL DEFAULT FALSE,
  fecha TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE orden_detalle (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  orden_id INTEGER NOT NULL REFERENCES ordenes(id),
  estudio VARCHAR(150) NOT NULL,
  observaciones VARCHAR(200)
);

CREATE TABLE resultados (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  orden_id INTEGER NOT NULL REFERENCES ordenes(id),
  archivo VARCHAR(255),
  texto TEXT,
  revisado BOOLEAN NOT NULL DEFAULT FALSE,
  fecha TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 3.7 Pagos
```sql
CREATE TABLE pagos (
  id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  paciente_id INTEGER NOT NULL REFERENCES pacientes(id),
  medico_id INTEGER NOT NULL REFERENCES medicos(id),
  registrado_por INTEGER NOT NULL REFERENCES usuarios(id),
  monto NUMERIC(10,2) NOT NULL CHECK (monto > 0),
  metodo metodo_pago NOT NULL,
  referencia_paciente VARCHAR(150),
  fecha TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

## 4. Relaciones

```
usuarios 1─N auditoria
usuarios 1─1 pacientes (opcional)
usuarios 1─1 medicos (opcional)
usuarios 1─N recepcionista_medico N─1 medicos
pacientes 1─N citas
medicos 1─N citas
especialidades 1─N citas
medicos N─M especialidades
medicos 1─N horarios / bloqueos
citas 1─1 fichas
citas 1─1 consultas 1─N recetas/documentos/ordenes
recetas 1─N receta_detalle
ordenes 1─N orden_detalle / resultados
pacientes 1─N pagos ; medicos 1─N pagos
```

## 5. Generación de códigos

- Secuencias por prefijo o función `siguiente_codigo('PAC')` → `PAC-0001`.
- Los códigos se generan en el **servicio** (no en el cliente).

## 6. RLS — Row Level Security

### 6.1 Contexto de sesión
En el bootstrap de cada request PHP:
```sql
SET LOCAL app.usuario_id = '<id>';
SET LOCAL app.rol = '<rol>';
SET LOCAL app.medico_id = '<id_medico|>';
SET LOCAL app.ip = '<ip_cliente>';
```
Acceso con funciones:
```sql
current_setting('app.usuario_id', true)::int
current_setting('app.rol', true)
current_setting('app.medico_id', true)::int
```

### 6.2 Rol de conexión
- La app usa un rol **`clinica_app`** (NO superuser).
- `ALTER TABLE ... ENABLE ROW LEVEL SECURITY;` y `FORCE ROW LEVEL SECURITY` en tablas sensibles.

### 6.3 Políticas resumidas

**citas**
```sql
-- admin/super_admin/recepcionista: todas
CREATE POLICY citas_admin ON citas FOR ALL USING (
  current_setting('app.rol', true) IN ('admin','super_admin','recepcionista')
);
-- recepcionista_medico: solo su médico
CREATE POLICY citas_recep_med ON citas FOR ALL USING (
  current_setting('app.rol', true) = 'recepcionista_medico'
  AND medico_id = current_setting('app.medico_id', true)::int
);
-- medico: solo las suyas
CREATE POLICY citas_medico ON citas FOR ALL USING (
  current_setting('app.rol', true) = 'medico'
  AND medico_id = current_setting('app.medico_id', true)::int
);
-- paciente: solo las propias (por usuario→paciente)
CREATE POLICY citas_paciente ON citas FOR SELECT USING (
  current_setting('app.rol', true) = 'paciente'
  AND paciente_id = (
    SELECT p.id FROM pacientes p
    WHERE p.usuario_id = current_setting('app.usuario_id', true)::int
  )
);
```

**pacientes**
```sql
CREATE POLICY pac_admin ON pacientes FOR ALL USING (
  current_setting('app.rol', true) IN ('admin','super_admin','recepcionista')
);
CREATE POLICY pac_med ON pacientes FOR SELECT USING (
  current_setting('app.rol', true) IN ('medico','recepcionista_medico')
  AND id IN (
    SELECT paciente_id FROM citas
    WHERE medico_id = current_setting('app.medico_id', true)::int
  )
);
CREATE POLICY pac_self ON pacientes FOR SELECT USING (
  current_setting('app.rol', true) = 'paciente'
  AND usuario_id = current_setting('app.usuario_id', true)::int
);
```

**consultas / recetas / documentos / ordenes / resultados / pagos**
- Análogas: admin/recep todo; recep_med y medico filtrado por `medico_id`; paciente lectura si `paciente_id` es el suyo.

**horarios / bloqueos / medico_especialidad**
- Escritura: admin. Lectura: medico (propios), recep_med (sus médicos).

**auditoria**
```sql
CREATE POLICY aud_admin ON auditoria FOR SELECT USING (
  current_setting('app.rol', true) IN ('admin','super_admin')
);
-- Sin INSERT/UPDATE/DELETE vía app (solo trigger SECURITY DEFINER)
```

**usuarios**
- admin/super_admin: todo. Otros: solo su propia fila (SELECT/UPDATE limitado).

### 6.4 Kiosco y página pública
- **Kiosco:** usuario de BD `clinica_kiosk` con políticas que permiten:
  - `INSERT` en `pacientes` (registro rápido), `citas` (origen='kiosco'), `fichas`.
  - `SELECT` limitado: `especialidades` activas, `medicos` activos, `horarios` (para disponibilidad).
  - No lectura de datos clínicos/pagos.
- **Página pública de pedidos:** rol restringido que solo lee `recetas`/`documentos`/`ordenes` filtrando por `codigo + codigo_acceso`, sin exponer columnas sensibles.

### 6.5 Auditoría con trigger
- `fn_auditar()` `SECURITY DEFINER` escribe la fila; el rol `clinica_app` no necesita permiso de INSERT en `auditoria` ni puede alterarla.

## 7. Integridad y transacciones

- **Registro de cita:** `BEGIN; SELECT ... FOR UPDATE; verificar superposición + cupo; INSERT; COMMIT;`
- **Índice único parcial** en `citas` como respaldo ante concurrencia.
- **FK** con `ON DELETE RESTRICT` para conservar historial (no borrado físico).

## 8. Índices recomendados

```
citas(medico_id,fecha), citas(paciente_id), citas(fecha), citas(estado)
horarios(medico_id,dia_semana)
auditoria(tabla_afectada), auditoria(fecha), auditoria(usuario_id)
pacientes(ci) único, especialidades lower(nombre) único
```

## 9. Criterios de aceptación

- [ ] El DDL crea todas las tablas y enums sin error.
- [ ] El índice `ux_cita_no_superpone` impide duplicados de slot activo.
- [ ] Las políticas RLS aíslan datos por rol.
- [ ] El trigger de auditoría registra IP + antes/después.
- [ ] El kiosco no accede a datos clínicos/pagos.
- [ ] La página pública valida código + acceso.

## 10. Tareas de implementación

1. Migración `000_enums.sql`, `001_...` por módulo.
2. Rol `clinica_app` + `clinica_kiosk` + permisos.
3. Políticas RLS + `FORCE ROW LEVEL SECURITY`.
4. Función `fn_auditar()` + triggers.
5. `SET LOCAL` en el bootstrap de conexión PDO.
6. Seeders (admin + especialidades + usuarios Matias/Lucas).
