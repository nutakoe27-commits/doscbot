# DocBot — Telegram-бот для самозанятых

Telegram-бот на PHP для самозанятых (НПД). Генерирует договоры оказания услуг и акты сдачи-приёмки в PDF.

## Возможности

- Регистрация самозанятого (ФИО, ИНН, банковские реквизиты)
- Создание договоров оказания услуг (PDF)
- Создание актов сдачи-приёмки (PDF)
- Поддержка 3 типов заказчиков: физлицо, ИП, юрлицо
- Управление клиентами
- Редактирование своих реквизитов
- НДС не облагается (422-ФЗ)

## Требования

- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Composer
- SSL-сертификат (для webhook Telegram)

## Установка

### 1. Клонирование репозитория

```bash
git clone https://github.com/your-user/docbot.git
cd docbot
```

### 2. Установка зависимостей

```bash
composer install
```

### 3. Настройка конфигурации

```bash
cp config.example.php config.php
```

Отредактируйте `config.php`, указав:
- `bot_token` — токен вашего бота от @BotFather
- `webhook_url` — URL вашего сервера (например, `https://your-domain.ru/index.php`)
- `db_host`, `db_name`, `db_user`, `db_password` — данные подключения к MySQL

### 4. Создание базы данных

```bash
mysql -u root -p your_database < install.sql
```

Или выполните SQL из файла `install.sql` через phpMyAdmin.

### 5. Установка webhook

Выполните в браузере или через curl:

```
https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook?url=https://your-domain.ru/index.php
```

### 6. Проверка

Напишите боту `/start` в Telegram.

## Структура проекта

```
index.php              — точка входа (webhook)
config.php             — конфигурация (не в git)
config.example.php     — шаблон конфига
composer.json          — зависимости
install.sql            — SQL-скрипт создания таблиц
src/
  BotHandler.php       — основная логика бота
  Database.php         — работа с MySQL (PDO)
  PdfGenerator.php     — генерация PDF (TCPDF)
  NumberToWords.php    — число прописью (русский)
templates/
  contract_service_template.html — шаблон договора
  act_template.html              — шаблон акта
storage/
  pdf/                 — сгенерированные PDF-файлы
```

## Команды бота

| Команда | Описание |
|---------|----------|
| `/start` | Начало работы / регистрация |
| `/menu` | Главное меню |
| `/mydata` | Просмотр/редактирование реквизитов |
| `/help` | Справка |

## Главное меню

- 📄 Создать договор
- 📋 Создать акт
- 👥 Мои клиенты
- ⚙️ Мои реквизиты
- ℹ️ Помощь

## Нумерация документов

- Договор: `ДОУ-YYYYMMDD-NNN` (например: ДОУ-20260330-001)
- Акт: `АКТ-YYYYMMDD-NNN` (например: АКТ-20260330-001)

## Деплой

Автоматический деплой через GitHub Actions (FTP на SpaceWeb) настроен в `.github/workflows/deploy.yml`. При пуше в `main` файлы автоматически загружаются на сервер.

Необходимые секреты в GitHub:
- `FTP_SERVER`
- `FTP_USERNAME`
- `FTP_PASSWORD`

## Лицензия

Проект для личного использования.
