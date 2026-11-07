# -*- coding: utf-8 -*-
"""Diagrama integral de paquetes del analisis (figura de la seccion 5.4).

Genera una vista unica que reune los paquetes PK-01 a PK-04, los casos de uso
que contiene cada paquete, las relaciones «include»/«extend» y las
dependencias «use» entre paquetes.
"""
from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import Ellipse, FancyArrowPatch, FancyBboxPatch, Rectangle

OUT = Path(__file__).with_name("62_paquetes_integral.png")

W, H = 16.0, 21.0
BLUE = "#0A6EBD"
NAVY = "#0B3C5D"
LIGHT = "#9CC3E5"
FILL = "#F7FBFF"
HEAD = "#EAF4FB"
TEXT = "#0B3C5D"
GRAY = "#5B6B7A"
ARROW = "#5B7C99"

plt.rcParams["font.family"] = "Arial"
fig, ax = plt.subplots(figsize=(8.0, 10.5), dpi=260)
fig.subplots_adjust(0, 0, 1, 1)
ax.set_xlim(0, W)
ax.set_ylim(0, H)
ax.set_aspect("equal")
ax.axis("off")

UC_W, UC_H = 2.22, 1.18
COL_GAP = 0.24
ROW_GAP = 0.30
LEFT = 0.95
FRAME_W = 10.05
PK04_X = 11.75
PK04_W = 3.95

# --- geometria de los paquetes -------------------------------------------------
PK03_Y, PK03_H = 14.65, 6.05
PK01_Y, PK01_H = 9.10, 4.45
PK02_Y, PK02_H = 3.55, 4.45
PK04_Y, PK04_H = 3.55, 17.15


def wrap(text, width=18):
    words = text.split()
    lines, line = [], ""
    for w in words:
        cand = (line + " " + w).strip()
        if len(cand) <= width:
            line = cand
        else:
            if line:
                lines.append(line)
            line = w
    if line:
        lines.append(line)
    return "\n".join(lines)


def package(x, y, w, h, title):
    ax.add_patch(Rectangle((x, y), w, h, facecolor="white", edgecolor=BLUE,
                           linewidth=1.25, zorder=1))
    ax.add_patch(Rectangle((x, y + h - 0.62), w, 0.62, facecolor=HEAD,
                           edgecolor=BLUE, linewidth=1.25, zorder=2))
    ax.text(x + 0.22, y + h - 0.31, title, ha="left", va="center",
            fontsize=9.2, fontweight="bold", color=NAVY, zorder=3)


def usecase(cx, cy, text, w=UC_W, h=UC_H):
    ax.add_patch(Ellipse((cx, cy), w, h, facecolor=FILL, edgecolor=BLUE,
                         linewidth=0.9, zorder=4))
    ax.text(cx, cy, wrap(text), ha="center", va="center", fontsize=6.5,
            color=TEXT, linespacing=1.05, zorder=5)
    return {"cx": cx, "cy": cy, "w": w, "h": h}


def row_xs():
    total = 4 * UC_W + 3 * COL_GAP
    x0 = LEFT + (FRAME_W - total) / 2 + UC_W / 2
    return [x0 + i * (UC_W + COL_GAP) for i in range(4)]


def grid_rows(top, rows):
    """Devuelve las ordenadas de las filas desde 'top' hacia abajo."""
    return [top - UC_H / 2 - i * (UC_H + ROW_GAP) for i in range(rows)]


def arrow(p1, p2, color=GRAY, width=0.9, dashed=True, rad=0.0, z=6):
    style = (0, (3.0, 2.2)) if dashed else "solid"
    ax.add_patch(FancyArrowPatch(
        p1, p2, arrowstyle="-|>", mutation_scale=7.5, linewidth=width,
        linestyle=style, color=color, shrinkA=0, shrinkB=0, zorder=z,
        connectionstyle=f"arc3,rad={rad}",
    ))


