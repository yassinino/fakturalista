<?php

namespace App\Http\Requests;

use App\Models\Feedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by the public site (POST /feedback, central) and the tenant app
 * (POST /api/feedback, auth:api). Only these keys ever reach the service:
 * a tenant_id/user_id/source/status in the body is simply ignored.
 */
class StoreFeedbackRequest extends FormRequest
{
    public const MAX_MESSAGE    = 5000;
    public const MAX_SCREENSHOT = 5120; // KB

    public function authorize(): bool
    {
        return true;
    }

    /** Tenant app submission: the email comes from the authenticated user. */
    public function isTenantSubmission(): bool
    {
        return tenancy()->initialized && $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $message = $this->input('message');

        if (is_string($message)) {
            // Plain text only: drop NUL/control characters (keep \t \n \r)
            // and normalise line endings. Output is always escaped.
            $message = preg_replace('/[^\P{C}\t\n\r]/u', '', $message) ?? '';
            $this->merge(['message' => trim(str_replace("\r\n", "\n", $message))]);
        }
    }

    public function rules(): array
    {
        return [
            'type'       => ['required', 'string', Rule::in(Feedback::TYPES)],
            'message'    => ['required', 'string', 'min:5', 'max:' . self::MAX_MESSAGE],
            'email'      => $this->isTenantSubmission()
                ? ['exclude']
                : ['required', 'string', 'email:rfc', 'max:255'],
            'page_url'   => ['nullable', 'string', 'max:2048'],
            'screenshot' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:' . self::MAX_SCREENSHOT,
                'dimensions:max_width=10000,max_height=10000',
            ],
        ];
    }
}
