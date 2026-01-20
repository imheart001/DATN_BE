<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // add soft delete
use Illuminate\Support\Str;

class Categories extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = "categories"; // phải điền đúng tên bảng mà mình cần trỏ tới trong csdl
    protected $fillable = ['id', 'name', 'slug', 'status'];
    protected static function booted()
{
    static::creating(function ($category) {
        if (empty($category->slug)) {
            $category->slug = Str::slug($category->name);
        }
    });
}
}
