<?php

namespace App\Http\Requests\AuthorizedTransferReceiver;

use Illuminate\Foundation\Http\FormRequest;

class CreateAuthorizedTransferReceiver extends FormRequest
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
            'user_id' => 'required|exists:users,id|unique:authorized_transfer_receivers,user_id',
        ];
    }

    public function messages()
    {
        return [
            'user_id.required' => 'User is required',
            'user_id.exists' => 'User does not exist',
            'user_id.unique' => 'User is already an authorized receiver',
        ];
    }
}
