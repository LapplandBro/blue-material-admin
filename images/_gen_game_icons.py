#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Generate circular game/mod SVG badges with path-outlined labels (true center)."""
import re
from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.misc.transform import Transform

ROOT = Path(__file__).resolve().parent
OUT = ROOT / "icons" / "games"
OUT.mkdir(parents=True, exist_ok=True)
ICONS_ROOT = ROOT / "icons"

FONT_CANDIDATES = [
    Path(r"C:\Windows\Fonts\segoeuib.ttf"),
    Path(r"C:\Windows\Fonts\arialbd.ttf"),
    Path(r"C:\Windows\Fonts\consolab.ttf"),
]

# name -> (bg, accent, label) | None to skip
ICONS = {
    "web": ("#1e3a5f", "#7ea8d4", "WEB"),
    "csgo": ("#0b1a0f", "#de9b35", "CS"),
    "cs2": ("#0a1628", "#f0a020", "CS2"),
    "csource": ("#1a1208", "#e8a317", "CSS"),
    "cspromod": ("#121820", "#4ea8ff", "CSP"),
    "tf2": None,
    "hl2dm": ("#1a2838", "#88b8ea", "HL2"),
    "hl2ctf": ("#152030", "#5dade2", "CTF"),
    "dods": ("#1a1810", "#c4a35a", "DOD"),
    "ins": ("#141810", "#8fbc8f", "INS"),
    "gmod": ("#102018", "#3ddc84", "GMOD"),
    "l4d": ("#1a1008", "#c0392b", "L4D"),
    "l4d2": ("#180c08", "#e74c3c", "L4D2"),
    "nmrih": ("#101010", "#a0a0a0", "NMR"),
    "alienswarm": ("#0c1810", "#2ecc71", "AS"),
    "cure": ("#180808", "#e67e22", "CURE"),
    "nucleardawn": ("#101818", "#27ae60", "ND"),
    "synergy": ("#101828", "#3498db", "SYN"),
    "zps": ("#141010", "#9b59b6", "ZPS"),
    "dys": ("#101018", "#8e44ad", "DYS"),
    "hidden": ("#0c0c10", "#7f8c8d", "HID"),
    "pvkii": ("#181208", "#d4a017", "PVK"),
    "pdark": ("#0c1020", "#2980b9", "PD"),
    "ship": ("#101820", "#1abc9c", "SHIP"),
    "ff": ("#181008", "#e67e22", "FF"),
    "eye": ("#100818", "#9b59b6", "EYE"),
    "sourceforts": ("#101820", "#5dade2", "SF"),
}

ROOT_ICONS = {
    "unknown": ("#152040", "#7ea8d4", "?"),
    "server": ("#0c1528", "#1e90ff", "SRV"),
    "mod": ("#101d35", "#4ea8ff", "MOD"),
}


def load_font():
    for p in FONT_CANDIDATES:
        if p.is_file():
            return TTFont(str(p)), p
    raise FileNotFoundError("No bold TTF found among candidates")


FONT, FONT_PATH = load_font()
GLYPH_SET = FONT.getGlyphSet()
CMAP = FONT.getBestCmap()
UPM = FONT["head"].unitsPerEm
OS2 = FONT["OS/2"]
ASC = float(OS2.sTypoAscender)
DESC = float(OS2.sTypoDescender)


def font_size_for(label):
    n = len(label)
    if n <= 1:
        return 58.0
    if n == 2:
        return 48.0
    if n == 3:
        return 38.0
    return 30.0


def letter_spacing_for(label):
    n = len(label)
    if n <= 2:
        return -0.04
    if n == 3:
        return -0.03
    return -0.02


