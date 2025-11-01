Para deployar en domcloud.com por primera vez

source: lINK DEL REPOSITORIO GITHUB
features:
  - mysql
  - ssl
  - ssl always
nginx:
  root: public_html/public
  fastcgi: on
  locations:
    - match: /
      try_files: $uri $uri/ /index.php$is_args$args
    - match: ~ \.[^\/]+(?<!\.php)$
      try_files: $uri =404
commands:
  - cp .env.example .env
  - sed -i 's/^#\s*\(DB_HOST=.*\)/\1/' .env
  - sed -i 's/^#\s*\(DB_PORT=.*\)/\1/' .env
  - sed -i 's/^#\s*\(DB_DATABASE=.*\)/\1/' .env
  - sed -i 's/^#\s*\(DB_USERNAME=.*\)/\1/' .env
  - sed -i 's/^#\s*\(DB_PASSWORD=.*\)/\1/' .env
  - sed -i "s/DB_HOST=127.0.0.1/DB_HOST=localhost/g" .env
  - sed -ri "s/DB_DATABASE=.*/DB_DATABASE=${DATABASE}/g" .env
  - sed -ri "s/DB_USERNAME=.*/DB_USERNAME=${USERNAME}/g" .env
  - sed -ri "s/DB_PASSWORD=.*/DB_PASSWORD=${PASSWORD}/g" .env
  - sed -ri "s/APP_URL=.*/APP_URL=http:\/\/${DOMAIN}/g" .env
  - sed -ri "s/DB_CONNECTION=.*/DB_CONNECTION=mysql/g" .env
  - composer install
  - php artisan migrate:fresh || true
  - php artisan key:generate
  - php artisan storage:link
  - php artisan livewire:publish
  - cp -r vendor/livewire/livewire/dist public/livewire
  - npm install
  - npm run build
  - chmod 0750 -R storage || true




--------------------------------------------------------------------------
Para activar un cron schedule


1. ssh the server

2. Get the public path to enter later with this commands:

   ```bash
   cd ~/public_html
   pwd
   ```

3. Get the PHP executable path:

   ```bash
   php -r 'echo PHP_BINARY, PHP_EOL;'
   ```

4. Edit your user crontab:

   ```bash
   crontab -e
   ```

5. Add the scheduler entry (one line): the one from the STEP 2 AND 3 above.
Once the file opens in `vim`, press `G` to jump to the bottom, then type `o` (lowercase “o”) to open a new blank line and enter insert mode.

   ```bash
   0 * * * * cd /home/gruponauticadev/public_html && /opt/remi/php83/root/usr/bin/php artisan schedule:run >> /home/gruponauticadev/cron.log 2>&1
   ```
   - Press `Esc` to exit insert mode.
   - Type `:wq` and press Enter to write the file and quit

6. Verify cron is registered:

   ```bash
   crontab -l
   ```

   

7. Optional, if says "No scheduled tasks have been defined." when running:

   ```bash
      php artisan schedule:list
      ```

   -  Check the values from the global config
   ```bash
   php artisan tinker
   >>> \App\Models\GlobalConfig::getValue('gmail_sync_interval_minutes');
   >>> \App\Models\GlobalConfig::getValue('gmail_client_id');
   ```
   

8. Optional: tail the log to confirm it runs each minute:

   ```bash
   tail -f /home/gruponauticadev/cron.log
   ```
  - to exit tail `Ctrl + C`

   You’ll see entries like “No scheduled commands are ready to run” until automation jobs are configured.

 With this cron in place, Laravel’s scheduler executes every minute and respects the `gmail_sync_interval_minutes` setting configured in Filament.




--------------------------------------------------------------------------
SI DA ERROR 419 - CORRER EN EL SERVIDOR
--------------------------------------------------------------------------

php artisan vendor:publish --tag=livewire:assets --force


