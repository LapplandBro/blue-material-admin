# Ветка `php-8.3` — PHP 8.3.22

Отдельная ветка репозитория [blue-material-admin](https://github.com/LapplandBro/blue-material-admin).  
Ветка **`main`** по-прежнему рассчитана на **PHP 7.1.33**. Сюда — только если на хостинге PHP **8.3.x** (проверено под **8.3.22**).

## Что изменено относительно `main`

| Компонент | Изменение |
|-----------|-----------|
| **ADOdb** | Патч: `each()` → `foreach`, `get_magic_quotes_gpc` с `function_exists` |
| **Smarty 2.6** | Патч: `create_function` → closure, `each()` → `current`/`next` |
| **xajax** | `get_magic_quotes_gpc` только через `function_exists` (CSRF не тронут) |
| **SourceQuery** | Замена вендорки на **xPaw PHP-Source-Query 6.0.0** (min PHP 8.2) |
| **CServerControl** | Загрузка без `bootstrap.php`, API панели прежний |
| **App** | Сигнатуры optional-before-required; `utf8_*` → `mb_convert_encoding`; `strftime` → `date` где нужно |

## Требования

- PHP **≥ 8.2** (ветка целится на **8.3.22**)
- Расширения: `mysqli`, `mbstring`, `json`, `openssl`, `curl`, `bcmath`, `xml`
- Для SourceQuery: **64-bit PHP** или `gmp`
- Apache `mod_rewrite` / `AllowOverride` для ЧПУ (как на `main`)

## Установка / обновление

1. Бери код с ветки `php-8.3` (не с `main`).
2. Не затирай `config.php` / `data/`.
3. Очисти `themes_c/` после заливки.
4. Если раньше стоял `main` на 7.1 — это **смена runtime**, не «просто обновить файлы» на том же PHP.

## Не делать

- Не мержить эту ветку в `main` без отдельного решения (ломает 7.1).
- Не ставить SourceQuery 6 на PHP 7.x.
- Не отключать CSRF в xajax.
