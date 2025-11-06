# Queue Worker Setup for Password Reset Emails

This guide explains how to set up and run the queue worker for Filament password reset emails with Gmail.

## Overview

Password reset emails are now queued to be sent asynchronously. This ensures that:
- Email sending doesn't block the user's request
- Failed email sends can be retried
- Better performance and user experience

## Prerequisites

1. **Queue Connection**: Make sure your `.env` file has:
   ```env
   QUEUE_CONNECTION=database
   ```
   (This is the default, but verify it's not set to `sync`)

2. **Database Migrations**: Ensure the queue tables are created:
   ```bash
   php artisan migrate
   ```

## Running the Queue Worker

### Development (Local)

For local development, you can run the queue worker manually:

```bash
php artisan queue:work
```

Or use the included dev script that runs multiple processes:
```bash
composer dev
```

This will start:
- Laravel development server
- Queue worker
- Log viewer (Pail)
- Vite dev server

### Production

For production, you should use a process manager to keep the queue worker running. Two options are provided:

#### Option 1: Supervisor (Recommended for Linux/Unix)

1. Install Supervisor (if not already installed):
   ```bash
   # Ubuntu/Debian
   sudo apt-get install supervisor
   
   # macOS (via Homebrew)
   brew install supervisor
   ```

2. Copy the supervisor configuration file:
   ```bash
   sudo cp queue-worker.conf /etc/supervisor/conf.d/queue-worker.conf
   ```

3. Update the configuration file with your project path:
   - Edit `/etc/supervisor/conf.d/queue-worker.conf`
   - Update `directory` and `command` paths to match your project location
   - Update `user` to your web server user (e.g., `www-data`, `nginx`, etc.)

4. Reload Supervisor:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start queue-worker:*
   ```

5. Check status:
   ```bash
   sudo supervisorctl status
   ```

#### Option 2: Systemd (Linux)

1. Copy the systemd service file:
   ```bash
   sudo cp queue-worker.service /etc/systemd/system/queue-worker.service
   ```

2. Update the service file with your project path and user

3. Enable and start the service:
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl enable queue-worker.service
   sudo systemctl start queue-worker.service
   ```

4. Check status:
   ```bash
   sudo systemctl status queue-worker.service
   ```

#### Option 3: Laravel Horizon (Advanced)

For more advanced queue management, consider using Laravel Horizon:
```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
```

Then run:
```bash
php artisan horizon
```

## Monitoring Queue Jobs

### View Pending Jobs
```bash
php artisan queue:monitor
```

### View Failed Jobs
```bash
php artisan queue:failed
```

### Retry Failed Jobs
```bash
# Retry all failed jobs
php artisan queue:retry all

# Retry a specific job
php artisan queue:retry {job-id}
```

### Clear Failed Jobs
```bash
php artisan queue:flush
```

## Troubleshooting

### Emails Not Sending

1. **Check if queue worker is running:**
   ```bash
   # Check supervisor status
   sudo supervisorctl status
   
   # Or check systemd status
   sudo systemctl status queue-worker.service
   ```

2. **Check for failed jobs:**
   ```bash
   php artisan queue:failed
   ```

3. **Check queue table:**
   ```bash
   php artisan tinker
   >>> DB::table('jobs')->count()
   ```

4. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Queue Worker Keeps Stopping

- Make sure the process manager (Supervisor/Systemd) is configured correctly
- Check the logs in `/var/log/supervisor/` or `journalctl -u queue-worker.service`
- Ensure the PHP path in the config is correct
- Check file permissions

### Jobs Stuck in Queue

If jobs are stuck and not processing:

1. Restart the queue worker:
   ```bash
   sudo supervisorctl restart queue-worker:*
   # or
   sudo systemctl restart queue-worker.service
   ```

2. Clear stuck jobs (use with caution):
   ```bash
   php artisan queue:clear
   ```

## Testing

To test the password reset email queue:

1. Make sure the queue worker is running
2. Go to the Filament login page
3. Click "Forgot password?"
4. Enter an email address
5. Check the `jobs` table to see if a job was created:
   ```bash
   php artisan tinker
   >>> DB::table('jobs')->latest()->first()
   ```
6. The email should be sent within a few seconds if the queue worker is running

## Configuration

### Queue Connection

The default queue connection is `database`. You can change this in `.env`:

```env
QUEUE_CONNECTION=database  # Options: sync, database, redis, sqs, etc.
```

**Note**: For password reset emails to be queued, `QUEUE_CONNECTION` must NOT be `sync`.

### Queue Settings

You can customize queue settings in `config/queue.php`:

- `retry_after`: Number of seconds to wait before retrying a failed job (default: 90)
- `queue`: Default queue name (default: 'default')
- `after_commit`: Whether to dispatch jobs after database transactions commit

## Additional Resources

- [Laravel Queue Documentation](https://laravel.com/docs/queues)
- [Filament Password Reset Documentation](https://filamentphp.com/docs/panels/authentication#password-reset)
- [Supervisor Documentation](http://supervisord.org/)

