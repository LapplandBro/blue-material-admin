#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Generate deep-blue circular game/mod SVG icons for Material Admin."""
from pathlib import Path

ROOT = Path(__file__).resolve().parent
OUT = ROOT / "icons" / "games"
OUT.mkdir(parents=True, exist_ok=True)
ICONS_ROOT = ROOT / "icons"

# name -> (bg, accent, label 1-4 chars) | None to skip
ICONS = {
    "web": ("#1e3a5f", "#7ea8d4", "WEB"),
    "csgo": ("#0b1a0f", "#de9b35", "CS"),
    "cs2": ("#0a1628", "#f0a020", "CS2"),
    "csource": ("#1a1208", "#e8a317", "CSS"),
    "cspromod": ("#121820", "#4ea8ff", "CSP"),
    "tf2": None,  # keep existing detailed svg
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

# Fallbacks written to images/icons/ (not games/)
ROOT_ICONS = {
    "unknown": ("#152040", "#7ea8d4", "?"),
    "server": ("#0c1528", "#1e90ff", "SRV"),
    "mod": ("#101d35", "#4ea8ff", "MOD"),
}


def font_size_for(label):
    """Fit bold caps inside ~100px usable diameter (circle r=56)."""
    n = len(label)
    if n <= 1:
        return 64
    if n == 2:
        return 52
    if n == 3:
        return 40
    return 32


def svg_badge(bg, accent, label):
    # Inset circle so at 16–18px the glyph reads round, not square-clipped.
    fs = font_size_for(label)
    # Force label into safe width regardless of system font metrics.
    text_w = 88 if len(label) >= 3 else (78 if len(label) == 2 else 56)
    return f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" aria-hidden="true">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="128" y2="128" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="{accent}" stop-opacity="0.35"/>
      <stop offset="1" stop-color="{bg}"/>
    </linearGradient>
  </defs>
  <circle cx="64" cy="64" r="60" fill="{bg}"/>
  <circle cx="64" cy="64" r="54" fill="url(#g)" stroke="{accent}" stroke-width="3" stroke-opacity="0.55"/>
  <text x="64" y="68" text-anchor="middle"
        font-family="Segoe UI, Rubik, Arial, sans-serif" font-weight="700"
        font-size="{fs}" fill="#e8f0ff" letter-spacing="-0.5"
        textLength="{text_w}" lengthAdjust="spacingAndGlyphs">{label}</text>
</svg>
"""


def write_icon(path, bg, accent, label):
    path.write_text(svg_badge(bg, accent, label), encoding="utf-8", newline="\n")
    print(f"wrote {path}")


def main():
    for key, meta in ICONS.items():
        if meta is None:
            print(f"skip {key} (keep existing)")
            continue
        bg, accent, label = meta
        write_icon(OUT / f"{key}.svg", bg, accent, label)

    for key, meta in ROOT_ICONS.items():
        bg, accent, label = meta
        write_icon(ICONS_ROOT / f"{key}.svg", bg, accent, label)
        # also keep copy under games/ for older map entries
        write_icon(OUT / f"{key}.svg", bg, accent, label)

    aliases = {
        "hl2-fortressforever.svg": "ff.svg",
        "SourceForts.svg": "sourceforts.svg",
    }
    for dest, src in aliases.items():
        src_path = OUT / src
        if src_path.exists():
            (OUT / dest).write_text(src_path.read_text(encoding="utf-8"), encoding="utf-8", newline="\n")
            print(f"alias {dest} <- {src}")


if __name__ == "__main__":
    main()
