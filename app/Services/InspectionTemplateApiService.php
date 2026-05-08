<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class InspectionTemplateApiService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.inspection_api.url'), '/');
        $this->token   = config('services.inspection_api.token');
    }

    /**
     * Base request handler (GET)
     */
    protected function getRequest(string $endpoint): array
    {
        $url = "{$this->baseUrl}{$endpoint}";

        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(30)
                ->get($url);

            return $this->handleResponse($response);

        } catch (RequestException $e) {
            return $this->handleException('Request timeout or connection error', $e);
        } catch (\Exception $e) {
            return $this->handleException('Unexpected error occurred', $e);
        }
    }

    /**
     * Standard response handler
     */
    protected function handleResponse($response): array
    {
        if ($response->failed()) {
            return [
                'success' => false,
                'message' => 'API request failed',
                'error'   => $response->json(),
                'status_code' => $response->status(),
            ];
        }

        $responseData = $response->json();

        return [
            'success' => true,
            'message' => 'Request success',
            'data' => $responseData['data'] ?? $responseData,
            'status_code' => $response->status(),
        ];
    }

    /**
     * Standard exception handler
     */
    protected function handleException(string $message, $exception): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error'   => $exception->getMessage(),
        ];
    }

    /**
     * Get all default inspection templates
     */
    public function getDefaultTemplates(): array
    {
        return $this->getRequest('/inspection/template/form-inspection-default');
    }

    /**
     * Get inspection template detail by ID
     */
    public function getTemplateDetail(int $id): array
    {
        return $this->getRequest("/inspection/template/form-inspection/{$id}");
    }
}