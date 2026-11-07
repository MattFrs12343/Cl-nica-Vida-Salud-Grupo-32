# -*- coding: utf-8 -*-
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from puds_lib import b, cover, front_matter, OUT_DOCX
from puds_content1 import chapter1, chapter2, chapter3, chapter4
from puds_content2 import chapter5, chapter6
from puds_content3 import chapter7, chapter8, closing, annexes


def main():
    cover(b)
    front_matter(b)
    chapter1()
    chapter2()
    chapter3()
    chapter4()
    chapter5()
    chapter6()
    chapter7()
    chapter8()
    closing()
    annexes()
    b.save(OUT_DOCX)
    print("Generado:", OUT_DOCX)
    print("Figuras:", b.fig_n, "Tablas:", b.tab_n)


if __name__ == "__main__":
    main()
