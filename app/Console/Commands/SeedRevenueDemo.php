<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SeedRevenueDemo extends Command
{
    protected $signature = 'app:seed-revenue-demo
                            {--count=100 : Number of bookings to create}
                            {--days=30 : Spread bookings over how many past days}';

    protected $description = 'Seed fake book_tickets data for revenue dashboard demo';

    public function handle(): void
    {
        $count = (int) $this->option('count');
        $days  = (int) $this->option('days');

        $this->info("Seeding {$count} fake bookings over the last {$days} days...");

        // 1. Gather existing data
        $timeDetails = DB::table('time_details')
            ->join('films', 'time_details.film_id', '=', 'films.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->select('time_details.id', 'time_details.film_id', 'time_details.room_id', 'movie_rooms.id_cinema')
            ->get();

        if ($timeDetails->isEmpty()) {
            $this->error('No time_details found! Run migration/seed or app:crawl-and-distribute first.');
            return;
        }

        $users = DB::table('users')->where('role', 0)->pluck('id')->toArray();
        if (empty($users)) {
            $users = DB::table('users')->pluck('id')->toArray();
        }
        if (empty($users)) {
            $this->error('No users found! Seed users first.');
            return;
        }

        $staffByCinema = DB::table('users')
            ->where('role', '>', 0)
            ->whereNotNull('id_cinema')
            ->pluck('id', 'id_cinema')
            ->toArray();

        $foods = DB::table('food')->select('id', 'price')->get();

        // 2. Price tiers
        $prices = [75000, 80000, 85000, 90000, 95000, 100000, 110000, 120000, 150000];

        $now = Carbon::now('Asia/Ho_Chi_Minh');
        $created = 0;
        $totalRevenue = 0;

        DB::beginTransaction();
        try {
            for ($i = 0; $i < $count; $i++) {
                $td = $timeDetails->random();
                $userId = $users[array_rand($users)];
                $daysAgo = rand(0, $days);
                $createdAt = $now->copy()
                    ->subDays($daysAgo)
                    ->setHour(rand(8, 22))
                    ->setMinute(rand(0, 59))
                    ->setSecond(rand(0, 59));

                // Number of seats (1-4)
                $seatCount = rand(1, 4);
                $seatPrice = $prices[array_rand($prices)];
                $amount = $seatPrice * $seatCount;

                // Random status: 1 = success (85%), 0 = pending (10%), 2 = refunded (5%)
                $statusRoll = rand(1, 100);
                if ($statusRoll <= 85) {
                    $status = 1;
                } elseif ($statusRoll <= 95) {
                    $status = 0;
                } else {
                    $status = 2;
                }

                // Random discount
                $discount = rand(0, 100) > 80 ? rand(1, 5) * 10000 : 0;

                // Payment method: 1 = cash, 2 = momo, 3 = vnpay
                $payment = rand(1, 3);

                $idCode = 'DEMO-' . strtoupper(Str::random(8));
                $staffId = $staffByCinema[$td->id_cinema] ?? 0;

                // Generate seat names
                $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K'];
                $seatNames = [];
                $startCol = rand(1, 12);
                $row = $rows[array_rand($rows)];
                for ($s = 0; $s < $seatCount; $s++) {
                    $seatNames[] = $row . ($startCol + $s);
                }
                $seatNameStr = implode(',', $seatNames);

                // Insert chair
                $chairId = DB::table('movie_chairs')->insertGetId([
                    'name' => $seatNameStr,
                    'id_time_detail' => $td->id,
                    'price' => $amount,
                    'book_ticket_detail' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // Insert booking
                $ticketId = DB::table('book_tickets')->insertGetId([
                    'user_id' => $userId,
                    'id_chair' => $chairId,
                    'id_time_detail' => $td->id,
                    'payment' => $payment,
                    'amount' => $amount,
                    'time' => $createdAt->format('Y-m-d H:i:s'),
                    'id_staff_check' => $staffId,
                    'id_code' => $idCode,
                    'status' => $status,
                    'discount_voucher' => $discount,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // Link chair to ticket
                DB::table('movie_chairs')->where('id', $chairId)->update([
                    'book_ticket_detail' => $ticketId,
                ]);

                // Random F&B for ~40% of bookings
                if ($foods->isNotEmpty() && rand(1, 100) <= 40) {
                    $selectedFoods = $foods->random(rand(1, min(3, $foods->count())));
                    foreach ($selectedFoods as $food) {
                        DB::table('food_ticket_details')->insert([
                            'book_ticket_id' => $ticketId,
                            'food_id' => $food->id,
                            'quantity' => rand(1, 3),
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ]);
                    }
                }

                $created++;
                $totalRevenue += $amount;

                if ($created % 20 === 0) {
                    $this->line("  → Created {$created}/{$count} bookings...");
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error: ' . $e->getMessage());
            return;
        }

        $this->newLine();
        $this->info('==========================================');
        $this->info("✅ Created {$created} fake bookings!");
        $this->info('💰 Total revenue: ' . number_format($totalRevenue) . ' VND');
        $this->info("📅 Spread over last {$days} days");
        $this->info('==========================================');
    }
}
