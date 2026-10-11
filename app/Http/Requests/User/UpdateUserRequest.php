<?php

namespace App\Http\Requests\User;

/**
 * Same fields as create; the password is optional (blank keeps it) and status has its own endpoint.
 */
class UpdateUserRequest extends StoreUserRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return collect(parent::rules())->except('is_active')->all();
    }

    protected function passwordRequired(): bool
    {
        return false;
    }
}
