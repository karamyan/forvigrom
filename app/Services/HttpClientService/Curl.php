<?php

declare(strict_types=1);

namespace App\Services\HttpClientService;


use GuzzleHttp\Client;

class Curl implements MakesHttpRequest
{
    public function init(string $url = '', array $config = []): mixed
    {
        return new Client($config);
    }

    public function exec(string $method = '', string $url, $config = [], array $options = [], array $data = [], string $action = ''): mixed
    {
        $caller = $this->init(config: $config);

        $response = $caller->request($method, $url, $options);

        $content = $response->getBody()->getContents();

        return $content;
    }
}
