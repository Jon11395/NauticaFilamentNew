#!/bin/bash

# Script para configurar el Queue Worker de Laravel
# Este script ayuda a configurar el worker necesario para procesar emails de reset password

set -e

echo "=========================================="
echo "Laravel Queue Worker Setup Script"
echo "=========================================="
echo ""

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Obtener información del sistema
PROJECT_DIR=$(pwd)
PHP_BINARY=$(php -r 'echo PHP_BINARY;')
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
USER=$(whoami)

echo -e "${GREEN}Información detectada:${NC}"
echo "  Directorio del proyecto: $PROJECT_DIR"
echo "  PHP Binary: $PHP_BINARY"
echo "  PHP Version: $PHP_VERSION"
echo "  Usuario: $USER"
echo ""

# Verificar que estamos en un proyecto Laravel
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: No se encontró el archivo artisan. Asegúrate de estar en el directorio raíz del proyecto Laravel.${NC}"
    exit 1
fi

# Verificar que la tabla jobs existe
echo -e "${YELLOW}Verificando que la tabla 'jobs' existe...${NC}"
if php artisan tinker --execute="echo \Illuminate\Support\Facades\Schema::hasTable('jobs') ? 'true' : 'false';" | grep -q "true"; then
    echo -e "${GREEN}✓ La tabla 'jobs' existe${NC}"
else
    echo -e "${RED}✗ La tabla 'jobs' no existe. Ejecutando migraciones...${NC}"
    php artisan migrate
fi

# Verificar configuración de queue
echo ""
echo -e "${YELLOW}Verificando configuración de queue...${NC}"
QUEUE_CONNECTION=$(php artisan tinker --execute="echo config('queue.default');" | tr -d '[:space:]')
echo "  Queue Connection: $QUEUE_CONNECTION"

if [ "$QUEUE_CONNECTION" != "database" ]; then
    echo -e "${YELLOW}Advertencia: La conexión de queue no está configurada como 'database'${NC}"
    echo "  Actualmente está configurada como: $QUEUE_CONNECTION"
    echo "  Para usar reset password con Gmail, necesitas 'database'"
    echo "  Edita tu archivo .env y configura: QUEUE_CONNECTION=database"
fi

echo ""
echo "=========================================="
echo "Configuración del Queue Worker con Cron"
echo "=========================================="
echo ""
echo "Este script configurará el queue worker para que se ejecute automáticamente"
echo "cada minuto mediante cron, procesando todos los jobs pendientes."
echo ""
read -p "¿Deseas continuar? (s/n): " continue_setup

if [ "$continue_setup" != "s" ] && [ "$continue_setup" != "S" ]; then
    echo "Operación cancelada"
    exit 0
fi

echo ""
echo -e "${YELLOW}Configurando entrada de cron...${NC}"

# Asegurar que el directorio de logs existe
mkdir -p "$PROJECT_DIR/storage/logs"
chmod -R 775 "$PROJECT_DIR/storage/logs" 2>/dev/null || true

CRON_LINE="* * * * * cd $PROJECT_DIR && $PHP_BINARY artisan queue:work database --stop-when-empty >> $PROJECT_DIR/storage/logs/queue.log 2>&1"

# Verificar si ya existe una entrada similar
if crontab -l 2>/dev/null | grep -q "queue:work"; then
    echo -e "${YELLOW}Ya existe una entrada de cron para queue:work${NC}"
    echo ""
    echo "Entradas actuales:"
    crontab -l 2>/dev/null | grep "queue:work" || true
    echo ""
    read -p "¿Deseas reemplazar la entrada existente? (s/n): " replace_existing
    if [ "$replace_existing" = "s" ] || [ "$replace_existing" = "S" ]; then
        # Eliminar entradas existentes y agregar la nueva
        (crontab -l 2>/dev/null | grep -v "queue:work"; echo "$CRON_LINE") | crontab -
        echo -e "${GREEN}✓ Entrada de cron reemplazada${NC}"
    else
        echo "Operación cancelada. La entrada existente se mantiene."
        exit 0
    fi
else
    # Agregar la entrada al crontab
    (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
    echo -e "${GREEN}✓ Entrada de cron agregada${NC}"
fi

echo ""
echo "La entrada configurada es:"
echo "  $CRON_LINE"
echo ""
echo -e "${GREEN}Verificando configuración...${NC}"
echo ""
echo "Entradas de cron actuales:"
crontab -l 2>/dev/null | grep "queue:work" || echo -e "${YELLOW}No se encontraron entradas${NC}"

echo ""
echo "=========================================="
echo "Verificación del estado"
echo "=========================================="

# Verificar si hay jobs pendientes
JOB_COUNT=$(php artisan tinker --execute="echo \Illuminate\Support\Facades\DB::table('jobs')->count();" | tr -d '[:space:]')
echo "  Jobs pendientes: $JOB_COUNT"

# Verificar si hay jobs fallidos
FAILED_COUNT=$(php artisan tinker --execute="echo \Illuminate\Support\Facades\DB::table('failed_jobs')->count();" | tr -d '[:space:]')
echo "  Jobs fallidos: $FAILED_COUNT"

echo ""
echo -e "${GREEN}✓ Configuración completada${NC}"
echo ""
echo "El worker se ejecutará automáticamente cada minuto."
echo ""
echo "Comandos útiles:"
echo "  - Ver logs: tail -f $PROJECT_DIR/storage/logs/queue.log"
echo "  - Ver entradas de cron: crontab -l"
echo "  - Procesar jobs manualmente: php artisan queue:work database --once"
echo "  - Ver jobs fallidos: php artisan queue:failed"

echo ""
echo -e "${GREEN}¡Listo!${NC}"

