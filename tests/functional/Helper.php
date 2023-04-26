<?php

declare(strict_types=1);

namespace Tests\functional;

use Exception;

/**
 * Class Helper.
 *
 * @package Tests\functional
 */
class Helper
{
    /**
     * Response keys.
     *
     * @var array
     */
    public array $jsonKeys = [];

    /**
     * Get response all keys.
     *
     * @param array $fields
     * @return array
     */
    private function getJsonKeys(array $fields): array
    {
        foreach ($fields as $key => $val) {
            if (is_array($val)) {
                $this->getJsonKeys($val);
            }

            if (gettype($key) !== 'integer')
                $this->jsonKeys[] = $key;
        }

        return $this->jsonKeys;
    }

    /**
     * Asserting json response with end result.
     *
     * @param $response
     * @param $result
     * @throws Exception
     */
    public function seeIsEqualResponseJsonKeys($response, $result)
    {
        $response = json_decode(json: $response, associative: true);

        $keys = $this->getJsonKeys($response);
        if (count(array_diff($keys, $result)) !== 0) {
            throw new Exception('Invalid response struct.');
        }
    }
}
