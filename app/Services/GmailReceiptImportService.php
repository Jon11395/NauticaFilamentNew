<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\GlobalConfig;
use App\Models\Provider;
use App\Services\Geo\CostaRicaLocationMapper;
use App\Services\Receipts\XmlReceiptParser;
use App\Services\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Smalot\PdfParser\Parser;

class GmailReceiptImportService
{
    public function __construct(
        protected GmailService $gmailService,
        protected XmlReceiptParser $parser,
        protected CostaRicaLocationMapper $costaRicaLocationMapper,
        protected ExchangeRateService $exchangeRateService,
    ) {
    }

    public function import(?int $maxResults = null): array
    {
        $summary = [
            'messages_processed' => 0,
            'expenses_created' => 0,
            'attachments_considered' => 0,
            'skipped' => [],
            'errors' => [],
            'notes' => [],
        ];

        try {
            $messages = $this->gmailService->getTodayEmails($maxResults);
        } catch (\Throwable $exception) {
            Log::error('Unable to fetch Gmail messages for receipt import: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            $summary['errors'][] = "Failed to fetch today's Gmail messages: " . $exception->getMessage();

            return $summary;
        }

        // Process all receipts (including credit notes) as temporal expenses
        foreach ($messages as $message) {
            $summary['messages_processed']++;

            if (empty($message['attachments'])) {
                continue;
            }

            $createdForMessage = false;
            $storedPdfPath = null;
            $pdfAttachments = array_values(array_filter(
                $message['attachments'] ?? [],
                fn (array $attachment): bool => $this->looksLikePdfAttachment($attachment)
            ));

            foreach ($message['attachments'] as $attachment) {
                if ($this->looksLikePdfAttachment($attachment)) {
                    continue;
                }

                if (!$this->attachmentContainsInvoiceXml($attachment)) {
                    continue;
                }

                if (!$this->looksLikeReceiptAttachment($attachment)) {
                    continue;
                }

                $summary['attachments_considered']++;

                try {
                    $parsed = $this->parser->parse($attachment['data']);
                } catch (\Throwable $exception) {
                    $error = sprintf(
                        'Failed to parse XML attachment %s for message %s: %s',
                        $attachment['filename'] ?? '(sin nombre)',
                        $message['id'] ?? 'desconocido',
                        $exception->getMessage()
                    );

                    Log::warning($error, [
                        'exception' => $exception,
                        'message_id' => $message['id'] ?? null,
                    ]);

                    $summary['errors'][] = $error;

                    continue;
                }

                // Process all receipts as temporal expenses
                $context = $this->resolveContext($parsed, $message, $attachment);

                if (!$context['provider_id']) {
                    $summary['skipped'][] = sprintf(
                        'No se pudo resolver el proveedor para el comprobante %s (%s).',
                        $parsed['voucher'] ?? 'sin consecutivo',
                        $parsed['provider_name'] ?? 'sin emisor'
                    );
                    continue;
                }

                $normalizedVoucher = $this->normalizeVoucher($parsed['voucher'] ?? null);

                if ($normalizedVoucher === null) {
                    $summary['skipped'][] = sprintf(
                        'El comprobante de %s no tiene un consecutivo numérico válido.',
                        $parsed['provider_name'] ?? 'proveedor desconocido'
                    );
                    continue;
                }

                if ($this->expenseAlreadyExists($normalizedVoucher, $context['provider_id'])) {
                    $summary['skipped'][] = sprintf(
                        'Ya existe un gasto temporal con el comprobante %s para el proveedor #%d.',
                        $normalizedVoucher,
                        $context['provider_id']
                    );
                    continue;
                }

                try {
                    $attachmentPath = $this->storeAttachment($attachment['data'], $attachment['filename'] ?? null);
                    
                    // Detect currency early to use it in concept building
                    $detectedCurrency = null;
                    $exchangeRate = null;
                    $currencySource = null;
                    
                    // Check XML first for currency information
                    $xmlCurrency = $parsed['currency'] ?? null;
                    $xmlExchangeRate = $parsed['exchange_rate'] ?? null;
                    
                    if (!empty($xmlCurrency) && strtoupper($xmlCurrency) === 'USD') {
                        $detectedCurrency = 'USD';
                        $exchangeRate = $xmlExchangeRate;
                        $currencySource = 'XML';
                    } elseif (empty($xmlCurrency) && !empty($pdfAttachments)) {
                        // XML doesn't have currency info, check PDF
                        $detectedCurrency = $this->detectCurrencyFromPdf($pdfAttachments[0]);
                        if ($detectedCurrency === 'USD') {
                            $currencySource = 'PDF';
                        }
                    }
                    
                    // Build concept with currency awareness
                    $concept = $this->buildConcept($parsed, $message, $detectedCurrency);
                    $amount = $this->normalizeAmount($parsed['amount'] ?? null);

                    if ($amount === null) {
                        $summary['skipped'][] = sprintf(
                            'El comprobante %s no tiene un monto válido.',
                            $parsed['voucher'] ?? 'sin consecutivo'
                        );

                        Storage::disk('public')->delete($attachmentPath);

                        continue;
                    }

                    if (!$context['expense_type_id']) {
                        $summary['notes'][] = sprintf(
                            'Se creó el comprobante %s para %s sin tipo de gasto. Asignar manualmente.',
                            $normalizedVoucher,
                            $parsed['provider_name'] ?? ('proveedor #' . $context['provider_id'])
                        );
                    }

                    if ($storedPdfPath === null) {
                        $storedPdfPath = $this->storeFirstPdfAttachment($pdfAttachments);
                    }

                    // Currency detection was moved up before concept building
                    $originalUsdAmount = null;

                    // If currency is USD, convert to colones
                    $convertedAmount = null;
                    if ($detectedCurrency === 'USD') {
                        $originalUsdAmount = (float) $amount;
                        
                        Log::info('USD currency detected - starting conversion', [
                            'voucher' => $normalizedVoucher,
                            'original_usd_amount' => $originalUsdAmount,
                            'amount_before_conversion' => $amount,
                            'exchange_rate_before_fetch' => $exchangeRate,
                            'currency_source' => $currencySource,
                        ]);
                        
                        // Get exchange rate: use XML if available, otherwise fetch from BCR
                        if (empty($exchangeRate)) {
                            $receiptDate = $this->resolveIssueDate($parsed['issue_date'] ?? null);
                            
                            Log::info('Fetching exchange rate from BCCR', [
                                'voucher' => $normalizedVoucher,
                                'receipt_date' => $receiptDate->format('Y-m-d'),
                                'currency_source' => $currencySource,
                            ]);
                            
                            $exchangeRate = $this->exchangeRateService->getUsdToCrcRate($receiptDate);
                            $currencySource = $currencySource === 'XML' ? 'XML (sin TC)' : 'PDF';
                            
                            Log::info('Exchange rate fetched from BCCR', [
                                'voucher' => $normalizedVoucher,
                                'exchange_rate' => $exchangeRate,
                                'source' => $currencySource,
                                'receipt_date' => $receiptDate->format('Y-m-d'),
                            ]);
                        } else {
                            Log::info('Using exchange rate from XML', [
                                'voucher' => $normalizedVoucher,
                                'exchange_rate' => $exchangeRate,
                                'source' => $currencySource,
                            ]);
                        }
                        
                        // Convert USD to CRC - check if exchange rate is valid
                        if ($exchangeRate !== null && $exchangeRate > 0) {
                            // Convert USD to CRC
                            $convertedAmount = $originalUsdAmount * (float) $exchangeRate;
                            $amount = number_format($convertedAmount, 4, '.', '');
                            
                            Log::info('USD to CRC conversion completed', [
                                'voucher' => $normalizedVoucher,
                                'original_usd' => $originalUsdAmount,
                                'exchange_rate' => $exchangeRate,
                                'converted_crc' => $convertedAmount,
                                'amount_after_conversion' => $amount,
                                'source' => $currencySource,
                            ]);
                            
                            $exchangeRateNote = sprintf(' (TC: %s)', number_format((float) $exchangeRate, 4, '.', ','));
                            $summary['notes'][] = sprintf(
                                'El comprobante %s estaba en dólares (USD $%s) y fue convertido a colones (₡%s)%s. [Fuente TC: %s]',
                                $normalizedVoucher,
                                number_format($originalUsdAmount, 2, '.', ','),
                                number_format($convertedAmount, 2, '.', ','),
                                $exchangeRateNote,
                                $currencySource
                            );
                            
                            // Add conversion note to concept
                            $conversionNote = sprintf(
                                '<br/><small style="color:#10b981;">[✓ Convertido de USD $%s a ₡%s usando tipo de cambio(BCCR): %s]</small>',
                                number_format($originalUsdAmount, 2, '.', ','),
                                number_format($convertedAmount, 2, '.', ','),
                                number_format((float) $exchangeRate, 4, '.', ',')
                            );
                            $concept = $concept ? $concept . $conversionNote : $conversionNote;
                        } else {
                            // Couldn't get exchange rate, keep as USD but add warning
                            Log::warning('Could not get exchange rate for USD receipt - conversion NOT performed', [
                                'voucher' => $normalizedVoucher,
                                'original_usd' => $originalUsdAmount,
                                'exchange_rate' => $exchangeRate,
                                'exchange_rate_type' => gettype($exchangeRate),
                                'currency_source' => $currencySource,
                                'amount_will_remain_as_usd' => $amount,
                            ]);
                            
                            $summary['errors'][] = sprintf(
                                'El comprobante %s está en dólares (USD $%s) pero no se pudo obtener el tipo de cambio del BCCR. Verificar conversión manual.',
                                $normalizedVoucher,
                                number_format($originalUsdAmount, 2, '.', ',')
                            );
                            
                            $currencyNote = '<br/><small style="color:#f59e0b;">[⚠️ MONEDA: USD - No se pudo obtener tipo de cambio del BCCR]</small>';
                            $concept = $concept ? $concept . $currencyNote : $currencyNote;
                        }
                    }

                    // For credit notes, use the reference number (clave of original invoice) instead of the credit note's own clave
                    $claveToStore = ($parsed['receipt_type'] ?? null) === 'nota_credito' && isset($parsed['reference_info']['numero'])
                        ? $parsed['reference_info']['numero']
                        : ($parsed['clave'] ?? null);

                    // Log the final amount being saved
                    Log::info('Creating expense with final amount', [
                        'voucher' => $normalizedVoucher,
                        'final_amount' => $amount,
                        'detected_currency' => $detectedCurrency ?? 'CRC',
                        'is_usd_converted' => ($detectedCurrency === 'USD' && isset($convertedAmount)),
                    ]);

                    Expense::create([
                        'voucher' => $normalizedVoucher,
                        'clave' => $claveToStore,
                        'document_type' => $parsed['receipt_type'] ?? null,
                        'date' => $this->resolveIssueDate($parsed['issue_date'] ?? null),
                        'concept' => $concept,
                        'amount' => $amount,
                        'type' => $context['type'],
                        'provider_id' => $context['provider_id'],
                        'project_id' => $context['project_id'],
                        'expense_type_id' => $context['expense_type_id'],
                        'attachment' => $this->buildAttachmentPayload($storedPdfPath, $attachmentPath),
                        'temporal' => true,
                    ]);

                    $summary['expenses_created']++;
                    $createdForMessage = true;
                } catch (\Throwable $exception) {
                    $error = sprintf(
                        'No se pudo crear el gasto para el comprobante %s: %s',
                        $parsed['voucher'] ?? 'sin consecutivo',
                        $exception->getMessage()
                    );

                    Log::error($error, [
                        'exception' => $exception,
                        'message_id' => $message['id'] ?? null,
                    ]);

                    $summary['errors'][] = $error;
                }
            }

        }

        return $summary;
    }

    protected function looksLikeReceiptAttachment(array $attachment): bool
    {
        $filename = strtolower($attachment['filename'] ?? '');
        $mimeType = strtolower($attachment['mime_type'] ?? '');

        if ($filename === '' && $mimeType === '') {
            return false;
        }

        if (str_ends_with($filename, '.xml')) {
            return true;
        }

        return str_contains($mimeType, 'xml');
    }

    protected function normalizeVoucher(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        // Keep leading zeros by avoiding casting to int; ensure we only persist digits when present
        $digitsOnly = preg_replace('/[^0-9]/', '', $trimmed);

        if ($digitsOnly === '') {
            return null;
        }

        return $digitsOnly;
    }

    protected function expenseAlreadyExists(string $voucher, int $providerId): bool
    {
        return Expense::query()
            ->where('voucher', $voucher)
            ->where('provider_id', $providerId)
            ->exists();
    }

    protected function storeAttachment(string $data, ?string $filename = null): string
    {
        $extension = $filename ? pathinfo($filename, PATHINFO_EXTENSION) : 'xml';
        $extension = $extension ? strtolower($extension) : 'xml';

        $path = 'gmail-receipts/' . now()->format('Y/m/') . Str::orderedUuid()->toString();
        $path .= $extension ? ('.' . $extension) : '';

        Storage::disk('public')->put($path, $data);

        return $path;
    }

    protected function buildConcept(array $parsed, array $message, ?string $currency = null): ?string
    {
        $conceptLines = collect($parsed['concept_lines'] ?? [])
            ->map(function ($line) use ($currency) {
                $detail = isset($line['detail']) ? trim(strip_tags((string) $line['detail'])) : null;
                $total = isset($line['total']) ? $this->formatLineAmount($line['total'], $currency) : null;
                $quantity = isset($line['quantity']) ? $this->formatQuantity($line['quantity']) : null;

                return [
                    'detail' => $detail,
                    'total' => $total,
                    'quantity' => $quantity,
                ];
            })
            ->filter(fn ($line) => filled($line['detail']))
            ->unique('detail');

        if ($conceptLines->isNotEmpty()) {
            $items = $conceptLines
                ->map(function ($line) {
                    $detail = htmlspecialchars($line['detail'], ENT_QUOTES, 'UTF-8');
                    $quantity = $line['quantity'] !== null ? '<strong>' . $line['quantity'] . '</strong> × ' : '';
                    $amount = $line['total'] !== null ? ' <strong>' . $line['total'] . '</strong>' : '';

                    return '<li>' . $quantity . $detail . $amount . '</li>';
                })
                ->implode('');

            return '<ul>' . $items . '</ul>';
        }

        $subject = $message['subject'] ?? null;

        return $subject !== null
            ? htmlspecialchars($subject, ENT_QUOTES, 'UTF-8')
            : null;
    }

    protected function formatLineAmount(?string $amount, ?string $currency = null): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9,.-]/', '', $amount);

