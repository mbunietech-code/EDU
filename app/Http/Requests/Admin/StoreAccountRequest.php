<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['auto_renew' => $this->boolean('auto_renew')] + self::loginMerge($this, true));
    }

    /**
     * Quick form: login email + password become the stored credentials
     * ("email: …\npassword: …" plus any extra notes) and the list name.
     */
    public static function loginMerge(FormRequest $request, bool $creating): array
    {
        $login = trim((string) $request->input('login_email', ''));
        $password = (string) $request->input('login_password', '');
        $merge = [];

        if ($login !== '' || $password !== '') {
            $extra = trim((string) $request->input('credentials', ''));
            $merge['credentials'] = trim(
                ($login !== '' ? "email: {$login}\n" : '').
                ($password !== '' ? "password: {$password}" : '').
                ($extra !== '' ? "\n{$extra}" : '')
            );

            // Blank, or still the "Waiting for login" placeholder: use the login.
            $name = trim((string) $request->input('name', ''));
            if ($login !== '' && ($name === '' || str_starts_with($name, 'Waiting for login'))) {
                $merge['name'] = $login;
            }
        }

        if ($creating && trim((string) $request->input('status', '')) === '') {
            $merge['status'] = 'available';
        }

        return $merge;
    }

    public function rules(): array
    {
        return \App\Models\Account::planRules() + [
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'credentials' => ['nullable', 'string', 'max:5000'],
            'login_email' => ['nullable', 'string', 'max:255'],
            'login_password' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['available', 'assigned', 'suspended', 'expired', 'maintenance', 'archived'])],
        ];
    }
}