def rel(a, b, label, vertical=False, rad=0.0, label_dy=0.0):
    """Flecha «include»/«extend» entre dos elipses."""
    if vertical:
        if b["cy"] > a["cy"]:
            p1 = (a["cx"], a["cy"] + a["h"] / 2)
            p2 = (b["cx"], b["cy"] - b["h"] / 2)
        else:
            p1 = (a["cx"], a["cy"] - a["h"] / 2)
            p2 = (b["cx"], b["cy"] + b["h"] / 2)
        lx, ly = (p1[0] + p2[0]) / 2 + 0.05, (p1[1] + p2[1]) / 2 + label_dy
        ax.text(lx, ly, label, ha="left", va="center", fontsize=6.1,
                color=GRAY, zorder=7, style="italic",
                bbox=dict(facecolor="white", edgecolor="none", pad=0.4))
    else:
        if b["cx"] > a["cx"]:
            p1 = (a["cx"] + a["w"] / 2, a["cy"])
            p2 = (b["cx"] - b["w"] / 2, b["cy"])
        else:
            p1 = (a["cx"] - a["w"] / 2, a["cy"])
            p2 = (b["cx"] + b["w"] / 2, b["cy"])
        lx, ly = (p1[0] + p2[0]) / 2, p1[1] + 0.10
        ax.text(lx, ly, label, ha="center", va="bottom", fontsize=6.1,
                color=GRAY, zorder=7, style="italic",
                bbox=dict(facecolor="white", edgecolor="none", pad=0.4))
    arrow(p1, p2, color=GRAY, width=0.85, dashed=True, rad=rad)


# --- marcos --------------------------------------------------------------------
package(LEFT, PK03_Y, FRAME_W, PK03_H, "PK-03  Atención y administración")
package(LEFT, PK01_Y, FRAME_W, PK01_H, "PK-01  Acceso público y reserva web")
package(LEFT, PK02_Y, FRAME_W, PK02_H, "PK-02  Citas y agenda")
package(PK04_X, PK04_Y, PK04_W, PK04_H, "PK-04  Servicios compartidos")

xs = row_xs()

# --- PK-03 ---------------------------------------------------------------------
r = grid_rows(PK03_Y + PK03_H - 0.62 - 0.28, 3)
uc = {}
uc["UC16"] = usecase(xs[0], r[0], "UC16 Registrar consulta")
uc["UC17"] = usecase(xs[1], r[0], "UC17 Generar receta, documento u orden")
uc["UC18"] = usecase(xs[2], r[0], "UC18 Enviar pedido por WhatsApp")
uc["UC19"] = usecase(xs[3], r[0], "UC19 Registrar pago")
uc["UC20"] = usecase(xs[0], r[1], "UC20 Gestionar médicos y especialidades")
uc["UC21"] = usecase(xs[1], r[1], "UC21 Configurar horarios y cupos")
uc["UC22"] = usecase(xs[2], r[1], "UC22 Gestionar usuarios")
uc["UC23"] = usecase(xs[3], r[1], "UC23 Generar reportes")
uc["UC24"] = usecase(xs[0], r[2], "UC24 Auditar catálogos")
uc["UC15"] = usecase(xs[1], r[2], "UC15 Emitir ficha en kiosco")

# --- PK-01 ---------------------------------------------------------------------
r = grid_rows(PK01_Y + PK01_H - 0.62 - 0.34, 2)
uc["UC01"] = usecase(xs[0], r[0], "UC01 Navegar por la landing")
uc["UC05"] = usecase(xs[1], r[0], "UC05 Reservar cita web")
uc["UC02"] = usecase(xs[2], r[0], "UC02 Buscar especialidad y médico")
uc["UC03"] = usecase(xs[3], r[0], "UC03 Registrarse o activar cuenta")
uc["UC04"] = usecase(xs[0], r[1], "UC04 Iniciar sesión")
uc["UC06"] = usecase(xs[1], r[1], "UC06 Cancelar cita")
uc["UC07"] = usecase(xs[2], r[1], "UC07 Consultar mis citas e historial")