        if ($normalized === '' || $normalized === null) {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        $float = (float) $normalized;

        if (!is_finite($float)) {
            return null;
        }

        // Use dollar sign for USD receipts, colon symbol for CRC
        $symbol = ($currency === 'USD') ? '$' : '₡';
        
        return $symbol . number_format($float, 2, '.', ',');
    }

    protected function formatQuantity(?string $quantity): ?string
    {
        if ($quantity === null || $quantity === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9,.]/', '', $quantity);

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        $float = (float) $normalized;

        if (!is_finite($float)) {
            return null;
        }

        if (fmod($float, 1.0) === 0.0) {
            return number_format($float, 0, '.', ',');
        }

        return rtrim(rtrim(number_format($float, 3, '.', ','), '0'), '.');
    }

    protected function buildAttachmentPayload(?string $pdfPath, string $xmlPath): string
    {
        if (!empty($pdfPath)) {
            return $pdfPath;
        }

        return $xmlPath;
    }

    protected function normalizeAmount(?string $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9,.-]/', '', $amount);

        if ($normalized === '' || $normalized === null) {
            return null;
        }

        // If both comma and dot exist, assume comma is thousand separator
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        $float = (float) $normalized;

        if (!is_finite($float)) {
            return null;
        }

        return number_format($float, 4, '.', '');
    }

    protected function resolveIssueDate(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : now();
        } catch (\Throwable) {
            return now();
        }
    }

    protected function resolveContext(array $parsed, array $message, array $attachment): array
    {
        $rule = $this->findMatchingRule($parsed, $message, $attachment);

        $providerId = $this->resolveProviderId($parsed, $rule);
        $expenseTypeId = $this->resolveExpenseTypeId($parsed, $rule, $providerId);
        $type = $this->resolveExpenseType($parsed, $rule);
        $projectId = $this->resolveProjectId($rule);

        return [
            'provider_id' => $providerId,
            'expense_type_id' => $expenseTypeId,
            'type' => $type,
            'project_id' => $projectId,
        ];
    }

    protected function resolveProviderId(array $parsed, ?array $rule): ?int
    {
        if ($rule && isset($rule['provider_id'])) {
            return (int) $rule['provider_id'];
        }

        $identification = $this->normalizeIdentifier($parsed['provider_identification'] ?? null);

        if ($identification) {
            if ($provider = $this->findProviderByIdentification($identification)) {
                return $provider->id;
            }

            $map = $this->providerIdentificationMappings();
            foreach ($map as $key => $providerId) {
                if ($this->normalizeIdentifier((string) $key) === $identification) {
                    return (int) $providerId;
                }
            }
        }

        if (!empty($parsed['provider_name'])) {
            foreach ($this->preferredProviderNames($parsed) as $candidateName) {
                $provider = $this->findProviderByName($candidateName);

                if ($provider) {
                    return $provider->id;
                }
            }
        }

        if ($identification) {
            $created = $this->createProviderFromParsed($parsed, $identification);

            if ($created) {
                return $created->id;
            }
        }

        $default = GlobalConfig::getValue('gmail_default_provider_id');

        return $default !== null ? (int) $default : null;
    }

    protected function resolveExpenseTypeId(array $parsed, ?array $rule, ?int $providerId): ?int
    {
        if ($rule && isset($rule['expense_type_id'])) {
            return (int) $rule['expense_type_id'];
        }

        $mappings = $this->providerExpenseTypeMappings();

        if ($providerId && isset($mappings[$providerId])) {
            return (int) $mappings[$providerId];
        }

        $default = GlobalConfig::getValue('gmail_default_expense_type_id');

        return $default !== null ? (int) $default : null;
    }

    // Hacienda CR (v4.x) defines TipoTransaccion per line: 01 = Contado (cash),
        // 02/03/04 variants = Crédito/Plazo. We map credit-style codes (and textual
        // values containing "CREDITO" or "PLAZO") to unpaid, and treat the contado
        // codes (or textual "CONTADO") as paid before falling back to CondicionVenta.
    protected function resolveExpenseType(array $parsed, ?array $rule): string
    {
        if ($rule && isset($rule['type']) && in_array($rule['type'], ['paid', 'unpaid'], true)) {
            return $rule['type'];
        }

        return match ($parsed['sale_condition'] ?? null) {
            '01', '1' => 'paid',
            '02', '2', '03', '3' => 'unpaid',
            default => 'paid',
        };
    }

    protected function resolveProjectId(?array $rule): ?int
    {
        if ($rule && isset($rule['project_id'])) {
            return (int) $rule['project_id'];
        }

        $default = GlobalConfig::getValue('gmail_temporal_project_id');

        return $default !== null ? (int) $default : null;
    }

    protected function providerExpenseTypeMappings(): array
    {
        $mappings = GlobalConfig::getValue('gmail_provider_expense_type_map');

        if (is_array($mappings)) {
            return $mappings;
        }

        if (is_string($mappings)) {
            $decoded = json_decode($mappings, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function providerIdentificationMappings(): array
    {
        $mappings = GlobalConfig::getValue('gmail_provider_identification_map');

        if (is_array($mappings)) {
            return $mappings;
        }

        if (is_string($mappings)) {
            $decoded = json_decode($mappings, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function findMatchingRule(array $parsed, array $message, array $attachment): ?array
    {
        foreach ($this->receiptRules() as $rule) {
            if ($this->ruleMatches($rule, $parsed, $message, $attachment)) {
                return $rule;
            }
        }

        return null;
    }

    protected function receiptRules(): array
    {
        $rules = GlobalConfig::getValue('gmail_receipt_rules');

        if (is_array($rules)) {
            return $rules;
        }

        if (is_string($rules)) {
            $decoded = json_decode($rules, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function ruleMatches(array $rule, array $parsed, array $message, array $attachment): bool
    {
        $conditions = [
            fn () => !isset($rule['identification'])
                || $this->normalizeString($parsed['provider_identification'] ?? '') === $this->normalizeString($rule['identification']),
            fn () => !isset($rule['from_contains'])
                || str_contains(strtolower($message['from'] ?? ''), strtolower($rule['from_contains'])),
            fn () => !isset($rule['subject_contains'])
                || str_contains(strtolower($message['subject'] ?? ''), strtolower($rule['subject_contains'])),
            fn () => !isset($rule['filename_contains'])
                || str_contains(strtolower($attachment['filename'] ?? ''), strtolower($rule['filename_contains'])),
            fn () => !isset($rule['provider_name_contains'])
                || str_contains(strtolower($parsed['provider_name'] ?? ''), strtolower($rule['provider_name_contains'])),
        ];

        foreach ($conditions as $condition) {
            if (!$condition()) {
                return false;
            }
        }

        return true;
    }

    protected function normalizeString(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_replace('/\s+/', '', strtolower($value));
    }

    protected function normalizeIdentifier(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9a-zA-Z]/', '', $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function findProviderByIdentification(string $identification): ?Provider
    {
        return Provider::query()
            ->where(function ($query) use ($identification) {
                $query->where('identification', $identification)
                    ->orWhereRaw(
                        "replace(replace(replace(identification, ' ', ''), '-', ''), '.', '') = ?",
                        [$identification]
                    );
            })
            ->first();
    }

    protected function findProviderByName(string $name): ?Provider
    {
        $normalizedName = mb_strtolower(trim($name));

        if ($normalizedName === '') {
            return null;
        }

        $provider = Provider::query()
            ->whereRaw('lower(trim(name)) = ?', [$normalizedName])
            ->first();

        if ($provider) {
            return $provider;
        }

        return Provider::query()
            ->where('name', 'like', '%' . $name . '%')
            ->orderBy('id')
            ->first();
    }

    protected function createProviderFromParsed(array $parsed, string $identification): ?Provider
    {
        $locationIds = $this->determineLocationIds($parsed);

        if (!$locationIds) {
            $locationIds = $this->providerDefaults();

            if (!$locationIds) {
                Log::warning('No se pudo determinar la ubicación para crear el proveedor y no existen valores por defecto configurados.');

                return null;
            }
        }

        $names = $this->preferredProviderNames($parsed);
        $name = $names->first();

        if (!$name) {
            Log::warning('El comprobante no incluye un nombre de proveedor válido, no se puede crear.');

            return null;
        }

        $phone = $parsed['provider_phone'] ?? null;
        $phoneCountry = $parsed['provider_phone_country'] ?? null;

        if ($phone && $phoneCountry && !str_starts_with($phone, '+')) {
            $phone = '+' . trim($phoneCountry) . ' ' . trim($phone);
        }

        try {
            return Provider::create([
                'name' => trim($name),
                'phone' => $phone ? trim($phone) : null,
                'email' => $parsed['provider_email'] ?? null,
                'identification' => $identification,
                'country_id' => $locationIds['country_id'],
                'state_id' => $locationIds['state_id'],
                'city_id' => $locationIds['city_id'],
            ]);
        } catch (\Throwable $exception) {
            Log::error('No se pudo crear el proveedor automáticamente desde Gmail.', [
                'identification' => $identification,
                'name' => $name,
                'exception' => $exception,
            ]);

            return null;
        }
    }

    protected function providerDefaults(): ?array
    {
        $defaults = GlobalConfig::getValue('gmail_provider_defaults');

        if (is_string($defaults)) {
            $decoded = json_decode($defaults, true);
            $defaults = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($defaults)) {
            return null;
        }

        $countryId = $defaults['country_id'] ?? null;
        $stateId = $defaults['state_id'] ?? null;
        $cityId = $defaults['city_id'] ?? null;

        if (!$countryId || !$stateId || !$cityId) {
            return null;
        }

        return [
            'country_id' => (int) $countryId,
            'state_id' => (int) $stateId,
            'city_id' => (int) $cityId,
        ];
    }

    protected function determineLocationIds(array $parsed): ?array
    {
        $location = $parsed['provider_location'] ?? null;

        if (!is_array($location)) {
            return null;
        }

        $province = $location['province_code'] ?? null;
        $canton = $location['canton_code'] ?? null;

        if (!$province || !$canton) {
            return null;
        }

        $resolved = $this->costaRicaLocationMapper->resolve($location);

        if (!$resolved) {
            return null;
        }

        return [
            'country_id' => $resolved['country_id'],
            'state_id' => $resolved['state_id'],
            'city_id' => $resolved['city_id'],
        ];
    }

    protected function attachmentContainsInvoiceXml(array $attachment): bool
    {
        $filename = strtolower($attachment['filename'] ?? '');
        $mimeType = strtolower($attachment['mime_type'] ?? '');

        if ($filename === '' && $mimeType === '') {
            return false;
        }

        $isXml = str_ends_with($filename, '.xml') || str_contains($mimeType, 'xml');

        if (!$isXml) {
            return false;
        }

        $data = $attachment['data'] ?? '';

        if ($data === '') {
            return false;
        }

        $snippet = substr($data, 0, 4096);

        if ($this->snippetContainsInvoiceRoot($snippet)) {
            return true;
        }

        $decoded = base64_decode($data, true);

        if ($decoded !== false && $this->snippetContainsInvoiceRoot(substr($decoded, 0, 4096))) {
            return true;
        }

        return false;
    }

    protected function snippetContainsInvoiceRoot(string $snippet): bool
    {
        $lower = strtolower($snippet);
        $keywords = [
            'facturaelectronica',
            'tiqueteelectronico',
            'notacreditoelectronica',
            'notadebitoelectronica',
            'mensajehacienda',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function looksLikePdfAttachment(array $attachment): bool
    {
        $filename = strtolower($attachment['filename'] ?? '');
        $mimeType = strtolower($attachment['mime_type'] ?? '');

        return str_ends_with($filename, '.pdf') || str_contains($mimeType, 'pdf');
    }

    protected function storePdfAttachment(?string $data, ?string $filename = null): ?string
    {
        if (empty($data)) {
            return null;
        }

        $extension = $filename ? pathinfo($filename, PATHINFO_EXTENSION) : 'pdf';
        $extension = $extension ? strtolower($extension) : 'pdf';

        $path = 'expenses/attachments/' . now()->format('Y/m/') . Str::orderedUuid()->toString();
        $path .= $extension ? ('.' . $extension) : '';

        Storage::disk('public')->put($path, $data);

        return $path;
    }

    protected function storeFirstPdfAttachment(array $attachments): ?string
    {
        foreach ($attachments as $attachment) {
            $path = $this->storePdfAttachment($attachment['data'] ?? null, $attachment['filename'] ?? null);

            if ($path) {
                return $path;
            }
        }

        return null;
    }

    protected function preferredProviderNames(array $parsed): Collection
    {
        return collect([
            $parsed['provider_trade_name'] ?? null,
            $parsed['provider_name'] ?? null,
        ])->map(function ($value) {
            return $value !== null ? trim((string) $value) : null;
        })->filter()
            ->unique()
            ->values();
    }

    protected function processCreditNote(array $parsed, array $message, array $attachment, array &$summary): bool
    {
        try {
            // Get reference information
            $referenceInfo = $parsed['reference_info'] ?? null;
            
            if (!$referenceInfo || !isset($referenceInfo['numero'])) {
                $summary['skipped'][] = sprintf(
                    'La nota de crédito %s no tiene información de referencia válida.',
                    $parsed['voucher'] ?? 'sin consecutivo'
                );
                return false;
            }

            // Resolve context (provider, project, etc)
            $context = $this->resolveContext($parsed, $message, $attachment);

            if (!$context['provider_id']) {
                $summary['skipped'][] = sprintf(
                    'No se pudo resolver el proveedor para la nota de crédito %s (%s).',
                    $parsed['voucher'] ?? 'sin consecutivo',
                    $parsed['provider_name'] ?? 'sin emisor'
                );
                return false;
            }

            // Find the referenced expense using the reference number
            $referencedExpense = $this->findReferencedExpense($referenceInfo['numero'], $context['provider_id']);

            $creditAmount = $this->normalizeAmount($parsed['amount'] ?? null);

            if ($creditAmount === null) {
                $summary['skipped'][] = sprintf(
                    'La nota de crédito %s no tiene un monto válido.',
                    $parsed['voucher'] ?? 'sin consecutivo'
                );
                return false;
            }

            // Store the credit note attachment
            $attachmentPath = $this->storeAttachment($attachment['data'], $attachment['filename'] ?? null);
            $pdfAttachments = array_values(array_filter(
                $message['attachments'] ?? [],
                fn (array $att) => $this->looksLikePdfAttachment($att)
            ));
            $storedPdfPath = $this->storeFirstPdfAttachment($pdfAttachments);
            $creditAmountFloat = (float) $creditAmount;

            // If referenced expense exists, adjust it
            if ($referencedExpense) {
                // Calculate new amount (original - credit)
                $originalAmount = (float) $referencedExpense->amount;
                $newAmount = $originalAmount - $creditAmountFloat;

                // Update the referenced expense
                $oldConcept = $referencedExpense->concept;
                $creditReason = $referenceInfo['razon'] ?? 'Nota de crédito aplicada';
                
                // Build updated concept
                $updatedConcept = $oldConcept . '<br/><small style="color:#dc3545;">[NOTA DE CRÉDITO: ' . htmlspecialchars($creditReason, ENT_QUOTES, 'UTF-8') . ' por ₡' . number_format($creditAmountFloat, 2, '.', ',') . ']</small>';
                
                // Determine if we need to attach the credit note
                $currentAttachment = $referencedExpense->attachment;
                $attachmentPayload = $currentAttachment;
                
                // If there's a PDF for the credit note, add it to attachments
                if ($storedPdfPath) {
                    if (is_array($currentAttachment)) {
                        $attachmentPayload = array_merge($currentAttachment, [$storedPdfPath]);
                    } else if (is_string($currentAttachment)) {
                        $attachmentPayload = [$currentAttachment, $storedPdfPath];
                    } else {
                        $attachmentPayload = $storedPdfPath;
                    }
                }

                // Update the expense
                $referencedExpense->update([
                    'amount' => $newAmount >= 0 ? number_format($newAmount, 4, '.', '') : '0.0000',
                    'concept' => $updatedConcept,
                    'attachment' => $attachmentPayload,
                ]);

                $summary['notes'][] = sprintf(
                    'Nota de crédito %s aplicada al gasto %s: reducido de ₡%s a ₡%s.',
                    $parsed['voucher'] ?? 'sin consecutivo',
                    $referenceInfo['numero'],
                    number_format($originalAmount, 2, '.', ','),
                    number_format(max(0, $newAmount), 2, '.', ',')
                );

                Log::info('Nota de crédito procesada exitosamente', [
                    'credit_note_voucher' => $parsed['voucher'],
                    'referenced_expense_id' => $referencedExpense->id,
                    'original_amount' => $originalAmount,
                    'credit_amount' => $creditAmountFloat,
                    'new_amount' => $newAmount,
                ]);

                return true;
            }

            // If no referenced expense found, create a temporal expense for the credit note
            $normalizedVoucher = $this->normalizeVoucher($parsed['voucher'] ?? null);
            
            if ($normalizedVoucher === null) {
                $summary['skipped'][] = sprintf(
                    'La nota de crédito de %s no tiene un consecutivo numérico válido.',
                    $parsed['provider_name'] ?? 'proveedor desconocido'
                );
                return false;
            }

            // Check if this credit note already exists
            if ($this->expenseAlreadyExists($normalizedVoucher, $context['provider_id'])) {
                $summary['skipped'][] = sprintf(
                    'Ya existe un gasto temporal con la nota de crédito %s para el proveedor #%d.',
                    $normalizedVoucher,
                    $context['provider_id']
                );
                return false;
            }

            // Create temporal expense for credit note
            $concept = $this->buildConcept($parsed, $message);
            $creditReason = $referenceInfo['razon'] ?? 'Nota de crédito';
            
            // Build concept showing it's a credit note
            $creditConcept = $concept ? $concept . '<br/><small style="color:#dc3545;">[NOTA DE CRÉDITO: ' . htmlspecialchars($creditReason, ENT_QUOTES, 'UTF-8') . ' - Referencia: ' . htmlspecialchars($referenceInfo['numero'], ENT_QUOTES, 'UTF-8') . ']</small>' : '[NOTA DE CRÉDITO]';

            Expense::create([
                'voucher' => $normalizedVoucher,
                'clave' => $parsed['clave'] ?? null,
                'date' => $this->resolveIssueDate($parsed['issue_date'] ?? null),
                'concept' => $creditConcept,
                'amount' => $creditAmount,
                'type' => $context['type'],
                'provider_id' => $context['provider_id'],
                'project_id' => $context['project_id'],
                'expense_type_id' => $context['expense_type_id'],
                'attachment' => $this->buildAttachmentPayload($storedPdfPath, $attachmentPath),
                'temporal' => true,
            ]);

            $summary['notes'][] = sprintf(
                'Nota de crédito %s creada como gasto temporal (referencia al gasto %s no encontrada).',
                $parsed['voucher'] ?? 'sin consecutivo',
                $referenceInfo['numero']
            );
            $summary['expenses_created']++;

            Log::info('Nota de crédito creada como gasto temporal', [
                'credit_note_voucher' => $parsed['voucher'],
                'referenced_number' => $referenceInfo['numero'],
                'amount' => $creditAmountFloat,
            ]);

            return true;
        } catch (\Throwable $exception) {
            $error = sprintf(
                'Error al procesar la nota de crédito %s: %s',
                $parsed['voucher'] ?? 'sin consecutivo',
                $exception->getMessage()
            );

            Log::error($error, [
                'exception' => $exception,
                'message_id' => $message['id'] ?? null,
            ]);

            $summary['errors'][] = $error;
            return false;
        }
    }

    protected function findReferencedExpense(string $referenceNumber, int $providerId): ?Expense
    {
        // First try to find by exact Clave (the 50-digit unique identifier)
        $expense = Expense::query()
            ->where('provider_id', $providerId)
            ->where('clave', $referenceNumber)
            ->first();

        if ($expense) {
            return $expense;
        }

        // If not found by Clave, try by voucher number
        $expense = Expense::query()
            ->where('provider_id', $providerId)
            ->where(function ($query) use ($referenceNumber) {
                $query->where('voucher', $referenceNumber)
                    ->orWhereRaw('voucher LIKE ?', [substr($referenceNumber, -10) . '%']);
            })
            ->first();

        if ($expense) {
            return $expense;
        }

        // Extract the voucher number from the Clave (last 20 digits typically contain the NumeroConsecutivo)
        // The Clave format is: country code + company + location + environment + consecutive + security
        // The consecutive number is usually in the last portion
        $normalizedReference = $this->normalizeVoucher($referenceNumber);

        if (!$normalizedReference) {
            return null;
        }

        // Try to find by normalized voucher
        return Expense::query()
            ->where('provider_id', $providerId)
            ->where('voucher', $normalizedReference)
            ->first();
    }

    protected function detectCurrencyFromPdf(array $pdfAttachment): ?string
    {
        try {
            $data = $pdfAttachment['data'] ?? null;
            
            if (empty($data)) {
                return null;
            }

            // Determine PDF content - Gmail attachments are usually already decoded
            // but check if it's base64 encoded or already binary
            $pdfContent = $data;
            
            // Check if it's already a PDF (starts with PDF signature)
            if (str_starts_with($data, '%PDF')) {
                $pdfContent = $data;
            } else {
                // Try to decode as base64
                $decoded = base64_decode($data, true);
                if ($decoded !== false && strlen($decoded) > 0 && str_starts_with($decoded, '%PDF')) {
                    $pdfContent = $decoded;
                } else {
                    // If it's not base64 and not PDF, it might be the raw data
                    $pdfContent = $data;
                }
            }

            // Parse PDF using smalot/pdfparser
            $parser = new Parser();
            $pdf = $parser->parseContent($pdfContent);
            
            // Extract text from all pages (limit to first few pages for performance)
            $text = '';
            $pages = $pdf->getPages();
            $maxPages = min(3, count($pages)); // Check first 3 pages
            
            for ($i = 0; $i < $maxPages; $i++) {
                $text .= $pages[$i]->getText() . ' ';
            }
            
            // Normalize text for searching
            $normalizedText = strtolower($text);
            
            // Check for dollar indicators
            $dollarIndicators = [
                '$', // Dollar sign
                'usd',
                'dólar',
                'dolar',
                'dollar',
                'dólares',
                'dolares',
                'dollars',
            ];
            
            $colonIndicators = [
                '₡', // Colon symbol
                'colón',
                'colon',
                'colones',
                'crc',
            ];
            
            // Count occurrences of dollar and colon indicators
            $dollarCount = 0;
            $colonCount = 0;
            
            foreach ($dollarIndicators as $indicator) {
                $dollarCount += substr_count($normalizedText, $indicator);
            }
            
            foreach ($colonIndicators as $indicator) {
                $colonCount += substr_count($normalizedText, $indicator);
            }
            
            // If we find dollar signs/indicators and significantly more than colon indicators, it's USD
            if ($dollarCount > 0 && ($dollarCount > $colonCount || $colonCount === 0)) {
                // Additional check: look for USD patterns near amounts
                if (preg_match('/\$\s*[\d,]+\.?\d*/', $text) || preg_match('/(usd|dólar|dollar)\s*[\d,]+\.?\d*/i', $text)) {
                    return 'USD';
                }
            }
            
            // Default to CRC (Colones) if no clear indication
            return null;
        } catch (\Throwable $exception) {
            // Log error but don't fail the import
            Log::warning('Failed to detect currency from PDF', [
                'exception' => $exception,
                'filename' => $pdfAttachment['filename'] ?? null,
            ]);
            
            return null;
        }
    }
}

