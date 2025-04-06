<?php

namespace App\Integrations\Banking\Account;

use App\Integrations\Banking\Gateway;
use App\Exceptions\InternalErrorException;

class Create extends Gateway
{
    protected string $name;
    protected string $documentNumber;
    protected string $email;

    public function __construct(string $name, string $documentNumber, string $email)
    {
        $this->name = $name;
        $this->documentNumber = $documentNumber;
        $this->email = $email;
    }

    protected function requestUrl(): string
    {
        return 'accounts';
    }

    public function handle(): array
    {
        try {
            $url = $this->requestUrl();

            $response = $this->sendRequest(
                method: 'post',
                url:    $url,
                action: 'CREATE_ACCOUNT',
                params: [
                    'name'            => $this->name,
                    'document_number' => $this->documentNumber,
                    'email'           => $this->email,
                ]
            );

            return $this->formatDetailsResponse($response);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw (new InternalErrorException(
                'BANKING_CONNECTION_FAILED',
                170001001,

            ))->defineResponseCode(500);
        }
    }
}
