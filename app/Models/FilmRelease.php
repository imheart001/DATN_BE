<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FilmRelease extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'film_releases';

    protected $fillable = ['id', 'film_id', 'release_date', 'end_date', 'label', 'note'];

    /**
     * Get the film that owns this release.
     */
    public function film()
    {
        return $this->belongsTo(Film::class);
    }

    /**
     * Get the time details (showtimes) for this release.
     */
    public function timeDetails()
    {
        return $this->hasMany(TimeDetail::class, 'film_release_id');
    }
}
