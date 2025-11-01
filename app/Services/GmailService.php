<?php

namespace App\Services;

use Google_Client;
use Google_Service_Gmail;
use Illuminate\Support\Facades\Log;

class GmailService
{
    protected ?Google_Client $client = null;
    protected ?Google_Service_Gmail $service = null;
    protected ?array $config = null;

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
     * Get Gmail configuration from global config
     */
    protected function getGmailConfig(): ?array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $config = [];

        $config['gmail_client_id'] = \App\Models\GlobalConfig::getValue('gmail_client_id');
        $config['gmail_client_secret'] = \App\Models\GlobalConfig::getValue('gmail_client_secret');
        $config['gmail_refresh_token'] = \App\Models\GlobalConfig::getValue('gmail_refresh_token');
        $config['gmail_user_email'] = \App\Models\GlobalConfig::getValue('gmail_user_email');

        if (empty($config['gmail_client_id']) && empty($config['gmail_client_secret'])) {
            return null;
        }

        $this->config = $config;

        return $this->config;
    }


    /**
     * Get unread emails from Gmail
     */
    public function getUnreadEmails(int $maxResults = 20): array
    {
        if (!$this->service) {
            if (!$this->initialize()) {
                throw new \Exception('Failed to initialize Gmail service');
            }
        }

        // Refresh the access token if it's expired
        if ($this->client->isAccessTokenExpired()) {
            Log::info('Access token expired, refreshing...');
            try {
                $this->client->fetchAccessTokenWithRefreshToken();
                Log::info('Token refreshed successfully');
            } catch (\Exception $e) {
                Log::error('Failed to refresh token: ' . $e->getMessage());
                throw new \Exception('Failed to refresh access token. Invalid credentials.');
            }
        }
        
        $userEmail = $this->getUserEmail();
        
        // Search for unread emails
        $query = 'is:unread';
        
        try {
            $results = $this->service->users_messages->listUsersMessages($userEmail, [
                'q' => $query,
                'maxResults' => $maxResults,
            ]);

            $messages = [];
            if ($results && $results->getMessages()) {
                foreach ($results->getMessages() as $message) {
                    $msg = $this->service->users_messages->get($userEmail, $message->getId(), ['format' => 'full']);
                    $messages[] = $this->formatMessage($msg, $userEmail);
                }
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
            
            throw $e;
        }
    }

    /**
     * Format Gmail message for easier use
     */
    protected function formatMessage($message, ?string $userEmail = null): array
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

        $attachments = [];
        if ($userEmail) {
            $attachments = $this->extractAttachments($payload, $message->getId(), $userEmail);
        }

        return [
            'id' => $message->getId(),
            'thread_id' => $message->getThreadId(),
            'snippet' => $message->getSnippet(),
            'from' => $from,
            'subject' => $subject,
            'date' => $date,
            'body' => $body,
            'attachments' => $attachments,
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
                $mimeType = $part->getMimeType();

                // Prefer plain text parts when available
                if (str_contains($mimeType ?? '', 'text/plain')) {
                    $decoded = $this->decodePartData($part->getBody()->getData());
                    if ($decoded !== '') {
                        return $decoded;
                    }
                }

                if ($part->getParts()) {
                    $nested = $this->extractBody($part);
                    if ($nested !== '') {
                        return $nested;
                    }
                }

                $decoded = $this->decodePartData($part->getBody()->getData());
                if ($decoded !== '') {
                    $body .= $decoded;
                }
            }
        } else {
            $data = $payload->getBody()->getData();
            if ($data) {
                $body = $this->decodePartData($data);
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

    protected function getUserEmail(): string
    {
        $config = $this->getGmailConfig();

        return $config['gmail_user_email'] ?? 'me';
    }

    protected function extractAttachments($payload, string $messageId, string $userEmail): array
    {
        $attachments = [];
        $parts = $payload->getParts();

        if (!$parts) {
            return $attachments;
        }

        foreach ($parts as $part) {
            if ($part->getFilename()) {
                $body = $part->getBody();
                $data = '';

                if ($body->getAttachmentId()) {
                    $attachment = $this->service->users_messages_attachments->get(
                        $userEmail,
                        $messageId,
                        $body->getAttachmentId()
                    );

                    $data = $this->decodePartData($attachment->getData());
                } else {
                    $data = $this->decodePartData($body->getData());
                }

                $attachments[] = [
                    'filename' => $part->getFilename(),
                    'mime_type' => $part->getMimeType(),
                    'size' => $part->getBody()->getSize(),
                    'data' => $data,
                ];
            }

            if ($part->getParts()) {
                $attachments = array_merge(
                    $attachments,
                    $this->extractAttachments($part, $messageId, $userEmail)
                );
            }
        }

        return $attachments;
    }

    protected function decodePartData(?string $data): string
    {
        if (empty($data)) {
            return '';
        }

        $data = strtr($data, '-_', '+/');

        return base64_decode($data, true) ?: '';
    }
}

