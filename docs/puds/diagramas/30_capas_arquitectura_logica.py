from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyArrowPatch, Polygon, Rectangle

OUT = Path(__file__).with_name("30_capas_arquitectura_logica.png")

W, H = 16.4, 12.8
BLUE = "#0A6EBD"
LIGHT = "#9CC3E5"
FILL = "#F7FBFF"
TEXT = "#0B3C5D"
ARROW = "#5B7C99"

plt.rcParams["font.family"] = "Arial"
fig, ax = plt.subplots(figsize=(8.4, 6.56), dpi=300)
fig.subplots_adjust(0, 0, 1, 1)
ax.set_xlim(0, W)
ax.set_ylim(0, H)
ax.set_aspect("equal")
ax.axis("off")


def band(label, y0, y1):
    ax.add_patch(Rectangle((0.2, y0), W - 0.4, y1 - y0, facecolor=FILL,
                           edgecolor=LIGHT, linewidth=1.1, zorder=0))
    ax.text(0.34, y1 - 0.17, label, fontsize=11.2, fontweight="bold",
            color=BLUE, va="center", ha="left", zorder=5,
            bbox=dict(boxstyle="square,pad=0.22", facecolor="white",
                      edgecolor=LIGHT, linewidth=1.0))


def folder(x, y, w, h, text, fontsize):
    tab_w = min(0.9, 0.34 * w)
    tab_h = 0.16
    points = [
        (x, y), (x, y + h), (x + tab_w, y + h),
        (x + tab_w + 0.12, y + h + tab_h), (x + w, y + h + tab_h),
        (x + w, y), (x, y),
    ]
    ax.add_patch(Polygon(points, closed=True, facecolor="white",
                         edgecolor=BLUE, linewidth=1.0, zorder=2))
    ax.text(x + w / 2, y + h / 2, text, ha="center", va="center",
            fontsize=fontsize, color=TEXT, linespacing=1.15, zorder=3)


def arrow_between(source, target):
    sx0, sy0, sx1, _ = source
    tx0, _, tx1, ty1 = target
    tx = min(max((sx0 + sx1) / 2, tx0 + 0.22), tx1 - 0.22)
    sx = min(max(tx, sx0 + 0.22), sx1 - 0.22)
    ax.add_patch(FancyArrowPatch(
        (sx, sy0), (tx, ty1), arrowstyle="-|>", mutation_scale=7,
        linewidth=0.8, linestyle=(0, (3, 2.5)), color=ARROW,
        alpha=0.55, shrinkA=1, shrinkB=1, zorder=1,
    ))


band("Capas Funcionales (Módulos del sistema)", 9.55, 12.35)
band("Capas Lógicas", 6.55, 9.35)
band("Capas Intermedias (Tecnologías / Frameworks)", 3.35, 6.35)
band("Capa de Software del Sistema", 0.15, 3.15)

modules = [
    "Gestión de\nUsuarios y\nSeguridad",
    "Gestión de\nPacientes",
    "Gestión Médica\ny Agenda",
    "Kiosco y\nFichas",
    "Atención\nClínica",
    "Panel de Control\ny Reportes",
]
mx0, my, mh, mgap = 0.55, 9.95, 1.45, 0.22
mw = (W - 2 * mx0 - (len(modules) - 1) * mgap) / len(modules)
module_boxes = []
for i, label in enumerate(modules):
    x = mx0 + i * (mw + mgap)
    folder(x, my, mw, mh, label, 9.2)
    module_boxes.append((x, my, x + mw, my + mh))

logical = [
    "Autenticación\ny Roles",
    "Servicios de\nNegocio",
    "Controladores\ny API",
    "Modelos de\nDatos",
]
lx0, ly, lh, lgap = 1.1, 7.0, 1.45, 0.35
lw = (W - 2 * lx0 - (len(logical) - 1) * lgap) / len(logical)
logical_boxes = []
for i, label in enumerate(logical):
    x = lx0 + i * (lw + lgap)
    folder(x, ly, lw, lh, label, 10.0)
    logical_boxes.append((x, ly, x + lw, ly + lh))

intermediate = [
    "HTML5, CSS3\ny JavaScript",
    "Bootstrap /\nGentelella",
    "API REST\n(PHP)",
    "Apache /\nPHP-FPM",
    "Git /\nGitHub",
]
ix0, iy, ih, igap = 0.75, 3.85, 1.55, 0.28
iw = (W - 2 * ix0 - (len(intermediate) - 1) * igap) / len(intermediate)
intermediate_boxes = []
for i, label in enumerate(intermediate):
    x = ix0 + i * (iw + igap)
    folder(x, iy, iw, ih, label, 9.4)
    intermediate_boxes.append((x, iy, x + iw, iy + ih))

software = ["PHP 8.1+", "PostgreSQL 16", "Navegador /\nPWA"]
sx0, sy, sh, sgap = 1.5, 0.65, 1.5, 0.45
sw = (W - 2 * sx0 - (len(software) - 1) * sgap) / len(software)
software_boxes = []
for i, label in enumerate(software):
    x = sx0 + i * (sw + sgap)
    folder(x, sy, sw, sh, label, 10.0)
    software_boxes.append((x, sy, x + sw, sy + sh))

functional_links = [
    (0, 0), (1, 1), (1, 3), (2, 1), (2, 2), (2, 3),
    (3, 1), (3, 2), (4, 1), (4, 3), (5, 2), (5, 3),
]
for source, target in functional_links:
    arrow_between(module_boxes[source], logical_boxes[target])

logical_links = [(0, 2), (1, 2), (2, 0), (2, 1), (2, 2), (2, 3), (3, 2)]
for source, target in logical_links:
    arrow_between(logical_boxes[source], intermediate_boxes[target])

software_links = [(0, 2), (1, 2), (2, 0), (2, 1), (3, 0)]
for source, target in software_links:
    arrow_between(intermediate_boxes[source], software_boxes[target])

fig.savefig(OUT, dpi=300, facecolor="white")
print(OUT)
