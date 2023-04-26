<?php

declare(strict_types=1);

namespace App\Services\HttpClientService;


interface MakesHttpRequest
{
    public function init(string $url = '', array $config = []): mixed;

    public function exec(string $method= '', string $url, array $config = [], array $options = [], array $data = [], string $action = ''): mixed;
}
