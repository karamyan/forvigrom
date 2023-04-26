<?php

declare(strict_types=1);

namespace App\Services\PaymentService;

use App\Partner;
use App\Payment;
use App\Services\PaymentService\Exceptions\FieldValidationException;
use Ramsey\Uuid\Type\Integer;

/**
 * Class PaymentAbstract
 *
 * @package App\Services\Payment
 */
abstract class PaymentAbstract
{
    /**
     * @var float
     */
    protected float|int|string $amount;

    /**
     * @var string
     */
    private string $currency;

    protected bool $hasCallback = true;

    /**
     * @var string
     */
    private string $currencyCode;

    private array $requestData = [];

    /**
     * PaymentAbstract constructor.
     *
     * @param Payment $payment
     * @param array $configs
     * @param Partner $partner
     */
    public function __construct(private Payment $payment, private array $configs, private Partner $partner)
    {
    }

    /**
     * Get payment id.
     *
     * @return mixed
     */
    public function getId(): int
    {
        return $this->payment->id;
    }

    /**
     * Get payment configs
     *
     * @return array
     */
    public function getPayment(): Payment
    {
        return $this->payment;
    }

    /**
     * Get payment configs
     *
     * @return array
     */
    public function getPartner(): Partner
    {
        return $this->partner;
    }

    /**
     * Get payment configs
     *
     * @return array|null
     */
    protected function getConfigs(): ?array
    {
        return json_decode($this->configs['config'], true);
    }

    /**
     * Get deposit fields.
     *
     * @return array
     */
    protected function getDepositFields(): array
    {
        return $this->configs['deposit_fields'] ? json_decode($this->configs['deposit_fields'], true) : [];
    }

    /**
     * Get deposit  callback fields.
     *
     * @return array
     */
    protected function getDepositCallbackFields(): array
    {
        return $this->configs['deposit_callback_fields'] ? json_decode($this->configs['deposit_callback_fields'], true) : [];
    }

    /**
     * Get withdraw fields.
     *
     * @return array
     */
    protected function getWithdrawFields(): array
    {
        return $this->configs['withdraw_fields'] ? json_decode($this->configs['withdraw_fields'], true) : [];
    }

    /**
     * Get withdraw callback fields.
     *
     * @return array
     */
    protected function getWithdrawCallbackFields(): array
    {
        return $this->configs['withdraw_callback_fields'] ? json_decode($this->configs['withdraw_callback_fields'], true) : [];
    }

    /**
     * Get terminal deposit fields.
     *
     * @param string $action
     * @return array
     */
    protected function getTerminalDepositFields(string $action): ?array
    {
        return $this->configs['terminal_deposit_fields'] ? json_decode($this->configs['terminal_deposit_fields'], true)[$action] : [];
    }

    /**
     * Get mobile app deposit fields.
     *
     * @param string $action
     * @return array
     */
    protected function getMobileAppDepositFields(string $action): array
    {
        return $this->configs['mobile_app_deposit_fields'] ? json_decode($this->configs['mobile_app_deposit_fields'], true)[$action] : [];
    }

    /**
     * Validate payment specific deposit fields.
     *
     * @param array $body
     * @throws FieldValidationException
     */
    public function validateDepositFields(array $body): void
    {
        $depositFields = $this->getDepositFields();

        $this->validateFields($body, $depositFields);
    }

    /**
     *  Validate payment specific deposit callback fields.
     *
     * @param array $body
     * @throws FieldValidationException
     */
    public function validateDepositCallbackFields(array $body): void
    {
        $depositCallbackFields = $this->getDepositCallbackFields();

        $this->validateFields($body, $depositCallbackFields);
    }

    /**
     * Validate payment specific withdraw fields.
     *
     * @param array $body
     * @throws FieldValidationException
     */
    public function validateWithdrawFields(array $body): void
    {
        $depositFields = $this->getWithdrawFields();

        $this->validateFields($body, $depositFields);
    }

    /**
     *  Validate payment specific withdraw callback fields.
     *
     * @param array $body
     * @throws FieldValidationException
     */
    public function validateWithdrawCallbackFields(array $body): void
    {
        $depositCallbackFields = $this->getWithdrawCallbackFields();

        $this->validateFields($body, $depositCallbackFields);
    }

    /**
     * Validate request body from terminal request.
     *
     * @param array $body
     * @return array
     * @throws FieldValidationException
     */
    public function validateTerminalDepositFields(array $body): void
    {
        $terminalDepositFields = $this->getTerminalDepositFields($body['action']);

        $this->validateFields($body, $terminalDepositFields);
    }

