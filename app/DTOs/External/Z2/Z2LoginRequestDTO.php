<?php

namespace App\DTOs\External\Z2;

/**
 * Data Transfer Object for Z2 login request.
 */
class Z2LoginRequestDTO
{
    public function __construct(
        public string $username,
        public string $password,
        public string $lang = 'en',
        public string $area = 'America',
        public string $systemFlag = '0',
        public string $appid = '1',
        public string $appversion = '200',
        public string $phone = 'web',
        public int $timezone = -5,
    ) {}

    /**
     * Convert the DTO to an array for API request.
     */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
            'lang' => $this->lang,
            'area' => $this->area,
            'systemFlag' => $this->systemFlag,
            'appid' => $this->appid,
            'appversion' => $this->appversion,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
        ];
    }
}
