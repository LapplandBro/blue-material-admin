#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Generate deep-blue circular game/mod SVG icons for Material Admin."""
from pathlib import Path

OUT = Path(__file__).resolve().parent / "icons" / "games"
OUT.mkdir(parents=True, exist_ok=True)

# name -> (bg, accent, label 1-4 chars, optional second line)
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
    "ff": ("#181008", "#e67e22", "FF"),  # Fortress Forever
    "eye": ("#100818", "#9b59b6", "EYE"),
    "sourceforts": ("#101820", "#5dade2", "SF"),
    "unknown": ("#152040", "#7ea8d4", "?"),
    "server": ("#0c1528", "#1e90ff", "SRV"),
    "mod": ("#101d35", "#4ea8ff", "MOD"),
}


def svg_badge(bg, accent, label):
    # Scale font by label length
    fs = 120 if len(label) <= 2 else (96 if len(label) <= 3 else 78)
    return f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" aria-hidden="true">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="128" y2="128" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="{accent}" stop-opacity="0.35"/>
      <stop offset="1" stop-color="{bg}"/>
    </linearGradient>
  </defs>
  <circle cx="64" cy="64" r="64" fill="{bg}"/>
  <circle cx="64" cy="64" r="58" fill="url(#g)" stroke="{accent}" stroke-width="3" stroke-opacity="0.55"/>
  <text x="64" y="64" text-anchor="middle" dominant-baseline="central"
        font-family="Segoe UI, Rubik, Arial, sans-serif" font-weight="700"
        font-size="{fs}" fill="#e8f0ff" letter-spacing="-1">{label}</text>
</svg>
"""


def main():
    for key, meta in ICONS.items():
        if meta is None:
            print(f"skip {key} (keep existing)")
            continue
        bg, accent, label = meta
        path = OUT / f"{key}.svg"
        path.write_text(svg_badge(bg, accent, label), encoding="utf-8", newline="\n")
        print(f"wrote {path.name}")

    # Alias filenames matching DB icon basenames without extension ambiguity
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
