<?php

namespace App\Mail\Transports;

use App\Models\GlobalConfig;
use App\Services\GmailService;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stringable;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\RawMessage;

class GmailApiTransport extends AbstractTransport implements Stringable
{
    protected GmailService $gmailService;
    protected ?string $fromEmail = null;

    public function __construct()
    {
        parent::__construct();
        $this->gmailService = new GmailService();
        
        // Only try to get email from config if database is available
        try {
            if (Schema::hasTable('global_configs')) {
                $this->fromEmail = GlobalConfig::getValue('gmail_user_email');
            }
        } catch (\Exception $e) {
            // Silently fail if database is not available
            // Will use default 'me' in send method
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function doSend(SentMessage $message): void
    {
        try {
            // Initialize with send permissions
            if (!$this->gmailService->initializeForSending()) {
                \Log::error('GmailApiTransport: Failed to initialize Gmail service for sending');
                throw new \RuntimeException('Failed to initialize Gmail service for sending emails. Make sure your refresh token has GMAIL_SEND scope.');
            }

            // Get the Gmail client from the service
            $client = $this->gmailService->getClient();
            if (!$client) {
                \Log::error('GmailApiTransport: Gmail client is not initialized');
                throw new \RuntimeException('Gmail client is not initialized');
            }
            
            // Refresh token if expired
            if ($client->isAccessTokenExpired()) {
                $client->fetchAccessTokenWithRefreshToken();
            }
            
            $service = new Google_Service_Gmail($client);

            // Get the user email for sending - always use GlobalConfig if available
            $userEmail = $this->getFromEmail();
            
            // Get the original message from SentMessage
            $originalMessage = $message->getOriginalMessage();
            
            // CRITICAL: Validate that we have a proper email address before proceeding
            // The userEmail must be a valid email, not "me" and definitely not a name like "Náutica"
            if (!$userEmail || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                // If userEmail is invalid, try to get it from GlobalConfig again
                $userEmail = $this->getFromEmail();
                
                // Final validation - if still invalid, throw error
                if (!$userEmail || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                    \Log::error('GmailApiTransport: Invalid email address for sending', [
                        'email' => $userEmail,
                        'is_me' => $userEmail === 'me',
                        'is_valid' => filter_var($userEmail, FILTER_VALIDATE_EMAIL) !== false
                    ]);
                    throw new \RuntimeException('Invalid email address configured for Gmail sending: ' . ($userEmail ?? 'null') . '. Please check your Gmail configuration in GlobalConfig.');
                }
            }
            
            // Additional safety check: ensure userEmail is not "me" (we need actual email for Gmail API)
            if ($userEmail === 'me') {
                \Log::error('GmailApiTransport: Email address is "me" which is not valid for Gmail API', [
                    'userEmail' => $userEmail
                ]);
                throw new \RuntimeException('Gmail user email is set to "me" which is not valid. Please configure a valid email address in GlobalConfig.');
            }
            
            // Get the from name - try to preserve it from the original message if valid
            $fromName = null; // Will default to email address if not set
            
            // Check if original message has a valid from address with a name
            if ($originalMessage->getFrom() && count($originalMessage->getFrom()) > 0) {
                try {
                    $originalFromAddress = $originalMessage->getFrom()[0];
                    $originalFromEmail = $originalFromAddress->getAddress();
                    $originalFromName = $originalFromAddress->getName();
                    
                    // Validate that the original email is actually an email (not a name)
                    if ($originalFromEmail && filter_var($originalFromEmail, FILTER_VALIDATE_EMAIL)) {
                        // Only use the original name if:
                        // 1. It exists
                        // 2. It's not "Laravel" or "Náutica" (to avoid confusion)
                        // 3. It's not a valid email address (names shouldn't be emails)
                        if ($originalFromName 
                            && $originalFromName !== 'Laravel' 
                            && $originalFromName !== 'Náutica'
                            && !filter_var($originalFromName, FILTER_VALIDATE_EMAIL)) {
                            $fromName = $originalFromName;
                        }
                    }
                } catch (\Exception $e) {
                    // If there's an error reading the original from address, ignore it
                    \Log::warning('GmailApiTransport: Error reading original from address', ['error' => $e->getMessage()]);
                }
            }
            
            // If we didn't get a valid name from the message, try GlobalConfig first, then config
            if (!$fromName) {
                try {
                    if (Schema::hasTable('global_configs')) {
                        $globalConfigName = GlobalConfig::getValue('gmail_from_name');
                        // Only use GlobalConfig name if it's not an email address and not "Laravel"
                        if ($globalConfigName 
                            && $globalConfigName !== 'Laravel' 
                            && !filter_var($globalConfigName, FILTER_VALIDATE_EMAIL)) {
                            $fromName = $globalConfigName;
                        }
                    }
                } catch (\Exception $e) {
                    // Fallback to config
                }
                
                // If still no name, try config
                if (!$fromName) {
                    $configName = config('mail.from.name', 'Náutica');
                    // Only use config name if it's not an email address and not "Laravel"
                    if ($configName 
                        && $configName !== 'Laravel' 
                        && !filter_var($configName, FILTER_VALIDATE_EMAIL)) {
                        $fromName = $configName;
                    }
                }
            }
            
            // If still no valid name, use a safe default
            if (!$fromName) {
                $fromName = 'Náutica';
            }
            
            // Always set the from address to ensure it's valid
            // First, clear any existing from addresses to avoid confusion
            $originalMessage->getHeaders()->remove('from');
            
            // Final validation before setting - ensure userEmail is definitely a valid email
            if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                \Log::error('GmailApiTransport: userEmail failed final validation before setting from address', [
                    'userEmail' => $userEmail,
                    'type' => gettype($userEmail)
                ]);
                throw new \RuntimeException('Invalid email address before setting from header: ' . $userEmail);
            }
            
            // Use Symfony's Address class to explicitly create the address object
            // This ensures proper formatting and prevents any confusion between email and name
            try {
                $fromAddress = new Address($userEmail, $fromName);
                $originalMessage->from($fromAddress);
            } catch (\Exception $e) {
                \Log::error('GmailApiTransport: Error creating or setting from address', [
                    'userEmail' => $userEmail,
                    'fromName' => $fromName,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
            
            // Convert to string for Gmail API
            // Symfony Mime handles UTF-8 encoding automatically, including subject encoding
            // Do NOT manipulate the subject - let Symfony handle it completely
            $messageString = $originalMessage->toString();
            
            // Encode the message in base64url format
            $messageBody = $this->base64urlEncode($messageString);

            // Create Gmail message
            $gmailMessage = new Google_Service_Gmail_Message();
            $gmailMessage->setRaw($messageBody);

            // Send the message
            $service->users_messages->send($userEmail, $gmailMessage);
        } catch (\Google\Service\Exception $e) {
            \Log::error('GmailApiTransport: Google API error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'errors' => $e->getErrors(),
            ]);
            throw new \RuntimeException('Gmail API error: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            \Log::error('GmailApiTransport: Unexpected error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the from email address, always from GlobalConfig if available
     */
    protected function getFromEmail(): string
    {
        // Try to get fresh value from GlobalConfig (in case it was updated)
        try {
            if (Schema::hasTable('global_configs')) {
                $email = GlobalConfig::getValue('gmail_user_email');
                if ($email) {
                    // Validate it's actually an email address, not a name
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        return $email;
                    } else {
                        // Log warning if we got a non-email value
                        \Log::warning('GmailApiTransport: GlobalConfig gmail_user_email is not a valid email', [
                            'value' => $email,
                            'is_valid' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback to cached value
            \Log::warning('GmailApiTransport: Error getting email from GlobalConfig', ['error' => $e->getMessage()]);
        }
        
        // Use cached value from constructor if valid
        if ($this->fromEmail && filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->fromEmail;
        }
        
        // Log error if we don't have a valid email
        \Log::error('GmailApiTransport: No valid email address found', [
            'cached_email' => $this->fromEmail,
            'is_cached_valid' => $this->fromEmail ? filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL) : false
        ]);
        
        // Fallback to 'me' which Gmail API accepts (but we'll validate this elsewhere)
        return 'me';
    }

    /**
     * Base64url encode (Gmail API requirement)
     */
    protected function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }
}

