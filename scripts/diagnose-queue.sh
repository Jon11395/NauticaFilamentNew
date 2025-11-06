#!/bin/bash

# Script de diagnóstico para problemas con el queue worker

set -e

echo "=========================================="
echo "Diagnóstico del Queue Worker"
echo "=========================================="
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

PROJECT_DIR=$(pwd)

# Verificar que estamos en un proyecto Laravel
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: No se encontró el archivo artisan. Asegúrate de estar en el directorio raíz del proyecto Laravel.${NC}"
    exit 1
fi

echo "1. Verificando configuración de queue..."
QUEUE_CONNECTION=$(php artisan tinker --execute="echo config('queue.default');" | tr -d '[:space:]')
if [ "$QUEUE_CONNECTION" = "database" ]; then
    echo -e "${GREEN}✓ Queue connection: $QUEUE_CONNECTION${NC}"
else
    echo -e "${RED}✗ Queue connection: $QUEUE_CONNECTION (debe ser 'database')${NC}"
fi

echo ""
echo "2. Verificando tabla 'jobs'..."
if php artisan tinker --execute="echo \Illuminate\Support\Facades\Schema::hasTable('jobs') ? 'true' : 'false';" | grep -q "true"; then
    echo -e "${GREEN}✓ La tabla 'jobs' existe${NC}"
else
    echo -e "${RED}✗ La tabla 'jobs' no existe. Ejecuta: php artisan migrate${NC}"
fi

echo ""
echo "3. Verificando jobs en la cola..."
JOB_COUNT=$(php artisan tinker --execute="echo \Illuminate\Support\Facades\DB::table('jobs')->count();" | tr -d '[:space:]')
if [ "$JOB_COUNT" -gt 0 ]; then
    echo -e "${YELLOW}⚠ Hay $JOB_COUNT jobs pendientes en la cola${NC}"
else
    echo -e "${GREEN}✓ No hay jobs pendientes${NC}"
fi

echo ""
echo "4. Verificando jobs fallidos..."
FAILED_COUNT=$(php artisan tinker --execute="echo \Illuminate\Support\Facades\DB::table('failed_jobs')->count();" | tr -d '[:space:]')
if [ "$FAILED_COUNT" -gt 0 ]; then
    echo -e "${RED}✗ Hay $FAILED_COUNT jobs fallidos${NC}"
    echo "  Ejecuta 'php artisan queue:failed' para ver los detalles"
else
    echo -e "${GREEN}✓ No hay jobs fallidos${NC}"
fi

echo ""
echo "5. Verificando configuración de cron..."
if crontab -l 2>/dev/null | grep -q "queue:work"; then
    echo -e "${GREEN}✓ Entrada de cron encontrada:${NC}"
    crontab -l 2>/dev/null | grep "queue:work"
else
    echo -e "${RED}✗ No se encontró entrada de cron para queue:work${NC}"
    echo "  Ejecuta: ./scripts/setup-queue-worker.sh"
fi

echo ""
echo "6. Verificando configuración de Gmail..."
GMAIL_CLIENT_ID=$(php artisan tinker --execute="echo \App\Models\GlobalConfig::getValue('gmail_client_id') ?: 'NO_CONFIGURADO';" | tr -d '[:space:]')
GMAIL_CLIENT_SECRET=$(php artisan tinker --execute="echo \App\Models\GlobalConfig::getValue('gmail_client_secret') ?: 'NO_CONFIGURADO';" | tr -d '[:space:]')
GMAIL_REFRESH_TOKEN=$(php artisan tinker --execute="echo \App\Models\GlobalConfig::getValue('gmail_refresh_token') ?: 'NO_CONFIGURADO';" | tr -d '[:space:]')
GMAIL_USER_EMAIL=$(php artisan tinker --execute="echo \App\Models\GlobalConfig::getValue('gmail_user_email') ?: 'NO_CONFIGURADO';" | tr -d '[:space:]')

if [ "$GMAIL_CLIENT_ID" != "NO_CONFIGURADO" ] && [ "$GMAIL_CLIENT_ID" != "" ]; then
    echo -e "${GREEN}✓ gmail_client_id: Configurado${NC}"
else
    echo -e "${RED}✗ gmail_client_id: NO_CONFIGURADO${NC}"
fi

if [ "$GMAIL_CLIENT_SECRET" != "NO_CONFIGURADO" ] && [ "$GMAIL_CLIENT_SECRET" != "" ]; then
    echo -e "${GREEN}✓ gmail_client_secret: Configurado${NC}"
else
    echo -e "${RED}✗ gmail_client_secret: NO_CONFIGURADO${NC}"
fi

if [ "$GMAIL_REFRESH_TOKEN" != "NO_CONFIGURADO" ] && [ "$GMAIL_REFRESH_TOKEN" != "" ]; then
    echo -e "${GREEN}✓ gmail_refresh_token: Configurado${NC}"
else
    echo -e "${RED}✗ gmail_refresh_token: NO_CONFIGURADO${NC}"
fi

if [ "$GMAIL_USER_EMAIL" != "NO_CONFIGURADO" ] && [ "$GMAIL_USER_EMAIL" != "" ]; then
    echo -e "${GREEN}✓ gmail_user_email: $GMAIL_USER_EMAIL${NC}"
else
    echo -e "${RED}✗ gmail_user_email: NO_CONFIGURADO${NC}"
fi

echo ""
echo "7. Verificando archivos de log..."
if [ -f "$PROJECT_DIR/storage/logs/queue.log" ]; then
    echo -e "${GREEN}✓ Log de queue existe: storage/logs/queue.log${NC}"
    echo "  Últimas líneas:"
    tail -n 5 "$PROJECT_DIR/storage/logs/queue.log" 2>/dev/null || echo "  (archivo vacío o sin permisos)"
else
    echo -e "${YELLOW}⚠ Log de queue no existe aún: storage/logs/queue.log${NC}"
    echo "  Se creará automáticamente cuando cron ejecute el worker"
fi

if [ -f "$PROJECT_DIR/storage/logs/laravel.log" ]; then
    echo -e "${GREEN}✓ Log de Laravel existe: storage/logs/laravel.log${NC}"
    echo "  Últimas líneas (posibles errores):"
    tail -n 5 "$PROJECT_DIR/storage/logs/laravel.log" 2>/dev/null | grep -i "error\|exception\|failed" || echo "  (no hay errores recientes)"
else
    echo -e "${YELLOW}⚠ Log de Laravel no existe aún${NC}"
fi

echo ""
echo "=========================================="
echo "Resumen y recomendaciones:"
echo "=========================================="
echo ""

if [ "$JOB_COUNT" -gt 0 ]; then
    echo -e "${YELLOW}Hay jobs pendientes. Para procesarlos manualmente:${NC}"
    echo "  php artisan queue:work database --once"
    echo ""
fi

if [ "$FAILED_COUNT" -gt 0 ]; then
    echo -e "${RED}Hay jobs fallidos. Para ver los detalles:${NC}"
    echo "  php artisan queue:failed"
    echo ""
    echo -e "${YELLOW}Para reintentar:${NC}"
    echo "  php artisan queue:retry all"
    echo ""
fi

if ! crontab -l 2>/dev/null | grep -q "queue:work"; then
    echo -e "${RED}No hay entrada de cron configurada.${NC}"
    echo "  Ejecuta: ./scripts/setup-queue-worker.sh"
    echo ""
fi

echo "Para monitorear en tiempo real:"
echo "  tail -f storage/logs/queue.log"
echo "  tail -f storage/logs/laravel.log"
echo ""

