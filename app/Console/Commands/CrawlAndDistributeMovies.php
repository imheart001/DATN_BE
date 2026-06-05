<?php
 
namespace App\Console\Commands;
 
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
 
// Models
use App\Models\Film;
use App\Models\Cinemas;
use App\Models\MovieRoom;
use App\Models\Time;
use App\Models\TimeDetail;
use App\Models\FilmRelease;
use App\Jobs\FetchMoviesFromOMDb;
 
class CrawlAndDistributeMovies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:crawl-and-distribute';
 
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl 40 popular movies and distribute them to cinemas with randomized showtimes for the next 3 days';
 
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $movieTitles = [
            'Avengers: Endgame',
            'Titanic',
            'The Dark Knight',
            'Interstellar',
            'The Matrix',
            'Spider-Man: No Way Home',
            'Joker',
            'Dune',
            'Gladiator',
            'Pulp Fiction',
            'The Godfather',
            'Forrest Gump',
            'Fight Club',
            'Star Wars: Episode IV - A New Hope',
            'Jurassic Park',
            'The Lion King',
            'Spirited Away',
            'The Lord of the Rings: The Fellowship of the Ring',
            'The Lord of the Rings: The Return of the King',
            'The Lord of the Rings: The Two Towers',
            'Parasite',
            'Avengers: Infinity War',
            'Spider-Man: Into the Spider-Verse',
            'Spider-Man: Across the Spider-Verse',
            'The Shawshank Redemption',
            'Whiplash',
            'Django Unchained',
            'The Wolf of Wall Street',
            'Shutter Island',
            'Goodfellas',
            'Oppenheimer',
            'Barbie',
            'The Batman',
            'Guardians of the Galaxy',
            'Toy Story',
            'Inside Out',
            'Coco',
            'Wall-E',
            'Up',
            'Ratatouille'
        ];
 
        $this->info('Starting Crawl and Distribute process...');
 
        $cinemas = Cinemas::all();
        if ($cinemas->isEmpty()) {
            $this->error('No cinemas found. Please seed cinemas first.');
            return;
        }
 
        $times = Time::all();
        if ($times->isEmpty()) {
            $this->error('No time slots found. Please seed times first.');
            return;
        }
 
        $rooms = MovieRoom::all();
        if ($rooms->isEmpty()) {
            $this->error('No movie rooms found. Please seed movie rooms first.');
            return;
        }
 
        $totalImported = 0;
        $totalDistributed = 0;
        $totalShowtimes = 0;
 
        foreach ($movieTitles as $index => $title) {
            $slug = Str::slug($title);
            $this->info(($index + 1) . "/40: Processing '{$title}'...");
 
            // 1. Crawl movie if not exists
            $film = Film::where('slug', $slug)->first();
            if (!$film) {
                $this->line("-> Crawling from OMDb API...");
                try {
                    $job = new FetchMoviesFromOMDb($title);
                    $job->handle();
                    $film = Film::where('slug', $slug)->first();
                    if ($film) {
                        $totalImported++;
                        $this->info("-> Crawled successfully: ID {$film->id}");
                    } else {
                        $this->error("-> Failed to crawl '{$title}'. Skip.");
                        continue;
                    }
                } catch (\Exception $e) {
                    $this->error("-> Error crawling '{$title}': " . $e->getMessage());
                    continue;
                }
                // Sleep briefly to avoid API rate limits
                usleep(500000); // 0.5s
            } else {
                $this->line("-> Movie already exists: ID {$film->id}");
            }
 
            // Get the film release record
            $filmRelease = FilmRelease::where('film_id', $film->id)->first();
            if (!$filmRelease) {
                // If somehow no release is found, create one
                $filmRelease = FilmRelease::create([
                    'film_id' => $film->id,
                    'release_date' => $film->release_date,
                    'end_date' => $film->end_date,
                    'label' => 'Khởi chiếu lần 1',
                ]);
            }
 
            // 2. Distribute to random cinemas (choose 2-3 cinemas out of 5)
            $randomCinemas = $cinemas->random(rand(2, 3));
            foreach ($randomCinemas as $cinema) {
                // Link in cinema_details
                $existsLink = DB::table('cinema_details')
                    ->where('cinema_id', $cinema->id)
                    ->where('film_id', $film->id)
                    ->exists();
 
                if (!$existsLink) {
                    DB::table('cinema_details')->insert([
                        'cinema_id' => $cinema->id,
                        'film_id' => $film->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $totalDistributed++;
                }
 
                // 3. Create showtimes for the next 3 days
                $cinemaRooms = $rooms->where('id_cinema', $cinema->id);
                if ($cinemaRooms->isEmpty()) {
                    continue;
                }
 
                // Date range: today, tomorrow, day after tomorrow
                for ($dayOffset = 0; $dayOffset < 3; $dayOffset++) {
                    $date = Carbon::today()->addDays($dayOffset)->toDateString();
 
                    foreach ($cinemaRooms as $room) {
                        // Choose 2 random time slots for this film in this room for this date
                        $selectedTimes = $times->random(min(2, $times->count()));
 
                        foreach ($selectedTimes as $timeSlot) {
                            // Check if showtime already exists
                            $existsShowtime = TimeDetail::where('date', $date)
                                ->where('time_id', $timeSlot->id)
                                ->where('room_id', $room->id)
                                ->exists();
 
                            if (!$existsShowtime) {
                                TimeDetail::create([
                                    'date' => $date,
                                    'time_id' => $timeSlot->id,
                                    'film_id' => $film->id,
                                    'film_release_id' => $filmRelease->id,
                                    'room_id' => $room->id,
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ]);
                                $totalShowtimes++;
                            }
                        }
                    }
                }
            }
        }
 
        $this->info('==========================================');
        $this->info("Process completed successfully!");
        $this->info("- Crawled {$totalImported} new movies.");
        $this->info("- Linked {$totalDistributed} cinema relationships.");
        $this->info("- Created {$totalShowtimes} showtime sessions.");
        $this->info('==========================================');
    }
}
