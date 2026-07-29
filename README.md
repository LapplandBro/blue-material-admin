# Material Admin | SourceBans

Веб-панель модерации для игровых серверов Source (CS:GO / CS2, TF2, и др.) на базе **SourceBans++** с темой **Material Admin** (`new_box`).

Автор форка: [lapplandbro](https://github.com/lapplandbro)

---

## About (коротко для GitHub)

> Material Admin panel for SourceBans++ — bans, mutes, servers, RCON, modern installer. PHP 7.1+.

Или по-русски:

> Веб-панель SourceBans++ (Material Admin): баны, муты, серверы, RCON, современный установщик. PHP 7.1+.

**Topics:** `sourcebans` `sourcemod` `csgo` `cs2` `tf2` `php` `moderation` `material-admin`

---

## Возможности

- Банлист, муты/гаги, админлист, список серверов онлайн
- Веб-RCON, кик/бан из мониторинга игроков
- Админы, группы, серверные флаги SourceMod, overrides
- Протесты и предложения банов, кастомные причины
- Уведомления, системный лог, SMTP
- SEO: `sitemap.xml`, `robots.txt`, Open Graph-обложка
- Антифрод LinkedAccounts через PARSEC API (`api.sibnet-software.ru`)
- Встроенный установщик с проверкой требований и автоочисткой `install/`

## Требования

См. [`requirements.txt`](requirements.txt).

Кратко:

| | Минимум | Рекомендуется |
|---|---|---|
| PHP | 7.1 | 8.0+ |
| MySQL / MariaDB | 5.0 | 5.5+ / 10.x |
| Расширения | mysqli, bcmath, xml, json, mbstring, openssl, curl | gd, gmp |

Нужны права на запись: `demos/`, `themes_c/`, `data/`, `config.php` (или корень сайта).

## Установка

1. Залей файлы в document root веб-сервера  
2. Создай пустую БД MySQL/MariaDB  
3. Открой `/install/` и пройди мастер  
4. Удали `install/` (кнопка на финише)  
5. При необходимости пропиши `STEAMAPIKEY` в `config.php`

Проверка без браузера:

```bash
php install/dry_run.php
```

## Стек

- PHP + MySQL (ADOdb)
- Тема Material Admin (`themes/new_box`)
- SourceBans++ 2.0.x

## Лицензия

Исходный SourceBans / SourceBans++ — **GNU GPL v3**.  
Этот форк сохраняет ту же лицензию. Сохраняй копирайты upstream при распространении.

## Credits

- [SourceBans++](https://sbpp.github.io/) / GameConnect SourceBans  
- Material Admin theme (форк / доработка панели)  
- [lapplandbro](https://github.com/lapplandbro) — развитие и сборка этого дистрибутива  
