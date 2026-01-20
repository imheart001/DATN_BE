<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Film;

class EnhanceMovieImages extends Command
{
    protected $signature = 'app:enhance-movie-images';
    protected $description = 'Update seeded films with high-quality poster and backdrop URLs';

    public function handle()
    {
        $this->info('Enhancing movie images...');

        $movieData = [
            'Avengers: Endgame' => [
                'poster' => 'https://image.tmdb.org/t/p/original/or06vS3eeERIDXG16Z9Rf6XPtqc.jpg',
                'image' => 'https://image.tmdb.org/t/p/original/7Ry6SZdSpeB37Y7psogTM9mYp6O.jpg',
            ],
            'Avatar' => [
                'poster' => 'https://image.tmdb.org/t/p/original/6EiRUJTLqh7gSAtURdn9CStpk69.jpg',
                'image' => 'https://image.tmdb.org/t/p/original/vL5LR6mDxotv32Zbsq9UyiIvMvL.jpg',
            ],
            'Titanic' => [
                'poster' => 'https://image.tmdb.org/t/p/original/9xj7rB6RfsLhyARre3vFEuS2m1v.jpg',
                'image' => 'https://image.tmdb.org/t/p/original/6ay9v78RulnEPrB6p4qWquSclhE.jpg',
            ],
            'Inception' => [
                'poster' => 'https://image.tmdb.org/t/p/original/edv5CZvR0rEkS9Q6DTivpSFFpPT.jpg',
                'image' => 'https://image.tmdb.org/t/p/original/s3TBrOfSls96OduvLJu7YCc0Zmo.jpg',
            ],
            'The Dark Knight' => [
                'poster' => 'https://image.tmdb.org/t/p/original/qJ2tW6WMUDp9EXm7IbmVgls25Uv.jpg',
                'image' => 'https://image.tmdb.org/t/p/original/nMKdUUmgrag9tPbwcq9o9v696mZ.jpg',
            ],
            'Interstellar' => [
                'poster' => 'https://image.tmdb.org/t/p/original/gEU2QniE6E77NI6vCU67oQvO6R9.jpg',
                'image' => 'https://image.tmdb.org/t/p/original/xJHbt_mCnyvWoibv94v9_P27.jpg',
            ],
            'The Matrix' => [
                'poster' => 'https://image.tmdb.org/t/p/original/f89U3Y9SJuCYFJj7v0qyY7ms9zd.jpg',
                'image' => 'https://image.tmdb.org/t/p/original/7uRb07B7pG9os8U2vAYv0T8K3is.jpg',
            ],
            'Spider-Man: No Way Home' => [
                'poster' => 'https://image.tmdb.org/t/p/original/1g0dhYtWyWtS9p7vQUvDNpjaH0i.jpg',
                'image' => 'https://image.tmdb.org/t/p/original/14QbnygCuTO0vl7rnZbt64Sgh5L.jpg',
            ],
            'Joker' => [
                'poster' => 'https://image.tmdb.org/t/p/original/udDclKVb9hp7QYv6v6piBF0Vfbr.jpg',
                'image' => 'https://image.tmdb.org/t/p/original/n6bUgiCUvY7rkPNoFWZ39qP99v4.jpg',
            ],
            'Dune' => [
                'poster' => 'https://image.tmdb.org/t/p/original/d5N0sS0trmdf7Rnkio9GvIn1YI1.jpg',
                'image' => 'https://image.tmdb.org/t/p/original/lzGA0n7Iizk2Bv19xOskbBvYy2O.jpg',
            ],
        ];

        foreach ($movieData as $title => $images) {
            $film = Film::where('name', 'like', "%{$title}%")->first();
            if ($film) {
                $film->update([
                    'poster' => $images['poster'],
                    'image' => $images['image'],
                ]);
                $this->info("Updated {$title}");
            } else {
                $this->warn("Movie not found: {$title}");
            }
        }

        $this->info('Done enhancing movies.');
    }
}
