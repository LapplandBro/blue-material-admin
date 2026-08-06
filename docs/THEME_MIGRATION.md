# План: выпил легаси-темы (Material Admin / Bootstrap 3 / Smarty 2)

Статус: **запланировано**  
Цель: честный GPLv3-дистрибутив без спорного UI-каркаса + нормальный фронт.

Кратко: логику банов/админов трогать не обязательно «с нуля».
Выпиливаем **оболочку** (`themes/new_box` layout + Smarty 2), данные и MySQL-схему можно переиспользовать.

---

## Зачем

1. **Лицензия** — каркас темы унаследован; NC/ThemeForest-происхождение конфликтует с «чистым GPL/коммерческой упаковкой».
2. **Техдолг** — Bootstrap 3, Waves/zmdi, `app.min.*`, JS layout (`ma-layout-status`), костыли `<base>` / `#^` / select.
3. **Smarty 2** — legacy, боль на PHP 8.x, слабый DX.

«Переписать всё с нуля на Laravel» звучит чище, но ты заново пишешь годы edge-case’ов
(баны/comms/группы/RCON/ваучеры/Steam/ACL). Разумнее: **сохранить домен + БД, заменить UI и шаблонизатор**.

---

## Фазы

### Фаза 0 — честность (сейчас)

- [x] `LICENSE` = GPLv3  
- [x] `NOTICE` + `themes/new_box/NOTICE` — слои лицензий  
- [x] README — явное предупреждение про тему  
- [ ] Не заявлять «весь zip = чисто GPL для коммерции», пока жив `themes/new_box` shell  

### Фаза 1 — инвентаризация контракта темы

Зафиксировать, что бэкенд отдаёт в шаблоны (не ломая PHP):

- список assign’ов Smarty по ключевым страницам: banlist, commslist, admin/*, login, servers  
- обязательные id/классы, на которые вешается `scripts/sourcebans.js` / xajax  
- попапы ShowBox, вкладки `#^N`, pagination `changePage`  

Артефакт: `docs/theme-contract.md` (таблица page → переменные → JS-зависимости).

### Фаза 2 — новый UI shell (parallel theme)

Новая папка, например `themes/blue_v2/`, **без** копипасты `app.min.*` / Material layout JS.

Стек (предложение, можно скорректировать):

| Было | Станет |
|------|--------|
| Bootstrap 3 | Bootstrap 5 **или** лёгкий свой CSS |
| Smarty 2 | Twig 3 (или Blade-подобный тонкий слой) |
| zmdi / Waves | SVG / один icon set (MIT) |
| MooTools-хвостики в UI | только то, что ещё нужно для xajax, потом тоже вынос |

Правила:

- не тащить классы `fg-line`, `sw-toggled`, `ma-*`  
- вендоры только с SPDX (MIT/Apache)  
- визуал может быть похож; **структура файлов и CSS — свои**

Критерий готовности фазы: login + banlist + один admin-раздел на новой теме.

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
- layout из `themes/new_box/js/functions.js` — выкинуть  
- ShowBox / вкладки — либо тонкая замена, либо минимальный vanilla  
- цель: убрать зависимость UI от MooTools там, где возможно (xajax может остаться на переходный период)

### Фаза 5 — выпил Smarty 2

- адаптер `Theme::assign/display` → Twig  
- удалить `includes/smarty/` после миграции всех tpl  
- установщик (`install/template`) перевести тем же проходом или простым PHP

### Фаза 6 — лицензионный финал

- удалить `themes/new_box` (или оставить archive-ветку)  
- обновить `NOTICE`: «UI shell — original / MIT stack»  
- README: один блок GPLv3 + vendors  
- тег релиза вроде `3.0.0` / `ui-v2`

---

## Что сознательно НЕ делаем в первой итерации

- Переписывание схемы MySQL и всей доменной логики «ради чистоты»  
- Смена SourceMod-протоколов / RCON ради фронта  
- Big-bang «выкатить всё разом без parallel theme»

---

## Критерии «можно коммерчески упаковывать спокойнее»

1. Нет Material Admin layout CSS/JS в дефолтной теме  
2. Нет зависимости от спорного shell в runtime  
3. `NOTICE` больше не предупреждает про legacy UI  
4. Smoke: banlist, admin bans, login, servers на PHP 8.3  

---

## Ответ на «проще было с нуля?»

**Долгосрочно UI — да, проще жить без этого каркаса.**  
**Продуктово «весь продукт с нуля» — нет:** БД, баны, comms, группы, ACL, Steam, ваучеры, edge-cases уже оплачены кровью.  
Оптимум: **новый фронт + старый домен**, не «Laravel ради Laravel» и не «ещё 10 лет красить Material».
