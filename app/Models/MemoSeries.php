<?php

namespace App\Models;

use App\Filters\MemoSeriesFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemoSeries extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $guarded = [];

    protected string $default_filters = MemoSeriesFilters::class;

    //create a function that will generate a memo series number should start with the current year, month and day
    public static function generateMemoSeries()
    {
        $memoSeries = MemoSeries::withoutTrashed()->latest()->first();
        if ($memoSeries) {
            $memoSeries = $memoSeries->memo_series;
            $memoSeries = explode('-', $memoSeries);
            $memoSeries = $memoSeries[1] + 1;
            $memoSeries = now()->format('Ym') . '-' . str_pad($memoSeries, 4, '0', STR_PAD_LEFT);
        } else {
            $memoSeries = now()->format('Ym') . '-0001';
        }

        return MemoSeries::create(['memo_series' => $memoSeries]);
    }

    public function fixedAssets()
    {
        return $this->hasMany(FixedAsset::class, 'memo_series_id', 'id');
    }
}