    /**
     * Validate request body from mobile app request.
     *
     * @param array $body
     * @return array
     * @throws FieldValidationException
     */
    public function validateMobileAppDepositFields(array $body): void
    {
        $mobileAppDepositFields = $this->getMobileAppDepositFields($body['action']);

        $this->validateFields($body, $mobileAppDepositFields);
    }

    /**
     * Mapping terminal deposit fields.
     *
     * @param array $body
     * @return array
     */
    public function mappingTerminalDepositFields(array $body): array
    {
        $terminalDepositFields = $this->getTerminalDepositFields($body['action']);

        return $this->mappingFields($terminalDepositFields, $body);
    }

    /**
     * Mapping mobile app deposit fields.
     *
     * @param array $body
     * @return array
     */
    public function mappingMobileAppDepositFields(array $body): array
    {
        $mobileAppDepositFields = $this->getMobileAppDepositFields($body['action']);

        return $this->mappingFields($mobileAppDepositFields, $body);
    }

    /**
     * Validate phone number.
     *
     * @param string $number
     * @return array|string|null
     * @throws FieldValidationException
     */
    protected function validatePhoneNumber(string $number): array|string|null
    {
        $number = preg_replace('/^0/', '+374', $number);

        if (!preg_match('/^[+][0-9]{11}+$/', $number)) {
            throw new FieldValidationException('Bad request', 400, [
                'wallet_id' => 'The wallet_id field is not valid phone number.'
            ]);
        }

        return $number;
    }

    /**
     * Validate Deposit fields from body.
     *
     * @param array $body
     * @param array $fields
     * @throws FieldValidationException
     */
    protected function validateFields(array $body, array $fields): void
    {
        foreach ($fields as $key => $value) {
            if ($value['required']) {
                if (!array_key_exists($key, $body)) {
                    throw new FieldValidationException('The given data was invalid.', 400, [
                        $key => ['The ' . str_replace('_', ' ', $key) . ' field is required.']
                    ]);
                }
                $this->validateType(type: $value['type'], key: $key, value: $body[$key]);
            } else {
                if (array_key_exists($key, $body)) {
                    if (!is_null($body[$key])) {
                        $this->validateType(type: $value['type'], key: $key, value: $body[$key]);
                    }
                }
            }
        }
    }

    /**
     * @param string|array $type
     * @param string|array $field
     * @param string $key
     * @throws FieldValidationException
     */
    private function validateType(string $type, string $key, string|array|int|float $value): void
    {
        if ($type === 'numeric') {
            if (!is_numeric($value)) {
                throw new FieldValidationException('The given data was invalid.', 400, [
                    $key => ['The ' . str_replace('_', ' ', $key) . ' is invalid format.']
                ]);
            }
        } else {
            $type = explode('|', $type);
            if (!in_array(gettype($value), $type)) {
                throw new FieldValidationException('The given data was invalid.', 400, [
                    $key => ['The ' . str_replace('_', ' ', $key) . ' is invalid type.']
                ]);
            }
        }
    }

    /**
     * Mapping to body if field has mapped key.
     *
     * @param array $fields
     * @param array $body
     * @return array
     */
    protected function mappingFields(array $fields, array $body): array
    {
        $result = [];
        foreach ($fields as $key => $value) {
            if (array_key_exists('mapped', $value)) {
                if ($value['type'] === 'array') {
                    $result[$value['mapped']] = $body[$key][0];
                } else {
                    if (array_key_exists($key, $body)) {
                        $result[$value['mapped']] = $body[$key];
                    } else {
                        $result[$value['mapped']] = '';
                    }
                }
                unset($body[$key]);
            }
        }

        return array_merge($result, $body);
    }

    /**
     * @param $amount
     */
    public function setAmount($amount): void
    {
        $this->amount = floatval(number_format(intval($amount), 2, '.', ''));
    }

    /**
     * @return float
     */
    public function getAmount(): float|int|string
    {
        return $this->amount;
    }

    /**
     * @param $currency
     */
    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;

        $this->currencyCode = CurrencyCodes::AvailableCurrencyCodes[$currency];
    }

    /**
     * @return float
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @return float
     */
    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    /**
     * @param array $requestData
     */
    public function setRequestData(array $requestData): void
    {
        $this->requestData = $requestData;
    }

    /**
     * @param array $requestData
     * @return mixed
     */
    public function getRequestData(array $requestData): array
    {
        return $this->requestData;
    }

    /**
     * @return bool
     */
    public function isHasCallback(): bool
    {
        return $this->hasCallback;
    }
}
