# SourceMod-плагины к панели

Исходники для компиляции рядом с веб-панелью. Не часть PHP-установщика — кладутся в `addons/sourcemod/` на игровом сервере.

## Material Admin

Ядро банов/мутов панели плюс меню, чекер, comms, голоса, recidivism.

`materialadmin.sp` в этой папке — слегка модифицирован, микро-фиксы в основном

## PARSEC (TF2)

Твинки по клиентскому fingerprint. Вместе это одно: **filenetwork** (передача файла) → **blackout_module** (проверка cvar) → **rebanner** (связь аккаунтов и повторный бан через Material Admin).

Нужны ещё `materialadmin.smx` и `materialadmin_check.smx`.

Gamedata `gamedata/filenetwork.txt` — в `addons/sourcemod/gamedata/` (движок TF2).

Метод FastDL: `fastdl/serve.php` кладётся в корень HTTP FastDL (рядом с `materials/`, `models/`, `sound/` и т.д.). Без него клиент не получает fingerprint-файл, и антифрод не склеивает твинки. В шапке файла — nginx/PHP-FPM (`gzip off`, `Content-Length`) и путь `$fingerprintFilePath` (должен совпасть с путём в rebanner).

## Сборка

Компилятор SourceMod 1.11+, include из этой папки `scripting/include/` плюс стандартные SM (`sourcemod`, `sdktools`, `dhooks` для filenetwork).
