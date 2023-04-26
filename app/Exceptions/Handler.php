<?php

namespace App\Exceptions;

use App\Http\Resources\TransactionResponse;
use App\Services\PaymentService\Exceptions\FieldValidationException;
use App\Services\PaymentService\Exceptions\ForbiddenResponseException;
use App\Services\PaymentService\Exceptions\InvalidTypeOfPaymentException;
use App\Services\PaymentService\Exceptions\PaymentProviderException;
use Error;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (PaymentProviderException $e) {
            $response = collect($e->getTransaction());
            $response->put('error_message', $e->getPaymentErrorMessage());
            $response->put('details', $e->getResponse());

            return (new TransactionResponse($response))->response();
        });

        $this->renderable(function (ValidationException $e) {
            return $this->apiErrorResponse(message: $e->getMessage(), errors: $e->errors(), status: $e->status);
        });

        $this->renderable(function (FieldValidationException $e) {
            return $this->apiErrorResponse(message: $e->getMessage(), errors: $e->getErrors(), status: $e->getCode());
        });

        $this->renderable(function (UnprocessableEntityHttpException $e) {
            return $this->apiErrorResponse(message: $e->getMessage(), status: $e->getStatusCode());
        });

        $this->renderable(function (NotFoundHttpException $e) {
            $msg = $e->getMessage() != '' ? $e->getMessage() : "Method not allowed.";

            return $this->apiErrorResponse(message: $msg, status: $e->getStatusCode());
        });

        $this->renderable(function (GuzzleException $e) {
            return $this->apiErrorResponse(message: $e->getMessage(), status: 503);
        });

        $this->renderable(function (ForbiddenResponseException $e) {
            return $this->apiErrorResponse(message: $e->getMessage(), status: $e->getCode());
        });

        $this->renderable(function (InvalidTypeOfPaymentException $e) {
            return $this->apiErrorResponse(message: $e->getMessage(), status: 503);
        });

        $this->renderable(function (RouteNotFoundException $e) {
            return $this->apiErrorResponse(message: 'Unauthorized', status: 401);
        });

        $this->renderable(function (Error $e) {
            return $this->apiErrorResponse(message: $e->getMessage(), status: $e->getCode());
        });
    }

    /**
     * Generate json error response.
     *
     * @param string $message
     * @param null $errors
     * @param int $status
     * @return JsonResponse
     */
    private function apiErrorResponse(string $message = '', array $errors = null, int $status = 0): JsonResponse
    {
        $data['error'] = true;
        $data['message'] = $message;
        $data['details'] = [];

        if ($errors) {
            $data['details'] = $errors;
        }

        return response()->json($data, $status);
    }
}
