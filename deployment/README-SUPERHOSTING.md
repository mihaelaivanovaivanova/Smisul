# Качване в SuperHosting

Папката `smisul.bg` вече съществува на хостинга. Архивът съдържа само двете
папки, които трябва да се поставят вътре в нея:

```text
smisul.bg/
├── root/                публичната директория на сайта
└── backend/             частният Laravel код
```

`smisul.bg/root` трябва да остане document root на домейна. Не поставяйте
`backend` вътре в `root`, защото съдържа конфигурацията и паролата за
базата.

## 1. Създаване на архива

От PowerShell в основната папка на проекта:

```powershell
.\scripts\build-superhosting.ps1
```

Скриптът създава `smisul-superhosting.zip`. Build-ът използва относителен
адрес `/api`, затова един и същ архив работи с всеки домейн.

## 2. Качване

1. В cPanel създайте MySQL база и потребител и дайте всички права на
   потребителя за базата.
2. Качете архива **в съществуващата `smisul.bg` папка** и го разархивирайте.
   След разархивиране трябва да има точно `smisul.bg/root` и
   `smisul.bg/backend`, без допълнителна вложена `smisul.bg` директория.
3. При чиста инсталация копирайте `smisul.bg/backend/install-config.example.php` като
   `smisul.bg/backend/install-config.php`. При обновяване не заменяйте съществуващия
   `install-config.php`. Отворете `smisul.bg/backend/install-config.php` и попълнете домейна, MySQL
   данните, администраторския имейл, паролата и собствен `install_token` с
   поне 32 произволни знака. Файлът е извън `root` и не се вижда през
   браузъра.
4. Изберете PHP 8.2 или по-нова версия за домейна и включете разширенията:
   `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `mbstring`, `openssl`,
   `pdo_mysql`, `session`, `tokenizer` и `xml`.

## 3. Автоматична инсталация през браузъра

Отворете:

```text
https://ВАШИЯТ-ДОМЕЙН/install.php
```

Въведете `install_token` от конфигурационния файл и натиснете **Инсталирай
сайта**. Инсталаторът:

1. проверява PHP и връзката с MySQL;
2. създава production `.env` и сигурен `APP_KEY`;
3. инсталира Composer пакетите, ако `vendor` липсва и хостингът разрешава
   `exec()`;
4. създава всички таблици и началните данни без тестовия customer акаунт;
5. създава администратора и опитва да направи публичната storage връзка;
6. заключва повторна инсталация и изтрива `install.php`.

Ако SuperHosting е забранил `exec()` и архивът няма `vendor`, изпълнете от
`smisul.bg/backend` само:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

и отворете `install.php` отново. Ако автоматичната storage връзка е забранена,
изпълнете следната команда, като замените `CPANEL_USER`:

```bash
ln -s /home/CPANEL_USER/smisul.bg/backend/storage/app/public /home/CPANEL_USER/smisul.bg/root/storage
```

### Ако предишната инсталация е прекъснала

Грешка по време на първоначалните migrations може да остави частично
създадени таблици, без Laravel да е записал migration-а като завършен. Преди
нов опит изтрийте всички таблици от тази празна инсталационна база чрез
phpMyAdmin (операция **Drop**), или създайте нова празна база и въведете новите
данни в `backend/install-config.php`. Не изтривайте таблици от база с реални
поръчки или клиентски данни.

След това проверете:

- `https://ВАШИЯТ-ДОМЕЙН/` — начална страница;
- `https://ВАШИЯТ-ДОМЕЙН/api/v1/products` — JSON от API;
- регистрация/вход и добавяне в количката;
- `https://ВАШИЯТ-ДОМЕЙН/sitemap.xml`.

След първия администраторски вход отворете **Settings → Payments**, попълнете
отделно iCard Sandbox и Production профилите, запишете PEM ключовете и
активирайте средата, която ще използвате. Ключовете се пазят криптирано и не
се показват повторно в браузъра.

За cron добавете задача веднъж в минута:

```cron
* * * * * cd /home/CPANEL_USER/smisul.bg/backend && php artisan schedule:run >/dev/null 2>&1
```

За реални плащания сменете iCard sandbox настройките едва след като получите
production данните и ключовете от платежния оператор.

## Обновяване на вече инсталиран сайт

При качване на нов архив върху съществуващ сайт трябва да се изпълнят и новите Laravel migrations.
Каченият `root/install.php` разпознава installation lock-а и автоматично преминава в безопасен
режим за обновяване: запазва текущите `.env`, `APP_KEY`, потребители и поръчки, изпълнява само
чакащите migrations и обновява Laravel cache-а. Отворете `/install.php`, въведете същия
`install_token` от `backend/install-config.php` и след успешното обновяване файлът се изтрива сам.

Алтернативно, през SSH изпълнете:

```bash
cd /home/CPANEL_USER/smisul.bg/backend
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Не сменяйте `APP_KEY` на съществуващ сайт. Ако той бъде сменен, вече записаните криптирани iCard
ключове няма да могат да бъдат прочетени и трябва да се въведат отново в **Settings → Payments**.

Архив, изграден с локалния работещ MiswakWebsite профил, може да съдържа еднократен private import
в `backend/storage/app/private/icard-import.php`. `install.php` го записва криптирано в базата и го
изтрива веднага. Файлът е извън `root` и не е достъпен през браузъра. Активната среда остава Sandbox;
Production се попълва отделно в **Settings → Payments**.
