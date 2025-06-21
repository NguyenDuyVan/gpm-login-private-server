<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Proxy;

class BulkCreateProxyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'proxies' => 'required|array|min:1|max:100',
            'proxies.*.raw_proxy' => [
                'required',
                'string',
                'max:500',
                function ($attribute, $value, $fail) {
                    // Basic validation for proxy format
                    if (!$this->isValidProxyFormat($value)) {
                        $fail("The proxy at {$attribute} must be in a valid proxy format.");
                    }
                },
            ],
            'proxies.*.status' => [
                'nullable',
                'string',
                'in:' . implode(',', [
                    Proxy::STATUS_ACTIVE,
                    Proxy::STATUS_INACTIVE,
                    Proxy::STATUS_TESTING,
                    Proxy::STATUS_ERROR
                ])
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'proxies.required' => 'Proxies array is required.',
            'proxies.array' => 'Proxies must be an array.',
            'proxies.min' => 'At least one proxy is required.',
            'proxies.max' => 'Cannot create more than 100 proxies at once.',
            'proxies.*.raw_proxy.required' => 'Each proxy must have a raw_proxy field.',
            'proxies.*.raw_proxy.max' => 'Proxy address cannot exceed 500 characters.',
            'proxies.*.status.in' => 'Status must be one of: active, inactive, testing, error.',
        ];
    }

    /**
     * Validate proxy format
     *
     * @param string $proxy
     * @return bool
     */
    private function isValidProxyFormat($proxy)
    {
        // Allow various proxy formats:
        // host:port
        // protocol://host:port
        // protocol://username:password@host:port

        $patterns = [
            // Basic host:port
            '/^[a-zA-Z0-9\.\-]+:\d+$/',
            // protocol://host:port
            '/^(https?|socks[45]):\/\/[a-zA-Z0-9\.\-]+:\d+$/',
            // protocol://username:password@host:port
            '/^(https?|socks[45]):\/\/[^@:]+:[^@:]+@[a-zA-Z0-9\.\-]+:\d+$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $proxy)) {
                return true;
            }
        }

        return false;
    }
}
