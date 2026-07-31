# Blue Material Admin за Caddy

На обычном shared hosting (Apache + `AllowOverride`) хватает `.htaccess` из корня панели — ЧПУ (`/banlist`, `/admin/bans`, …) работает само.

Если схема **Caddy → Apache (PHP)** и у Apache выключен `AllowOverride`, запросы вроде `/banlist` **не** попадают в `index.php`. Apache отдаёт 404/500, Caddy показывает свою error-page — сайт «сломан», хотя `index.php?p=banlist` открывается.

## Что нужно

1. PHP-панель на бэкенде (Apache/`php-fpm` и т.п.).
2. Caddy 2.x с `reverse_proxy` на этот бэкенд.
3. **ЧПУ на Apache** (`AllowOverride All` + `.htaccess` или `RewriteRule` в vhost).

### Не делай rewrite ЧПУ в Caddy вместе с канонизацией в PHP

Панель умеет `301 index.php?p=banlist → /banlist`. Если Caddy до прокси переписывает `/banlist` в `/index.php?p=banlist`, получается петля:

`/banlist` → rewrite → PHP 301 → `/banlist` → … → **ERR_TOO_MANY_REDIRECTS**

Правильно: **только Apache** (внутренний rewrite, `REQUEST_URI` остаётся `/banlist`). В Caddy — только `reverse_proxy` + `X-Forwarded-*`.

## Минимальный фрагмент Caddyfile (сайт панели)

```caddy
example.com, www.example.com {
	encode gzip zstd

	reverse_proxy 127.0.0.1:5987 {
		header_up Host {host}
		header_up X-Forwarded-Proto {scheme}
		header_up X-Forwarded-Host {host}
		header_up X-Forwarded-Port 443
		header_up X-Real-IP {remote_host}
	}
}
```

## Apache за Caddy (обязательно для ЧПУ)

В `<VirtualHost>` бэкенда:

```apache
DocumentRoot /var/www/html

<Directory /var/www/html>
	Options -Indexes +FollowSymLinks
	AllowOverride All
	Require all granted
</Directory>
```

И модуль: `a2enmod rewrite` → `systemctl reload apache2`.

## `config.php`

```php
define('SB_WP_URL', 'https://example.com');  // без слэша в конце, https как снаружи
```

Caddy обязан слать `X-Forwarded-Proto` / `X-Forwarded-Host` (см. фрагмент выше), иначе куки Secure / редиректы поедут.

## Проверка после деплоя

```bash
# Важен GET (не curl -I / HEAD): HEAD не проходит через PHP-канонизацию.
curl -s -o /dev/null -w "%{http_code} redirects:%{num_redirects}\n" -L --max-redirs 3 https://example.com/banlist
# ожидается: 200 redirects:0
```
