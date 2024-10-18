<?php

namespace App\Http\Requests\ApiTokenRequest;

use App\Models\ApiToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CreateApiTokenRequest extends FormRequest
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
            'token' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Retrieve all tokens and check if the provided token matches any decrypted token
                    $tokens = ApiToken::get(['token']);
                    foreach ($tokens as $token) {
                        try {
                            if ($value == Crypt::decryptString($token->token)) {
                                $fail('Token already exists');
                                break;
                            }
                        } catch (\Exception $e) {
                            // Handle decryption error (e.g., log the error, skip the token, etc.)
                            $fail('Token decryption error');
                        }
                    }
                }
            ],
            'endpoint' => 'required|string|unique:api_tokens,endpoint',
            'p_name' => ['required', 'string', 'max:255', 'unique:api_tokens,p_name']
        ];
    }

    public function messages()
    {
        return [
            'token.required' => 'Token is required',
            'endpoint.required' => 'Endpoint is required',
            'endpoint.string' => 'Provide a valid endpoint',
            'p_name.required' => 'Project name is required',
            'p_name.unique' => 'Project name already exists'
        ];
    }
}
