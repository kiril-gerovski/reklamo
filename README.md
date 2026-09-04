# Reklamo.bg

Онлайн магазин за промо пакети с лого на клиента — **Вариант А** от `docs/Reklamo-4-varianta-za-izgrazhdane.docx`:
WordPress + WooCommerce, плащане по банков път, специално разработена тема. Единственият външен плъгин е WooCommerce; всичко останало е наш код + конфигурация от таблото.

Пълният план: [`docs/PLAN.md`](docs/PLAN.md). Дизайн: [`design/`](design/).

## Какво има в репото

Само нашият код. WordPress и WooCommerce се инсталират от `scripts/setup.sh` и са в `.gitignore`, така че собственикът може да ги обновява от таблото.

```
wp-content/themes/reklamo/        тема — само презентация
wp-content/plugins/reklamo-core/  плъгин — цялата бизнес логика (статуси, качване, имейли)
scripts/seed.sh                       ЦЯЛАТА конфигурация на сайта като WP-CLI команди
docker-compose.yml                локална среда (Apache, MariaDB, Mailpit)
```

## Локална среда

Изисквания: Docker + Compose v2. PHP на хоста не е нужен.

```bash
cp .env.example .env      # по желание — setup.sh го прави сам
scripts/setup.sh              # нула → работещ магазин на български
```

Стекът слуша само на `127.0.0.1`. От вашата машина:

```bash
ssh -p 22022 -L 8080:127.0.0.1:8080 -L 8025:127.0.0.1:8025 dev@178.104.78.114
```

| Какво | Къде |
|---|---|
| Сайт | http://localhost:8080 |
| Админ | http://localhost:8080/wp-admin — данни в `.env` |
| Mailpit (всички изходящи имейли) | http://localhost:8025 |

## Ежедневни команди

```bash
scripts/wp <команда>          # WP-CLI, напр. scripts/wp plugin list
scripts/seed.sh               # приложи конфигурацията отново (идемпотентно)
scripts/reset.sh              # изтрий всичко и изгради наново — ГЛАВНИЯТ ТЕСТ
scripts/lint.sh               # PHPCS + WordPress Coding Standards
scripts/make-fixtures.sh 150  # тестови файлове с истински magic bytes (AI/PSD/CDR/EPS/SVG)
docker compose logs -f wp
```

## Правилото за конфигурацията

Базата данни не е в git — **`scripts/seed.sh` е**. Всяка настройка, която кликнете в таблото, трябва да се превърне в ред в `seed.sh`, иначе не съществува. `scripts/reset.sh` доказва, че нищо не е останало само в локалната база.

PHP лимитите в `config/php/uploads.ini` са нарочно ниски (64M) — имитират споделен хостинг. Не ги вдигайте, за да „мине тест“.
