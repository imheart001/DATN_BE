<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    public function QueryRevenue($year)
    {
        return  $query = DB::table('book_tickets')
            ->join('movie_chairs', 'book_tickets.id_chair', '=', 'movie_chairs.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->join('cinemas', 'movie_rooms.id_cinema', '=', 'cinemas.id')
            ->join('films', 'time_details.film_id', '=', 'films.id')
            ->whereYear('book_tickets.created_at', $year)
            ;
    }
    public function Revenue(Request $request)
    {
        $now = Carbon::now();
        //thống kê từ lúc bắt đầu đến hiện tại và lọc theo tháng
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $day = $startDate->day;
            $month = $startDate->month;
            $year = $startDate->year;
        } else {
            $day = $request->day ?? date('d');
            $year = $request->year ?? date('Y');
            $month = $request->month ?? date('m');
            $startDate = Carbon::create($year, $month, 1, 0, 0, 0);
            // Nếu năm hoặc tháng lọc khác năm hoặc tháng hiện tại, sử dụng ngày cuối tháng làm ngày kết thúc, ngược lại sử dụng ngày hiện tại
            if ($year != date('Y') || $month != date('m')) {
                $endDate = $startDate->copy()->endOfMonth();
            } else {
                $endDate = $now;
            }
        }
        // Tính toán doanh thu từ ngày 01 đến ngày kết thúc (cuối tháng hoặc ngày hiện tại)
        $voucher_is_onl_count = DB::table('vouchers')
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->count();

        $revenue_voucher_is_onl = DB::table('vouchers')
            ->select('code', 'price_voucher', 'usage_limit', DB::raw('(usage_limit - remaining_limit) / usage_limit * 100.0 as percentage_used'))
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->orderByDesc(DB::raw('(usage_limit - remaining_limit) / usage_limit * 100.0'))
            ->get();
        $cinemas = DB::table('cinemas')->get();
        $Revenue_on_days_in_the_month = [];
        
        // Optimize daily revenues (1 bulk query for ticket amounts and 1 for food prices)
        $ticketsData = DB::table('book_tickets')
            ->join('movie_chairs', 'book_tickets.id_chair', '=', 'movie_chairs.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->join('cinemas', 'movie_rooms.id_cinema', '=', 'cinemas.id')
            ->whereBetween('book_tickets.created_at', [$startDate, $endDate])
            ->where('book_tickets.status', '<>', 2)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy(DB::raw('DATE(book_tickets.created_at)'), 'cinemas.id')
            ->select(
                DB::raw('DATE(book_tickets.created_at) as date'),
                'cinemas.id as id_cinema',
                DB::raw('SUM(book_tickets.amount) as total_amount'),
                DB::raw('SUM(movie_chairs.price) as total_chair_price')
            )
            ->get()
            ->groupBy('date');

        $foodsData = DB::table('food_ticket_details')
            ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
            ->join('book_tickets', 'food_ticket_details.book_ticket_id', '=', 'book_tickets.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->whereBetween('book_tickets.created_at', [$startDate, $endDate])
            ->where('book_tickets.status', '<>', 2)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy(DB::raw('DATE(book_tickets.created_at)'), 'movie_rooms.id_cinema')
            ->select(
                DB::raw('DATE(book_tickets.created_at) as date'),
                'movie_rooms.id_cinema as id_cinema',
                DB::raw('SUM(food_ticket_details.quantity * food.price) as total_food_price')
            )
            ->get()
            ->groupBy('date');

        for ($currentDate = $startDate->copy(); $currentDate->lte($endDate); $currentDate->addDay()) {
            $dateStr = $currentDate->format('Y-m-d');
            $dailyRevenues = [];

            $dayTickets = isset($ticketsData[$dateStr]) ? $ticketsData[$dateStr]->keyBy('id_cinema') : collect();
            $dayFoods = isset($foodsData[$dateStr]) ? $foodsData[$dateStr]->keyBy('id_cinema') : collect();

            foreach ($cinemas as $cinema) {
                $ticketInfo = $dayTickets->get($cinema->id);
                $foodPrice = $dayFoods->get($cinema->id)?->total_food_price ?? 0;

                $dailyRevenues[$cinema->name] = [
                    'total_amount' => $ticketInfo ? intval($ticketInfo->total_amount) : 0,
                    'total_chair_price' => $ticketInfo ? intval($ticketInfo->total_chair_price) : 0,
                    'total_food_price' => intval($foodPrice),
                ];
            }
            $Revenue_on_days_in_the_month[$dateStr] = $dailyRevenues;
        }

        $monthsWithData = $this->QueryRevenue($year)
            ->where('book_tickets.status', '<>', 2)
            ->select(DB::raw('MONTH(book_tickets.created_at) as month'))
            ->groupBy(DB::raw('MONTH(book_tickets.created_at)'))
            ->pluck('month');
        $startMonth = $monthsWithData->min();
        $endMonth = $monthsWithData->max();
        
        // Optimize monthly revenues (1 bulk query for ticket amounts and 1 for food prices)
        $monthlyTickets = DB::table('book_tickets')
            ->join('movie_chairs', 'book_tickets.id_chair', '=', 'movie_chairs.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->join('cinemas', 'movie_rooms.id_cinema', '=', 'cinemas.id')
            ->whereYear('book_tickets.created_at', $year)
            ->where('book_tickets.status', '<>', 2)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy(DB::raw('MONTH(book_tickets.created_at)'), 'cinemas.id')
            ->select(
                DB::raw('MONTH(book_tickets.created_at) as month'),
                'cinemas.id as id_cinema',
                DB::raw('SUM(book_tickets.amount) as total_amount'),
                DB::raw('SUM(movie_chairs.price) as total_chair_price')
            )
            ->get()
            ->groupBy('month');

        $monthlyFoods = DB::table('food_ticket_details')
            ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
            ->join('book_tickets', 'food_ticket_details.book_ticket_id', '=', 'book_tickets.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->whereYear('book_tickets.created_at', $year)
            ->where('book_tickets.status', '<>', 2)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy(DB::raw('MONTH(book_tickets.created_at)'), 'movie_rooms.id_cinema')
            ->select(
                DB::raw('MONTH(book_tickets.created_at) as month'),
                'movie_rooms.id_cinema as id_cinema',
                DB::raw('SUM(food_ticket_details.quantity * food.price) as total_food_price')
            )
            ->get()
            ->groupBy('month');

        $Revenue_by_cinema_in_the_month = [];
        for ($months = 1; $months <= 12; $months++) {
            $monthRevenues = [];
            $monthTickets = isset($monthlyTickets[$months]) ? $monthlyTickets[$months]->keyBy('id_cinema') : collect();
            $monthFoods = isset($monthlyFoods[$months]) ? $monthlyFoods[$months]->keyBy('id_cinema') : collect();

            foreach ($cinemas as $cinema) {
                $ticketInfo = $monthTickets->get($cinema->id);
                $foodPrice = $monthFoods->get($cinema->id)?->total_food_price ?? 0;

                $monthRevenues[$cinema->name] = [
                    'total_amount' => $ticketInfo ? intval($ticketInfo->total_amount) : 0,
                    'total_chair_price' => $ticketInfo ? intval($ticketInfo->total_chair_price) : 0,
                    'total_food_price' => intval($foodPrice),
                ];
            }
            $Revenue_by_cinema_in_the_month[$year . '-' . $months] = $monthRevenues;
        }

        $day = $request->day ?? date('d');
        $year = $request->year ?? date('Y');
        $month = $request->month ?? date('m');

        $yearsWithData = $this->QueryRevenue($year)
            ->where('book_tickets.status', '<>', 2)
            ->select(DB::raw('YEAR(book_tickets.created_at) as year'))
            ->groupBy(DB::raw('YEAR(book_tickets.created_at)'))
            ->pluck('year');
        $startYear = $yearsWithData->min();
        $endYear = $yearsWithData->max();

        // Optimize yearly revenues (1 bulk query for ticket amounts and 1 for food prices)
        $yearlyTickets = DB::table('book_tickets')
            ->join('movie_chairs', 'book_tickets.id_chair', '=', 'movie_chairs.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->join('cinemas', 'movie_rooms.id_cinema', '=', 'cinemas.id')
            ->where('book_tickets.status', '<>', 2)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy(DB::raw('YEAR(book_tickets.created_at)'), 'cinemas.id')
            ->select(
                DB::raw('YEAR(book_tickets.created_at) as year'),
                'cinemas.id as id_cinema',
                DB::raw('SUM(book_tickets.amount) as total_amount'),
                DB::raw('SUM(movie_chairs.price) as total_chair_price')
            )
            ->get()
            ->groupBy('year');

        $yearlyFoods = DB::table('food_ticket_details')
            ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
            ->join('book_tickets', 'food_ticket_details.book_ticket_id', '=', 'book_tickets.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->where('book_tickets.status', '<>', 2)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy(DB::raw('YEAR(book_tickets.created_at)'), 'movie_rooms.id_cinema')
            ->select(
                DB::raw('YEAR(book_tickets.created_at) as year'),
                'movie_rooms.id_cinema as id_cinema',
                DB::raw('SUM(food_ticket_details.quantity * food.price) as total_food_price')
            )
            ->get()
            ->groupBy('year');

        $Revenue_by_cinema_in_the_year = [];
        if ($startYear && $endYear) {
            for ($currentYear = $startYear; $currentYear <= $endYear; $currentYear++) {
                $yearRevenues = [];
                $yearTickets = isset($yearlyTickets[$currentYear]) ? $yearlyTickets[$currentYear]->keyBy('id_cinema') : collect();
                $yearFoods = isset($yearlyFoods[$currentYear]) ? $yearlyFoods[$currentYear]->keyBy('id_cinema') : collect();

                foreach ($cinemas as $cinema) {
                    $ticketInfo = $yearTickets->get($cinema->id);
                    $foodPrice = $yearFoods->get($cinema->id)?->total_food_price ?? 0;

                    $yearRevenues[$cinema->name] = [
                        'total_amount' => $ticketInfo ? intval($ticketInfo->total_amount) : 0,
                        'total_chair_price' => $ticketInfo ? intval($ticketInfo->total_chair_price) : 0,
                        'total_food_price' => intval($foodPrice),
                    ];
                }
                $Revenue_by_cinema_in_the_year[$currentYear] = $yearRevenues;
            }
        }
        $query =  DB::table('book_tickets')
            ->whereYear('created_at', $year);
        if ($request->month !== null) {
            $query->whereMonth('created_at', $month);
        }
        $revenue_month_y = $query->sum('amount');
        // Tính ngày bắt đầu của tháng
        $today = now();
        // Đếm phim đang chiếu dựa trên film_releases (hỗ trợ re-release)
        $released_film_num = DB::table('film_releases')
            ->join('films', 'films.id', '=', 'film_releases.film_id')
            ->where('film_releases.release_date', '<=', $today)
            ->where('film_releases.end_date', '>=', $today)
            ->whereNull('film_releases.deleted_at')
            ->whereNull('films.deleted_at')
            ->distinct('film_releases.film_id')
            ->count('film_releases.film_id');
        $count_food = DB::table('food')->count();
        $count_cinema =  DB::table('cinemas')->where('status', 1)->count();
        $ticket_day_query =  $this->QueryRevenue($year)
            ->join('users', 'book_tickets.id_staff_check', '=', 'users.id');
        if ($request->has('start_date') && $request->has('end_date')) {
            $ticket_day_query->whereBetween('book_tickets.created_at', [$startDate, $endDate]);
        } else {
            $ticket_day_query->whereDay('book_tickets.created_at', $day)
                ->whereMonth('book_tickets.created_at', $month);
        }
        $ticket_day = $ticket_day_query
            ->select('users.name', DB::raw('COUNT(book_tickets.id) as total_tickets'))
            ->groupBy('book_tickets.id_staff_check', 'users.name')
            ->get();
        $ticket_mon =
            $this->QueryRevenue($year)
            ->join('users', 'book_tickets.id_staff_check', '=', 'users.id')


            ->whereMonth('book_tickets.created_at', $month)
            ->select('users.name', DB::raw('COUNT(book_tickets.id) as total_tickets'))
            ->groupBy('book_tickets.id_staff_check', 'users.name')
            ->get();
        $ticket_year =  $this->QueryRevenue($year)
            ->join('users', 'book_tickets.id_staff_check', '=', 'users.id')

            ->where('users.role', '<>', 0)
            ->whereYear('book_tickets.created_at', $year)
            ->select('users.name', DB::raw('COUNT(book_tickets.id) as total_tickets'))
            ->groupBy('book_tickets.id_staff_check', 'users.name')
            ->get();
        $newUsers = User::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();

        //-------------------------------------
        //lấy ra film có doanh thu cao nhất tháng

        $revenue_film_month = DB::table('book_tickets')
            ->leftJoin('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->leftJoin('films', 'time_details.film_id', '=', 'films.id')
            ->leftJoin('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->leftJoin('cinemas', 'cinemas.id', '=', 'movie_rooms.id_cinema')
            ->select(
                'films.name as film_name',
                DB::raw('ROUND(SUM(CASE WHEN book_tickets.status <> 2 THEN book_tickets.amount ELSE 0 END), 0) as TotalAmount'),
                DB::raw('COUNT(CASE WHEN book_tickets.status <> 2 THEN book_tickets.id ELSE NULL END) as TotalTickets'),
                DB::raw('0 as RefundAmount'),
                DB::raw('0 as RefundTickets')
            )
            ->whereMonth('book_tickets.created_at', $month)
            ->whereYear('book_tickets.created_at', $year)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy('films.name')
            ->orderBy('TotalAmount', 'desc')
            ->get();

        //----------------------------------------------------------------
        //lấy ra 5 khách hàng thân thiết
        $user_friendly = DB::table('book_tickets')
            ->join('users', 'book_tickets.user_id', '=', 'users.id')
            ->join('members', 'members.id_user', '=', 'users.id')
            ->select('users.name', DB::raw('SUM(book_tickets.amount) as TotalAmount'), 'members.card_class')
            ->groupBy('users.name', 'members.card_class')
            ->orderBy('TotalAmount', 'desc')
            ->take(5)->whereNull('book_tickets.deleted_at')
            ->get();

        //----------------------------------------------------------------
        //lấy ra tổng số vé bán ra trong tháng theo từng phim
        //----------------------------------------------------------------
        //lấy ra tống doanh thu từ đồ ăn theo tháng
        $totalPricefoodmon =  $this->QueryRevenue($year)
        ->join('food_ticket_details', 'book_tickets.id', '=', 'food_ticket_details.book_ticket_id')
            ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
           
            ->whereMonth('book_tickets.created_at', $month)
            ->whereNull('food_ticket_details.deleted_at')
            ->sum(DB::raw('food_ticket_details.quantity * food.price'));
        //----------------------------------------------------------------
        // lấy ra so sánh doanh thu tháng này với tháng trước

        $month2 = $month;
        $year2 = $year;

        // Lấy tháng và năm trước
        $selectedDate = Carbon::create($year2, $month2, 1);
        $lastMonth = $selectedDate->copy()->subMonth();
        $lastMonthNumber = $lastMonth->month;
        $lastYear = $lastMonth->year;

        // Tính toán doanh thu tháng hiện tại
        $currentMonthRevenue = DB::table('book_tickets')
            ->whereMonth('created_at', $month2)
            ->whereYear('created_at', $year2)->whereNull('book_tickets.deleted_at')
            ->sum('amount');

        // Tính toán doanh thu tháng trước
        $lastMonthRevenue = DB::table('book_tickets')
            ->whereMonth('created_at', $lastMonthNumber)
            ->whereYear('created_at', $lastYear)->whereNull('book_tickets.deleted_at')
            ->sum('amount');
        // So sánh doanh thu
        $comparison = $currentMonthRevenue - $lastMonthRevenue;
        $revenueTodayQuery = DB::table('book_tickets')->whereNull('book_tickets.deleted_at');
        if ($request->has('start_date') && $request->has('end_date')) {
            $revenueTodayQuery->whereBetween('book_tickets.created_at', [$startDate, $endDate]);
        } else {
            $revenueTodayQuery->whereDay('book_tickets.created_at', $day)
                ->whereMonth('book_tickets.created_at', $month)
                ->whereYear('book_tickets.created_at', $year);
        }
        $revenueToday = $revenueTodayQuery->sum('amount');
        //-------------------------------
        //lấy ra khách hàng mới trong ngày
        $user_count = User::count();
        //------------------------------------
        //lấy ra film có doanh thu cao nhất trong ngày

        $revenue_film_query = DB::table('book_tickets')
            ->leftJoin('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->leftJoin('films', 'time_details.film_id', '=', 'films.id')
            ->leftJoin('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->leftJoin('cinemas', 'cinemas.id', '=', 'movie_rooms.id_cinema')
            ->select(
                'films.name as film_name',
                DB::raw('ROUND(SUM(CASE WHEN book_tickets.status <> 2 THEN book_tickets.amount ELSE 0 END), 0) as TotalAmount'),
                DB::raw('COUNT(CASE WHEN book_tickets.status <> 2 THEN book_tickets.id ELSE NULL END) as TotalTickets'),
                DB::raw('0 as RefundAmount'),
                DB::raw('0 as RefundTickets')
            )
            ->whereNull('book_tickets.deleted_at');
        if ($request->has('start_date') && $request->has('end_date')) {
            $revenue_film_query->whereBetween('book_tickets.created_at', [$startDate, $endDate]);
        } else {
            $revenue_film_query->whereDay('book_tickets.created_at', $day)
                ->whereMonth('book_tickets.created_at', $month)
                ->whereYear('book_tickets.created_at', $year);
        }
        $revenue_film = $revenue_film_query
            ->groupBy('films.name')
            ->orderBy('TotalAmount', 'desc')
            ->get();


        $totalPricefooddayQuery = $this->QueryRevenue($year)
            ->join('food_ticket_details', 'book_tickets.id', '=', 'food_ticket_details.book_ticket_id')
            ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
            ->whereNull('food_ticket_details.deleted_at');
        if ($request->has('start_date') && $request->has('end_date')) {
            $totalPricefooddayQuery->whereBetween('book_tickets.created_at', [$startDate, $endDate]);
        } else {
            $totalPricefooddayQuery->whereDay('book_tickets.created_at', $day)
                ->whereMonth('book_tickets.created_at', $month);
        }
        $totalPricefoodday = $totalPricefooddayQuery->sum(DB::raw('food_ticket_details.quantity * food.price'));

        $data = [
            "released_film_num" => $released_film_num,
            "count_food" => $count_food,
            "count_cinema" => $count_cinema,
            'user_count' => $user_count,
            'voucher_is_onl_count' => $voucher_is_onl_count,
            "revenue_voucher_is_onl" =>$revenue_voucher_is_onl,
            "revenue_day" => [
                "revenueToday" => $revenueToday,
                'revenue_and_refund_day' => $revenue_film,
                'totalPricefoodday' => $totalPricefoodday,
                'ticket_day' => $ticket_day,
                'ticket_mon' => $ticket_mon,
                'ticket_year' => $ticket_year

            ],
            "revenue_month" => [
                'revenue_month_y' => $revenue_month_y,
                'revenue_and_refund_month' => $revenue_film_month,
                'user_friendly' => $user_friendly,
                'comparison' => $comparison,
                'totalPricefoodmon' => $totalPricefoodmon
            ],
            "statistical_cinema" => [
                'Revenue_by_cinema_in_the_month' => $Revenue_by_cinema_in_the_month, // biểu đồ các tháng trong năm của admin rạp
                'Revenue_by_cinema_on_the_day' => $Revenue_on_days_in_the_month, // biểu đồ các ngày trong tháng của admin rạpp
                'Revenue_by_cinema_in_the_year' => $Revenue_by_cinema_in_the_year // biểu đồ các năm của admin rạp

            ],

        ];


        return $data;
    }
    public function Revenue_cinema(Request $request)
    {
        $now = Carbon::now();
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $day = $startDate->day;
            $month = $startDate->month;
            $year = $startDate->year;
        } else {
            $day = $request->day ?? date('d');
            $year = $request->year ?? date('Y');
            $month = $request->month ?? date('m');
            $startDate = Carbon::create($year, $month, 1, 0, 0, 0);
            // Nếu năm hoặc tháng lọc khác năm hoặc tháng hiện tại, sử dụng ngày cuối tháng làm ngày kết thúc, ngược lại sử dụng ngày hiện tại
            if ($year != date('Y') || $month != date('m')) {
                $endDate = $startDate->copy()->endOfMonth();
            } else {
                $endDate = $now;
            }
        }

        $Revenue_on_days_in_the_month = [];
        
        // Optimize daily revenues for single cinema (1 bulk query for ticket amounts and 1 for food prices)
        $ticketsData = DB::table('book_tickets')
            ->join('movie_chairs', 'book_tickets.id_chair', '=', 'movie_chairs.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->whereBetween('book_tickets.created_at', [$startDate, $endDate])
            ->where('movie_rooms.id_cinema', $request->id_cinema)
            ->where('book_tickets.status', '<>', 2)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy(DB::raw('DATE(book_tickets.created_at)'))
            ->select(
                DB::raw('DATE(book_tickets.created_at) as date'),
                DB::raw('SUM(book_tickets.amount) as total_amount'),
                DB::raw('SUM(movie_chairs.price) as total_chair_price')
            )
            ->get()
            ->keyBy('date');

        $foodsData = DB::table('food_ticket_details')
            ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
            ->join('book_tickets', 'food_ticket_details.book_ticket_id', '=', 'book_tickets.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->whereBetween('book_tickets.created_at', [$startDate, $endDate])
            ->where('movie_rooms.id_cinema', $request->id_cinema)
            ->where('book_tickets.status', '<>', 2)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy(DB::raw('DATE(book_tickets.created_at)'))
            ->select(
                DB::raw('DATE(book_tickets.created_at) as date'),
                DB::raw('SUM(food_ticket_details.quantity * food.price) as total_food_price')
            )
            ->get()
            ->keyBy('date');

        for ($currentDate = $startDate->copy(); $currentDate->lte($endDate); $currentDate->addDay()) {
            $dateStr = $currentDate->format('Y-m-d');
            $ticketInfo = $ticketsData->get($dateStr);
            $foodPrice = $foodsData->get($dateStr)?->total_food_price ?? 0;

            $Revenue_on_days_in_the_month[$dateStr] = [
                'total_amount' => $ticketInfo ? intval($ticketInfo->total_amount) : 0,
                'total_chair_price' => $ticketInfo ? intval($ticketInfo->total_chair_price) : 0,
                'total_food_price' => intval($foodPrice),
            ];
        }
        
        $Revenue_in_months_of_the_year = [];

        // Optimize monthly revenues for single cinema (1 bulk query for ticket amounts and 1 for food prices)
        $monthlyTickets = DB::table('book_tickets')
            ->join('movie_chairs', 'book_tickets.id_chair', '=', 'movie_chairs.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->whereYear('book_tickets.created_at', $year)
            ->where('movie_rooms.id_cinema', $request->id_cinema)
            ->where('book_tickets.status', '<>', 2)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy(DB::raw('MONTH(book_tickets.created_at)'))
            ->select(
                DB::raw('MONTH(book_tickets.created_at) as month'),
                DB::raw('SUM(book_tickets.amount) as total_amount'),
                DB::raw('SUM(movie_chairs.price) as total_chair_price')
            )
            ->get()
            ->keyBy('month');

        $monthlyFoods = DB::table('food_ticket_details')
            ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
            ->join('book_tickets', 'food_ticket_details.book_ticket_id', '=', 'book_tickets.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->whereYear('book_tickets.created_at', $year)
            ->where('movie_rooms.id_cinema', $request->id_cinema)
            ->where('book_tickets.status', '<>', 2)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy(DB::raw('MONTH(book_tickets.created_at)'))
            ->select(
                DB::raw('MONTH(book_tickets.created_at) as month'),
                DB::raw('SUM(food_ticket_details.quantity * food.price) as total_food_price')
            )
            ->get()
            ->keyBy('month');

        for ($months = 1; $months <= 12; $months++) {
            $ticketInfo = $monthlyTickets->get($months);
            $foodPrice = $monthlyFoods->get($months)?->total_food_price ?? 0;

            $Revenue_in_months_of_the_year[$year .'-' .$months] = [
                'total_amount' => $ticketInfo ? intval($ticketInfo->total_amount) : 0,
                'total_chair_price' => $ticketInfo ? intval($ticketInfo->total_chair_price) : 0,
                'total_food_price' => intval($foodPrice),
            ];
        }

        $yearsWithData = DB::table('book_tickets')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->join('cinemas', 'cinemas.id', '=', 'movie_rooms.id_cinema')
            ->where('cinemas.id', $request->id_cinema)
            ->where('book_tickets.status', '<>', 2)
            ->select(DB::raw('YEAR(book_tickets.created_at) as year'))
            ->groupBy(DB::raw('YEAR(book_tickets.created_at)'))
            ->pluck('year');

        $startYear = $yearsWithData->min();
        $endYear = $yearsWithData->max();

        // Optimize yearly revenues for single cinema (1 bulk query for ticket amounts and 1 for food prices)
        $yearlyTickets = DB::table('book_tickets')
            ->join('movie_chairs', 'book_tickets.id_chair', '=', 'movie_chairs.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->where('movie_rooms.id_cinema', $request->id_cinema)
            ->where('book_tickets.status', '<>', 2)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy(DB::raw('YEAR(book_tickets.created_at)'))
            ->select(
                DB::raw('YEAR(book_tickets.created_at) as year'),
                DB::raw('SUM(book_tickets.amount) as total_amount'),
                DB::raw('SUM(movie_chairs.price) as total_chair_price')
            )
            ->get()
            ->keyBy('year');

        $yearlyFoods = DB::table('food_ticket_details')
            ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
            ->join('book_tickets', 'food_ticket_details.book_ticket_id', '=', 'book_tickets.id')
            ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->join('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->where('movie_rooms.id_cinema', $request->id_cinema)
            ->where('book_tickets.status', '<>', 2)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy(DB::raw('YEAR(book_tickets.created_at)'))
            ->select(
                DB::raw('YEAR(book_tickets.created_at) as year'),
                DB::raw('SUM(food_ticket_details.quantity * food.price) as total_food_price')
            )
            ->get()
            ->keyBy('year');

        $Revenue_by_year = [];
        if ($startYear && $endYear) {
            for ($currentYear = $startYear; $currentYear <= $endYear; $currentYear++) {
                $ticketInfo = $yearlyTickets->get($currentYear);
                $foodPrice = $yearlyFoods->get($currentYear)?->total_food_price ?? 0;

                $Revenue_by_year[] = [
                    'total_amount' => $ticketInfo ? intval($ticketInfo->total_amount) : 0,
                    'total_chair_price' => $ticketInfo ? intval($ticketInfo->total_chair_price) : 0,
                    'total_food_price' => intval($foodPrice),
                ];
            }
        }
        // lọc theo ngày 
        $day = intval($request->day ?? date('d'));
        $year = intval($request->year ?? date('Y'));
        $month = intval($request->month ?? date('m'));
        /// lấy ra doanh thu 1 ngày theo rạp cho nhân viên xem
        $revenue_staff_day_query = $this->QueryRevenue($year)
            ->where('book_tickets.status', '<>', 2)
            ->where('cinemas.id', $request->id_cinema);
        if ($request->has('start_date') && $request->has('end_date')) {
            $revenue_staff_day_query->whereBetween('book_tickets.created_at', [$startDate, $endDate]);
        } else {
            $revenue_staff_day_query->whereDay('book_tickets.created_at', $day)
                                    ->whereMonth('book_tickets.created_at', $month);
        }
        $revenue_staff_day = $revenue_staff_day_query
            ->select('cinemas.name as cinema_name', DB::raw('SUM(book_tickets.amount) as total_amount'))
            ->groupBy('cinemas.name')
            ->get();

        ///số vé bán ra theo từng tên phim của một ngày
        $tickets_total_day_query = $this->QueryRevenue($year)
            ->select('films.name', DB::raw('COUNT(book_tickets.id) as total_tickets'), DB::raw('SUM(book_tickets.amount) as TotalAmount'))
            ->where('cinemas.id', $request->id_cinema);
        if ($request->has('start_date') && $request->has('end_date')) {
            $tickets_total_day_query->whereBetween('book_tickets.created_at', [$startDate, $endDate]);
        } else {
            $tickets_total_day_query->whereDay('book_tickets.created_at', $day)
                                    ->whereMonth('book_tickets.created_at', $month);
        }
        $tickets_total_day = $tickets_total_day_query
            ->groupBy('films.name')
            ->get();
        ///lấy ra tổng doanh thu theo ngày từ đồ ăn
        $revenue_food_query = $this->QueryRevenue($year)
            ->join('food_ticket_details', 'book_tickets.id', '=', 'food_ticket_details.book_ticket_id')
            ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
            ->where('book_tickets.status', '<>', 2)
            ->where('cinemas.id', $request->id_cinema);
        if ($request->has('start_date') && $request->has('end_date')) {
            $revenue_food_query->whereBetween('book_tickets.created_at', [$startDate, $endDate]);
        } else {
            $revenue_food_query->whereDay('book_tickets.created_at', $day)
                               ->whereMonth('book_tickets.created_at', $month);
        }
        $revenue_food = $revenue_food_query
            ->select(DB::raw('SUM(food_ticket_details.quantity * food.price) as total_food_price'))
            ->get()->first();
        //lọc ra doanh thu ngày tháng năm hiện tại theo rạp (admin)
        $revenue_admin_day_filter_query = $this->QueryRevenue($year)
            ->where('book_tickets.status', '<>', 2)
            ->where('cinemas.id', $request->id_cinema);
        if ($request->has('start_date') && $request->has('end_date')) {
            $revenue_admin_day_filter_query->whereBetween('book_tickets.created_at', [$startDate, $endDate]);
        } else {
            $revenue_admin_day_filter_query->whereDay('book_tickets.created_at', $day)
                                           ->whereMonth('book_tickets.created_at', $month);
        }
        $revenue_admin_day_filter = $revenue_admin_day_filter_query
            ->select('cinemas.name as cinema_name', DB::raw('SUM(book_tickets.amount) as total_amount'))
            ->groupBy('cinemas.name')
            ->get();

        //lọc ra doanh thu tháng  hiện tại theo rạp (admin)
        $revenue_admin_mon_filter = $this->QueryRevenue($year)
            ->where('book_tickets.status', '<>', 2)
            ->where('cinemas.id', $request->id_cinema)
            ->whereMonth('book_tickets.created_at', $month)

            ->select('cinemas.name as cinema_name', DB::raw('SUM(book_tickets.amount) as total_amount'))
            ->groupBy('cinemas.name')
            ->get();
        //lọc ra doanh thu năm  hiện tại theo rạp (admin)
        $revenue_admin_year_filter = $this->QueryRevenue($year)
            ->where('book_tickets.status', '<>', 2)
            ->where('cinemas.id', $request->id_cinema)

            ->select('cinemas.name as cinema_name', DB::raw('SUM(book_tickets.amount) as total_amount'))
            ->groupBy('cinemas.name')
            ->get();


        //lấy ra doanh thu ngày tháng năm hiện tại theo rạp tự động (admin)


        ///số vé bán ra theo từng tên phim của một ngày
        $tickets_total_mon = $this->QueryRevenue($year)
            ->select('films.name', DB::raw('COUNT(book_tickets.id) as total_tickets'), DB::raw('SUM(book_tickets.amount) as TotalAmount'))
            ->where('book_tickets.status', '<>', 2)

            ->whereMonth('book_tickets.created_at', '=', $month)

            ->where('cinemas.id', $request->id_cinema)
            ->groupBy('films.name')
            ->get();

        $ticket_staff_fill_day_query =  $this->QueryRevenue($year)
            ->join('users', 'book_tickets.id_staff_check', '=', 'users.id')
            ->where('users.role', '<>', 0)
            ->where('cinemas.id', $request->id_cinema)
            ->where('book_tickets.status', '<>', 2);
        if ($request->has('start_date') && $request->has('end_date')) {
            $ticket_staff_fill_day_query->whereBetween('book_tickets.created_at', [$startDate, $endDate]);
        } else {
            $ticket_staff_fill_day_query->whereDay('book_tickets.created_at', $day)
                                        ->whereMonth('book_tickets.created_at', $month);
        }
        $ticket_staff_fill_day = $ticket_staff_fill_day_query
            ->select('users.name', DB::raw('COUNT(book_tickets.id) as total_tickets'))
            ->groupBy('book_tickets.id_staff_check', 'users.name')
            ->get();

        $ticket_staff_fill_mon = $this->QueryRevenue($year)
            ->join('users', 'book_tickets.id_staff_check', '=', 'users.id')

            ->where('users.role', '<>', 0)
            ->where('book_tickets.status', '<>', 2)

            ->where('cinemas.id', $request->id_cinema)
            ->whereMonth('book_tickets.created_at', $month)

            ->select('users.name', DB::raw('COUNT(book_tickets.id) as total_tickets'))
            ->groupBy('book_tickets.id_staff_check', 'users.name')
            ->get();

        $total_food_mon = $this->QueryRevenue($year)
        ->join('food_ticket_details', 'book_tickets.id', '=', 'food_ticket_details.book_ticket_id')
            ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
           
            ->whereMonth('book_tickets.created_at', $month)
            ->where('book_tickets.status', '<>', 2)

            ->where('cinemas.id', $request->id_cinema)
            ->select(DB::raw('SUM(food_ticket_details.quantity * food.price) as total_food_price'))
            ->get()->first();

        $total_food_year = $this->QueryRevenue($year)
        ->join('food_ticket_details', 'book_tickets.id', '=', 'food_ticket_details.book_ticket_id')
        ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
       
            ->where('book_tickets.status', '<>', 2)
            ->where('cinemas.id', $request->id_cinema)
            ->select(DB::raw('SUM(food_ticket_details.quantity * food.price) as total_food_price'))
            ->get()->first();

        $revenue_film_query = DB::table('book_tickets')
            ->leftJoin('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->leftJoin('films', 'time_details.film_id', '=', 'films.id')
            ->leftJoin('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->leftJoin('cinemas', 'cinemas.id', '=', 'movie_rooms.id_cinema')
            ->select(
                'films.name as film_name',
                DB::raw('ROUND(SUM(CASE WHEN book_tickets.status <> 2 THEN book_tickets.amount ELSE 0 END), 0) as TotalAmount'),
                DB::raw('COUNT(CASE WHEN book_tickets.status <> 2 THEN book_tickets.id ELSE NULL END) as TotalTickets'),
                DB::raw('0 as RefundAmount'),
                DB::raw('0 as RefundTickets')
            )
            ->where('cinemas.id', $request->id_cinema)
            ->whereNull('book_tickets.deleted_at');
        if ($request->has('start_date') && $request->has('end_date')) {
            $revenue_film_query->whereBetween('book_tickets.created_at', [$startDate, $endDate]);
        } else {
            $revenue_film_query->whereDay('book_tickets.created_at', $day)
                               ->whereMonth('book_tickets.created_at', $month)
                               ->whereYear('book_tickets.created_at', $year);
        }
        $revenue_film = $revenue_film_query
            ->groupBy('films.name')
            ->orderBy('TotalAmount', 'desc')
            ->get();

        $revenue_film_month = DB::table('book_tickets')
            ->leftJoin('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
            ->leftJoin('films', 'time_details.film_id', '=', 'films.id')
            ->leftJoin('movie_rooms', 'time_details.room_id', '=', 'movie_rooms.id')
            ->leftJoin('cinemas', 'cinemas.id', '=', 'movie_rooms.id_cinema')
            ->select(
                'films.name as film_name',
                DB::raw('ROUND(SUM(CASE WHEN book_tickets.status <> 2 THEN book_tickets.amount ELSE 0 END), 0) as TotalAmount'),
                DB::raw('COUNT(CASE WHEN book_tickets.status <> 2 THEN book_tickets.id ELSE NULL END) as TotalTickets'),
                DB::raw('0 as RefundAmount'),
                DB::raw('0 as RefundTickets')
            )
            ->where('cinemas.id', $request->id_cinema)
            ->whereMonth('book_tickets.created_at', $month)
            ->whereYear('book_tickets.created_at', $year)
            ->whereNull('book_tickets.deleted_at')
            ->groupBy('films.name')
            ->orderBy('TotalAmount', 'desc')
            ->get();



        // Lấy giá trị và đặt vào mảng

        return [
            "revenue_admin_cinema" => [  // thông tin cho admin rạpp xem, nếu truyền day vào thì sẽ tính theo tháng và năm hiện tại 
                // ví dụ chuyền vào 5 thì sẽ tính theo 2023/12/09, nếu k chuyền thì sẽ tính theo thời gian hiện tại
                //  Tương tự tháng và năm như vậy, chỉ truyền vào tháng sẽ tính theo ngày và năm hiện tại, chỉ có số tháng thay đổi.
                //  ví dụ nhập month = 7 thì sẽ tính 2023/07/09
                // nếu không ra dữ liệu ngày có nghĩa là ngày đó k có dữ liệu
                'revenue_admin_day_filter' => $revenue_admin_day_filter,    //thống kê ngày của rạp
                'revenue_admin_mon_filter' => $revenue_admin_mon_filter,    // thống kê tháng của rạp
                'revenue_admin_year_filter' => $revenue_admin_year_filter,  // thống kê năm của rạp
                'revenue_and_refund_day_cinema' => $revenue_film,  //
                'revenue_and_refund_month_cinema' => $revenue_film_month,
                'ticket_staff_fill_day' => $ticket_staff_fill_day,      // số lượng vé check của nhân viên.
                'ticket_staff_fill_mon' => $ticket_staff_fill_mon,
                'revenue_food_day' => $revenue_food->total_food_price,        // tổng doanh thu đồ ăn
                'total_food_mon' => $total_food_mon->total_food_price,
                'total_food_year' => $total_food_year->total_food_price,

            ],
            "statistical_cinema" => [
                'Revenue_in_months_of_the_year' => $Revenue_in_months_of_the_year, // biểu đồ các tháng trong năm của admin rạp
                'Revenue_on_days_in_the_month' => $Revenue_on_days_in_the_month, // biểu đồ các ngày trong tháng của admin rạpp
                'Revenue_by_year' => $Revenue_by_year // biểu đồ các năm của admin rạp

            ]
        ];
    }

    public function Revenue_cinema_staff(Request $request)
    {
        $day = intval($request->day ?? date('d'));
        $year = intval($request->year ?? date('Y'));
        $month = intval($request->month ?? date('m'));
        /// lấy ra doanh thu 1 ngày theo rạp cho nhân viên xem
        $revenue_staff_day = $this->QueryRevenue($year)
            ->where('book_tickets.status', '<>', 2)
            ->where('cinemas.id', $request->id_cinema)
            ->whereDay('book_tickets.created_at', $day)
            ->whereMonth('book_tickets.created_at', $month)
            ->select('cinemas.name as cinema_name', DB::raw('SUM(book_tickets.amount) as total_amount'))
            ->groupBy('cinemas.name')
            ->get();
        $tickets_total_day = $this->QueryRevenue($year)
            ->select('films.name', DB::raw('COUNT(book_tickets.id) as total_tickets'), DB::raw('SUM(book_tickets.amount) as TotalAmount'))
            ->whereDay('book_tickets.created_at', $day)
            ->whereMonth('book_tickets.created_at', $month)

            ->where('cinemas.id', $request->id_cinema)
            ->groupBy('films.name')
            ->get();
        $revenue_food = $this->QueryRevenue($year)
        ->join('food_ticket_details', 'book_tickets.id', '=', 'food_ticket_details.book_ticket_id')
            ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
           
            ->where('book_tickets.status', '<>', 2)
            ->whereDay('book_tickets.created_at', $day)
            ->whereMonth('book_tickets.created_at', $month)

            ->where('cinemas.id', $request->id_cinema)
            ->select(DB::raw('SUM(food_ticket_details.quantity * food.price) as total_food_price'))
            ->get()->first();
        return ["revenue_staff" => [ // thông tin cho nhân viên xem 
            'revenue_staff_day' => $revenue_staff_day, // thống kê 
            'tickets_total_day' => $tickets_total_day, // xem tên nhân viên và số lượng vé check-in
            'revenue_food' => $revenue_food // thống kê đồ ăn
        ]];
    }

    /**
     * Doanh thu theo từng đợt chiếu của phim.
     * Input: film_id (required), film_release_id (optional)
     */
    public function revenueByRelease(Request $request)
    {
        $filmId = $request->film_id;
        $filmReleaseId = $request->film_release_id;
        $year = $request->year ?? date('Y');

        if (!$filmId) {
            return response()->json(['message' => 'film_id là bắt buộc'], 422);
        }

        // Lấy danh sách đợt chiếu
        $releasesQuery = DB::table('film_releases')
            ->where('film_id', $filmId)
            ->whereNull('deleted_at');

        if ($filmReleaseId) {
            $releasesQuery->where('id', $filmReleaseId);
        }

        $releases = $releasesQuery->orderBy('release_date', 'desc')->get();

        $result = [];
        foreach ($releases as $release) {
            // Doanh thu vé cho đợt chiếu này
            $ticketRevenue = DB::table('book_tickets')
                ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
                ->where('time_details.film_release_id', $release->id)
                ->where('book_tickets.status', '<>', 2)
                ->whereNull('book_tickets.deleted_at')
                ->sum('book_tickets.amount');

            // Doanh thu đồ ăn cho đợt chiếu này
            $foodRevenue = DB::table('food_ticket_details')
                ->join('food', 'food_ticket_details.food_id', '=', 'food.id')
                ->join('book_tickets', 'food_ticket_details.book_ticket_id', '=', 'book_tickets.id')
                ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
                ->where('time_details.film_release_id', $release->id)
                ->where('book_tickets.status', '<>', 2)
                ->whereNull('book_tickets.deleted_at')
                ->whereNull('food_ticket_details.deleted_at')
                ->sum(DB::raw('food_ticket_details.quantity * food.price'));

            // Số vé bán
            $ticketCount = DB::table('book_tickets')
                ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
                ->where('time_details.film_release_id', $release->id)
                ->where('book_tickets.status', '<>', 2)
                ->whereNull('book_tickets.deleted_at')
                ->count();

            // Số vé hoàn
            $refundCount = DB::table('book_tickets')
                ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
                ->where('time_details.film_release_id', $release->id)
                ->where('book_tickets.status', 2)
                ->whereNull('book_tickets.deleted_at')
                ->count();

            $refundAmount = DB::table('book_tickets')
                ->join('time_details', 'book_tickets.id_time_detail', '=', 'time_details.id')
                ->where('time_details.film_release_id', $release->id)
                ->where('book_tickets.status', 2)
                ->whereNull('book_tickets.deleted_at')
                ->sum(DB::raw('ROUND(0.3 * book_tickets.amount, 0)'));

            $result[] = [
                'release_id' => $release->id,
                'label' => $release->label,
                'release_date' => $release->release_date,
                'end_date' => $release->end_date,
                'note' => $release->note,
                'ticket_revenue' => intval($ticketRevenue),
                'food_revenue' => intval($foodRevenue),
                'total_revenue' => intval($ticketRevenue) + intval($foodRevenue),
                'ticket_count' => $ticketCount,
                'refund_count' => $refundCount,
                'refund_amount' => intval($refundAmount),
            ];
        }

        // Tổng doanh thu tất cả đợt chiếu
        $grandTotal = array_sum(array_column($result, 'total_revenue'));

        return response()->json([
            'film_id' => $filmId,
            'grand_total' => $grandTotal,
            'releases' => $result,
        ]);
    }
}
