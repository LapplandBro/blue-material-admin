# Ветка php-8.3

Эта ветка — для хостинга на **PHP 8.3** (подойдёт и 8.2). Если сайт ещё крутится на **PHP 7.1**, не бери её: там ветка [`main`](https://github.com/LapplandBro/blue-material-admin/tree/main).

Интерфейс здесь один: **Blue V2** (Twig и Bootstrap 5). Старой светлой темы в этой ветке нет.

## Что нужно

- PHP 8.2 или новее, лучше 8.3  
- Расширения: mysqli, mbstring, json, openssl, curl, bcmath, xml  
- Для опроса игровых серверов — 64-битный PHP или расширение gmp  
- ЧПУ как обычно: Apache `mod_rewrite` / `AllowOverride`

## Как переехать с main

1. Скачай код именно с `php-8.3`.  
2. Не затирай `config.php` и `data/`.  
3. Это смена версии PHP, а не «просто накатить файлы» на семёрку. SourceQuery 6 на PHP 7 не встанет.

Ветки `php-8.3` и `main` специально не сливаются: у них разный runtime.
