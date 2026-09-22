<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use App\Services\MathCaptchaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a public, self-service trial registration
 * (routes/web.php POST /register -> RegisterTrialController::store()).
 *
 * Deliberately minimal - the brief is explicit that signup should ask for
 * as little as possible. Only first/last name, email, password, and the
 * business name are required; tax_id and country are optional, matching
 * the "do not ask for unnecessary information during signup" instruction.
 */
class RegisterTrialRequest extends FormRequest
{
    /**
     * Email normalization (task requirement): trim + lowercase before
     * validation/uniqueness checks/storage, so "User@Example.com" and
     * "user@example.com" are always treated as the same account.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => trim(strtolower((string) $this->input('email')))]);
        }
        foreach (['first_name', 'last_name', 'company_name', 'tax_id'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }
    }

    public function authorize(): bool
    {
        // Public endpoint - anyone may attempt to register. Abuse is
        // handled by the `register` rate limiter (routes/web.php) and the
        // math captcha below, not by an authorization check.
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'email'      => ['required', 'string', 'email', 'max:255'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
            'company_name' => ['required', 'string', 'max:150'],
            // Optional Spain/Morocco fiscal identifier - reused as-is by
            // TenantProvisioningService/CompanyProfile later; never
            // required at signup (see docs/morocco-phase-1b-identity.md's
            // own "do not force fiscal identifiers" precedent).
            'tax_id'   => ['nullable', 'string', 'max:32'],
            'country'  => ['nullable', 'string', 'size:2'],
            'captcha_answer' => ['required'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Indica tu nombre.',
            'email.required'      => 'Indica tu email.',
            'email.email'         => 'Introduce un email válido.',
            'password.required'   => 'Elige una contraseña.',
            'password.min'        => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed'  => 'Las contraseñas no coinciden.',
            'company_name.required' => 'Indica el nombre de tu negocio.',
            'captcha_answer.required' => 'Resuelve la comprobación de seguridad.',
        ];
    }

    /**
     * Additional checks that don't fit a static rule: the math captcha
     * (session-bound, consumed on verification - see MathCaptchaService)
     * and an application-level "this email already has a trial" guard.
     *
     * This is app-level, not a DB unique constraint on tenants.owner_email -
     * a deliberate choice: existing tenant data was never guaranteed to be
     * email-unique (nothing has ever enforced that), so adding a blind
     * unique index here risks failing to migrate against real data. The
     * actual hard, DB-level duplicate-tenant protection is the pre-existing
     * unique constraint on `domains.domain` (see
     * TenantProvisioningService::generateUniqueSubdomain()) plus the
     * request-level lock in RegisterTrialController - this check is the
     * user-facing "you already have an account" message on top of that.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!MathCaptchaService::verify($this->input('captcha_answer'))) {
                $validator->errors()->add('captcha_answer', 'La respuesta no es correcta. Inténtalo de nuevo.');
            }

            $email = $this->input('email');
            if ($email && Tenant::where('owner_email', $email)->exists()) {
                $validator->errors()->add('email', 'Ya existe una cuenta con este email. Inicia sesión en su lugar.');
            }
        });
    }
}
