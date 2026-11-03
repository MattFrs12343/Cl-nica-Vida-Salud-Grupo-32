from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyArrowPatch, Polygon, Rectangle

OUT = Path(__file__).with_name("43_impl_arquitectura_sistema.png")

W, H = 16.4, 13.2
BLUE = "#0A6EBD"
LIGHT = "#9CC3E5"
FILL = "#F7FBFF"
TEXT = "#0B3C5D"
ARROW = "#5B7C99"
GRAY = "#5B6B7A"

plt.rcParams["font.family"] = "Arial"
fig, ax = plt.subplots(figsize=(8.4, 6.76), dpi=300)
fig.subplots_adjust(0, 0, 1, 1)
ax.set_xlim(0, W)
ax.set_ylim(0, H)
ax.set_aspect("equal")
ax.axis("off")


def folder(x, y, w, h, label, fontsize=8.6, tab_w=None, fill="white"):
    tab_w = tab_w if tab_w else min(0.95, 0.34 * w)
    tab_h = 0.20
    points = [
        (x, y), (x, y + h), (x + tab_w, y + h),
        (x + tab_w + 0.14, y + h + tab_h), (x + w, y + h + tab_h),
        (x + w, y), (x, y),
    ]
    ax.add_patch(Polygon(points, closed=True, facecolor=fill,
                         edgecolor=BLUE, linewidth=1.1, zorder=2))
    ax.text(x + w / 2, y + h / 2, label, ha="center", va="center",
            fontsize=fontsize, color=TEXT, linespacing=1.18, zorder=3)


def component(x, y, w, h, stereotype, name, sub=None, fontsize=8.4,
              icon=True, dashed=False):
    ax.add_patch(Rectangle((x, y), w, h, facecolor="white",
                           edgecolor=BLUE, linewidth=1.1,
                           linestyle="--" if dashed else "-", zorder=2))
    if icon:
        ax.add_patch(Rectangle((x + 0.14, y + h - 0.33), 0.30, 0.18,
                               facecolor="white", edgecolor=BLUE,
                               linewidth=0.8, zorder=3))
        ax.add_patch(Rectangle((x + 0.22, y + h - 0.38), 0.30, 0.18,
                               facecolor="white", edgecolor=BLUE,
                               linewidth=0.8, zorder=3))
    ax.text(x + w / 2, y + h - 0.27, stereotype, ha="center", va="center",
            fontsize=fontsize - 1.3, color=BLUE, style="italic", zorder=3)
    if sub:
        ax.text(x + w / 2, y + h / 2 - 0.02, name, ha="center", va="center",
                fontsize=fontsize, color=TEXT, fontweight="bold",
                linespacing=1.12, zorder=3)
        ax.text(x + w / 2, y + 0.30, sub, ha="center", va="center",
                fontsize=fontsize - 1.7, color=GRAY, zorder=3)
    else:
        ax.text(x + w / 2, y + h / 2 - 0.08, name, ha="center", va="center",
                fontsize=fontsize, color=TEXT, fontweight="bold",
                linespacing=1.12, zorder=3)


def data_box(x, y, w, h, text, fontsize=8.2):
    ax.add_patch(Rectangle((x, y), w, h, facecolor="white",
                           edgecolor=LIGHT, linewidth=0.85, zorder=3))
    ax.text(x + w / 2, y + h / 2, text, ha="center", va="center",
            fontsize=fontsize, color=TEXT, zorder=4)


def report_file(x, y, w, h, stereotype, name, fontsize=8.0):
    ax.add_patch(Rectangle((x, y), w, h, facecolor="white",
                           edgecolor=LIGHT, linewidth=0.9, zorder=3))
    # folded corner
    fold = 0.22
    ax.add_patch(Polygon([(x + w - fold, y + h), (x + w, y + h - fold),
                          (x + w - fold, y + h - fold)],
                         closed=True, facecolor=FILL, edgecolor=LIGHT,
                         linewidth=0.7, zorder=4))
    ax.text(x + w / 2, y + h - 0.22, stereotype, ha="center", va="center",
            fontsize=fontsize - 1.0, color=BLUE, style="italic", zorder=4)
    ax.text(x + w / 2, y + h / 2 - 0.10, name, ha="center", va="center",
            fontsize=fontsize, color=TEXT, zorder=4)


def dep(source, target, rad=0.0, alpha=0.65):
    ax.add_patch(FancyArrowPatch(
        source, target, arrowstyle="-|>", mutation_scale=8,
        linewidth=0.9, linestyle=(0, (3.2, 2.4)), color=ARROW,
        alpha=alpha, shrinkA=2, shrinkB=2, zorder=1,
        connectionstyle=f"arc3,rad={rad}",
    ))


