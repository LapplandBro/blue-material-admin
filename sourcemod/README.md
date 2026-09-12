# SourceMod-плагины к панели

Исходники для компиляции рядом с веб-панелью. Не часть PHP-установщика — кладутся в `addons/sourcemod/` на игровом сервере.

## Material Admin

Ядро банов/мутов панели плюс меню, чекер, comms, голоса, recidivism.

`materialadmin.sp` в этой папке — без мем-звуков; бан от античита (StAC / Little Anti-Cheat) кикает сразу, без паузы 3 секунды.

## PARSEC (TF2)

Твинки по клиентскому fingerprint. Вместе это одно: **filenetwork** (передача файла) → **blackout_module** (проверка cvar) → **rebanner** (связь аккаунтов и повторный бан через Material Admin).

Нужны ещё `materialadmin.smx` и `materialadmin_check.smx`.

Gamedata `gamedata/filenetwork.txt` — в `addons/sourcemod/gamedata/` (движок TF2).

## Сборка

Компилятор SourceMod 1.11+, include из этой папки `scripting/include/` плюс стандартные SM (`sourcemod`, `sdktools`, `dhooks` для filenetwork).
