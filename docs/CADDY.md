# Панель за Caddy

На обычном хостинге с Apache хватает `.htaccess` из корня: адреса вроде `/banlist` и `/admin/bans` сами попадают в `index.php`.

Если снаружи стоит **Caddy**, а PHP крутит Apache, и у Apache выключен `AllowOverride`, красивые адреса **не** доходят до панели. Apache отдаёт 404, Caddy показывает свою ошибку. При этом `index.php?p=banlist` открывается — кажется, что «сломан сайт», хотя сломан только rewrite.

## Как надо

1. PHP на бэкенде (Apache или php-fpm).  
2. Caddy 2 только проксирует запрос назад.  
3. ЧПУ делает **Apache** (`AllowOverride All` плюс `.htaccess`, либо правила в vhost).

Не дублируй ЧПУ в Caddy. Панель сама умеет редирект `index.php?p=banlist` → `/banlist`. Если Caddy перед этим переписывает `/banlist` обратно в `index.php`, получится бесконечный редирект.

## Кусок Caddyfile

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

## Apache сзади Caddy

```apache
DocumentRoot /var/www/html

<Directory /var/www/html>
	Options -Indexes +FollowSymLinks
	AllowOverride All
	Require all granted
</Directory>
```

Модуль rewrite должен быть включён.

В `config.php` укажи внешний адрес сайта, без слэша в конце:

```php
define('SB_WP_URL', 'https://example.com');
```

Caddy обязан слать `X-Forwarded-Proto` и `X-Forwarded-Host`, иначе куки и редиректы поедут.

## Проверка

Нужен именно GET, не HEAD: канонизация в PHP на HEAD не срабатывает.

```bash
curl -s -o /dev/null -w "%{http_code} redirects:%{num_redirects}\n" -L --max-redirs 3 https://example.com/banlist
```

Ожидается `200` и ноль редиректов.
