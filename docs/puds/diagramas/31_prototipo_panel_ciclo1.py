# -*- coding: utf-8 -*-
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

OUT = Path(__file__).with_suffix(".png")
FONTS = Path(r"C:\Windows\Fonts")

NAVY = "#0B3C5D"
BLUE = "#0A6EBD"
TEAL = "#1B998B"
GRAY = "#5B6B7A"
LIGHT = "#EAF4FB"
BORDER = "#B8D4EA"
PALE = "#F7FAFD"
WHITE = "#FFFFFF"
BLACK = "#1F2933"


def font(name, size):
    return ImageFont.truetype(str(FONTS / name), size)


REG = font("segoeui.ttf", 24)
REG_SM = font("segoeui.ttf", 20)
REG_MD = font("segoeui.ttf", 26)
BOLD = font("segoeuib.ttf", 26)
BOLD_MD = font("segoeuib.ttf", 30)
BOLD_LG = font("segoeuib.ttf", 50)
BOLD_XL = font("segoeuib.ttf", 36)


def box(draw, xy, fill=None, outline=BLACK, width=2, radius=0):
    if radius:
        draw.rounded_rectangle(xy, radius=radius, fill=fill, outline=outline, width=width)
    else:
        draw.rectangle(xy, fill=fill, outline=outline, width=width)


def text(draw, xy, value, f, fill=BLACK, anchor="la"):
    draw.text(xy, value, font=f, fill=fill, anchor=anchor)


def badge(draw, xy, value, f, fill, fg=WHITE, pad_x=18, pad_y=8, right=False):
    x, y = xy
    bbox = draw.textbbox((0, 0), value, font=f)
    w = bbox[2] - bbox[0]
    h = bbox[3] - bbox[1]
    if right:
        x -= w + 2 * pad_x
    box(draw, (x, y, x + w + 2 * pad_x, y + h + 2 * pad_y), fill=fill, outline=fill, radius=10)
    text(draw, (x + pad_x, y + pad_y - 2), value, f, fg)


img = Image.new("RGB", (1600, 1000), WHITE)
d = ImageDraw.Draw(img)

box(d, (0, 0, 1599, 999), fill=WHITE, outline=BLACK, width=3)

d.rectangle((0, 0, 1599, 92), fill=NAVY)
text(d, (36, 46), "Clínica Vida Salud — Panel administrativo", BOLD_XL, WHITE, "lm")
badge(d, (1564, 24), "Ciclo 1 · Plataforma base", REG, TEAL, WHITE, right=True)

d.rectangle((0, 92, 300, 999), fill="#F4F8FC")
d.line((300, 92, 300, 999), fill=BORDER, width=3)
text(d, (28, 120), "CICLO 1", BOLD, GRAY)

menu = [
    ("Inicio", 160, False),
    ("Pacientes", 210, True),
    ("Médicos", 260, False),
    ("Especialidades", 310, False),
    ("Horarios y cupos", 360, False),
    ("Usuarios", 410, False),
]
for label, y, selected in menu:
    if selected:
        d.rectangle((18, y - 12, 282, y + 36), fill=LIGHT)
        d.rectangle((18, y - 12, 24, y + 36), fill=BLUE)
    text(d, (40, y), label, BOLD if selected else REG, BLUE if selected else BLACK)

box(d, (24, 748, 276, 944), fill=WHITE, outline=BORDER, width=2, radius=10)
text(d, (42, 770), "Prototipo de baja", BOLD, NAVY)
text(d, (42, 802), "fidelidad", BOLD, NAVY)
text(d, (42, 846), "Ciclo 1 · Plataforma base", REG_SM, TEAL)
text(d, (42, 882), "UC08, UC20–UC22", REG_SM, GRAY)
text(d, (42, 914), "Catálogos y horarios", REG_SM, GRAY)

text(d, (340, 130), "Pacientes", BOLD_LG, NAVY)
badge(d, (600, 144), "Ciclo 1", REG, LIGHT, TEAL)
text(d, (340, 198), "UC08 · Gestión de pacientes", REG_MD, GRAY)

