<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Exceptions;

use Exception;
use JetBrains\PhpStorm\Pure;
use Throwable;

/**
 * Class FieldValidationException.
 *
 * @package App\Services\Payment\Exceptions
 */
class FieldValidationException extends Exception
{
    /**
     * Errors list
     *
     * @var array
     */
    protected $errors;

    /**
     * FieldValidationException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param array           $errors
     * @param \Throwable|null $previous
     */
    #[Pure]
    public function __construct(string $message = "", int $code = 0, array $errors = [], Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Return errors list.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
