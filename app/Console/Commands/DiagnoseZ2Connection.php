<?php

namespace App\Console\Commands;

use App\Services\Z2\Z2Diagnostics;
use Illuminate\Console\Command;

class DiagnoseZ2Connection extends Command
{
    protected $signature = 'z2:diagnose';
    protected $description = 'Diagnose Z2 Cloud connection step by step';

    public function handle(Z2Diagnostics $diagnostics): void
    {
        $this->info('Running Z2 Cloud diagnostics...');
        $this->newLine();

        $results = $diagnostics->run();

        // Config
        $this->info('=== Configuration ===');
        $this->table(['Key', 'Value'], [
            ['Base URL', $results['config']['base_url']],
            ['Username', $results['config']['username']],
            ['Password', $results['config']['password']],
        ]);
        $this->newLine();

        // HTTP Connectivity
        $this->info('=== HTTP Connectivity Test ===');
        $http = $results['http_connectivity'];
        if ($http['has_error']) {
            $this->error('HTTP Error: ' . $http['error']);
        } else {
            $this->info('HTTP Code: ' . $http['http_code']);
            $this->info('Response Length: ' . $http['response_length']);
            $this->info('Has JSESSIONID: ' . ($http['has_jsessionid'] ? 'YES' : 'NO'));
        }
        $this->newLine();

        // RSA Key
        $this->info('=== RSA Key Test ===');
        $rsa = $results['rsa_key'];
        if ($rsa['success']) {
            $this->info('Success: YES');
            $this->info('Has pubmodules_base64: ' . ($rsa['has_pubmodules_base64'] ? 'YES' : 'NO'));
            $this->info('Has pubexponent: ' . ($rsa['has_pubexponent'] ? 'YES' : 'NO'));
            $this->info('Base64 Length: ' . $rsa['pubmodules_base64_length']);
        } else {
            $this->error('RSA Key Error: ' . ($rsa['error'] ?? 'Unknown'));
        }
        $this->newLine();

        // RSA Encryption
        $this->info('=== RSA Encryption Test ===');
        $enc = $results['rsa_encrypt'];
        if ($enc['success']) {
            $this->info('Success: YES');
            $this->info('Encrypted Length: ' . $enc['encrypted_length']);
            $this->info('Hex Length: ' . $enc['hex_length']);
        } else {
            $this->error('RSA Encryption Error: ' . ($enc['error'] ?? 'Unknown'));
        }
        $this->newLine();

        // Login
        $this->info('=== Login Test ===');
        $login = $results['login'];
        if ($login['success']) {
            $this->info('Success: YES');
            $this->info('Authenticated: ' . ($login['authenticated'] ? 'YES' : 'NO'));
            $this->info('Session ID: ' . $login['session_id']);
        } else {
            $this->error('Login Error: ' . ($login['error'] ?? 'Unknown'));
            if (isset($login['raw_response'])) {
                $this->warn('Raw Response: ' . json_encode($login['raw_response']));
            }
        }
        $this->newLine();

        // Device List
        if (isset($results['device_list'])) {
            $this->info('=== Device List Test ===');
            $dl = $results['device_list'];
            $this->info('Has Response: ' . ($dl['has_response'] ? 'YES' : 'NO'));
            $this->info('Has aaData: ' . ($dl['has_aaData'] ? 'YES' : 'NO'));
            $this->info('Device Count: ' . $dl['count']);
            if (!empty($dl['raw_keys'])) {
                $this->info('Response Keys: ' . implode(', ', $dl['raw_keys']));
            }
        }
    }
}
