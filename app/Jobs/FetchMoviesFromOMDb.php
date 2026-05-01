<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Models
use App\Models\Film;
use App\Models\Categories;
use App\Models\CategoryDetail;
use App\Models\FilmMaker;
use App\Models\FilmRelease;

class FetchMoviesFromOMDb implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $identifier;
    protected $apiKey = 'c6686677';
    protected $baseUrl = 'http://www.omdbapi.com/';

    /**
     * Create a new job instance.
     *
     * @param string $identifier IMDb ID (tt...) or Movie Title
     */
    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Determine search parameter (i for ID, t for Title)
        $param = 't';
        if (preg_match('/^tt\d+$/', $this->identifier)) {
            $param = 'i';
        }

        $response = Http::get($this->baseUrl, [
            'apikey' => $this->apiKey,
            $param => $this->identifier,
            'plot' => 'full'
        ]);

        if ($response->failed()) {
            Log::error("OMDb API Connection Failed: " . $response->body());
            return;
        }

        $data = $response->json();

        if (isset($data['Response']) && $data['Response'] === 'False') {
            Log::error("OMDb API Error for {$this->identifier}: " . ($data['Error'] ?? 'Unknown error'));
            return;
        }

        // Process Data inside Transaction
        DB::transaction(function () use ($data) {
            $this->saveMovieData($data);
        });
    }

    protected function saveMovieData($data)
    {
        $title = $data['Title'];
        $slug = Str::slug($title);

        // Check availability
        $film = Film::where('slug', $slug)->first();
        if ($film) {
            // Option: Update existing or Skip. Let's Skip for now to avoid overwriting custom data.
            Log::info("Movie already exists: {$title}");
            return;
        }

        // Parsing Date
        $releaseDate = date('Y-m-d');
        if ($data['Released'] !== 'N/A') {
            $releaseDate = date('Y-m-d', strtotime($data['Released']));
        }
        $endDate = date('Y-m-d', strtotime($releaseDate . ' + 45 days'));

        // Parsing Time (136 min -> 136)
        $time = (int) filter_var($data['Runtime'], FILTER_SANITIZE_NUMBER_INT);

        // Image logic
        $poster = ($data['Poster'] !== 'N/A') ? $data['Poster'] : 'https://placehold.co/400x600?text=' . urlencode($title);

        // --- 1. Create Film ---
        $film = Film::create([
            'name' => $title,
            'slug' => $slug,
            'image' => $poster, // Use poster for both image fields for now
            'poster' => $poster,
            'limit_age' => $data['Rated'] ?? 'PG',
            'trailer' => '', // Cannot be null
            'time' => $time . ' minutes',
            'release_date' => $releaseDate,
            'end_date' => $endDate,
            'description' => $data['Plot'] ?? '',
            'status' => 1
        ]);

        // Auto-create the first film release
        FilmRelease::create([
            'film_id' => $film->id,
            'release_date' => $releaseDate,
            'end_date' => $endDate,
            'label' => 'Khởi chiếu lần 1',
        ]);

        // --- 2. Categories (Genres) ---
        if (isset($data['Genre']) && $data['Genre'] !== 'N/A') {
            $genres = array_map('trim', explode(',', $data['Genre']));
            foreach ($genres as $genreName) {
                // Find or Create Category
                $category = Categories::firstOrCreate(
                    ['slug' => Str::slug($genreName)],
                    ['name' => $genreName, 'status' => 1]
                );

                // Link to Film
                CategoryDetail::create([
                    'category_id' => $category->id,
                    'film_id' => $film->id
                ]);
            }
        }

        // --- 3. Film Makers (Directors) ---
        if (isset($data['Director']) && $data['Director'] !== 'N/A') {
            $directors = array_map('trim', explode(',', $data['Director']));
            foreach ($directors as $dirName) {
                FilmMaker::create([
                    'type' => 2, // Director
                    'name' => $dirName,
                    'image' => 'https://placehold.co/200x200?text=' . urlencode($dirName),
                    'as' => 'Director',
                    'film_id' => $film->id
                ]);
            }
        }

        // --- 4. Film Makers (Actors) ---
        if (isset($data['Actors']) && $data['Actors'] !== 'N/A') {
            $actors = array_map('trim', explode(',', $data['Actors']));
            foreach ($actors as $actorName) {
                FilmMaker::create([
                    'type' => 1, // Actor
                    'name' => $actorName,
                    'image' => 'https://placehold.co/200x200?text=' . urlencode($actorName),
                    'as' => 'Actor',
                    'film_id' => $film->id
                ]);
            }
        }

        Log::info("Imported movie: {$title}");
    }
}
