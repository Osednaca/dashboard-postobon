<?php

namespace App\Services\Z2;

use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

class Z2Diagnostics
{
    private FanCloudService $client;

    public function __construct(FanCloudService $client)
    {
        $this->client = $client;
    }

    public function run(): array
    {
        $results = [];

        // 1. Check configuration
        $results['config'] = [
            'base_url' => config('z2.base_url'),
            'username' => $this->client->username ? 'SET' : 'NOT SET',
            'password' => config('z2.password') ? 'SET' : 'NOT SET',
        ];

        // 2. Test raw HTTP connectivity
        $results['http_connectivity'] = $this->testRawHttp();

        // 3. Test RSA key fetch
        $results['rsa_key'] = $this->testRsaKey();

        // 4. Test RSA encryption
        $results['rsa_encrypt'] = $this->testRsaEncryption();

        // 5. Test login
        $results['login'] = $this->testLogin();

        // 6. Test device list
        if ($results['login']['success'] ?? false) {
            $results['device_list'] = $this->testDeviceList();
        }

        return $results;
    }

    private function testRawHttp(): array
    {
        try {
            $ch = curl_init(config('z2.base_url') . '/admin/AdminLoginR');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            return [
                'http_code' => $httpCode,
                'has_error' => !empty($error),
                'error' => $error,
                'response_length' => strlen($response),
                'has_jsessionid' => str_contains($response, 'JSESSIONID'),
            ];
        } catch (\Throwable $e) {
            return [
                'http_code' => null,
                'has_error' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function testRsaKey(): array
    {
        try {
            $response = $this->client->request('GET', '/admin/AdminLoginR', [], false);

            if ($response === null) {
                return ['success' => false, 'error' => 'Response is null'];
            }

            return [
                'success' => true,
                'has_pubmodules_base64' => isset($response['pubmodules_base64']),
                'has_pubexponent' => isset($response['pubexponent']),
                'pubmodules_base64_length' => isset($response['pubmodules_base64']) ? strlen($response['pubmodules_base64']) : 0,
                'raw_keys' => array_keys($response),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function testRsaEncryption(): array
    {
        try {
            $response = $this->client->request('GET', '/admin/AdminLoginR', [], false);
            if (! $response || ! isset($response['pubmodules_base64'])) {
                return ['success' => false, 'error' => 'No RSA key available'];
            }

            $pubModulesBase64 = $response['pubmodules_base64'];
            $pem = "-----BEGIN PUBLIC KEY-----\n"
                 . chunk_split($pubModulesBase64, 64, "\n")
                 . "-----END PUBLIC KEY-----";

            $key = PublicKeyLoader::load($pem)->withPadding(RSA::ENCRYPTION_NONE);
            $password = config('z2.password');
            $blockSize = 128;
            $data = str_repeat("\x00", $blockSize - strlen($password)) . $password;
            $encrypted = $key->encrypt($data);

            return [
                'success' => true,
                'encrypted_length' => strlen($encrypted),
                'hex_length' => strlen(bin2hex($encrypted)),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function testLogin(): array
    {
        try {
            // First clear any existing session to force fresh auth
            \Illuminate\Support\Facades\Cache::forget('z2_session_cookies');
            \Illuminate\Support\Facades\Cache::forget('z2_advertiser_id');

            // Get RSA key manually
            $keyData = $this->client->request('GET', '/admin/AdminLoginR', [], false);
            if (! $keyData || ! isset($keyData['pubmodules_base64'])) {
                return ['success' => false, 'error' => 'Failed to get RSA key'];
            }

            // Encrypt password with textbook RSA (NO_PADDING, zero-padded)
            $pubModulesBase64 = $keyData['pubmodules_base64'];
            $password = config('z2.password');

            $pem = "-----BEGIN PUBLIC KEY-----\n"
                 . chunk_split($pubModulesBase64, 64, "\n")
                 . "-----END PUBLIC KEY-----";

            $key = PublicKeyLoader::load($pem)->withPadding(RSA::ENCRYPTION_NONE);
            $blockSize = 128;
            $data = str_repeat("\x00", $blockSize - strlen($password)) . $password;
            $encrypted = $key->encrypt($data);
            $encryptedPassword = bin2hex($encrypted);

            // Call login with phpseclib3 encryption
            $rawResponse = $this->client->request('POST', '/User/loginR', [
                'userName' => $this->client->username,
                'password' => $encryptedPassword,
                'loginType' => '1',
            ], false);

            $success = ($rawResponse['result'] ?? -1) === 0;

            // Save authenticated session on success
            if ($success) {
                $this->client->markAuthenticated();
                $this->client->saveSession();
                if (isset($rawResponse['adVertisers']['idAdvertiser'])) {
                    $this->client->setAdvertiserId((string) $rawResponse['adVertisers']['idAdvertiser']);
                }
            }

            return [
                'success' => $success,
                'method_used' => 'phpseclib3-NO_PADDING-zero-padded',
                'authenticated' => $this->client->isAuthenticated(),
                'session_id' => $this->client->getSessionId() ? 'PRESENT' : 'MISSING',
                'raw_response' => $rawResponse,
                'error' => ! $success ? 'Login returned result: ' . ($rawResponse['result'] ?? 'null') . ' - message: ' . ($rawResponse['msg'] ?? $rawResponse['message'] ?? 'none') : null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function testDeviceList(): array
    {
        try {
            // Get group list first
            $groupResponse = $this->client->request('POST', '/User/groupList', [
                'userName' => $this->client->username,
            ]);

            $groupIds = [0];
            if ($groupResponse && isset($groupResponse['aaData'])) {
                foreach ($groupResponse['aaData'] as $group) {
                    $groupIds[] = $group['idGroup'] ?? 0;
                }
            }

            $totalDevices = 0;
            $firstDevice = null;
            foreach ($groupIds as $groupId) {
                $response = $this->client->request('POST', '/User/groupDeviceList', [
                    'userName' => $this->client->username,
                    'iDisplayStart' => 0,
                    'iDisplayLength' => 50,
                    'deviceCode' => '',
                    'groupID' => $groupId,
                ]);

                if ($response && isset($response['aaData'])) {
                    $totalDevices += count($response['aaData']);
                    if ($firstDevice === null && count($response['aaData']) > 0) {
                        $firstDevice = array_keys($response['aaData'][0]);
                    }
                }
            }

            return [
                'has_response' => true,
                'has_aaData' => $totalDevices > 0,
                'count' => $totalDevices,
                'raw_keys' => ['result', 'iTotalRecords', 'aaData', 'iTotalDisplayRecords'],
                'first_device' => $firstDevice ?? [],
            ];
        } catch (\Throwable $e) {
            return [
                'has_response' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
