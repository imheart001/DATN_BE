<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\FetchMoviesFromOMDb;

class ImportMovies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-movies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import popular movies from OMDb API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $movies = [
            'Avengers: Endgame',
            'Avatar',
            'Titanic',
            'Inception',
            'The Dark Knight',
            'Interstellar',
            'The Matrix',
            'Spider-Man: No Way Home',
            'Joker',
            'Dune'
        ];

        $this->info('Starting import of ' . count($movies) . ' movies...');

        foreach ($movies as $title) {
            $this->info("Dispatching job for: $title");
            FetchMoviesFromOMDb::dispatch($title);
        }

        $this->info('All jobs dispatched! Run queue worker to process.');
    }
}
