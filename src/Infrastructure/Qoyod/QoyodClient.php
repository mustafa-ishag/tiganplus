<?php

namespace EtganERP\Infrastructure\Qoyod;

use Exception;

class QoyodClient
{
    private string $apiKey;
    private string $baseUrl = 'https://api.qoyod.com/2.0';

    public function __construct(string $apiKey)
    {
        if (empty($apiKey)) {
            throw new Exception("Qoyod API key is missing or empty.");
        }
        $this->apiKey = $apiKey;
    }

    /**
     * إرسال طلب إلى API قيود
     */
    private function request(string $method, string $endpoint, array $data = [])
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        
        $ch = curl_init($url);
        
        $headers = [
            'API-KEY: ' . $this->apiKey,
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Disable SSL verification for local dev if needed, but better to keep it true for production
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if (!empty($data) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }

        $decodedResponse = json_decode($response, true);
        
        if ($httpCode < 200 || $httpCode >= 300) {
            // Error response from Qoyod
            $errorMsg = "Qoyod API Error HTTP {$httpCode}: " . $response;
            if (isset($decodedResponse['errors'])) {
                $errorMsg .= " | Details: " . json_encode($decodedResponse['errors'], JSON_UNESCAPED_UNICODE);
            }
            throw new Exception($errorMsg);
        }

        return $decodedResponse;
    }

    /**
     * إنشاء فاتورة مبيعات في قيود
     *
     * @param array $invoiceData
     * @return array
     */
    public function createInvoice(array $invoiceData): array
    {
        // يجب أن نغلف البيانات بمفتاح 'invoice' حسب الـ API الخاص بإنشاء Invoice إذا كان مثل الـ Bills
        // في قيود الـ Invoice Payload يكون: {"invoice": { "contact_id": ..., "line_items": [...] }}
        $payload = ['invoice' => $invoiceData];
        
        return $this->request('POST', 'invoices', $payload);
    }
}
