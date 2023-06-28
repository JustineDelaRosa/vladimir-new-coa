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
                'printer_ip' => 'required|unique:printer_i_p_s,ip',
                'name' => 'required|unique:printer_i_p_s,name',
            ];
        }
        if($this->isMethod('put')){
            $id = $this->route()->parameter('printer-ip');
            return [
                //unique ignore his own id
                'printer_ip' => 'required|unique:printer_i_p_s,ip,'.$id,
                'name' => 'required|unique:printer_i_p_s,name,'.$id,
            ];
        }

        if ($this->isMethod('patch')) {
            return [
                'printer_id' => 'required|exists:printer_i_p_s,id',
            ];
        }
    }
}
