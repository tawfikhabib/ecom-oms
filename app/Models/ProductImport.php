<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImport extends Model
{
    use HasFactory;

    protected $table = 'product_imports';

    protected $fillable = [
        'path', 'uploader_id', 'status', 'results_path', 'errors',
    ];

    protected $casts = [
        'errors' => 'array',
    ];
}
