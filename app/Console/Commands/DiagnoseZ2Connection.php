<?php

namespace App\Console\Commands;

use App\Services\Z2\Z2Diagnostics;
use Illuminate\Console\Command;

class DiagnoseZ2Connection extends Command
{
    protected $signature = 'z2:diagnose';
    protected $description = 'Diagnose private cloud connection step by step';

    public function handle(Z2Diagnostics $diagnostics): void
    {
        $this->info('Running private cloud diagnostics...');
        $this->newLine();

        $results = $diagnostics->run();

        // Config
        $this->info('=== Configuration ===');
        $this->table(['Key', 'Value'], [
            ['Base URL', $results['config']['base_url']],
            ['Token', $results['config']['token']],
            ['Timeout', $results['config']['timeout']],
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
        }
        $this->newLine();

        // Status
        $this->info('=== Status Endpoint Test ===');
        $status = $results['status'];
        if ($status['success']) {
            $this->info('Success: YES');
            $this->info('Devices reported: ' . $status['devices']);
            $this->info('Online: ' . $status['online']);
            if (! empty($status['raw_keys'])) {
                $this->info('Response Keys: ' . implode(', ', $status['raw_keys']));
            }
        } else {
            $this->error('Status Error: ' . ($status['error'] ?? 'Unknown'));
        }
        $this->newLine();

        // Auth
        $this->info('=== Auth Test ===');
        $auth = $results['auth'];
        if ($auth['success']) {
            $this->info('Success: YES');
            $this->info('Authenticated: ' . ($auth['authenticated'] ? 'YES' : 'NO'));
            $this->info('Token: ' . $auth['token']);
        } else {
            $this->error('Auth Error: ' . ($auth['error'] ?? 'Unknown'));
        }
        $this->newLine();

        // Device List
        if (isset($results['device_list'])) {
            $this->info('=== Device List Test ===');
            $dl = $results['device_list'];
            $this->info('Has Response: ' . ($dl['has_response'] ? 'YES' : 'NO'));
            $this->info('Device Count: ' . $dl['count']);
            if (! empty($dl['raw_keys'])) {
                $this->info('Response Keys: ' . implode(', ', $dl['raw_keys']));
            }
            if (! empty($dl['first_device'])) {
                $this->info('First Device Fields: ' . implode(', ', $dl['first_device']));
            }
        }
        $this->newLine();

        // Media List
        if (isset($results['media_list'])) {
            $this->info('=== Media List Test ===');
            $ml = $results['media_list'];
            $this->info('Has Response: ' . ($ml['has_response'] ? 'YES' : 'NO'));
            $this->info('Media Count: ' . $ml['count']);
            if ($ml['first_file']) {
                $this->info('First File: ' . $ml['first_file']);
            }
        }
    }
}