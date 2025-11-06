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
   ```

   ```bash
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
   0 * * * * cd /home/gruponautica/public_html && /opt/remi/php83/root/usr/bin/php artisan schedule:run >> /home/gruponautica/cron.log 2>&1
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
   tail -f /home/gruponautica/cron.log
   ```
  - to exit tail `Ctrl + C`

   You’ll see entries like “No scheduled commands are ready to run” until automation jobs are configured.

 With this cron in place, Laravel's scheduler executes every minute and respects the `gmail_sync_interval_minutes` setting configured in Filament.


--------------------------------------------------------------------------
Para configurar el Queue Worker (necesario para reset password con Gmail)

El sistema de reset password usa colas (queues) para enviar emails a través del servicio Gmail. 
Necesitas ejecutar un worker que procese estas colas continuamente.

IMPORTANTE: Asegúrate de que la migración de jobs se haya ejecutado:
```bash
php artisan migrate
```

Verifica que la tabla 'jobs' existe:
```bash
php artisan tinker
>>> \Illuminate\Support\Facades\Schema::hasTable('jobs');
>>> exit
```

**CRÍTICO: Configura el mailer para usar Gmail API**

Antes de configurar el queue worker, asegúrate de que el mailer esté configurado para usar Gmail API:

```bash
# Verifica la configuración actual
grep MAIL_MAILER .env

# Si no está configurado o está como 'log', cámbialo a 'gmail-api'
sed -i 's/^MAIL_MAILER=.*/MAIL_MAILER=gmail-api/' .env

# O si no existe la línea, agrégalo
if ! grep -q "^MAIL_MAILER=" .env; then
    echo "MAIL_MAILER=gmail-api" >> .env
fi

# Verifica que se cambió correctamente
grep MAIL_MAILER .env

# Limpia la caché de configuración
php artisan config:clear
php artisan cache:clear

