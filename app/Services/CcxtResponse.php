<?php

namespace App\Services;

/**
 * Response wrapper for CcxtExchangeService.
 * 
 * Provides backward compatibility with AccountExecutionJob which expects
 * Laravel HTTP response-like interface (->successful(), ->json(), ->body()).
 */
class CcxtResponse
{
    protected bool $success;
    protected array $data;

    public function __construct(bool $success, $data = [])
    {
        $this->success = $success;
        $this->data = is_array($data) ? $data : [];
    }

    /**
     * Check if the response was successful.
     * Compatible with Laravel HTTP response ->successful().
     */
    public function successful(): bool
    {
        return $this->success;
    }

    /**
     * Get the JSON decoded response body.
     * Compatible with Laravel HTTP response ->json().
     */
    public function json($key = null, $default = null)
    {
        if ($key !== null) {
            return $this->data[$key] ?? $default;
        }
        return $this->data;
    }

    /**
     * Get the raw response body as string.
     * Compatible with Laravel HTTP response ->body().
     */
    public function body(): string
    {
        return json_encode($this->data);
    }

    /**
     * Get the HTTP status code.
     * Compatible with Laravel HTTP response ->status().
     */
    public function status(): int
    {
        return $this->success ? 200 : 400;
    }

    /**
     * Magic accessor for data keys (e.g. $response->avgPrice).
     */
    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Check if a key exists in the data.
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }
}