# ---------------------------------------------------------------- packages
packages = [
    ("P1  Gestión de\nConsultas Médicas", 0.45, 11.05, 3.69, 1.22),
    ("P2  Gestión de\nCitas y Agenda", 4.39, 11.05, 3.69, 1.22),
    ("P3  Administración\ny Seguridad", 8.33, 11.05, 3.69, 1.22),
    ("P4  Servicios\nCompartidos", 12.27, 11.05, 3.69, 1.22),
]
for label, x, y, w, h in packages:
    folder(x, y, w, h, label, fontsize=8.4, fill=FILL)

# ------------------------------------------------------------ main module
component(5.35, 7.35, 5.70, 2.40, "«main»",
          "Sistema Web de Gestión\nde Consultas Médicas",
          "public/index.php · Router frontal", fontsize=9.3)
# extra route line under the component
ax.text(8.20, 7.95, "routes  →  controllers  →  services  →  models",
        ha="center", va="center", fontsize=6.55, color=GRAY, zorder=3)

# ------------------------------------------------------- file and library
component(0.45, 7.80, 4.15, 1.70, "«file»", "Configuración",
          ".env · config/app.php\ndatabase.php · mail.php", fontsize=8.2,
          icon=False, dashed=True)
component(11.80, 7.80, 4.15, 1.70, "«library»", "Librerías",
          "PDO · PHPMailer · QR\nGentelella / Bootstrap", fontsize=8.2,
          icon=False, dashed=True)

# --------------------------------------------------------------- database
folder(0.45, 0.45, 7.55, 5.55, "", fill=FILL)
ax.text(0.62, 5.63, "B.D.  PostgreSQL 16", ha="left", va="center",
        fontsize=9.2, fontweight="bold", color=BLUE, zorder=5)
ax.text(0.62, 5.27, "«database» · tablas, enums, índices, RLS y triggers",
        ha="left", va="center", fontsize=7.6, color=GRAY, style="italic",
        zorder=5)

tables = [
    "T_Usuario", "T_Paciente", "T_Medico",
    "T_Especialidad", "T_Horario", "T_Bloqueo",
    "T_Cita", "T_Ficha", "T_Consulta",
    "T_Receta", "T_Pago", "T_Auditoria",
]
tx0, ty0 = 0.65, 1.10
tw, th, tgx, tgy = 2.30, 0.72, 0.18, 0.17
for i, table in enumerate(tables):
    col, row = i % 3, i // 3
    data_box(tx0 + col * (tw + tgx), ty0 + (3 - row) * (th + tgy),
             tw, th, table, fontsize=8.2)

# ---------------------------------------------------------------- reports
folder(8.35, 0.45, 7.60, 5.55, "", fill=FILL)
ax.text(8.52, 5.63, "Reportes", ha="left", va="center",
        fontsize=9.2, fontweight="bold", color=BLUE, zorder=5)
ax.text(8.52, 5.27, "«package» · salidas PDF y XLSX del sistema",
        ha="left", va="center", fontsize=7.6, color=GRAY, style="italic",
        zorder=5)

reports_pdf = [
    ("«PDF»", "R_HistoriaClinica.pdf"),
    ("«PDF»", "R_Recetas.pdf"),
    ("«PDF»", "R_Consultas.pdf"),
]
reports_xlsx = [
    ("«XLSX»", "R_HistoriaGral.xlsx"),
    ("«XLSX»", "R_Pacientes.xlsx"),
    ("«XLSX»", "R_Citas.xlsx"),
]
rx0, ry0 = 8.58, 1.10
rw, rh, rgx, rgy = 3.52, 0.92, 0.22, 0.25
for i, (st, name) in enumerate(reports_pdf):
    report_file(rx0, ry0 + (2 - i) * (rh + rgy), rw, rh, st, name,
                fontsize=8.0)
for i, (st, name) in enumerate(reports_xlsx):
    report_file(rx0 + rw + rgx, ry0 + (2 - i) * (rh + rgy), rw, rh, st,
                name, fontsize=8.0)

# --------------------------------------------------------------- relations
for label, x, y, w, h in packages:
    target_x = x + w / 2
    source_x = min(max(target_x, 5.65), 10.75)
    dep((source_x, 9.75), (target_x, y - 0.03))
dep((5.35, 8.65), (4.62, 8.65))
dep((11.05, 8.65), (11.78, 8.65))
dep((6.35, 7.35), (5.15, 6.02))
dep((10.05, 7.35), (11.70, 6.02))

fig.savefig(OUT, dpi=300, facecolor="white")
print(OUT)