def text_path_centered(label, font_size, cx=64.0, cy=64.0, max_width=92.0):
    """Build SVG path `d` for label, ink-box centered at (cx, cy)."""
    tracking = letter_spacing_for(label) * UPM
    glyphs = []
    pen_x = 0.0
    for ch in label:
        gname = CMAP.get(ord(ch))
        if gname is None:
            continue
        g = GLYPH_SET[gname]
        glyphs.append((g, pen_x))
        pen_x += g.width + tracking
    if not glyphs:
        return ""

    raw_w = pen_x - tracking
    if raw_w <= 0:
        raw_w = pen_x

    scale = font_size / UPM
    if raw_w * scale > max_width:
        scale = max_width / raw_w

    # Provisional placement, then nudge by real ink bounds (fixes left-heavy glyphs).
    x0 = 0.0
    baseline = (ASC + DESC) * scale / 2.0

    def draw_all(pen_cls, ox=0.0, oy=0.0):
        dest = pen_cls(GLYPH_SET)
        for g, adv in glyphs:
            t = Transform(scale, 0, 0, -scale, x0 + adv * scale + ox, baseline + oy)
            g.draw(TransformPen(dest, t))
        return dest

    bounds_pen = draw_all(BoundsPen)
    if not bounds_pen.bounds:
        return ""
    xmin, ymin, xmax, ymax = bounds_pen.bounds
    ox = cx - (xmin + xmax) / 2.0
    oy = cy - (ymin + ymax) / 2.0

    svg_pen = draw_all(SVGPathPen, ox, oy)
    d = svg_pen.getCommands()

    def _round_num(m):
        v = float(m.group(0))
        s = "{:.2f}".format(v)
        return s.rstrip("0").rstrip(".")

    return re.sub(r"-?\d+\.\d+", _round_num, d)


def lighten(hex_color, amount=0.22):
    h = hex_color.lstrip("#")
    r, g, b = int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16)
    r = min(255, int(r + (255 - r) * amount))
    g = min(255, int(g + (255 - g) * amount))
    b = min(255, int(b + (255 - b) * amount))
    return "#{:02x}{:02x}{:02x}".format(r, g, b)


def svg_badge(bg, accent, label):
    fs = font_size_for(label)
    d = text_path_centered(label, fs)
    hi = lighten(accent, 0.35)
    mid = lighten(bg, 0.18)
    return (
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" aria-hidden="true">\n'
        "  <defs>\n"
        '    <radialGradient id="fill" cx="36%" cy="30%" r="72%">\n'
        '      <stop offset="0" stop-color="' + hi + '" stop-opacity="0.55"/>\n'
        '      <stop offset="0.45" stop-color="' + mid + '"/>\n'
        '      <stop offset="1" stop-color="' + bg + '"/>\n'
        "    </radialGradient>\n"
        '    <linearGradient id="rim" x1="20" y1="12" x2="110" y2="118" gradientUnits="userSpaceOnUse">\n'
        '      <stop offset="0" stop-color="' + hi + '"/>\n'
        '      <stop offset="0.55" stop-color="' + accent + '"/>\n'
        '      <stop offset="1" stop-color="' + bg + '"/>\n'
        "    </linearGradient>\n"
        "  </defs>\n"
        '  <circle cx="64" cy="64" r="62" fill="#03070f"/>\n'
        '  <circle cx="64" cy="64" r="58.5" fill="url(#fill)" stroke="url(#rim)" stroke-width="3"/>\n'
        '  <circle cx="64" cy="64" r="51" fill="none" stroke="' + accent + '" stroke-opacity="0.28" stroke-width="1.5"/>\n'
        '  <path d="M30 38 A40 40 0 0 1 92 30" fill="none" stroke="#ffffff" stroke-opacity="0.14" stroke-width="2.5" stroke-linecap="round"/>\n'
        '  <path d="' + d + '" fill="#000000" fill-opacity="0.35" transform="translate(0 1.25)"/>\n'
        '  <path d="' + d + '" fill="#f5f8ff"/>\n'
        "</svg>\n"
    )


def write_icon(path, bg, accent, label):
    path.write_text(svg_badge(bg, accent, label), encoding="utf-8", newline="\n")
    print("wrote {}  ({})".format(path.name, label))


def main():
    print("font: {}".format(FONT_PATH))
    for key, meta in ICONS.items():
        if meta is None:
            print("skip {}".format(key))
            continue
        bg, accent, label = meta
        write_icon(OUT / "{}.svg".format(key), bg, accent, label)

    for key, meta in ROOT_ICONS.items():
        bg, accent, label = meta
        write_icon(ICONS_ROOT / "{}.svg".format(key), bg, accent, label)
        write_icon(OUT / "{}.svg".format(key), bg, accent, label)

    aliases = {
        "hl2-fortressforever.svg": "ff.svg",
        "SourceForts.svg": "sourceforts.svg",
    }
    for dest, src in aliases.items():
        src_path = OUT / src
        if src_path.exists():
            (OUT / dest).write_text(src_path.read_text(encoding="utf-8"), encoding="utf-8", newline="\n")
            print("alias {} <- {}".format(dest, src))


if __name__ == "__main__":
    main()
