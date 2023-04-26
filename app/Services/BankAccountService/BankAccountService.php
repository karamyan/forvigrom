<?php

declare(strict_types=1);

namespace App\Services\BankAccountService;


use App\BankAccount;
use App\Services\HttpClientService\Curl;
use App\Services\HttpClientService\HttpClientService;
use App\Services\PaymentService\Exceptions\ForbiddenResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class BankAccountService.
 *
 * @package App\Services\BankAccountService
 */
class BankAccountService
{
    /**
     * @var string
     */
    private const HOST = 'https://partneris.evoca.am:5875';

    /**
     * @var array|string[]
     */
    private array $paths = [
        'authorize' => '/api/Identity/authenticate',
        'clientCheck' => '/api/Account/ClientCheck',
        'createAccount' => '/api/Account/AddNonClientAccount',
    ];

    /**
     * @var string
     */
    private string $authorization;

    /**
     * @return mixed
     */
    public function getAuthorization(): string
    {
        return $this->authorization;
    }

    /**
     * @param mixed $authorization
     */
    public function setAuthorization(string $authorization): void
    {
        $this->authorization = $authorization;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function getPath(string $key): string
    {
        return $this->paths[$key];
    }

    public function __construct(Request $request, private array $configs)
    {
    }

    public function authorize(): void
    {
        $client = new HttpClientService(new Curl());

        $data = [
            'username' => $this->configs['username'],
            'password' => $this->configs['password']
        ];

        $content = $client->makeRequest(method: 'POST', url: self::HOST . $this->getPath('authorize'), config: [
            'headers' => ['Content-Type' => 'application/json'],
            'verify' => false
        ], options: [
            'body' => json_encode($data),
            'timeout' => 25
        ], data: $data);

        $result = json_decode($content, true);

        $this->setAuthorization($result['token']);
    }

    public function clientCheck()
    {
        $body = request()->all();

        $clientId = $body['client_id'];

        $bankAccount = BankAccount::where('client_id', $body['client_id'])->where('bank_slug', $body['bank'])->firstOrCreate([
            'client_id' => $body['client_id'],
            'bank_slug' => $body['bank'],
            'partner_account_id' => $body['partner_account_id'],
            'partner_id' => $body['partner_id'],
            'status' => BankAccountStatus::NEW,
        ]);

        if ($bankAccount->status !== BankAccountStatus::NEW) {
            return $bankAccount;
        }

        $this->authorize();
        $client = new HttpClientService(new Curl());

//        $data = [
//            'partnerClientId' => "42907496", //$body['client_id'],
//            "ssn" => '5414930805', //$body['social_number']
//            "mobile" => '37491066202', //$body['phone_number']
//            "passport" => 'AT03606640', //$body['passport_number']
//            "email" => 'sona.gharagyozyan@smartbet.am', //$body['email']
//        ];

        $data = [
            'partnerClientId' => "{$body['client_id']}",
            "ssn" => $body['ssn'],
            "mobile" => $body['mobile'],
            "passport" => $body['passport'],
            "email" => $body['email'],
        ];

        $bankAccount->status = BankAccountStatus::PROCESSING;
        $bankAccount->request_data = json_encode($data);
        $bankAccount->save();

        $content = $client->makeRequest(method: 'POST', url: self::HOST . $this->getPath('clientCheck'), config: [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->getAuthorization()
            ],
            'verify' => false
        ], options: [
            'body' => json_encode($data),
            'timeout' => 50
        ], data: $data);

        $bankAccount->response_data = $content;
        $bankAccount->save();

        $result = json_decode($content, true);

        $bankAccount['details'] = [
            'redirect_to' => 'https://api.evocabank.am:5541/EN/' . $result['requestId']
        ];

        return $bankAccount;
    }

    public function accountCallback($bankSlug)
    {
        if (config('app.env') === 'production') {
            $currentIp = $_SERVER['HTTP_X_REAL_IP'] ?? request()->ip();

            if ($currentIp !== '83.139.21.155') {
                Log::channel('errors')->error('IP address is not allowed.', [
                    'payment_request_id' => request()->get('payment_request_id'),
                    "ip" => $_SERVER['HTTP_X_REAL_IP'] ?? request()->ip(),
                    "request_uri" => request()->route()->uri(),
                    "request_body" => request()->all()
                ]);

                throw new ForbiddenResponseException('IP address is not allowed.', 403);
            }
        }

        $body = request()->all();

        $accountStatus = $body['accountStatus'];
        $bookmakerClientId = $body['bookmakerId'];

        $bankAccount = BankAccount::where('client_id', $bookmakerClientId)->where('bank_slug', $bankSlug)->whereIn('status', [BankAccountStatus::NEW, BankAccountStatus::PROCESSING])->first();

        if (is_null($bankAccount)) {
            Log::channel('errors')->info('Object not found', [
                'action' => 'accountCallback',
                'body' => $body,
            ]);
            throw new NotFoundHttpException('Object not found');
        }

        $status = $bankAccount->status;

        $bankAccount->callback_response_data = json_encode($body);
        $bankAccount->bank_account_id = $body['bindAccount']['Account'];

        if ($accountStatus == '0' || $accountStatus == '5') {
            $bankAccount->status = BankAccountStatus::PROCESSING;
        } elseif ($accountStatus == '1') {
            $bankAccount->status = BankAccountStatus::SUCCESS;
        } elseif ($accountStatus == '4') {
            $bankAccount->status = BankAccountStatus::FAILED;
        }

        $bankAccount->save();

        if ($status !== $bankAccount->status) {
            app('platform-api')->bankAccountCallback(details: $body, data: $bankAccount->toArray(), queueable: true);
        }

        return [
            "success" => true,
            "message" => 'Account status updated',
            "statusCode" => 100
        ];
    }
}
