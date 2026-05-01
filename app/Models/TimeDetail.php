<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // add soft delete


class TimeDetail extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = "time_details"; // phải điền đúng tên bảng mà mình cần trỏ tới trong csdl
    protected $fillable = ['id', 'date', 'time_id', 'film_id', 'film_release_id', 'room_id', 'status'];

    /**
     * Get the film release period this showtime belongs to.
     */
    public function filmRelease()
    {
        return $this->belongsTo(FilmRelease::class, 'film_release_id');
    }
}