# Verifica que está correcto
php artisan tinker
>>> config('mail.default');
>>> exit
```

Debe retornar `"gmail-api"`. Si retorna `"log"`, los emails se guardarán en los logs en lugar de enviarse.

CONFIGURACIÓN DEL QUEUE WORKER CON CRON

Esta opción ejecuta el worker cada minuto procesando todos los jobs pendientes. 
Es la solución recomendada cuando no tienes acceso sudo.

1. Obtén la ruta del proyecto:
   ```bash
   cd ~/public_html
   pwd
   ```
   Guarda esta ruta, la necesitarás en el paso 3.

2. Obtén la ruta del ejecutable PHP:
   ```bash
   php -r 'echo PHP_BINARY, PHP_EOL;'
   ```
   Guarda esta ruta, la necesitarás en el paso 3.

3. Edita el crontab:
   ```bash
   crontab -e
   ```

4. Agrega esta línea (reemplaza las rutas con las que obtuviste en los pasos 1 y 2):
   ```bash
   * * * * * cd /home/gruponauticadev/public_html && /opt/remi/php83/root/usr/bin/php artisan queue:work database --stop-when-empty >> /home/gruponauticadev/public_html/storage/logs/queue.log 2>&1
   ```

   Esto procesará todos los jobs pendientes cada minuto.

5. Verifica que la entrada se agregó correctamente:
   ```bash
   crontab -l
   ```

6. Verifica que el directorio de logs existe:
   ```bash
   mkdir -p ~/public_html/storage/logs
   chmod -R 775 ~/public_html/storage/logs
   ```


VERIFICACIÓN Y TROUBLESHOOTING

1. Verifica que el queue connection está configurado correctamente:
   ```bash
   php artisan tinker
   >>> config('queue.default');
   >>> exit
   ```
   Debe retornar 'database'

2. Verifica que hay jobs en la cola:
   ```bash
   php artisan tinker
   >>> \Illuminate\Support\Facades\DB::table('jobs')->count();
   >>> exit
   ```

3. Prueba el reset password y diagnostica problemas:

   **Paso 1: Solicita el reset password**
   - Ve a la página de reset password
   - Ingresa un email válido
   - Envía la solicitud

   **Paso 2: Verifica que el job se creó**
   ```bash
   php artisan tinker
   >>> \Illuminate\Support\Facades\DB::table('jobs')->count();
   >>> exit
   ```
   Si el contador es mayor a 0, el job se creó correctamente.

   **Paso 3: Verifica que cron está configurado**
   ```bash
   crontab -l | grep queue:work
   ```
   Debe mostrar la línea de cron. Si no muestra nada, ejecuta: `./scripts/setup-queue-worker.sh`

   **Paso 4: Ejecuta el script de diagnóstico (recomendado)**
   Dale permisos de ejecución
   ```bash
   chmod +x scripts/diagnose-queue.sh
   ```

   ```bash
   bash ./scripts/diagnose-queue.sh
   ```
   Este script verificará automáticamente todos los puntos de configuración y te mostrará qué está mal.

   **Paso 5: Procesa el job manualmente (para testing inmediato)**
   ```bash
   php artisan queue:work database --once
   ```
   Esto procesará un job inmediatamente. Si funciona, el problema es que cron no está ejecutándose.

   **Paso 6: Verifica si hay jobs fallidos**
   ```bash
   php artisan queue:failed
   ```
   Si hay jobs fallidos, verás el error. Luego ejecuta:
   ```bash
   php artisan queue:retry all
   ```

   **Paso 7: Revisa los logs para errores**
   ```bash
   # Logs del queue worker
   tail -n 50 storage/logs/queue.log
   
   # Logs generales de Laravel (busca errores de Gmail)
   tail -n 200 storage/logs/laravel.log | grep -i "gmail\|error\|exception\|failed"
   
   # O ver todos los logs recientes (últimas 200 líneas)
   tail -n 200 storage/logs/laravel.log
   
   # Buscar específicamente errores relacionados con el transporte Gmail
   tail -n 500 storage/logs/laravel.log | grep -A 10 -B 10 -i "gmail\|GmailApiTransport\|transport"
   ```
   Busca errores relacionados con Gmail, autenticación, o permisos. 
   
   **IMPORTANTE**: Si ves `Filament\Notifications\Auth\ResetPassword` en los logs pero el email no se envía, 
   el problema puede ser que Filament está usando su propia notificación que no está configurada 
   para usar el transporte Gmail API. Verifica los logs de Laravel para ver si hay errores al enviar.
   
   **Si no encuentras errores en los logs**, prueba enviar un email de prueba directamente:
   ```bash
   php artisan tinker
   >>> $user = \App\Models\User::first();
   >>> $user->notify(new \App\Notifications\ResetPasswordNotification('test-token'));
   >>> exit
   ```
   Esto debería crear un job y luego puedes procesarlo manualmente para ver el error.

   **Paso 8: Verifica la configuración de Gmail**
   ```bash
   php artisan tinker
   >>> \App\Models\GlobalConfig::getValue('gmail_client_id');
   >>> \App\Models\GlobalConfig::getValue('gmail_client_secret');
   >>> \App\Models\GlobalConfig::getValue('gmail_refresh_token');
   >>> \App\Models\GlobalConfig::getValue('gmail_user_email');
   >>> exit
   ```
   Todos estos valores deben estar configurados. El `gmail_refresh_token` debe tener el scope `GMAIL_SEND`.

4. Problemas comunes y soluciones:

   **Problema: Los emails se loguean pero no se envían**
   - Verifica que el mailer esté configurado como `gmail-api`:
     ```bash
     php artisan tinker
     >>> config('mail.default');
     >>> exit
     ```
     Debe retornar `"gmail-api"`. Si retorna `"log"`, los emails se guardarán en los logs.
   - Si está como `"log"`, configúralo:
     ```bash
     sed -i 's/^MAIL_MAILER=.*/MAIL_MAILER=gmail-api/' .env
     php artisan config:clear
     ```
   - Verifica que la configuración de Gmail esté correcta (ver Paso 8 arriba)

   **Problema: El job se crea pero no se procesa**
   - Verifica que cron está ejecutándose: espera 1-2 minutos después de crear el job
   - Verifica que la entrada de cron está activa: `crontab -l`
   - Procesa manualmente: `php artisan queue:work database --once`
   - Verifica permisos del archivo de logs: `ls -la storage/logs/queue.log`

   **Problema: El job falla al procesarse**
   - Revisa `php artisan queue:failed` para ver el error específico
   - Verifica la configuración de Gmail (ver Paso 8 arriba)
   - Verifica que el refresh token tiene el scope GMAIL_SEND
   - Revisa los logs: `tail -f storage/logs/laravel.log`
   - **Si ves `Filament\Notifications\Auth\ResetPassword` en los logs**: Esto significa que Filament está usando su propia notificación. 
     Verifica los logs de Laravel para ver si hay errores al enviar el email. La notificación de Filament debería 
     usar el mailer configurado (`gmail-api`), pero puede haber un problema con la configuración de Gmail.

   **Problema: Cron no está ejecutándose**
   - Verifica que cron está activo en el servidor (contacta al administrador si es necesario)
   - Verifica que la ruta del PHP en cron es correcta: `php -r 'echo PHP_BINARY;'`
   - Verifica que la ruta del proyecto en cron es correcta: `pwd`
   - Prueba ejecutando el comando manualmente desde el directorio del proyecto

5. Comandos útiles para diagnóstico:

   ```bash
   # Ver todos los jobs en la cola (con detalles)
   php artisan tinker
   >>> \Illuminate\Support\Facades\DB::table('jobs')->get();
   >>> exit

   # Ver el payload de un job específico (útil para debugging)
   php artisan tinker
   >>> $job = \Illuminate\Support\Facades\DB::table('jobs')->first();
   >>> json_decode($job->payload, true);
   >>> exit

   # Procesar un job manualmente (útil para testing)
   php artisan queue:work database --once

   # Ver jobs fallidos con detalles
   php artisan queue:failed

   # Reintentar todos los jobs fallidos
   php artisan queue:retry all

   # Reintentar un job específico (reemplaza UUID con el id del job fallido)
   php artisan queue:retry <uuid>

   # Limpiar todos los jobs fallidos
   php artisan queue:flush

   # Ver logs en tiempo real
   tail -f storage/logs/queue.log
   tail -f storage/logs/laravel.log
   ```


NOTAS IMPORTANTES:

- El worker se ejecuta automáticamente cada minuto mediante cron
- Si los jobs no se procesan, verifica que la entrada de cron esté activa: `crontab -l`
- El parámetro `--stop-when-empty` asegura que el worker se detenga después de procesar todos los jobs, evitando procesos duplicados
- Los logs se guardan en `storage/logs/queue.log` para monitorear el funcionamiento
- El parámetro `--tries=3` (configurado en el código) hace que los jobs se reintenten hasta 3 veces si fallan



--------------------------------------------------------------------------
SI DA ERROR 419 - CORRER EN EL SERVIDOR
--------------------------------------------------------------------------

php artisan vendor:publish --tag=livewire:assets --force


