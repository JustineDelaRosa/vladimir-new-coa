<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Base64ImageValidation implements Rule
{
    protected $accountability;
    public function __construct($accountability = null)
    {
        $this->accountability = $accountability;
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
        // Check if the value is a valid base64 string
        if (preg_match('/^data:image\/(\w+);base64,/', $value, $type)) {
            $data = substr($value, strpos($value, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif

            if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'svg'])) {
                return false;
            }

            $data = base64_decode($data);
            if ($data === false) {
                return false;
            }

            return true;

        } else if ($value == null && $this->accountability == 'Common') {
            return true;
        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Something went wrong with the image please try again.';
    }
}
