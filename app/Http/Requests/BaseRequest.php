<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseRequest extends FormRequest
{
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json(
                [
                    'message' => 'Invalid Request',
                    'errors' => [
                        'title' => 'Oops . Something went wrong , try again or contact the support',
                        'detail' => $validator->errors()->first(),
                    ],
                ], 422)
        );
    }
}
