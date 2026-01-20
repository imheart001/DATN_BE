<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TimeDetail;
use App\Models\Time;
use App\Models\Film;
use App\Models\MovieRoom;
use Carbon\Carbon;

class SeedFutureSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed-future-schedules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed numerous movie schedules from Jan 30 to Feb 05, 2026';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Future Schedule Seeder...');

        $films = Film::all();
        if ($films->isEmpty()) {
            $this->error('No films found. Run OMDb import first.');
            return;
        }

        $times = Time::all();
        if ($times->isEmpty()) {
            $this->warn('No time slots found. Creating some default ones...');
            $timeSlots = ['08:00', '10:30', '13:00', '15:30', '18:00', '20:30', '23:00'];
            foreach ($timeSlots as $slot) {
                Time::create(['time' => $slot]);
            }
            $times = Time::all();
        }

        $rooms = MovieRoom::all();
        if ($rooms->isEmpty()) {
            $this->error('No movie rooms found. Run standard seeders first.');
            return;
        }

        $startDate = Carbon::create(2026, 1, 30);
        $endDate = Carbon::create(2026, 2, 5);

        $this->info("Generating schedules from {$startDate->toDateString()} to {$endDate->toDateString()}...");

        $count = 0;
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $this->info("Processing date: {$date->toDateString()}");

            foreach ($films as $film) {
                // For each film, create 2-4 random schedules per day
                $dailySessions = rand(2, 4);

                for ($i = 0; $i < $dailySessions; $i++) {
                    TimeDetail::create([
                        'date' => $date->toDateString(),
                        'time_id' => $times->random()->id,
                        'film_id' => $film->id,
                        'room_id' => $rooms->random()->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $count++;
                }
            }
        }

        $this->info("Successfully created {$count} future schedules!");
    }
}