box(d, (340, 246, 930, 300), fill=WHITE, outline=BORDER, width=2, radius=8)
text(d, (362, 273), "Buscar por CI, nombre o apellido", REG, GRAY, "lm")
box(d, (944, 246, 1080, 300), fill=BLUE, outline=BLUE, width=2, radius=8)
text(d, (1012, 273), "Buscar", BOLD, WHITE, "mm")
box(d, (1300, 246, 1560, 300), fill=TEAL, outline=TEAL, width=2, radius=8)
text(d, (1430, 273), "Nuevo paciente", BOLD, WHITE, "mm")

table_x0, table_x1 = 340, 1560
header_y, row_h = 340, 64
cols = [340, 520, 960, 1150, 1300, 1560]
headers = ["CI", "Nombre y apellido", "Teléfono", "Estado", "Acciones"]
d.rectangle((table_x0, header_y, table_x1, header_y + 54), fill=NAVY)
for i, head in enumerate(headers):
    text(d, (cols[i] + 16, header_y + 27), head, BOLD, WHITE, "lm")

rows = [
    ("12345678", "Ana Torres Quispe", "700-12345", "Activo"),
    ("87654321", "Luis Rojas Mamani", "711-98765", "Activo"),
    ("45678912", "María Gutiérrez Choque", "722-45678", "Inactivo"),
    ("33445566", "Jorge Salazar Rojas", "733-11223", "Activo"),
]
for idx, (ci, name, phone, status) in enumerate(rows):
    y0 = header_y + 54 + idx * row_h
    y1 = y0 + row_h
    d.rectangle((table_x0, y0, table_x1, y1), fill=WHITE if idx % 2 == 0 else PALE)
    d.line((table_x0, y1, table_x1, y1), fill=BORDER, width=1)
    text(d, (cols[0] + 16, y0 + row_h / 2), ci, REG, BLACK, "lm")
    text(d, (cols[1] + 16, y0 + row_h / 2), name, REG, BLACK, "lm")
    text(d, (cols[2] + 16, y0 + row_h / 2), phone, REG, BLACK, "lm")
    color = TEAL if status == "Activo" else GRAY
    badge(d, (cols[3] + 12, y0 + 15), status, REG_SM, color, WHITE, pad_x=14, pad_y=6)
    action = "Editar · Desactivar" if status == "Activo" else "Reactivar"
    text(d, (cols[4] + 16, y0 + row_h / 2), action, REG_SM, BLUE, "lm")
for x in cols[1:-1]:
    d.line((x, header_y, x, header_y + 54 + len(rows) * row_h), fill=BORDER, width=1)
d.rectangle((table_x0, header_y, table_x1, header_y + 54 + len(rows) * row_h), outline=BORDER, width=2)

box(d, (340, 650, 1090, 858), fill=LIGHT, outline=BORDER, width=2, radius=10)
text(d, (365, 675), "Catálogos y disponibilidad", BOLD_MD, NAVY)
text(d, (365, 720), "Médicos y especialidades · UC20", REG, BLACK)
text(d, (365, 758), "Horarios y cupos · UC21", REG, BLACK)
box(d, (365, 802, 865, 824), fill=WHITE, outline=BORDER, width=1, radius=6)
d.rectangle((365, 802, 615, 824), fill=TEAL)
d.rectangle((615, 802, 865, 824), fill=BLUE)
text(d, (365, 836), "Web 50%", REG_SM, TEAL)
text(d, (865, 836), "Kiosco 50%", REG_SM, BLUE, "ra")

box(d, (1120, 650, 1560, 858), fill=WHITE, outline=BORDER, width=2, radius=10)
text(d, (1145, 675), "Alcance del Ciclo 1", BOLD_MD, NAVY)
for i, item in enumerate(["Pacientes (UC08)", "Médicos y especialidades (UC20)", "Horarios y cupos (UC21)", "Usuarios (UC22)"]):
    text(d, (1145, 720 + i * 32), "•  " + item, REG_SM, BLACK)

text(d, (340, 910), "Ciclo 1: plataforma base y catálogos. La reserva web, el kiosco y la historia clínica se prototipan en el Ciclo 2.", REG_SM, GRAY)

img.save(OUT, "PNG")
print(OUT)