# --- PK-02 ---------------------------------------------------------------------
r = grid_rows(PK02_Y + PK02_H - 0.62 - 0.34, 2)
uc["UC09"] = usecase(xs[0], r[0], "UC09 Programar cita")
uc["UC10"] = usecase(xs[1], r[0], "UC10 Reprogramar cita")
uc["UC11"] = usecase(xs[2], r[0], "UC11 Cancelar cita en recepción")
uc["UC08"] = usecase(xs[3], r[0], "UC08 Gestionar pacientes")
uc["UC12"] = usecase(xs[0], r[1], "UC12 Controlar asistencia")
uc["UC13"] = usecase(xs[1], r[1], "UC13 Gestionar reconsultas")
uc["UC14"] = usecase(xs[2], r[1], "UC14 Consultar agenda e historial")

# --- relaciones entre casos de uso ---------------------------------------------
rel(uc["UC05"], uc["UC02"], "«include»")
rel(uc["UC06"], uc["UC07"], "«extend»")
rel(uc["UC09"], uc["UC04"], "«include»", vertical=True, label_dy=-0.17)
rel(uc["UC11"], uc["UC14"], "«extend»", vertical=True)
rel(uc["UC13"], uc["UC12"], "«extend»")
rel(uc["UC15"], uc["UC05"], "«include»", vertical=True, label_dy=-0.17)
rel(uc["UC18"], uc["UC17"], "«extend»")

# --- dependencias entre paquetes -----------------------------------------------
# PK-03 -> PK-01 (tramo vertical izquierdo)
arrow((LEFT + 0.28, PK03_Y), (LEFT + 0.28, PK01_Y + PK01_H),
      color=BLUE, width=1.25, dashed=True, z=8)
ax.text(xs[0] + 0.18, (PK03_Y + PK01_Y + PK01_H) / 2 + 0.18,
        "«use» reserva web", ha="left", va="center", fontsize=6.2,
        color=BLUE, zorder=9, style="italic",
        bbox=dict(facecolor="white", edgecolor="none", pad=0.4))

# PK-01 -> PK-02 (tramo vertical izquierdo)
arrow((LEFT + 0.28, PK01_Y), (LEFT + 0.28, PK02_Y + PK02_H),
      color=BLUE, width=1.25, dashed=True, z=8)
ax.text(xs[0] + 0.18, (PK01_Y + PK02_Y + PK02_H) / 2 + 0.18,
        "«use» disponibilidad", ha="left", va="center",
        fontsize=6.2, color=BLUE, zorder=9, style="italic",
        bbox=dict(facecolor="white", edgecolor="none", pad=0.4))

# PK-03 -> PK-02 (recorrido por el margen izquierdo, sin curvas)
ax.plot([LEFT, LEFT - 0.22, LEFT - 0.22],
        [PK03_Y + 0.55, PK03_Y + 0.55, PK02_Y + 1.15],
        color=BLUE, linewidth=1.25, linestyle=(0, (3.0, 2.2)), zorder=8)
arrow((LEFT - 0.22, PK02_Y + 1.15), (LEFT, PK02_Y + 1.15), color=BLUE,
      width=1.25, dashed=True, z=8)
ax.text(LEFT - 0.42, 11.35, "«use» agenda", ha="center", va="center",
        rotation=90, fontsize=6.2, color=BLUE, zorder=9, style="italic",
        bbox=dict(facecolor="white", edgecolor="none", pad=0.4))

# Dependencias horizontales hacia PK-04
for y in (18.10, 11.30, 5.80):
    arrow((LEFT + FRAME_W, y), (PK04_X, y), color=BLUE, width=1.25,
          dashed=True, z=8)

