# SourceMod к этой панели

Плагины для игрового сервера, не для PHP-установщика. После компиляции файлы `.smx` кладутся в `addons/sourcemod/plugins/` на машине с игрой.

## Material Admin

Ядро банов и мутов, меню, проверка прошлых наказаний, comms, голоса, раздел «рецидив».

Сборка в этой папке — без мем-звуков. Если античит (StAC / Little Anti-Cheat) даёт бан, игрока кикает **сразу**, без паузы в три секунды.

## PARSEC на TF2

Ищет твинки по файлу-отпечатку на клиенте. Это не один плагин, а связка:

1. **filenetwork** — качает и отдаёт файл  
2. **blackout_module** — смотрит клиентские настройки  
3. **rebanner** — склеивает аккаунты и снова банит через Material Admin  

Нужны ещё скомпилированные `materialadmin` и `materialadmin_check`.

Файл `gamedata/filenetwork.txt` копируется в `addons/sourcemod/gamedata/` (подписи под TF2).

FastDL: скрипт `fastdl/serve.php` кладётся в **корень веб-раздачи** карт и моделей (рядом с папками `materials/`, `models/`, `sound/`). Без него клиент не получает отпечаток, и твинки не склеиваются. В шапке скрипта — как выключить gzip в nginx и какой путь прописать в `$fingerprintFilePath` (тот же, что в rebanner).

## Как собирать

SourceMod 1.11 или новее. Include — из `scripting/include/` плюс стандартные `sourcemod`, `sdktools` и `dhooks` (для filenetwork).
