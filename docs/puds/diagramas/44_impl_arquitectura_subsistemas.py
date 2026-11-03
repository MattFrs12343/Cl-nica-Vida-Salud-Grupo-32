from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyArrowPatch, Rectangle

OUT = Path(__file__).with_name("44_impl_arquitectura_subsistemas.png")

W, H = 16.4, 19.8
BLUE = "#0A6EBD"
LIGHT = "#9CC3E5"
FILL = "#F7FBFF"
TEXT = "#0B3C5D"
ARROW = "#5B7C99"
GRAY = "#5B6B7A"

plt.rcParams["font.family"] = "Arial"
fig, ax = plt.subplots(figsize=(8.4, 10.14), dpi=300)
fig.subplots_adjust(0, 0, 1, 1)
ax.set_xlim(0, W)
ax.set_ylim(0, H)
ax.set_aspect("equal")
ax.axis("off")

BOX_X = 1.12
BOX_W = 3.565
BOX_H = 0.96
GAP = 0.215


def comp_box(x, y, stereotype, name, fontsize=8.05):
    ax.add_patch(Rectangle((x, y), BOX_W, BOX_H, facecolor="white",
                           edgecolor=BLUE, linewidth=0.95, zorder=3))
    ax.text(x + BOX_W / 2, y + BOX_H - 0.24, stereotype, ha="center",
            va="center", fontsize=fontsize - 1.15, color=BLUE,
            style="italic", zorder=4)
    ax.text(x + BOX_W / 2, y + BOX_H / 2 - 0.13, name, ha="center",
            va="center", fontsize=fontsize, color=TEXT,
            fontweight="bold", zorder=4)


def band(y, h, title):
    ax.add_patch(Rectangle((0.25, y), W - 0.50, h, facecolor=FILL,
                           edgecolor=LIGHT, linewidth=1.1, zorder=0))
    ax.text(1.12, y + h - 0.34, title, ha="left", va="center",
            fontsize=10.2, fontweight="bold", color=BLUE, zorder=5)
    ax.plot([1.12, W - 0.40], [y + h - 0.58, y + h - 0.58],
            color=LIGHT, linewidth=0.9, zorder=1)
    for label, cy in (("Interfaz", y + 4.83), ("Negocio", y + 2.98),
                      ("Datos", y + 1.13)):
        ax.text(0.58, cy, label, ha="center", va="center", rotation=90,
                fontsize=6.5, color=GRAY, fontweight="bold", zorder=5)
    for sep in (y + 4.02, y + 2.17):
        ax.plot([1.12, W - 0.40], [sep, sep], color=LIGHT,
                linewidth=0.7, linestyle=(0, (4, 3)), zorder=1)


def dep(source, target, rad=0.0):
    ax.add_patch(FancyArrowPatch(
        source, target, arrowstyle="-|>", mutation_scale=7.5,
        linewidth=0.85, linestyle=(0, (3.0, 2.3)), color=ARROW,
        alpha=0.65, shrinkA=2, shrinkB=2, zorder=2,
        connectionstyle=f"arc3,rad={rad}",
    ))


def row_centers(y):
    top = [BOX_X + i * (BOX_W + GAP) + BOX_W / 2 for i in range(4)]
    return top


def draw_subsystem(y, title, forms, classes, tables, links):
    band(y, 6.15, title)
    top_y = y + 4.35
    mid_y = y + 2.50
    bot_y = y + 0.65
    xs = row_centers(y)

    for i, (st, name) in enumerate(forms):
        comp_box(xs[i] - BOX_W / 2, top_y, st, name)
    for i, (st, name) in enumerate(classes):
        comp_box(xs[i] - BOX_W / 2, mid_y, st, name)
    for i, (st, name) in enumerate(tables):
        comp_box(xs[i] - BOX_W / 2, bot_y, st, name)

    for i in range(4):
        dep((xs[i], top_y), (xs[i], mid_y + BOX_H))
    for src, dst, rad in links:
        dep((xs[src], mid_y), (xs[dst], bot_y + BOX_H), rad=rad)


draw_subsystem(
    13.35,
    "P1 · Gestión de Consultas Médicas",
    [("«form»", "FRegistroConsulta"), ("«form»", "FConsultaMedica"),
     ("«form»", "FHistoriaClinica"), ("«report»", "RHistoriaClinica")],
    [("«class»", "GestionFicha"), ("«class»", "GestionConsulta"),
     ("«class»", "GestionHistoria"), ("«class»", "ReporteConsulta")],
    [("«table»", "T_Ficha"), ("«table»", "T_Consulta"),
     ("«table»", "T_Paciente"), ("«table»", "T_Historia")],
    [(0, 0, 0.0), (1, 1, 0.0), (1, 2, 0.04), (2, 3, 0.0),
     (2, 2, -0.06), (3, 3, 0.0), (3, 1, 0.05)],
)

draw_subsystem(
    6.95,
    "P2 · Gestión de Citas y Agenda",
    [("«form»", "FReservaWeb"), ("«form»", "FProgramarCita"),
     ("«form»", "FReprogramarCita"), ("«form»", "FKiosco")],
    [("«class»", "DisponibilidadService"), ("«class»", "CitaService"),
     ("«class»", "ReconsultaService"), ("«class»", "FichaService")],
    [("«table»", "T_Cita"), ("«table»", "T_Horario"),
     ("«table»", "T_Bloqueo"), ("«table»", "T_Ficha")],
    [(0, 0, 0.0), (0, 1, 0.05), (0, 2, 0.07), (1, 0, 0.0),
     (1, 1, 0.0), (2, 0, 0.0), (3, 3, 0.0), (3, 0, -0.07)],
)

draw_subsystem(
    0.55,
    "P3 · Administración, Seguridad y Reportes",
    [("«form»", "FLogin"), ("«form»", "FUsuarios"),
     ("«form»", "FMedicos"), ("«report»", "FReportes")],
    [("«class»", "AuthService"), ("«class»", "UsuarioModel"),
     ("«class»", "MedicoModel"), ("«class»", "ReporteModel")],
    [("«table»", "T_Usuario"), ("«table»", "T_Medico"),
     ("«table»", "T_Especialidad"), ("«table»", "T_Auditoria")],
    [(0, 0, 0.0), (1, 0, 0.0), (2, 1, 0.0), (2, 2, 0.05),
     (3, 1, 0.0), (3, 2, 0.04), (3, 3, 0.08)],
)

ax.text(1.12, 0.22,
        "T_Historia agrupa consultas, recetas, documentos, órdenes y resultados. "
        "Las flechas discontinuas representan dependencias de uso.",
        ha="left", va="center", fontsize=6.6, color=GRAY, zorder=5)

fig.savefig(OUT, dpi=300, facecolor="white")
print(OUT)
