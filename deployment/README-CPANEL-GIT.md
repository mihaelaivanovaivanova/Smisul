# Git deployment в SuperHosting

Проектът се клонира извън публичната директория, а `.cpanel.yml` публикува
само готовите файлове на сайта. Активният deployment branch е
`NewFunnelLayout`.

## Структура на хостинга

```text
/home/CPANEL_USER/
├── repositories/
│   └── smisul/                 # cPanel Git checkout (не е публичен)
└── smisul.bg/
    ├── root/                   # document root: React, .htaccess, laravel.php
    └── backend/                # Laravel, vendor, .env и private storage
```

`.env`, `backend/install-config.php`, Laravel storage, логовете, качените
файлове и публичната storage връзка се запазват при следващ deployment.

## Еднократна подготовка

1. Домейнът трябва да сочи към `/home/CPANEL_USER/smisul.bg/root`.
2. В cPanel изберете PHP 8.2 или по-нова версия.
3. Инсталирайте Composer в home директорията, ако командата `composer` не е
   налична:

   ```bash
   cd "$HOME"
   php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
   php composer-setup.php --filename=composer.phar
   rm composer-setup.php
   ```

4. В **cPanel → Git Version Control** клонирайте:

   ```text
   https://github.com/mihaelaivanovaivanova/Smisul.git
   ```

   Използвайте repository path `/home/CPANEL_USER/repositories/smisul`, а не
   `smisul.bg`.
5. От **Manage → Checked-Out Branch** изберете `NewFunnelLayout`.

## Подготовка и качване на промени

Frontend build-ът се генерира локално, защото Node.js версията в shared
hosting плана може да е по-стара от изискваната от Vite.

```powershell
git switch NewFunnelLayout
.\scripts\prepare-cpanel-deploy.cmd
git add .
git commit -m "Prepare cPanel deployment"
git push origin NewFunnelLayout
```

След push отворете **Git Version Control → Manage → Pull or Deploy**:

1. натиснете **Update from Remote**;
2. проверете, че текущият branch е `NewFunnelLayout`;
3. натиснете **Deploy HEAD Commit**.

`.cpanel.yml` стартира `deployment/deploy-cpanel.sh`. Скриптът проверява
branch-а, инсталира production Composer пакетите, създава само `root` и
`backend`, копира файловете и при вече инсталиран сайт изпълнява чакащите
Laravel migrations.

`.cpanel.yml` трябва да остане в основната директория на Git repository-то.
Не го местете и не го качвайте ръчно в `smisul.bg/root`. cPanel го прочита
автоматично при **Deploy HEAD Commit**.

## Изцяло чиста повторна инсталация

Deployment скриптът умишлено запазва `.env`, `install-config.php`, storage и
installation lock-а при нормални обновявания. Затова изтриване само на
публичните файлове не превръща съществуващ сайт в чиста инсталация.

Ако няма данни за запазване, най-безопасният clean-install поток е:

1. Преименувайте текущата `/home/CPANEL_USER/smisul.bg` директория като
   резервно копие, например `smisul.bg.backup`, вместо да изтривате отделни
   файлове на сляпо.
2. Създайте отново празните директории `smisul.bg/root` и
   `smisul.bg/backend`; document root на домейна остава
   `/home/CPANEL_USER/smisul.bg/root`.
3. Създайте нова празна MySQL база и потребител в cPanel и дайте **ALL
   PRIVILEGES** на потребителя за тази база. Не използвайте база с останали
   таблици от прекъсната инсталация.
4. В Git Version Control натиснете **Update from Remote**, проверете branch
   `NewFunnelLayout`, след това **Deploy HEAD Commit**. Deployment-ът ще
   постави `install.php` само защото новият backend все още няма `.env`.
5. Копирайте `backend/install-config.example.php` като
   `backend/install-config.php`, попълнете го и отворете `/install.php`.

Не добавяйте `migrate:fresh`, изтриване на база или изтриване на storage в
`.cpanel.yml`: файлът се изпълнява при всеки бъдещ deploy и такава команда би
унищожила работещия сайт.

## Първа инсталация

След първия deployment:

1. копирайте `smisul.bg/backend/install-config.example.php` като
   `smisul.bg/backend/install-config.php`;
2. попълнете MySQL данните, домейна, администратора и install token-а;
3. отворете `https://smisul.bg/install.php` и стартирайте инсталацията.

При следващи deployment-и `.env`, install конфигурацията, базата и storage
данните не се заменят. Миграциите се изпълняват автоматично, а публичният
`install.php` се премахва.
