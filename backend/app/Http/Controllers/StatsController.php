<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatsController extends Controller
{
    /**
     * Get KPIs (Key Performance Indicators)
     * GET /api/stats/kpis?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function getKPIs(Request $request)
    {
        $from = $request->input('from', Carbon::now()->subDays(7)->format('Y-m-d'));
        $to = $request->input('to', Carbon::now()->format('Y-m-d'));

        // Visitas hoy
        $today = Visit::whereDate('created_at', Carbon::today())->count();

        // Visitas esta semana
        $thisWeek = Visit::whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ])->count();

        // Promedio diario en el rango
        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);
        $days = max(1, $fromDate->diffInDays($toDate) + 1);
        $totalInRange = Visit::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->count();
        
        // Calcular promedio con 1 decimal si es menor a 1
        $averageValue = $totalInRange / $days;
        $dailyAverage = $averageValue < 1 ? round($averageValue, 1) : round($averageValue);

        // Duración promedio (en minutos)
        $avgDuration = Visit::whereNotNull('end_at')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, end_at)) as avg_duration')
            ->value('avg_duration');
        $avgDuration = round($avgDuration ?? 0);

        return response()->json([
            'today' => $today,
            'thisWeek' => $thisWeek,
            'dailyAverage' => $dailyAverage,
            'avgDuration' => $avgDuration
        ]);
    }

    /**
     * Get daily trend of visits
     * GET /api/stats/daily?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function getDailyTrend(Request $request)
    {
        $from = $request->input('from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $to = $request->input('to', Carbon::now()->format('Y-m-d'));

        $dailyVisits = Visit::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as visits')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Generar todos los días en el rango (incluir días sin visitas)
        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);
        $allDates = [];
        $visitsMap = $dailyVisits->pluck('visits', 'date')->toArray();

        while ($fromDate->lte($toDate)) {
            $dateStr = $fromDate->format('Y-m-d');
            $allDates[] = $dateStr;
            $fromDate->addDay();
        }

        $dates = $allDates;
        $visits = array_map(function($date) use ($visitsMap) {
            return $visitsMap[$date] ?? 0;
        }, $allDates);

        return response()->json([
            'dates' => $dates,
            'visits' => $visits
        ]);
    }

    /**
     * Get visits by department
     * GET /api/stats/by-department?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function getByDepartment(Request $request)
    {
        $from = $request->input('from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $to = $request->input('to', Carbon::now()->format('Y-m-d'));

        $departmentVisits = Visit::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->selectRaw('department, COUNT(*) as visits')
            ->groupBy('department')
            ->orderByDesc('visits')
            ->limit(10)
            ->get();

        $total = $departmentVisits->sum('visits');

        $data = $departmentVisits->map(function($item) use ($total) {
            return [
                'department' => $item->department,
                'visits' => $item->visits,
                'percentage' => $total > 0 ? round(($item->visits / $total) * 100, 1) : 0
            ];
        });

        return response()->json($data);
    }

    /**
     * Get average visit duration
     * GET /api/stats/duration?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function getAverageDuration(Request $request)
    {
        $from = $request->input('from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $to = $request->input('to', Carbon::now()->format('Y-m-d'));

        $durations = Visit::whereNotNull('end_at')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('
                AVG(TIMESTAMPDIFF(MINUTE, created_at, end_at)) as average,
                MIN(TIMESTAMPDIFF(MINUTE, created_at, end_at)) as min,
                MAX(TIMESTAMPDIFF(MINUTE, created_at, end_at)) as max
            ')
            ->first();

        return response()->json([
            'average' => round($durations->average ?? 0),
            'min' => round($durations->min ?? 0),
            'max' => round($durations->max ?? 0)
        ]);
    }

    /**
     * Get hourly peak distribution (0-23h)
     * GET /api/stats/hourly?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function getHourlyPeak(Request $request)
    {
        $from = $request->input('from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $to = $request->input('to', Carbon::now()->format('Y-m-d'));

        $hourlyVisits = Visit::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as visits')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('visits', 'hour')
            ->toArray();

        // Generar todas las horas (0-23)
        $data = [];
        for ($h = 0; $h < 24; $h++) {
            $hourStr = str_pad($h, 2, '0', STR_PAD_LEFT);
            $data[] = [
                'hour' => $hourStr . 'h',
                'label' => $hourStr . ':00',
                'visits' => $hourlyVisits[$h] ?? 0
            ];
        }

        return response()->json($data);
    }

    /**
     * Get weekday average
     * GET /api/stats/weekday-average?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function getWeekdayAverage(Request $request)
    {
        $from = $request->input('from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $to = $request->input('to', Carbon::now()->format('Y-m-d'));

        // DAYOFWEEK: 1=Sunday, 2=Monday, ..., 7=Saturday
        $weekdayVisits = Visit::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('DAYOFWEEK(created_at) as dayofweek, COUNT(*) as total_visits')
            ->groupBy('dayofweek')
            ->get()
            ->pluck('total_visits', 'dayofweek')
            ->toArray();

        // Calcular número de ocurrencias de cada día en el rango
        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);
        $dayCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0];
        
        $current = $fromDate->copy();
        while ($current->lte($toDate)) {
            $dayOfWeek = $current->dayOfWeek + 1; // Carbon: 0=Sunday, PHP DAYOFWEEK: 1=Sunday
            if ($dayOfWeek == 7) $dayOfWeek = 1;
            else $dayOfWeek++;
            
            $dayCounts[$dayOfWeek]++;
            $current->addDay();
        }

        // Mapear a formato esperado (Lunes a Domingo)
        $dayMap = [
            2 => ['L', 'Lunes'],      // Monday
            3 => ['M', 'Martes'],     // Tuesday
            4 => ['X', 'Miércoles'],  // Wednesday
            5 => ['J', 'Jueves'],     // Thursday
            6 => ['V', 'Viernes'],    // Friday
            7 => ['S', 'Sábado'],     // Saturday
            1 => ['D', 'Domingo']     // Sunday
        ];

        $data = [];
        foreach ([2, 3, 4, 5, 6, 7, 1] as $dayNum) {
            $totalVisits = $weekdayVisits[$dayNum] ?? 0;
            $occurrences = max(1, $dayCounts[$dayNum]);
            $average = round($totalVisits / $occurrences);

            $data[] = [
                'day' => $dayMap[$dayNum][0],
                'label' => $dayMap[$dayNum][1],
                'average' => $average
            ];
        }

        return response()->json($data);
    }

    /**
     * Get weekly comparison (current week vs previous week)
     * GET /api/stats/weekly-compare?week=YYYY-MM-DD
     */
    public function getWeeklyCompare(Request $request)
    {
        $weekDate = $request->input('week', Carbon::now()->format('Y-m-d'));
        $currentWeekStart = Carbon::parse($weekDate)->startOfWeek();
        $currentWeekEnd = Carbon::parse($weekDate)->endOfWeek();
        
        $previousWeekStart = $currentWeekStart->copy()->subWeek();
        $previousWeekEnd = $currentWeekEnd->copy()->subWeek();

        // Visitas semana actual por día
        $currentWeekVisits = Visit::whereBetween('created_at', [
            $currentWeekStart->format('Y-m-d H:i:s'),
            $currentWeekEnd->format('Y-m-d H:i:s')
        ])
        ->selectRaw('DAYOFWEEK(created_at) as dayofweek, COUNT(*) as visits')
        ->groupBy('dayofweek')
        ->get()
        ->pluck('visits', 'dayofweek')
        ->toArray();

        // Visitas semana anterior por día
        $previousWeekVisits = Visit::whereBetween('created_at', [
            $previousWeekStart->format('Y-m-d H:i:s'),
            $previousWeekEnd->format('Y-m-d H:i:s')
        ])
        ->selectRaw('DAYOFWEEK(created_at) as dayofweek, COUNT(*) as visits')
        ->groupBy('dayofweek')
        ->get()
        ->pluck('visits', 'dayofweek')
        ->toArray();

        // Mapear a formato esperado
        $dayMap = [
            2 => ['L', 'Lunes'],
            3 => ['M', 'Martes'],
            4 => ['X', 'Miércoles'],
            5 => ['J', 'Jueves'],
            6 => ['V', 'Viernes'],
            7 => ['S', 'Sábado'],
            1 => ['D', 'Domingo']
        ];

        $data = [];
        foreach ([2, 3, 4, 5, 6, 7, 1] as $dayNum) {
            $current = $currentWeekVisits[$dayNum] ?? 0;
            $previous = $previousWeekVisits[$dayNum] ?? 0;
            
            $change = 0;
            if ($previous > 0) {
                $change = round((($current - $previous) / $previous) * 100, 1);
            } elseif ($current > 0) {
                $change = 100;
            }

            $data[] = [
                'day' => $dayMap[$dayNum][0],
                'label' => $dayMap[$dayNum][1],
                'current' => $current,
                'previous' => $previous,
                'change' => $change
            ];
        }

        return response()->json($data);
    }
}
