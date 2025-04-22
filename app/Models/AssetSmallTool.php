<?php

namespace App\Models;


use App\Filters\ReplacementSmallToolFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssetSmallTool extends Model implements HasMedia
{
    use HasFactory, Filterable, InteractsWithMedia, SoftDeletes;

    protected $guarded = [];
    protected string $default_filters = ReplacementSmallToolFilters::class;

    public function item()
    {
        return $this->belongsTo(Item::class, 'small_tool_id', 'id');
    }

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id', 'id');
    }

    public function receivingWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'receiving_warehouse_id', 'sync_id');
    }

    public function storeBase64Images(array $images)
    {
        foreach ($images as $key => $base64Image) {
            // Decode the base64 image data
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));

            // Create a temporary image file
//            $imageName = Str::slug($key);
            $fileName = $key . '.png';
            $filePath = sys_get_temp_dir() . '/' . $fileName;
            file_put_contents($filePath, $imageData);

            // Store the image file to the Spatie Media Library
            $this->addMedia($filePath)
                ->toMediaCollection($key);

            // Delete the temporary image file
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
}
