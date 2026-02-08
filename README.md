# Bonus System — Interview Task

## Назначение

Сервис рассчитывает маркетинговые бонусы партнёров.

**Условие завершения задания:** все тесты должны проходить (`PASS`).
---

## Бизнес-процесс

1. Запускается расчёт за период.
2. Получаются активные партнёры.
3. Рассчитывается бонус для каждого партнёра по продажам.
4. Результат сохраняется в БД.
5. Печатаются диагностические метрики.

---

## Требования к реализации (SLA)

### 1. Скорость доступа к данным
- Бизнес-требование: доступ к продажам не должен быть узким местом.
- Критерий: общее время тестового расчёта `<= 0.30 sec`.

### 2. Эффективность использования ресурсов
- Бизнес-требование: обработка больших выборок без существенного роста RAM.
- Критерий: пиковое потребление памяти в тестовом сценарии `<= 6 MB`.

### 3. Чистота входных данных (Data Integrity)
- Бизнес-требование: не начислять выплаты на невалидных данных.
- Критерий: невалидный `tier` должен приводить к ошибке.

### 4. Модульность и гибкость архитектуры
- Бизнес-требование: источник аналитических данных должен быть заменяемым.
- Критерий: сервис не зависит от конкретной реализации хранилища.

### 5. Строгость контрактов данных
- Бизнес-требование: формат данных для интеграций должен быть предсказуемым.
- Критерий: аналитика возвращает объект-сущность контракта.

### 6. Корректность вычислений
- Бизнес-требование: промежуточные и итоговые расчёты должны быть арифметически корректными.
- Критерий: результаты вычисления не должны быть нулевыми или отрицательными там, где ожидаются положительные значения.

---

## Команда запуска приложения (Codespaces)

```bash
php bin/console.php calculate-bonuses 2026-02
```

## Команда запуска тестов (Codespaces)

```bash
vendor/bin/phpunit --testdox --colors=never
```

## Полезные команды (Codespaces)

```bash
composer install
php bin/setup-db.php
php bin/console.php calculate-bonuses 2026-02
vendor/bin/phpunit --testdox --colors=never
```

## Команда запуска приложения (локально через Docker)

```bash
docker-compose exec php php bin/console.php calculate-bonuses 2026-02
```

## Команда запуска тестов (локально через Docker)

```bash
docker-compose exec php vendor/bin/phpunit --testdox --colors=never
```

## Полезные команды (локально через Docker)

```bash
docker-compose up -d
docker-compose exec php composer install
docker-compose exec php php bin/setup-db.php
docker-compose exec php php bin/console.php calculate-bonuses 2026-02
docker-compose exec php vendor/bin/phpunit --testdox --colors=never
```

---

## Как читать тесты

- `ERROR` — нарушение требования.
- `SLO` — ожидаемая норма.
- `Факт` — измеренное значение.

Дополнительно проверяется:
- базовая корректность бизнес-расчёта бонусов на фиксированном сценарии.

Основные файлы проверок:
- `tests/Service/BonusCalculatorTest.php`
- `tests/Command/CalculateBonusesCommandTest.php`
- `tests/Service/SalesAnalyzerTest.php`

---

## Как пользоваться Xdebug

Xdebug уже установлен в контейнере.

### Проверка статуса Xdebug

Codespaces:
```bash
php -v | grep -i xdebug
```

Docker local:
```bash
docker-compose exec php php -v | grep -i xdebug
```

### Запуск отладки в VS Code

1. Поставь breakpoint (`F9`) в нужном месте.
2. Открой `Run and Debug`.
3. Запусти конфигурацию `Listen for Xdebug`.
4. Запусти команду приложения:
   - Codespaces: `php bin/console.php calculate-bonuses 2026-02`
   - Docker local: `docker-compose exec php php bin/console.php calculate-bonuses 2026-02`

Если breakpoint не срабатывает, перезапусти debug-сессию и проверь конфигурацию в `.vscode/launch.json`.
