<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    private Carbon $now;

    public function run(): void
    {
        $this->now = Carbon::now('Asia/Ho_Chi_Minh');

        DB::transaction(function () {
            $counts = [];

            $cinemaIds = $this->seedCinemas();
            $counts['cinemas'] = count($cinemaIds);

            $roomIds = $this->seedRooms($cinemaIds);
            $counts['movie_rooms'] = count($roomIds);

            $categoryIds = $this->seedCategories();
            $counts['categories'] = count($categoryIds);

            $filmIds = $this->seedFilms();
            $counts['films'] = count($filmIds);

            $filmReleaseIds = $this->seedFilmReleases($filmIds);
            $counts['film_releases'] = count($filmReleaseIds);

            $counts['category_details'] = $this->seedCategoryDetails($categoryIds, $filmIds);
            $counts['cinema_details'] = $this->seedCinemaDetails($cinemaIds, $filmIds);

            $timeIds = $this->seedTimes();
            $counts['times'] = count($timeIds);

            $userIds = $this->seedUsers($cinemaIds);
            $counts['users'] = count($userIds);
            $counts['members'] = $this->seedMembers($userIds);

            $timeDetailIds = $this->seedTimeDetails($roomIds, $filmIds, $timeIds, $filmReleaseIds);
            $counts['time_details'] = count($timeDetailIds);

            $chairIds = $this->seedBookedChairs($timeDetailIds);
            $counts['movie_chairs'] = count($chairIds);

            $ticketIds = $this->seedBookTickets($userIds, $cinemaIds, $roomIds, $timeDetailIds, $chairIds);
            $counts['book_tickets'] = count($ticketIds);

            $foodIds = $this->seedFood();
            $counts['food'] = count($foodIds);
            $counts['food_ticket_details'] = $this->seedFoodTicketDetails($ticketIds, $foodIds);

            $voucherCodes = $this->seedVouchers();
            $counts['vouchers'] = count($voucherCodes);

            $counts['banners'] = $this->seedBanners();
            $blogIds = $this->seedBlogs();
            $counts['blogs'] = count($blogIds);
            $counts['comments'] = $this->seedComments($blogIds);
            $counts['feedback'] = $this->seedFeedback($userIds);
            $counts['rate_stars'] = $this->seedRateStars($userIds, $filmIds);
            $counts['photos'] = $this->seedPhotos($filmIds);
            $counts['film_makers'] = $this->seedFilmMakers($filmIds);

            $this->command?->info(
                'Demo data seeded: ' . array_sum($counts) . ' planned rows across ' . count($counts) . ' tables.'
            );
        });
    }

    private function seedCinemas(): array
    {
        $cinemas = [
            'hn' => ['DATN Cinema Ha Noi', '12 Nguyen Trai, Thanh Xuan, Ha Noi'],
            'hcm' => ['DATN Cinema Sai Gon', '88 Le Loi, Quan 1, TP. Ho Chi Minh'],
            'dn' => ['DATN Cinema Da Nang', '45 Bach Dang, Hai Chau, Da Nang'],
            'hp' => ['DATN Cinema Hai Phong', '21 Tran Phu, Ngo Quyen, Hai Phong'],
            'ct' => ['DATN Cinema Can Tho', '9 Mau Than, Ninh Kieu, Can Tho'],
        ];

        $ids = [];
        foreach ($cinemas as $key => [$name, $address]) {
            $ids[$key] = $this->upsert('cinemas', ['name' => $name], [
                'address' => $address,
                'status' => 1,
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    private function seedRooms(array $cinemaIds): array
    {
        $rooms = [
            'hn-room-1' => ['hn', 'Ha Noi Room 01'],
            'hcm-room-1' => ['hcm', 'Sai Gon Room 01'],
            'dn-room-1' => ['dn', 'Da Nang Room 01'],
            'hp-room-1' => ['hp', 'Hai Phong Room 01'],
            'ct-room-1' => ['ct', 'Can Tho Room 01'],
        ];

        $ids = [];
        foreach ($rooms as $key => [$cinemaKey, $name]) {
            $ids[$key] = $this->upsert('movie_rooms', ['name' => $name], [
                'id_cinema' => $cinemaIds[$cinemaKey],
                'status' => 1,
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    private function seedCategories(): array
    {
        $categories = [
            'hanh-dong' => 'Hanh dong',
            'hai' => 'Hai',
            'tinh-cam' => 'Tinh cam',
            'kinh-di' => 'Kinh di',
            'hoat-hinh' => 'Hoat hinh',
        ];

        $ids = [];
        foreach ($categories as $slug => $name) {
            $ids[$slug] = $this->upsert('categories', ['slug' => $slug], [
                'name' => $name,
                'status' => 1,
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    private function seedFilms(): array
    {
        $films = [
            ['lat-mat-demo', 'Lat Mat Demo', 'T13', '118'],
            ['nha-ba-nu-demo', 'Nha Ba Nu Demo', 'T13', '102'],
            ['dat-rung-phuong-nam-demo', 'Dat Rung Phuong Nam Demo', 'T13', '110'],
            ['mai-demo', 'Mai Demo', 'T16', '131'],
            ['dao-pho-va-piano-demo', 'Dao Pho Va Piano Demo', 'T13', '100'],
            ['avatar-demo', 'Avatar Demo', 'T13', '162'],
            ['inside-out-demo', 'Inside Out Demo', 'P', '96'],
            ['godzilla-demo', 'Godzilla Demo', 'T16', '115'],
        ];

        $ids = [];
        foreach ($films as $index => [$slug, $name, $limitAge, $minutes]) {
            $ids[$slug] = $this->upsert('films', ['slug' => $slug], [
                'name' => $name,
                'image' => "https://picsum.photos/seed/{$slug}-image/640/360",
                'poster' => "https://picsum.photos/seed/{$slug}-poster/480/720",
                'limit_age' => $limitAge,
                'trailer' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'time' => $minutes,
                'release_date' => $this->now->copy()->subDays(7 + $index)->toDateString(),
                'end_date' => $this->now->copy()->addDays(25 + $index)->toDateString(),
                'description' => "Phim demo {$name} dung de test lich chieu, dat ve va doanh thu.",
                'status' => 1,
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    private function seedCategoryDetails(array $categoryIds, array $filmIds): int
    {
        $categoryKeys = array_keys($categoryIds);
        $count = 0;

        foreach (array_values($filmIds) as $index => $filmId) {
            $categoryId = $categoryIds[$categoryKeys[$index % count($categoryKeys)]];
            $this->upsertNoId('category_details', [
                'category_id' => $categoryId,
                'film_id' => $filmId,
            ], ['deleted_at' => null]);
            $count++;
        }

        return $count;
    }

    private function seedCinemaDetails(array $cinemaIds, array $filmIds): int
    {
        $cinemaKeys = array_keys($cinemaIds);
        $count = 0;

        foreach (array_values($filmIds) as $index => $filmId) {
            $cinemaId = $cinemaIds[$cinemaKeys[$index % count($cinemaKeys)]];
            $this->upsertNoId('cinema_details', [
                'cinema_id' => $cinemaId,
                'film_id' => $filmId,
            ], ['deleted_at' => null]);
            $count++;
        }

        return $count;
    }

    private function seedTimes(): array
    {
        $times = ['09:00:00', '11:30:00', '14:00:00', '17:30:00', '20:30:00'];
        $ids = [];

        foreach ($times as $time) {
            $ids[$time] = $this->upsert('times', ['time' => $time], [
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    private function seedUsers(array $cinemaIds): array
    {
        $users = [
            'admin' => ['Admin Demo', 'admin@datn.test', 1, null, 500000],
            'staff-hn' => ['Nhan vien Ha Noi', 'staff.hn@datn.test', 1, $cinemaIds['hn'], 0],
            'staff-hcm' => ['Nhan vien Sai Gon', 'staff.hcm@datn.test', 1, $cinemaIds['hcm'], 0],
            'staff-dn' => ['Nhan vien Da Nang', 'staff.dn@datn.test', 1, $cinemaIds['dn'], 0],
            'staff-hp' => ['Nhan vien Hai Phong', 'staff.hp@datn.test', 1, $cinemaIds['hp'], 0],
            'staff-ct' => ['Nhan vien Can Tho', 'staff.ct@datn.test', 1, $cinemaIds['ct'], 0],
            'customer-1' => ['Khach Hang 01', 'customer1@datn.test', 0, null, 120000],
            'customer-2' => ['Khach Hang 02', 'customer2@datn.test', 0, null, 80000],
            'customer-3' => ['Khach Hang 03', 'customer3@datn.test', 0, null, 50000],
            'customer-4' => ['Khach Hang 04', 'customer4@datn.test', 0, null, 30000],
        ];

        $ids = [];
        foreach ($users as $key => [$name, $email, $role, $cinemaId, $coin]) {
            $ids[$key] = $this->upsert('users', ['email' => $email], [
                'name' => $name,
                'id_cinema' => $cinemaId,
                'image' => "https://i.pravatar.cc/300?u={$email}",
                'phone' => '09' . str_pad((string) count($ids), 8, '0', STR_PAD_LEFT),
                'role' => $role,
                'email_verified_at' => $this->now,
                'password' => Hash::make('password'),
                'date_of_birth' => $this->now->copy()->subYears(24 + count($ids))->toDateString(),
                'coin' => $coin,
                'remember_token' => null,
                'reset_password_token' => null,
                'reset_password_token_expiry' => null,
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    private function seedMembers(array $userIds): int
    {
        $customers = ['customer-1', 'customer-2', 'customer-3', 'customer-4'];

        foreach ($customers as $index => $key) {
            $this->upsert('members', ['id_card' => 'DEMO' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)], [
                'card_class' => $index === 0 ? 2 : 1,
                'activation_date' => $this->now->copy()->subMonths($index + 1)->toDateString(),
                'total_spending' => 500000 + ($index * 150000),
                'accumulated_points' => 200 + ($index * 40),
                'points_used' => 20 * $index,
                'usable_points' => 180 + ($index * 20),
                'id_user' => $userIds[$key],
            ]);
        }

        return count($customers);
    }

    private function seedTimeDetails(array $roomIds, array $filmIds, array $timeIds, array $filmReleaseIds): array
    {
        $roomValues = array_values($roomIds);
        $filmValues = array_values($filmIds);
        $timeValues = array_values($timeIds);
        $releaseValues = array_values($filmReleaseIds);
        $ids = [];

        for ($i = 0; $i < 60; $i++) {
            $date = $this->now->copy()->addDays(intdiv($i, 6))->toDateString();
            $key = "demo-showtime-{$i}";
            $filmId = $filmValues[$i % count($filmValues)];

            // Find matching film_release_id for this film_id
            $matchingReleaseId = null;
            foreach ($filmReleaseIds as $relKey => $relId) {
                // The first release keys are like 'lat-mat-demo-r1', etc.
                $filmSlug = str_replace(['-r1', '-r2'], '', $relKey);
                $filmKeys = array_keys($filmIds);
                $filmIndex = $i % count($filmValues);
                if (isset($filmKeys[$filmIndex])) {
                    $targetSlug = $filmKeys[$filmIndex];
                    if (str_starts_with($relKey, $targetSlug . '-r')) {
                        $matchingReleaseId = $relId;
                        break; // Take the first matching release
                    }
                }
            }

            $ids[$key] = $this->upsert('time_details', [
                'date' => $date,
                'time_id' => $timeValues[$i % count($timeValues)],
                'film_id' => $filmId,
                'room_id' => $roomValues[$i % count($roomValues)],
            ], [
                'film_release_id' => $matchingReleaseId,
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    private function seedBookedChairs(array $timeDetailIds): array
    {
        $seatGroups = ['A1,A2', 'B3,B4', 'C5', 'D6,D7', 'E8', 'F9,F10'];
        $timeDetailValues = array_values($timeDetailIds);
        $ids = [];

        foreach ($seatGroups as $index => $seatName) {
            $ids[$index] = $this->upsert('movie_chairs', [
                'name' => $seatName,
                'id_time_detail' => $timeDetailValues[$index % count($timeDetailValues)],
            ], [
                'price' => str_contains($seatName, ',') ? '180000' : '90000',
                'book_ticket_detail' => null,
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    private function seedBookTickets(
        array $userIds,
        array $cinemaIds,
        array $roomIds,
        array $timeDetailIds,
        array $chairIds
    ): array {
        $customers = [$userIds['customer-1'], $userIds['customer-2'], $userIds['customer-3'], $userIds['customer-4']];
        $staffByCinema = [
            $cinemaIds['hn'] => $userIds['staff-hn'],
            $cinemaIds['hcm'] => $userIds['staff-hcm'],
            $cinemaIds['dn'] => $userIds['staff-dn'],
            $cinemaIds['hp'] => $userIds['staff-hp'],
            $cinemaIds['ct'] => $userIds['staff-ct'],
        ];
        $roomCinema = [
            $roomIds['hn-room-1'] => $cinemaIds['hn'],
            $roomIds['hcm-room-1'] => $cinemaIds['hcm'],
            $roomIds['dn-room-1'] => $cinemaIds['dn'],
            $roomIds['hp-room-1'] => $cinemaIds['hp'],
            $roomIds['ct-room-1'] => $cinemaIds['ct'],
        ];
        $timeDetails = DB::table('time_details')->whereIn('id', array_values($timeDetailIds))->get()->keyBy('id');
        $ids = [];

        foreach (array_values($chairIds) as $index => $chairId) {
            $timeDetailId = array_values($timeDetailIds)[$index % count($timeDetailIds)];
            $timeDetail = $timeDetails[$timeDetailId];
            $cinemaId = $roomCinema[$timeDetail->room_id];
            $amount = (int) DB::table('movie_chairs')->where('id', $chairId)->value('price');
            $idCode = 'DEMO-TK-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);

            $ticketId = $this->upsert('book_tickets', ['id_code' => $idCode], [
                'user_id' => $customers[$index % count($customers)],
                'id_chair' => $chairId,
                'id_time_detail' => $timeDetailId,
                'payment' => $index % 2 === 0 ? 1 : 2,
                'amount' => $amount,
                'time' => $this->now->copy()->subDays($index)->format('Y-m-d H:i:s'),
                'id_staff_check' => $staffByCinema[$cinemaId],
                'status' => [1, 1, 0, 1, 2, 1][$index],
                'discount_voucher' => $index === 1 ? 20000 : 0,
                'deleted_at' => null,
            ]);

            DB::table('movie_chairs')->where('id', $chairId)->update([
                'book_ticket_detail' => $ticketId,
                'updated_at' => $this->now,
            ]);

            $ids[$idCode] = $ticketId;
        }

        return $ids;
    }

    private function seedFood(): array
    {
        $foods = [
            'Combo Popcorn 1' => ['45000', 'combo-popcorn-1', '1 Bắp ngọt lớn + 1 Nước ngọt Coca Cola 22oz', 100],
            'Combo Popcorn 2' => ['69000', 'combo-popcorn-2', '1 Bắp ngọt lớn + 2 Nước ngọt Coca Cola 22oz', 100],
            'Coca Cola' => ['25000', 'coca-cola', 'Nước ngọt Coca Cola lon mát lạnh 330ml', 150],
            'Pepsi' => ['25000', 'pepsi', 'Nước ngọt Pepsi lon mát lạnh 330ml', 150],
            'Nachos' => ['55000', 'nachos', 'Bánh khoai tây chiên giòn kèm xốt phô mai', 80],
        ];

        $ids = [];
        foreach ($foods as $name => [$price, $seed, $desc, $qty]) {
            $ids[$name] = $this->upsert('food', ['name' => $name], [
                'image' => "https://picsum.photos/seed/{$seed}/300/300",
                'price' => $price,
                'description' => $desc,
                'quantity' => $qty,
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    private function seedFoodTicketDetails(array $ticketIds, array $foodIds): int
    {
        $tickets = array_values($ticketIds);
        $foods = array_values($foodIds);

        for ($i = 0; $i < 3; $i++) {
            $this->upsertNoId('food_ticket_details', [
                'book_ticket_id' => $tickets[$i],
                'food_id' => $foods[$i],
            ], [
                'quantity' => $i + 1,
                'deleted_at' => null,
            ]);
        }

        return 3;
    }

    private function seedVouchers(): array
    {
        $vouchers = [
            'DEMO20' => [20, 20000, 100000],
            'DEMO30' => [30, 30000, 150000],
            'DATN50K' => [0, 50000, 200000],
        ];

        foreach ($vouchers as $code => [$percent, $priceVoucher, $minimumAmount]) {
            $this->upsert('vouchers', ['code' => $code], [
                'start_time' => $this->now->copy()->subDay(),
                'end_time' => $this->now->copy()->addMonth(),
                'usage_limit' => 100,
                'price_voucher' => $priceVoucher,
                'description' => "Voucher demo {$code}",
                'remaining_limit' => 100,
                'limit' => 1,
                'percent' => $percent,
                'minimum_amount' => $minimumAmount,
                'deleted_at' => null,
            ]);
        }

        return array_keys($vouchers);
    }

    private function seedBanners(): int
    {
        $banners = [
            'Dat ve nhanh cung DATN Cinema',
            'Uu dai combo bap nuoc',
        ];

        foreach ($banners as $index => $title) {
            $this->upsert('banners', ['title' => $title], [
                'image' => 'https://picsum.photos/seed/datn-banner-' . ($index + 1) . '/1200/420',
                'deleted_at' => null,
            ]);
        }

        return count($banners);
    }

    private function seedBlogs(): array
    {
        $blogs = [
            'lich-chieu-cuoi-tuan-demo' => 'Lich chieu cuoi tuan demo',
            'combo-bap-nuoc-demo' => 'Combo bap nuoc demo',
        ];

        $ids = [];
        foreach ($blogs as $slug => $title) {
            $ids[$slug] = $this->upsert('blogs', ['slug' => $slug], [
                'title' => $title,
                'image' => "https://picsum.photos/seed/{$slug}/640/360",
                'content' => "Noi dung demo cho bai viet {$title}.",
                'status' => 1,
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    private function seedComments(array $blogIds): int
    {
        $comments = [
            ['Khach demo 01', 'Lich chieu rat de theo doi.'],
            ['Khach demo 02', 'Combo gia on cho ngay cuoi tuan.'],
        ];

        foreach ($comments as $index => [$name, $content]) {
            $this->upsertNoId('comments', [
                'blogs_id' => array_values($blogIds)[$index % count($blogIds)],
                'user_name' => $name,
            ], ['content' => $content], false);
        }

        return count($comments);
    }

    private function seedFeedback(array $userIds): int
    {
        $customers = [$userIds['customer-1'], $userIds['customer-2']];

        foreach ($customers as $index => $userId) {
            $this->upsertNoId('feedback', [
                'user_id' => $userId,
                'content' => 'Feedback demo ' . ($index + 1),
            ], [
                'status' => 1,
                'deleted_at' => null,
            ]);
        }

        return count($customers);
    }

    private function seedRateStars(array $userIds, array $filmIds): int
    {
        $customers = [$userIds['customer-1'], $userIds['customer-2'], $userIds['customer-3']];
        $films = array_values($filmIds);

        foreach ($customers as $index => $userId) {
            $this->upsertNoId('rate_stars', [
                'user_id' => $userId,
                'film_id' => $films[$index],
            ], [
                'comment' => 'Danh gia demo ' . ($index + 1),
                'star_rating' => 5 - ($index % 2),
                'deleted_at' => null,
            ]);
        }

        return count($customers);
    }

    private function seedPhotos(array $filmIds): int
    {
        foreach (array_slice($filmIds, 0, 2) as $filmId) {
            $this->upsertNoId('photos', [
                'film_id' => $filmId,
                'image' => "https://picsum.photos/seed/datn-photo-{$filmId}/640/360",
            ], ['deleted_at' => null]);
        }

        return 2;
    }

    private function seedFilmMakers(array $filmIds): int
    {
        $names = ['Dao dien An', 'Dien vien Binh', 'Dao dien Chi', 'Dien vien Dung', 'Dao dien Em'];
        $films = array_values($filmIds);

        foreach ($names as $index => $name) {
            $this->upsertNoId('film_makers', [
                'name' => $name,
                'film_id' => $films[$index % count($films)],
            ], [
                'type' => $index % 2 === 0 ? 1 : 2,
                'image' => "https://i.pravatar.cc/300?u=film-maker-{$index}",
                'as' => $index % 2 === 0 ? 'Director' : 'Actor',
                'deleted_at' => null,
            ]);
        }

        return count($names);
    }

    private function seedFilmReleases(array $filmIds): array
    {
        $ids = [];
        $filmKeys = array_keys($filmIds);

        foreach ($filmIds as $slug => $filmId) {
            // Create the initial release (matching the film's own dates)
            $film = DB::table('films')->where('id', $filmId)->first();
            if (!$film) continue;

            $ids[$slug . '-r1'] = $this->upsert('film_releases', [
                'film_id' => $filmId,
                'release_date' => $film->release_date,
            ], [
                'end_date' => $film->end_date,
                'label' => 'Khởi chiếu lần 1',
                'note' => 'Tự động tạo từ DemoDataSeeder',
                'deleted_at' => null,
            ]);
        }

        // Add re-releases for 2 demo films
        $reReleaseFilms = array_slice($filmKeys, 0, 2);
        foreach ($reReleaseFilms as $index => $slug) {
            $filmId = $filmIds[$slug];
            $ids[$slug . '-r2'] = $this->upsert('film_releases', [
                'film_id' => $filmId,
                'release_date' => $this->now->copy()->addDays(30 + $index * 5)->toDateString(),
            ], [
                'end_date' => $this->now->copy()->addDays(60 + $index * 5)->toDateString(),
                'label' => 'Khởi chiếu lại',
                'note' => 'Demo re-release cho phần thống kê doanh thu theo đợt',
                'deleted_at' => null,
            ]);
        }

        return $ids;
    }

    private function upsert(string $table, array $match, array $values): int
    {
        DB::table($table)->updateOrInsert($match, $this->withTimestamps($values));

        return (int) DB::table($table)->where($match)->value('id');
    }

    private function upsertNoId(string $table, array $match, array $values = [], bool $softDeletes = true): void
    {
        $payload = $this->withTimestamps($values);
        if ($softDeletes && ! array_key_exists('deleted_at', $payload)) {
            $payload['deleted_at'] = null;
        }

        DB::table($table)->updateOrInsert($match, $payload);
    }

    private function withTimestamps(array $values): array
    {
        return array_merge($values, [
            'created_at' => $values['created_at'] ?? $this->now,
            'updated_at' => $this->now,
        ]);
    }
}
