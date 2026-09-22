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
 * as little as possible. Only full name, email and password are required;
 * phone is optional. Business/fiscal details (company name, ICE, IF, RC,
 * address, currency, etc.) are never asked here - they belong to the
 * onboarding flow that runs after account creation.
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
        foreach (['name', 'phone'] as $field) {
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
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'string', 'email', 'max:255'],
            // Morocco-friendly, but never required - a user must be able
            // to create an account without entering a phone number.
            'phone'    => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'captcha_answer' => ['required'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'      => 'Indica tu nombre completo.',
            'email.required'     => 'Indica tu email.',
            'email.email'        => 'Introduce un email válido.',
            'password.required'  => 'Elige una contraseña.',
            'password.min'       => 'La contraseña debe tener al menos 8 caracteres.',
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
