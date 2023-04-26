<?php

declare(strict_types=1);

namespace App\Services\HttpClientService;


use SoapClient;

class Soap implements MakesHttpRequest
{
    public function init(string $url = '', array $config = []): mixed
    {
        return new SoapClient($url, $config);
    }

    public function exec(string $method = '', string $url, array $config = [], array $options = [], array $data = [], string $action = ''): mixed
    {
        $caller = $this->init(url: $url, config: $options);

        $response = $caller->__soapCall($action, [
            $action => $data
        ]);

        return $response;
    }
}
