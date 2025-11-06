<?php

namespace App\Console\Commands;

use App\Models\GlobalConfig;
use Google_Client;
use Google_Service_Gmail;
use Illuminate\Console\Command;

class GenerateGmailOAuthToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gmail:generate-oauth-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new Gmail OAuth token with SEND scope for password reset emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Gmail OAuth Token Generator');
        $this->info('================================');
        $this->newLine();

        // Get credentials from GlobalConfig
        $clientId = GlobalConfig::getValue('gmail_client_id');
        $clientSecret = GlobalConfig::getValue('gmail_client_secret');

        if (!$clientId || !$clientSecret) {
            $this->error('Gmail Client ID and Client Secret must be configured in GlobalConfig first!');
            $this->info('Please configure them in: Admin Panel → Configuraciones → Configuraciones Globales → Gmail tab');
            return Command::FAILURE;
        }

        $this->info('Using credentials from GlobalConfig:');
        $this->line('Client ID: ' . substr($clientId, 0, 20) . '...');
        $this->newLine();

        // Create Google Client
        $client = new Google_Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob'); // For command line

        // Set scopes for reading AND sending emails
        $client->addScope(Google_Service_Gmail::GMAIL_READONLY);
        $client->addScope(Google_Service_Gmail::GMAIL_SEND);

        // Generate authorization URL
        $authUrl = $client->createAuthUrl();

        $this->info('Step 1: Open this URL in your browser:');
        $this->line($authUrl);
        $this->newLine();
        $this->info('Step 2: Authorize the application and copy the authorization code.');
        $this->newLine();

        $authCode = $this->ask('Enter the authorization code here');

        if (!$authCode) {
            $this->error('Authorization code is required!');
            return Command::FAILURE;
        }

        try {
            // Exchange authorization code for tokens
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check for errors
            if (array_key_exists('error', $accessToken)) {
                $this->error('Error: ' . $accessToken['error']);
                if (isset($accessToken['error_description'])) {
                    $this->error('Description: ' . $accessToken['error_description']);
                }
                return Command::FAILURE;
            }

            // Get refresh token
            $refreshToken = $client->getRefreshToken();

            if (!$refreshToken) {
                $this->warn('No refresh token received. The access token may be valid, but you may need to re-authorize later.');
                $this->line('Access Token: ' . json_encode($accessToken));
            } else {
                $this->newLine();
                $this->info('✓ Successfully generated OAuth token!');
                $this->newLine();
                $this->info('Your Refresh Token (save this in GlobalConfig):');
                $this->line($refreshToken);
                $this->newLine();

                // Ask if user wants to save it automatically
                if ($this->confirm('Do you want to save this refresh token to GlobalConfig now?', true)) {
                    GlobalConfig::setValue('gmail_refresh_token', $refreshToken, 'string', 'Refresh Token for Gmail API with SEND scope');
                    $this->info('✓ Refresh token saved to GlobalConfig!');
                    $this->newLine();
                    $this->info('You can now use password reset emails with OAuth.');
                } else {
                    $this->info('Please manually update the refresh token in:');
                    $this->line('Admin Panel → Configuraciones → Configuraciones Globales → Gmail tab');
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error generating token: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

