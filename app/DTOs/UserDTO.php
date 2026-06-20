<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class UserDTO
{
    /**
     * @param string|null $name
     * @param string|null $email
     * @param string|null $password
     * @param string|null $role
     * @param string|null $emailVerifiedAt
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $password = null,
        public readonly ?string $role = null,
        public readonly ?string $emailVerifiedAt = null,
    ) {
    }

    /**
     * Create a UserDTO from a Request.
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password'),
            role: $request->input('role'),
            emailVerifiedAt: $request->input('email_verified_at'),
        );
    }

    /**
     * Convert the DTO to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ];

        if ($this->password !== null) {
            $data['password'] = $this->password;
        }

        if ($this->emailVerifiedAt !== null) {
            $data['email_verified_at'] = $this->emailVerifiedAt;
        }

        return array_filter($data, fn ($value) => $value !== null);
    }
}
