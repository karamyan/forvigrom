<?php

declare(strict_types=1);

namespace App\Services\HttpClientService;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HttpClientService
{
    public function __construct(private MakesHttpRequest $caller)
    {
    }

    public function makeRequest(string $method = '', string $url, array $config = [], array $options = [], $data = [], string $action = '')
    {
        $start = microtime(true);
        $content = $this->caller->exec($method, $url, $config, $options, $data, $action);
        $end = microtime(true);

        $execution = ($end - $start);

        $platformRequestId = request()->input('platform_request_id');

        Log::channel('requests')->info('

        -----------------------------------------------------------------------------------------
        ');

        Log::channel('requests')->info('HttpClientService: ' . $platformRequestId, [
            'payment_request_id' => request()->get('payment_request_id'),
            'method' => $method,
            'url' => $url,
            'request_data' => $data,
            'response_data' => $content,
            'configs' => $config,
            'execution_time' => $execution
        ]);

        Log::channel('requests')->info('
        -----------------------------------------------------------------------------------------

        ');

        return $content;
    }
}
