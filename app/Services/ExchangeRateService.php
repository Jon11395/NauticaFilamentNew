<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /**
     * Get USD to CRC exchange rate
     * 
     * @param Carbon|null $date Date for the exchange rate (defaults to today)
     * @return float|null Exchange rate or null if unable to fetch
     */
    public function getUsdToCrcRate(?Carbon $date = null): ?float
    {
        $date = ($date ?? now())->copy();
        
        // Adjust date if it's a weekend - BCCR doesn't publish rates on weekends
        // Use the last business day
        while ($date->isWeekend()) {
            $date->subDay();
        }
        
        // Cache key based on date
        $cacheKey = 'bccr_exchange_rate_' . $date->format('Y-m-d');
        
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($date) {
            return $this->fetchFromBCCR($date->copy());
        });
    }

    /**
     * Fetch exchange rate from Banco Central de Costa Rica
     * 
     * @param Carbon $date
     * @return float|null
     */
    protected function fetchFromBCCR(Carbon $date): ?float
    {
        try {
            // First try to get from BCCR ventanilla page (most reliable, no token required)
            $ventanillaRate = $this->fetchFromBCCRVentanilla($date);
            if ($ventanillaRate !== null) {
                return $ventanillaRate;
            }
            
            // BCCR Web Service for exchange rates
            // Indicator 317 is the USD to CRC purchase rate (compra) - this gives 498
            // Indicator 318 is the selling rate (venta) - this gives 520
            // We want compra (317) first since that's what Postman returns
            $indicatorCode = '317'; // Compra (purchase rate)
            $startDate = $date->format('d/m/Y');
            $endDate = $date->format('d/m/Y');
            
            // Use the XML endpoint like Postman does
            $url = 'https://gee.bccr.fi.cr/Indicadores/Suscripciones/WS/wsindicadoreseconomicos.asmx/ObtenerIndicadoresEconomicosXML';
            
            try {
                $token = config('services.bccr.token');
                
                $requestParams = [
                    'Indicador' => $indicatorCode,
                    'FechaInicio' => $startDate,
                    'FechaFinal' => $endDate, // Note: FechaFinal, not FechaFin
                    'Nombre' => config('services.bccr.nombre', 'Jonathan'),
                    'SubNiveles' => 'N',
                ];
                
                // Only add email and token if token is configured
                if ($token) {
                    $requestParams['CorreoElectronico'] = config('services.bccr.email', 'jopal11395@gmail.com');
                    $requestParams['Token'] = $token;
                }
                
                Log::info('BCCR API Request', [
                    'url' => $url,
                    'indicator' => $indicatorCode,
                    'date' => $date->format('Y-m-d'),
                    'params' => array_merge($requestParams, ['Token' => $requestParams['Token'] ?? '***NOT SET***']),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);
                
                $response = Http::timeout(10)
                    ->get($url, $requestParams);
                
                Log::info('BCCR API Response Status', [
                    'status_code' => $response->status(),
                    'successful' => $response->successful(),
                    'headers' => $response->headers(),
                ]);
                
                if ($response->successful()) {
                    $xmlContent = $response->body();
                    
                    // Log the FULL response for debugging
                    Log::info('BCCR API Response Body', [
                        'indicator' => $indicatorCode,
                        'date' => $date->format('Y-m-d'),
                        'response_length' => strlen($xmlContent),
                        'response_preview' => substr($xmlContent, 0, 500),
                        'full_response' => $xmlContent, // Log full response to see what Postman sees
                    ]);
                    
                    // Parse XML response
                    libxml_use_internal_errors(true);
                    $xml = simplexml_load_string($xmlContent);
                    
                    Log::info('BCCR XML Parsing Attempt', [
                        'xml_loaded' => $xml !== false,
                        'xml_string_length' => strlen($xmlContent),
                    ]);
                    
                    if ($xml !== false) {
                        // Log XML structure
                        $xmlString = $xml->asXML();
                        Log::debug('BCCR XML Structure', [
                            'xml_string_preview' => substr($xmlString, 0, 1000),
                            'xml_string_full' => $xmlString,
                        ]);
                        // Try multiple XPath patterns to find the value
                        $xpaths = [
                            '//*[local-name()="NUM_VALOR"]',
                            '//NUM_VALOR',
                            '//string',
                            '//*[contains(local-name(), "VALOR")]',
                            '//*[local-name()="INGC011_CAT_INDICADORECONOMIC"]/*[local-name()="NUM_VALOR"]',
                        ];
                        
                        foreach ($xpaths as $xpath) {
                            Log::debug('Trying XPath', ['xpath' => $xpath]);
                            $values = $xml->xpath($xpath);
                            
                            Log::debug('XPath Results', [
                                'xpath' => $xpath,
                                'values_count' => count($values),
                                'values' => array_map(fn($v) => (string) $v, $values ?? []),
                            ]);
                            
                            if (!empty($values)) {
                                foreach ($values as $val) {
                                    $value = trim((string) $val);
                                    Log::debug('Evaluating XPath Value', [
                                        'raw_value' => $value,
                                        'is_numeric' => is_numeric($value),
                                        'xpath' => $xpath,
                                    ]);
                                    
                                    if ($value !== '' && is_numeric($value)) {
                                        $rate = (float) $value;
                                        Log::info('Found Numeric Value', [
                                            'value' => $value,
                                            'rate' => $rate,
                                            'xpath' => $xpath,
                                            'in_range' => $rate > 400 && $rate < 1000,
                                        ]);
                                        
                                        if ($rate > 400 && $rate < 1000) { // Sanity check for CRC rates
                                            Log::info('Fetched exchange rate from BCCR API', [
                                                'indicator' => $indicatorCode,
                                                'date' => $date->format('Y-m-d'),
                                                'rate' => $rate,
                                                'xpath' => $xpath,
                                                'source' => 'BCCR API (Compra)',
                                            ]);
                                            return $rate;
                                        } else {
                                            Log::warning('Rate outside valid range', [
                                                'rate' => $rate,
                                                'range' => '400-1000',
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                        
                        // If XPath didn't work, try to find any numeric value in the XML
                        $xmlString = $xml->asXML();
                        Log::debug('Trying NUM_VALOR regex pattern');
                        if (preg_match('/<NUM_VALOR[^>]*>([\d.]+)<\/NUM_VALOR>/i', $xmlString, $matches)) {
                            Log::info('NUM_VALOR regex match found', [
                                'matches' => $matches,
                                'full_match' => $matches[0] ?? null,
                                'captured' => $matches[1] ?? null,
                            ]);
                            $rate = (float) $matches[1];
                            if ($rate > 400 && $rate < 1000) {
                                Log::info('Fetched exchange rate from BCCR API (regex)', [
                                    'indicator' => $indicatorCode,
                                    'date' => $date->format('Y-m-d'),
                                    'rate' => $rate,
                                    'source' => 'BCCR API (Compra)',
                                ]);
                                return $rate;
                            } else {
                                Log::warning('NUM_VALOR regex rate outside range', [
                                    'rate' => $rate,
                                    'range' => '400-1000',
                                ]);
                            }
                        } else {
                            Log::debug('NUM_VALOR regex pattern did not match');
                        }
                        
                        // Try to find any number that looks like an exchange rate
                        Log::debug('Trying pattern match for exchange rate format');
                        if (preg_match_all('/(\d{3})\.?(\d{2})/i', $xmlString, $allMatches)) {
                            Log::info('Pattern match found potential rates', [
                                'all_matches' => $allMatches[0],
                                'full_matches' => $allMatches,
                            ]);
                            foreach ($allMatches[0] as $match) {
                                $rate = (float) str_replace(',', '.', $match);
                                Log::debug('Evaluating pattern match', [
                                    'match' => $match,
                                    'rate' => $rate,
                                    'in_range' => $rate > 400 && $rate < 1000,
                                ]);
                                if ($rate > 400 && $rate < 1000) {
                                    Log::info('Fetched exchange rate from BCCR API (pattern match)', [
                                        'indicator' => $indicatorCode,
                                        'date' => $date->format('Y-m-d'),
                                        'rate' => $rate,
                                        'match' => $match,
                                        'source' => 'BCCR API (Compra)',
                                    ]);
                                    return $rate;
                                }
                            }
                        } else {
                            Log::debug('Pattern match did not find any exchange rate format');
                        }
                        
                        Log::warning('Could not extract exchange rate from XML using any method', [
                            'indicator' => $indicatorCode,
                            'date' => $date->format('Y-m-d'),
                            'xml_preview' => substr($xmlString, 0, 500),
                        ]);
                    } else {
                        $errors = libxml_get_errors();
                        libxml_clear_errors();
                        Log::warning('Failed to parse BCCR XML', [
                            'indicator' => $indicatorCode,
                            'errors' => array_map(fn($e) => $e->message, $errors),
                        ]);
                    }
                } else {
                    Log::error('BCCR API request failed', [
                        'indicator' => $indicatorCode,
                        'status' => $response->status(),
                        'headers' => $response->headers(),
                        'body_preview' => substr($response->body(), 0, 1000),
                        'body_full' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Error fetching from BCCR API', [
                    'indicator' => $indicatorCode,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Fallback: Try scraping from BCCR website
            $websiteRate = $this->fetchFromBCCRWebsite($date);
            
            if ($websiteRate !== null) {
                return $websiteRate;
            }
            
            // If all methods fail, log error and return null
            Log::error('All methods failed to fetch exchange rate from BCCR', [
                'date' => $date->format('Y-m-d'),
            ]);
            
            return null;
            
        } catch (\Throwable $exception) {
            Log::warning('Failed to fetch exchange rate from BCCR API', [
                'exception' => $exception->getMessage(),
                'date' => $date->format('Y-m-d'),
            ]);
            
            // Fallback to website scraping
            $websiteRate = $this->fetchFromBCCRWebsite($date);
            
            if ($websiteRate !== null) {
                return $websiteRate;
            }
            
            return null;
        }
    }

    /**
     * Fetch exchange rate from BCCR ventanilla page (no token required)
     * This is the most reliable source for Banco de Costa Rica compra rate
     * 
     * @param Carbon $date
     * @return float|null
     */
    protected function fetchFromBCCRVentanilla(Carbon $date): ?float
    {
        try {
            $response = Http::timeout(10)
                ->get('https://gee.bccr.fi.cr/IndicadoresEconomicos/Cuadros/frmConsultaTCVentanilla.aspx');
            
            if ($response->successful()) {
                $html = $response->body();
                
                Log::info('BCCR Ventanilla HTML', [
                    'html_length' => strlen($html),
                    'html_preview' => substr($html, 0, 5000),
                ]);
                
                // Try to find the table with Banco de Costa Rica
                // Look for the bank name followed by table cells with numbers
                
                // Pattern 1: Look for "Banco de Costa Rica" in a table row, then find first number (compra)
                // Match: <tr>...Banco de Costa Rica...<td>495.00</td>...<td>509.00</td>
                if (preg_match('/Banco\s+de\s+Costa\s+Rica.*?<td[^>]*>(\d{3})[,.](\d{2})<\/td>/is', $html, $matches)) {
                    $compra = (float) ($matches[1] . '.' . $matches[2]);
                    if ($compra > 400 && $compra < 1000) {
                        Log::info('Fetched exchange rate from BCCR Ventanilla (HTML table td)', [
                            'date' => $date->format('Y-m-d'),
                            'rate' => $compra,
                            'source' => 'BCCR Ventanilla - Banco de Costa Rica (Compra)',
                        ]);
                        return $compra;
                    }
                }
                
                // Pattern 2: Look for "Banco de Costa Rica" followed by any number format
                if (preg_match('/Banco\s+de\s+Costa\s+Rica[^0-9]*(\d{3})[,.](\d{2})/i', $html, $matches)) {
                    $compra = (float) ($matches[1] . '.' . $matches[2]);
                    if ($compra > 400 && $compra < 1000) {
                        Log::info('Fetched exchange rate from BCCR Ventanilla (compra)', [
                            'date' => $date->format('Y-m-d'),
                            'rate' => $compra,
                            'source' => 'BCCR Ventanilla - Banco de Costa Rica (Compra)',
                        ]);
                        return $compra;
                    }
                }
                
                // Pattern 3: Look for table structure with both rates (compra and venta)
                if (preg_match('/Banco\s+de\s+Costa\s+Rica[^0-9]*(\d{3})[,.](\d{2})[^0-9]*(\d{3})[,.](\d{2})/i', $html, $matches)) {
                    $compra = (float) ($matches[1] . '.' . $matches[2]);
                    if ($compra > 400 && $compra < 1000) {
                        Log::info('Fetched exchange rate from BCCR Ventanilla (table with both rates)', [
                            'date' => $date->format('Y-m-d'),
                            'rate' => $compra,
                            'compra' => $compra,
                            'venta' => (float) ($matches[3] . '.' . $matches[4]),
                            'source' => 'BCCR Ventanilla - Banco de Costa Rica',
                        ]);
                        return $compra;
                    }
                }
                
                // Pattern 4: Look for any 3-digit number followed by 2-digit number near "Banco de Costa Rica"
                // This handles cases where formatting is different
                if (preg_match('/Banco\s+de\s+Costa\s+Rica.*?(\d{3})\s*[,.]\s*(\d{2})/is', $html, $matches)) {
                    $compra = (float) ($matches[1] . '.' . $matches[2]);
                    if ($compra > 400 && $compra < 1000) {
                        Log::info('Fetched exchange rate from BCCR Ventanilla (flexible pattern)', [
                            'date' => $date->format('Y-m-d'),
                            'rate' => $compra,
                            'source' => 'BCCR Ventanilla - Banco de Costa Rica (Compra)',
                        ]);
                        return $compra;
                    }
                }
                
                // Pattern 5: Try without decimal separator (49500 -> 495.00)
                if (preg_match('/Banco\s+de\s+Costa\s+Rica[^0-9]*(\d{5})/i', $html, $matches)) {
                    $rawValue = $matches[1];
                    if (strlen($rawValue) === 5) {
                        $compra = (float) (substr($rawValue, 0, 3) . '.' . substr($rawValue, 3, 2));
                        if ($compra > 400 && $compra < 1000) {
                            Log::info('Fetched exchange rate from BCCR Ventanilla (5-digit format)', [
                                'date' => $date->format('Y-m-d'),
                                'rate' => $compra,
                                'raw_value' => $rawValue,
                                'source' => 'BCCR Ventanilla - Banco de Costa Rica',
                            ]);
                            return $compra;
                        }
                    }
                }
                
                // Pattern 6: Try to find any number in the 495-500 range near "Banco"
                // This is a last resort pattern
                if (preg_match_all('/(\d{3})[,.](\d{2})/i', $html, $allMatches, PREG_SET_ORDER)) {
                    foreach ($allMatches as $match) {
                        $potentialRate = (float) ($match[1] . '.' . $match[2]);
                        // Check if it's near "Banco" in the HTML
                        $pos = strpos($html, $match[0]);
                        $context = substr($html, max(0, $pos - 100), 200);
                        if (stripos($context, 'banco') !== false && $potentialRate > 490 && $potentialRate < 510) {
                            Log::info('Fetched exchange rate from BCCR Ventanilla (context-based)', [
                                'date' => $date->format('Y-m-d'),
                                'rate' => $potentialRate,
                                'context' => $context,
                                'source' => 'BCCR Ventanilla - Banco de Costa Rica (Compra)',
                            ]);
                            return $potentialRate;
                        }
                    }
                }
                
                Log::warning('Could not extract exchange rate from BCCR Ventanilla HTML', [
                    'html_snippet' => substr($html, 0, 5000),
                ]);
            } else {
                Log::error('Failed to fetch from BCCR Ventanilla', [
                    'date' => $date->format('Y-m-d'),
                    'status' => $response->status() ?? 'unknown',
                ]);
            }
            
            return null;
            
        } catch (\Throwable $exception) {
            Log::error('Failed to fetch exchange rate from BCCR Ventanilla', [
                'exception' => $exception->getMessage(),
                'date' => $date->format('Y-m-d'),
            ]);
            
            return null;
        }
    }
    
    /**
     * Fallback method to fetch exchange rate from BCCR website
     * 
     * @param Carbon $date
     * @return float|null
     */
    protected function fetchFromBCCRWebsite(Carbon $date): ?float
    {
        try {
            // BCCR publishes exchange rates at: https://www.bccr.fi.cr/indicadores-economicos/tipos-de-cambio
            // For the current date, try to get the latest rate
            
            $response = Http::timeout(10)
                ->get('https://www.bccr.fi.cr/indicadores-economicos/tipos-de-cambio');
            
            if ($response->successful()) {
                $html = $response->body();
                
                Log::debug('BCCR Website HTML', [
                    'html_length' => strlen($html),
                    'html_preview' => substr($html, 0, 1000),
                ]);
                
                // Try multiple patterns to find the purchase rate (compra)
                // Look for "compra" followed by a number, or USD/Dólar with compra
                $patterns = [
                    '/compra.*?USD.*?(\d{3,4}[,.]\d{2,4})/i',
                    '/USD.*?compra.*?(\d{3,4}[,.]\d{2,4})/i',
                    '/Dólar.*?compra.*?(\d{3,4}[,.]\d{2,4})/i',
                    '/compra[^<]*?(\d{3,4}[,.]\d{2,4})/i',
                    '/USD.*?(\d{3,4}[,.]\d{2,4})/i',
                    '/Dólar.*?(\d{3,4}[,.]\d{2,4})/i',
                ];
                
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $html, $matches)) {
                        $rateStr = str_replace(',', '.', $matches[1]);
                        $rate = (float) $rateStr;
                        
                        if ($rate > 400 && $rate < 1000) { // Sanity check for CRC rates
                            Log::info('Fetched exchange rate from BCCR website', [
                                'date' => $date->format('Y-m-d'),
                                'rate' => $rate,
                                'pattern' => $pattern,
                            ]);
                            return $rate;
                        }
                    }
                }
                
                Log::warning('Could not extract exchange rate from BCCR website HTML');
            }
            
            // Don't return a default - let it fail so we know there's an issue
            Log::error('Failed to extract exchange rate from BCCR website', [
                'date' => $date->format('Y-m-d'),
            ]);
            
            return null;
            
        } catch (\Throwable $exception) {
            Log::error('Failed to fetch exchange rate from BCCR website', [
                'exception' => $exception->getMessage(),
                'date' => $date->format('Y-m-d'),
            ]);
            
            return null;
        }
    }
}

