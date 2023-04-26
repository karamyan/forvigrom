<?php

declare(strict_types=1);

namespace App\Services\PaymentService\Exceptions;

use RuntimeException;

/**
 * Class ForbiddenResponseException.
 *
 * @package App\Services\Payment\Exceptions
 */
class InvalidTypeOfPaymentException extends RuntimeException
{
}
