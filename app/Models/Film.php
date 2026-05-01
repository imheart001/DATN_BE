<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // add soft delete

class Film extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = "films";
    protected $fillable = ['id', 'name', 'slug', 'status', 'limit_age', 'trailer', 'time', 'image', 'poster','release_date', 'end_date', 'description', 'status'];

    /**
     * Get all release periods for this film.
     */
    public function releases()
    {
        return $this->hasMany(FilmRelease::class);
    }

    /**
     * Get the currently active release (if any).
     */
    public function activeRelease()
    {
        $today = now()->toDateString();
        return $this->releases()
            ->where('release_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->latest('release_date')
            ->first();
    }

    /**
     * Get the most recent release (by release_date).
     */
    public function latestRelease()
    {
        return $this->releases()
            ->latest('release_date')
            ->first();
    }
}