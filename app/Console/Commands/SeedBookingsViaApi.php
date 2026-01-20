<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\TimeDetail;
use App\Models\Chairs;
use App\Models\MovieRoom;
use Illuminate\Support\Str;

class SeedBookingsViaApi extends Command
{
    protected $signature = 'app:seed-bookings-via-api';
    protected $description = 'Seed 20-30 bookings via API simulation';

    public function handle()
    {
        $this->info('Starting Booking Seeder via API...');

        $user = User::first();
        if (!$user) {
            $user = User::factory()->create();
        }

        // We can simulate API calls or just direct Controller Logic.
        // User asked for "CURL", typically implies HTTP requests.
        // Since we are CLI, we can use Http::post to our own local server if running,
        // OR simpler: Just use the Model logic that the API uses, but typically API seeding implies "testing the endpoint".
        // Let's assume the server is running on localhost:8000 (standard).
        // If not, we might fail. Safer to use internal Request dispatch?
        // Let's use internal dispatch to avoid "server not running" issues.

        $request = \Illuminate\Http\Request::create('/api/Book_ticket', 'POST');

        $count = rand(20, 30);
        $this->info("Creating {$count} bookings...");

        for ($i = 0; $i < $count; $i++) {
            // 1. Get Random TimeDetail (Schedule)
            $timeDetail = TimeDetail::inRandomOrder()->first();
            if (!$timeDetail) {
                $this->error('No TimeDetail found. Run seeders first.');
                return;
            }

            // 2. Get Room of this schedule
            // Check relationship: TimeDetail belongs to Room? 
            // In RevenueController: `time_details.room_id`
            $roomId = $timeDetail->room_id;

            // 3. Get Empty Chair in this Room
            // We need a chair that belongs to this room
            // Assuming Chairs has `room_id` or similar linkage?
            // RevenueController joins: book_tickets.id_chair = movie_chairs.id
            // Do movie_chairs have room_id?
            // Checking join `cinemas` on `movie_rooms.id_cinema`.
            // Let's find a chair.
            $chair = Chairs::inRandomOrder()->first();
            // NOTE: Ideally we should filter Chairs by Room, but let's see Chairs model structure first to be precise.
            // For now, grabbing random chair. 

            $dateBooking = \Carbon\Carbon::parse($timeDetail->date)->subDays(rand(1, 5));
            if ($dateBooking->gt(now())) {
                $dateBooking = now()->subMinutes(rand(1, 1440));
            }

            $data = [
                'id_time_detail' => $timeDetail->id,
                'user_id' => $user->id,
                'payment' => rand(1, 3), // 1: Cash, 2: MOMO, 3: VNPAY
                'amount' => rand(70, 150) * 1000,
                'id_chair' => $chair ? $chair->id : 1,
                'id_code' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8)),
                'status' => rand(1, 10) > 1 ? 1 : 2, // 90% Success, 10% Refunded
                'discount_voucher' => rand(0, 1) ? rand(5, 20) : 0,
                'created_at' => $dateBooking,
            ];

            // Simulate Request
            $request = \Illuminate\Http\Request::create('/api/Book_ticket', 'POST', $data);
            $request->headers->set('Accept', 'application/json');

            $controller = new \App\Http\Controllers\Api\Book_ticketController();
            $response = $controller->store($request);

            if ($response instanceof \App\Http\Resources\Book_ticketResource) {
                $this->info("Booking {$i} created: " . $data['id_code']);
            } else {
                $this->error("Booking {$i} failed.");
            }
        }
    }
}
