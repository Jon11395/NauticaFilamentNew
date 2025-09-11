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
Para despliegue de actualizaciones:

* Vamos a domcloud.co a nuestro proyecto en la parte de Despliegue->Añadir una tarea de despliegue y le damos al boton de Configurar Webhook, tomamos el WEBHOOK_SECRET y el WEBHOOK_AUTH
* Creamos los secrets en el github repo en settings->Secrets and Variables->Action new reporsitory secret

* Para crear el workflow, vamos a nuestro repo en github->actions->create new workflow->set up a workflow yourself, le damos un nombre y pegamos el siguiente texto, despues guardamos y ejecutamos el workflow para asegurarnos que funciona:

name: Sync on DOM Cloud

on:
  workflow_dispatch: {}
  push:
    branches:
      - main
      - master

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - name: Invoke deployment hook
        uses: distributhor/workflow-webhook@v3
        env:
          webhook_url: https://my.domcloud.co/api/githubdeploy
          webhook_secret: ${{ secrets.WEBHOOK_SECRET }}
          webhook_auth: ${{ secrets.WEBHOOK_AUTH }}
          data: >-
            {
              "commands": [
                "git pull",
                "composer install --no-interaction --prefer-dist --optimize-autoloader",
                "npm install",
                "npm run build",
                "php artisan migrate --force",
                "php artisan config:cache",
                "php artisan route:cache",
                "php artisan view:cache",
                "restart"
              ]
            }





