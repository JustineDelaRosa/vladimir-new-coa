<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniqueMajorMinorAccTitle implements Rule
{
    private $ignoreId;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(int $ignoreId = null)
    {
        $this->ignoreId = $ignoreId;
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
        $query = DB::table('minor_categories')
            ->where([
                'major_category_id' => request('major_category_id'),
                'minor_category_name' => request('minor_category_name'),
                'account_title_sync_id' => request('account_title_sync_id')
            ]);

        if ($this->ignoreId !== null) {
            $query->where('id', '!=', $this->ignoreId);
        }

        return $query->doesntExist();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'This minor category already exists.';
    }
}
