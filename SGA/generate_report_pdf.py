from pathlib import Path
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import cm
from reportlab.pdfgen import canvas
from reportlab.pdfbase import pdfmetrics


def wrap_text(text: str, font_name: str, font_size: int, max_width: float):
    words = text.split()
    if not words:
        return [""]
    lines = []
    current = words[0]
    for word in words[1:]:
        candidate = f"{current} {word}"
        if pdfmetrics.stringWidth(candidate, font_name, font_size) <= max_width:
            current = candidate
        else:
            lines.append(current)
            current = word
    lines.append(current)
    return lines


def main():
    base = Path(__file__).resolve().parent
    src = base / "Rapport_SGA.md"
    dst = base / "Rapport_SGA.pdf"

    text = src.read_text(encoding="utf-8").splitlines()

    c = canvas.Canvas(str(dst), pagesize=A4)
    width, height = A4
    margin_left = 2.2 * cm
    margin_right = 2.2 * cm
    margin_top = 2.0 * cm
    margin_bottom = 2.0 * cm
    max_width = width - margin_left - margin_right

    y = height - margin_top
    line_gap = 15

    def new_page():
        nonlocal y
        c.showPage()
        y = height - margin_top

    for raw in text:
        line = raw.rstrip()
        if not line:
            y -= line_gap * 0.6
            if y < margin_bottom:
                new_page()
            continue

        if line.startswith("## "):
            font_name, size = "Helvetica-Bold", 16
            content = line[3:]
            extra = 6
        elif line.startswith("### "):
            font_name, size = "Helvetica-Bold", 13
            content = line[4:]
            extra = 3
        elif line.startswith("#### "):
            font_name, size = "Helvetica-Bold", 11
            content = line[5:]
            extra = 2
        elif line.startswith("- "):
            font_name, size = "Helvetica", 11
            content = f"• {line[2:]}"
            extra = 0
        elif line[:2].isdigit() and line[1:3] == ". ":
            font_name, size = "Helvetica", 11
            content = line
            extra = 0
        elif line.startswith("# "):
            font_name, size = "Helvetica-Bold", 18
            content = line[2:]
            extra = 8
        else:
            font_name, size = "Helvetica", 11
            content = line
            extra = 0

        wrapped = wrap_text(content, font_name, size, max_width)
        for part in wrapped:
            if y < margin_bottom + line_gap:
                new_page()
            c.setFont(font_name, size)
            c.drawString(margin_left, y, part)
            y -= line_gap
        y -= extra

    c.save()
    print(f"PDF generated: {dst}")


if __name__ == "__main__":
    main()