# --- contenido de PK-04 --------------------------------------------------------
services = [
    ("Autenticación", "Inicio de sesión, tokens y\ncontrol de intentos."),
    ("Correo", "Confirmaciones y avisos\npor SMTP."),
    ("Auditoría", "Registro de cambios en\ncatálogos y accesos."),
    ("Persistencia", "Acceso a PostgreSQL,\ntransacciones y respaldo."),
]
sy = PK04_Y + PK04_H - 0.90
for name, desc in services:
    ax.add_patch(FancyBboxPatch((PK04_X + 0.28, sy - 1.72), PK04_W - 0.56,
                                1.62, boxstyle="round,pad=0.02,rounding_size=0.10",
                                facecolor=FILL, edgecolor=LIGHT, linewidth=0.9,
                                zorder=4))
    ax.text(PK04_X + PK04_W / 2, sy - 0.38, name, ha="center", va="center",
            fontsize=7.6, fontweight="bold", color=NAVY, zorder=5)
    ax.text(PK04_X + PK04_W / 2, sy - 1.00, desc, ha="center", va="center",
            fontsize=6.1, color=GRAY, linespacing=1.15, zorder=5)
    sy -= 1.95
ax.plot([PK04_X + 0.30, PK04_X + PK04_W - 0.30], [10.55, 10.55],
        color=LIGHT, linewidth=0.8, zorder=5)
ax.text(PK04_X + PK04_W / 2, 10.05, "Dependencias recibidas",
        ha="center", va="center", fontsize=7.0, fontweight="bold",
        color=NAVY, zorder=5)
deps = [("PK-01", "seguridad y correo"),
        ("PK-02", "persistencia"),
        ("PK-03", "correo y auditoría")]
dy = 9.45
for pkg, detail in deps:
    ax.text(PK04_X + 0.34, dy, pkg, ha="left", va="center", fontsize=6.6,
            fontweight="bold", color=BLUE, zorder=5)
    ax.text(PK04_X + 1.18, dy, detail, ha="left", va="center", fontsize=6.3,
            color=GRAY, zorder=5)
    dy -= 0.62
ax.text(PK04_X + PK04_W / 2, 6.55,
        "Paquete transversal: no contiene\ncasos de uso de negocio.",
        ha="center", va="center", fontsize=6.3, color=GRAY,
        style="italic", linespacing=1.2, zorder=5)

# --- leyenda -------------------------------------------------------------------
lx, ly, lw, lh = LEFT, 0.35, FRAME_W, 2.65
ax.add_patch(Rectangle((lx, ly), lw, lh, facecolor="white", edgecolor=LIGHT,
                       linewidth=0.9, zorder=1))
ax.text(lx + 0.18, ly + lh - 0.30, "Leyenda", ha="left", va="center",
        fontsize=7.4, fontweight="bold", color=BLUE, zorder=3)
ax.add_patch(Ellipse((lx + 0.55, ly + 1.40), 0.62, 0.34, facecolor=FILL,
                     edgecolor=BLUE, linewidth=0.8, zorder=3))
ax.text(lx + 1.00, ly + 1.40, "Caso de uso", ha="left", va="center",
        fontsize=6.4, color=TEXT, zorder=3)
arrow((lx + 2.75, ly + 1.40), (lx + 3.45, ly + 1.40), color=GRAY,
      width=0.85, dashed=True, z=3)
ax.text(lx + 3.55, ly + 1.40, "«include» / «extend» entre casos de uso",
        ha="left", va="center", fontsize=6.4, color=TEXT, zorder=3)
arrow((lx + 7.35, ly + 1.40), (lx + 8.05, ly + 1.40), color=BLUE,
      width=1.25, dashed=True, z=3)
ax.text(lx + 8.15, ly + 1.40, "«use» dependencia entre paquetes",
        ha="left", va="center", fontsize=6.4, color=TEXT, zorder=3)
ax.text(lx + 0.18, ly + 0.62,
        "Cada caso de uso pertenece a un único paquete; las flechas discontinuas "
        "grises cruzan el límite cuando una relación «include» reutiliza un caso de otro paquete.",
        ha="left", va="center", fontsize=5.8, color=GRAY, style="italic", zorder=3)

fig.savefig(OUT, dpi=260, facecolor="white")
print("Generado:", OUT)
