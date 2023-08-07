<?php

namespace App\Http\Requests\PrinterIP;

use Illuminate\Foundation\Http\FormRequest;

class PrinterIPRequest extends FormRequest
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
        if($this->isMethod('post')){
            return [
                //only allow ip with 10.10.x.x format
                'ip' => 'required|unique:printer_i_p_s,ip|ip|regex:/^10\.10\.\d{1,3}\.\d{1,3}$/',
                'name' => 'required|unique:printer_i_p_s,name',
            ];
        }
        if($this->isMethod('put') && ($this->route()->parameter('printer_ip'))){
            $id = $this->route()->parameter('printer_ip');
            return [
                //unique ignore his own id
                'ip' => 'required|ip|unique:printer_i_p_s,ip,'.$id,
                'name' => 'required|unique:printer_i_p_s,name,'.$id,
            ];
        }

        if ($this->isMethod('patch')) {
            return [
                'printer_id' => 'required|exists:printer_i_p_s,id',
            ];
        }
    }

    public function messages(): array
    {
        return [
            'ip.regex' => 'The ip format is invalid.',
            'ip.unique' => 'The ip has already been taken.',
            'name.unique' => 'The name has already been taken.',
            'printer_id.required' => 'The printer id field is required.',
            'printer_id.exists' => 'The printer id field is invalid.',
        ];
    }
}
