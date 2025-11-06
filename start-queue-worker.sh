#!/bin/bash

# Quick start script for queue worker (Development)
# This script starts the Laravel queue worker for processing password reset emails

echo "Starting Laravel Queue Worker..."
echo "Press Ctrl+C to stop"
echo ""

cd "$(dirname "$0")"
php artisan queue:work --verbose --tries=3 --timeout=90

