<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class FileOrX implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if ($value instanceof \Illuminate\Http\UploadedFile) {
            $mime = $value->getMimeType();
            return in_array($mime, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
//                'application/vnd.ms-excel',
//                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg',
                'image/png',
                'image/jpg',
            ]);
        }
        return $value === 'x';
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Please check the file type of :attribute. Currently, only PDF, DOC, DOCX, XLS, and XLSX are allowed.';
    }
}
