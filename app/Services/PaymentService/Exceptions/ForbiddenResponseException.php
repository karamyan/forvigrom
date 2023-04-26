<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Class ForbiddenResponseException
 *
 * @package App\Services\Payments\Exceptions
 */
class ForbiddenResponseException extends Exception
{
    /**
     * Errors list
     *
     * @var array
     */
    protected $errors;

    /**
     * ForbiddenResponseException constructor.
     *
     * @param string $message
     * @param int $code
     * @param array $errors
     * @param Throwable|null $previous
     */
    public function __construct(string $message = "Forbidden", int $code = Response::HTTP_FORBIDDEN, array $errors = [], Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }
}
