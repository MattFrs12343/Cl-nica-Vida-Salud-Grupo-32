# -*- coding: utf-8 -*-
"""Genera los iconos UML de paquete usados en la tabla 5.1.1."""
import os
from PIL import Image, ImageDraw, ImageFont

BASE = os.path.dirname(os.path.abspath(__file__))
FONT_BOLD = r"C:\Windows\Fonts\arialbd.ttf"
FONT_REG = r"C:\Windows\Fonts\arial.ttf"

W, H = 1400, 760
TAB_W, TAB_H = 720, 118
BODY_TOP = 118
BODY_LEFT, BODY_RIGHT, BODY_BOTTOM = 58, 1342, 706

PACKAGES = {
    "paquete_pk01.png": ("PK-01", ["Acceso público", "y reserva web"], "Casos de uso UC01–UC07"),
    "paquete_pk02.png": ("PK-02", ["Citas y agenda"], "Casos de uso UC08–UC14"),
    "paquete_pk03.png": ("PK-03", ["Atención y", "administración"], "Casos de uso UC15–UC25"),
    "paquete_pk04.png": ("PK-04", ["Servicios", "compartidos"], "Paquete transversal"),
}


def fit_font(draw, text, path, max_width, start=64):
    size = start
    while size > 24:
        font = ImageFont.truetype(path, size)
        box = draw.textbbox((0, 0), text, font=font)
        if box[2] - box[0] <= max_width:
            return font
        size -= 2
    return ImageFont.truetype(path, 24)


def draw_package(filename, code, title_lines, subtitle):
    img = Image.new("RGBA", (W, H), (255, 255, 255, 0))
    draw = ImageDraw.Draw(img)

    # Sombra.
    shadow = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    sd = ImageDraw.Draw(shadow)
    sd.rounded_rectangle((BODY_LEFT + 16, BODY_TOP + 16, BODY_RIGHT + 16, BODY_BOTTOM + 16),
                         radius=10, fill=(0, 0, 0, 40))
    sd.rounded_rectangle((BODY_LEFT + 16, 118 + 16, BODY_LEFT + TAB_W + 16, 236 + 16),
                         radius=8, fill=(0, 0, 0, 40))
    img.alpha_composite(shadow)
    draw = ImageDraw.Draw(img)

    # Cuerpo y pestaña del paquete UML.
    draw.rounded_rectangle((BODY_LEFT, BODY_TOP, BODY_RIGHT, BODY_BOTTOM), radius=10,
                           fill=(253, 246, 227, 255), outline=(91, 70, 54, 255), width=5)
    draw.rounded_rectangle((BODY_LEFT, 118, BODY_LEFT + TAB_W, 236), radius=8,
                           fill=(243, 230, 211, 255), outline=(91, 70, 54, 255), width=5)
    # Ocultar la línea inferior de la pestaña para integrarla al cuerpo.
    draw.rectangle((BODY_LEFT + 4, 226, BODY_LEFT + TAB_W - 4, 240), fill=(243, 230, 211, 255))

    font_code = ImageFont.truetype(FONT_BOLD, 58)
    draw.text((BODY_LEFT + 34, 143), code, font=font_code, fill=(58, 42, 30, 255))

    # Título centrado en el cuerpo.
    max_text_w = BODY_RIGHT - BODY_LEFT - 120
    title_font = ImageFont.truetype(FONT_BOLD, 62)
    line_h = 78
    total_h = line_h * len(title_lines)
    y = BODY_TOP + (BODY_BOTTOM - BODY_TOP - total_h) // 2 - 34
    for line in title_lines:
        font = fit_font(draw, line, FONT_BOLD, max_text_w, 62)
        box = draw.textbbox((0, 0), line, font=font)
        x = (W - (box[2] - box[0])) // 2
        draw.text((x, y), line, font=font, fill=(58, 42, 30, 255))
        y += line_h

    sub_font = ImageFont.truetype(FONT_REG, 34)
    box = draw.textbbox((0, 0), subtitle, font=sub_font)
    x = (W - (box[2] - box[0])) // 2
    draw.text((x, BODY_BOTTOM - 90), subtitle, font=sub_font, fill=(107, 90, 74, 255))

    img.save(os.path.join(BASE, filename))
    print("Generado:", filename)


if __name__ == "__main__":
    for filename, (code, lines, subtitle) in PACKAGES.items():
        draw_package(filename, code, lines, subtitle)
