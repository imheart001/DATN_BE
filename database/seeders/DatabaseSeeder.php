<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Use all models
use App\Models\User;
use App\Models\Categories;
use App\Models\Film;
use App\Models\Banner;
use App\Models\Blogs;
use App\Models\Cinemas;
use App\Models\Food;
use App\Models\Time;
use App\Models\voucher;
use App\Models\Feedback;
use App\Models\FilmMaker;
use App\Models\photos;
use App\Models\MovieRoom;
use App\Models\CategoryDetail;
use App\Models\social_networks;
use App\Models\Contact_infos;
use App\Models\member;
use App\Models\RateStar;
use App\Models\Comment;
use App\Models\TimeDetail;
use App\Models\Book_ticket;
use App\Models\Chairs;
use App\Models\Food_ticket_detail;
use App\Models\UsedVoucher;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Independent Models
        User::factory(20)->create();
        Categories::factory(10)->create();
        Film::factory(20)->create();
        Banner::factory(5)->create();
        Blogs::factory(10)->create();
        Cinemas::factory(5)->create();
        Food::factory(20)->create();
        Time::factory(10)->create();
        voucher::factory(10)->create();

        // Models with Level 1 Dependencies
        Feedback::factory(10)->create();
        FilmMaker::factory(40)->create();
        photos::factory(40)->create();
        MovieRoom::factory(15)->create();
        CategoryDetail::factory(30)->create();
        social_networks::factory(10)->create();
        Contact_infos::factory(10)->create();
        member::factory(10)->create();
        RateStar::factory(50)->create();
        Comment::factory(50)->create();

        // Models with Level 2 Dependencies (TimeDetail depends on Time, Film, Room)
        TimeDetail::factory(50)->create();

        // Models with Level 3 Dependencies (BookTicket, Chairs depend on TimeDetail)
        Book_ticket::withoutEvents(function () {
            Book_ticket::factory(20)->create();
        });
        Chairs::factory(100)->create();

        // Models with Level 4 Dependencies (FoodTicketDetail, UsedVoucher)
        Food_ticket_detail::factory(30)->create();
        UsedVoucher::factory(10)->create();

        echo "Database seeding completed successfully.\n";
    }
}
