<?php

namespace App\Services;

use Google_Client;
use Google_Service_Gmail;
use Illuminate\Support\Facades\Log;

class GmailService
{
    protected ?Google_Client $client = null;
    protected ?Google_Service_Gmail $service = null;

    /**
     * Initialize Gmail client with credentials from settings
     */
    public function initialize(): bool
    {
        try {
            $config = $this->getGmailConfig();

            if (!$config || !isset($config['gmail_client_id']) || !isset($config['gmail_client_secret'])) {
                Log::error('Gmail configuration is incomplete');
                return false;
            }

            $this->client = new Google_Client();
            $this->client->setClientId($config['gmail_client_id']);
            $this->client->setClientSecret($config['gmail_client_secret']);
            $this->client->setAccessType('offline');
            $this->client->setPrompt('select_account consent');

            // Set up OAuth 2.0 scopes for reading Gmail
            $this->client->addScope(Google_Service_Gmail::GMAIL_READONLY);

            // Set refresh token if available
            if (isset($config['gmail_refresh_token']) && !empty($config['gmail_refresh_token'])) {
                // Set the access token with the refresh token
                // The Google Client will use the refresh token to get a new access token
                $tokenData = [
                    'access_token' => '',
                    'refresh_token' => $config['gmail_refresh_token'],
                    'created' => time() - 3600, // Set to expired so it refreshes
                    'expires_in' => 3600,
                ];
                $this->client->setAccessToken($tokenData);
                
                // Try to fetch a fresh access token immediately
                try {
                    if ($this->client->isAccessTokenExpired()) {
                        $this->client->fetchAccessTokenWithRefreshToken();
                        Log::info('Successfully fetched access token using refresh token');
                    } else {
                        Log::info('Access token is still valid');
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not fetch access token: ' . $e->getMessage() . '. The refresh token may be invalid.');
                }
            } else {
                Log::warning('No refresh token found in configuration');
            }

            $this->service = new Google_Service_Gmail($this->client);

            Log::info('Gmail client initialized successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to initialize Gmail client: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Gmail configuration from general settings
     */
    protected function getGmailConfig(): ?array
    {
        $generalSetting = \Joaopaulolndev\FilamentGeneralSettings\Models\GeneralSetting::first();
        
        if (!$generalSetting || !$generalSetting->more_configs) {
            return null;
        }

        return $generalSetting->more_configs;
    }


    /**
     * Get unread emails from Gmail
     */
    public function getUnreadEmails(int $maxResults = 20): array
    {
        if (!$this->service) {
            if (!$this->initialize()) {
                return [];
            }
        }

        try {
            // Refresh the access token if it's expired
            if ($this->client->isAccessTokenExpired()) {
                Log::info('Access token expired, refreshing...');
                $this->client->fetchAccessTokenWithRefreshToken();
                Log::info('Token refreshed successfully');
            }
            
            $userEmail = $this->getGmailConfig()['gmail_user_email'] ?? 'me';
            
            // Search for unread emails
            $query = 'is:unread';
            
            $results = $this->service->users_messages->listUsersMessages($userEmail, [
                'q' => $query,
                'maxResults' => $maxResults,
            ]);

            $messages = [];
            foreach ($results->getMessages() as $message) {
                $msg = $this->service->users_messages->get($userEmail, $message->getId());
                $messages[] = $this->formatMessage($msg);
            }

            return $messages;
        } catch (\Exception $e) {
            Log::error('Failed to fetch unread emails: ' . $e->getMessage());
            
            // Log additional debugging info
            if ($this->client) {
                $token = $this->client->getAccessToken();
                Log::error('Token info: ' . json_encode([
                    'has_token' => !empty($token),
                    'is_expired' => $this->client->isAccessTokenExpired(),
                    'has_refresh_token' => !empty($this->client->getRefreshToken()),
                ]));
            }
            
            return [];
        }
    }

    /**
     * Format Gmail message for easier use
     */
    protected function formatMessage($message): array
    {
        $payload = $message->getPayload();
        $headers = $payload->getHeaders();
        
        $from = '';
        $subject = '';
        $date = '';
        $body = '';

        foreach ($headers as $header) {
            switch ($header->getName()) {
                case 'From':
                    $from = $header->getValue();
                    break;
                case 'Subject':
                    $subject = $header->getValue();
                    break;
                case 'Date':
                    $date = $header->getValue();
                    break;
            }
        }

        // Extract body
        $body = $this->extractBody($payload);

        return [
            'id' => $message->getId(),
            'thread_id' => $message->getThreadId(),
            'snippet' => $message->getSnippet(),
            'from' => $from,
            'subject' => $subject,
            'date' => $date,
            'body' => $body,
        ];
    }

    /**
     * Extract body text from message payload
     */
    protected function extractBody($payload): string
    {
        $body = '';
        $parts = $payload->getParts();

        if ($parts) {
            foreach ($parts as $part) {
                $data = $part->getBody()->getData();
                if ($data) {
                    $body .= base64_decode(strtr($data, '-_', '+/'));
                }
            }
        } else {
            $data = $payload->getBody()->getData();
            if ($data) {
                $body = base64_decode(strtr($data, '-_', '+/'));
            }
        }

        return $body;
    }

    /**
     * Mark emails as read
     */
    public function markAsRead(array $messageIds): bool
    {
        if (!$this->service) {
            if (!$this->initialize()) {
                return false;
            }
        }

        try {
            // Refresh the access token if it's expired
            if ($this->client->isAccessTokenExpired()) {
                Log::info('Access token expired, refreshing...');
                $this->client->fetchAccessTokenWithRefreshToken();
                Log::info('Token refreshed successfully');
            }
            
            $userEmail = $this->getGmailConfig()['gmail_user_email'] ?? 'me';

            foreach ($messageIds as $messageId) {
                $this->service->users_messages->modify($userEmail, $messageId, new \Google_Service_Gmail_ModifyMessageRequest([
                    'removeLabelIds' => ['UNREAD'],
                ]));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mark emails as read: ' . $e->getMessage());
            
            // Log additional debugging info
            if ($this->client) {
                $token = $this->client->getAccessToken();
                Log::error('Token info: ' . json_encode([
                    'has_token' => !empty($token),
                    'is_expired' => $this->client->isAccessTokenExpired(),
                    'has_refresh_token' => !empty($this->client->getRefreshToken()),
                ]));
            }
            
            return false;
        }
    }
}

