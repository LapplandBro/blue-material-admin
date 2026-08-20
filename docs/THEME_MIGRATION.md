# План: выпил легаси-темы (Material Admin / Bootstrap 3 / Smarty 2)

Статус: **живой сайт и установщик — Blue V2** (Twig + Bootstrap 5.3). Папка `themes/new_box` удалена. Smarty на запросах не вызывается.

Кратко: логику банов/админов трогать не обязательно «с нуля».
Выпилили **оболочку** (`themes/new_box` layout + Smarty 2), данные и MySQL-схему переиспользуем.

Стек зафиксирован: **Twig + Bootstrap 5.3**.
Новых правил ЧПУ для v2 нет.

---

## Зачем

1. **Лицензия** — каркас темы был унаследован; NC/ThemeForest-происхождение конфликтовало с «чистым GPL».
2. **Техдолг** — Bootstrap 3, Waves/zmdi, `app.min.*`, JS layout (`ma-layout-status`).
3. **Smarty 2** — legacy, боль на PHP 8.x.

«Переписать всё с нуля на Laravel» звучит чище, но ты заново пишешь годы edge-case’ов
(баны/comms/группы/RCON/ваучеры/Steam/ACL). Разумнее: **сохранить домен + БД, заменить UI и шаблонизатор**.

---

## Фазы

### Фаза 0 — честность

- [x] `LICENSE` = GPLv3
- [x] `NOTICE` + `themes/blue_v2/NOTICE` — слои лицензий
- [x] README — тема `themes/blue_v2`

### Фаза 1 — инвентаризация контракта темы

Зафиксировать, что бэкенд отдаёт в шаблоны (не ломая PHP):

- список assign’ов по ключевым страницам: banlist, commslist, admin/*, login, servers
- обязательные id/классы, на которые вешается `scripts/sourcebans.js` / AJAX
- попапы ShowBox, вкладки `#^N`, pagination `changePage`

Артефакт: `docs/theme-contract.md` (таблица page → переменные → JS-зависимости).

### Фаза 2 — новый UI shell — сделано

Папка `themes/blue_v2/` — **без** копипасты `app.min.*` / Material layout JS.

| Было | Стало |
|------|--------|
| Bootstrap 3 | **Bootstrap 5.3** |
| Smarty 2 | **Twig** (`includes/Twig-3.28.0` на PHP ≥8.1; `includes/twig` 1.44 на PHP 7.1) |
| zmdi / Waves | Bootstrap Icons |
| xajax XML | JSON (`includes/SbAjax.php`) |

Превью-переключатель не нужен: публичка, админка и установщик — Blue V2.

Раньше: `?ui=v2` / cookie `sb_ui`, назад `?ui=legacy`. Сейчас эти параметры игнорируются.

На PHP ≥8.1 загрузчик берёт `includes/Twig-3.28.0`. Локальный XAMPP 7.1 остаётся на `includes/twig` (1.44). `composer install` по-прежнему опционален.

### Фаза 3 — перенос страниц

По приоритету трафика/риска:

1. `login` / `login2fa` / `lostpassword`
2. `banlist` / `commslist` / `servers`
3. `admin` hub + bans/comms/admins
4. settings / menu / pay / parsec / recidivism
5. редкие: upload popups, kickit/blockit, install templates

Каждая страница: новый tpl + проверка JS + smoke на PHP 8.3.

### Фаза 4 — JS без легаси-обвязки

- `sbAbs` / `sbGo` / `sbLoc` оставить (они про ЧПУ, не про Material)
- layout из `themes/new_box/js/functions.js` — удалён вместе с темой
- ShowBox / вкладки — SweetAlert + vanilla

### Фаза 5 — выпил Smarty 2

- [x] живой сайт: `CThemeBag` (`assign` / `_tpl_vars`) вместо `new Smarty()`; `themes_c` не требуется
- [x] `includes/smarty/` удалён
- [x] установщик (`install/template`) на Bootstrap 5 / vanilla JS, без Material vendor

### Фаза 6 — лицензионный финал

- [x] удалить `themes/new_box`
- [x] обновить `NOTICE`: UI shell — Blue V2 (GPLv3) + MIT/BSD vendors
- [x] README: GPLv3 + vendors
- тег релиза вроде `3.0.0` / `ui-v2` — по желанию

---

## Что сознательно НЕ делаем

- Переписывание схемы MySQL и всей доменной логики «ради чистоты»
- Смена SourceMod-протоколов / RCON ради фронта
- Переписывание HTML пунктов меню в БД: классы zmdi в `_menu` мапятся в Bootstrap Icons на лету

---

## Критерии «можно коммерчески упаковывать спокойнее»

1. Нет Material Admin layout CSS/JS в дефолтной теме
2. Нет зависимости от спорного shell в runtime
3. `NOTICE` описывает Blue V2 + обычные OSS vendors
4. Smoke: banlist, admin bans, login, servers на PHP 8.3

---

## Ответ на «проще было с нуля?»

**Долгосрочно UI — да, проще жить без этого каркаса.**
**Продуктово «весь продукт с нуля» — нет:** БД, баны, comms, группы, ACL, Steam, ваучеры, edge-cases уже оплачены кровью.
Оптимум: **новый фронт + старый домен**, не «Laravel ради Laravel» и не «ещё 10 лет красить Material».
