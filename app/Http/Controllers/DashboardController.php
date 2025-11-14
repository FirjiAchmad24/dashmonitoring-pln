<?php

namespace App\Http\Controllers;

use App\Models\BfkoData;
use App\Models\ServiceFee;
use App\Models\CCTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Get BFKO summary
        $bfkoTotal = BfkoData::sum('nilai_angsuran');
        $bfkoCount = BfkoData::count();
        $bfkoEmployees = BfkoData::select('nip')->distinct()->count();
        
        // Get Service Fee summary (correct field names)
        $serviceFeeTotal = ServiceFee::sum('transaction_amount'); // Changed to transaction_amount (full amount, not just fee)
        $serviceFeeCount = ServiceFee::count();
        $serviceFeeHotel = ServiceFee::where('service_type', 'hotel')->count(); // Changed from 'jenis_penginapan'
        $serviceFeeFlight = ServiceFee::where('service_type', 'flight')->count(); // Changed from 'jenis_penginapan'
        
        // Get CC Card summary (correct field names)
        $ccTotal = CCTransaction::sum('payment_amount'); // Changed from 'nilai' to 'payment_amount'
        $ccCount = CCTransaction::count();
        $ccEmployees = CCTransaction::select('personel_number')->distinct()->count(); // Changed from 'nip' to 'personel_number'
        
        // Get monthly data for all categories (all 12 months)
        $monthOrder = [
            'Januari' => 1, 'Februari' => 2, 'Maret' => 3, 'April' => 4,
            'Mei' => 5, 'Juni' => 6, 'Juli' => 7, 'Agustus' => 8,
            'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12
        ];
        
        $monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        
        // Build monthly data combining all 3 categories for all 12 months
        $monthlyData = collect($monthNames)->map(function($bulan) use ($monthOrder) {
            // BFKO data for this month
            $bfkoTotal = BfkoData::where('bulan', $bulan)->sum('nilai_angsuran');
            
            // Get month number for matching with date fields
            $monthNum = $monthOrder[$bulan] ?? 0;
            
            // CC Card - format M/D/YYYY tidak bisa diparse SQLite, harus manual
            $ccTotal = CCTransaction::whereNotNull('departure_date')
                ->get()
                ->filter(function($item) use ($monthNum) {
                    try {
                        // Parse format M/D/YYYY atau n/j/Y
                        $date = \DateTime::createFromFormat('n/j/Y', $item->departure_date);
                        if ($date && (int)$date->format('n') === $monthNum) {
                            return true;
                        }
                    } catch (\Exception $e) {
                    }
                    return false;
                })
                ->sum('payment_amount');
            
            // Service Fee - extract from transaction_time (any year)
            $sfTotal = ServiceFee::whereRaw("CAST(strftime('%m', transaction_time) AS INTEGER) = ?", [$monthNum])
                ->sum('transaction_amount');
            
            return [
                'month' => substr($bulan, 0, 3),
                'bfko' => (float)$bfkoTotal,
                'ccCard' => (float)$ccTotal,
                'serviceFee' => (float)$sfTotal,
            ];
        })->values();
        
        // Get recent transactions (prioritize manual add/edit with timestamps)
        $recentTransactions = collect();
        
        // BFKO recent - prioritize updated_at (for edits), then created_at (for new), fallback to tahun/bulan
        $bfkoRecent = BfkoData::query()
            ->orderByRaw("COALESCE(updated_at, created_at, datetime('now')) DESC")
            ->orderBy('tahun', 'desc')
            ->limit(3)
            ->get()
            ->map(function($item) {
                $timestamp = $item->updated_at ?? $item->created_at;
                return [
                    'person' => $item->nama,
                    'date' => $item->tanggal_bayar ?? ($timestamp ? $timestamp->format('d-m-Y') : "{$item->bulan} {$item->tahun}"),
                    'category' => 'BFKO',
                    'description' => "Angsuran BFKO - {$item->bulan} {$item->tahun}",
                    'total' => 'Rp ' . number_format((float)$item->nilai_angsuran, 0, ',', '.'),
                    'status' => $item->status_angsuran ?? 'Complete',
                    'sort_date' => $timestamp ?? now()->subYears(100),
                    'is_new' => $timestamp && $timestamp->isToday()
                ];
            });
        
        // Service Fee recent - prioritize timestamps over transaction_time
        $serviceFeeRecent = ServiceFee::query()
            ->orderByRaw("COALESCE(updated_at, created_at, transaction_time, datetime('now')) DESC")
            ->limit(2)
            ->get()
            ->map(function($item) {
                $timestamp = $item->updated_at ?? $item->created_at;
                $type = ucfirst($item->service_type ?? 'Service');
                $location = $item->hotel_name ?? $item->route ?? 'Unknown';
                return [
                    'person' => $item->employee_name ?? 'Unknown',
                    'date' => $timestamp ? $timestamp->format('d-m-Y') : ($item->transaction_time ? date('d-m-Y', strtotime($item->transaction_time)) : '-'),
                    'category' => 'Service Fee',
                    'description' => "{$type} - {$location}",
                    'total' => 'Rp ' . number_format($item->transaction_amount ?? 0, 0, ',', '.'),
                    'status' => ucfirst($item->status ?? 'Complete'),
                    'sort_date' => $timestamp ?? ($item->transaction_time ? strtotime($item->transaction_time) : now()->subYears(100)),
                    'is_new' => $timestamp && $timestamp->isToday()
                ];
            });
        
        // CC Card recent - prioritize timestamps over departure_date
        $ccRecent = CCTransaction::query()
            ->orderByRaw("COALESCE(updated_at, created_at, departure_date, datetime('now')) DESC")
            ->limit(2)
            ->get()
            ->map(function($item) {
                $timestamp = $item->updated_at ?? $item->created_at;
                return [
                    'person' => $item->employee_name ?? 'Unknown',
                    'date' => $timestamp ? $timestamp->format('d-m-Y') : ($item->departure_date ?? '-'),
                    'category' => 'CC Card',
                    'description' => "{$item->transaction_type} - {$item->trip_destination_full}",
                    'total' => 'Rp ' . number_format($item->payment_amount ?? 0, 0, ',', '.'),
                    'status' => ucfirst($item->status ?? 'Complete'),
                    'sort_date' => $timestamp ?? ($item->departure_date ? strtotime($item->departure_date) : now()->subYears(100)),
                    'is_new' => $timestamp && $timestamp->isToday()
                ];
            });
        
        $recentTransactions = $recentTransactions
            ->concat($bfkoRecent)
            ->concat($serviceFeeRecent)
            ->concat($ccRecent)
            ->sortByDesc(function($item) {
                // Handle both Carbon and unix timestamp
                return $item['sort_date'] instanceof \Carbon\Carbon ? $item['sort_date']->timestamp : (is_numeric($item['sort_date']) ? $item['sort_date'] : 0);
            })
            ->take(8)
            ->values();
        
        return Inertia::render('Dashboard', [
            'summary' => [
                'bfko' => [
                    'total' => $bfkoTotal,
                    'count' => $bfkoCount,
                    'employees' => $bfkoEmployees
                ],
                'serviceFee' => [
                    'total' => $serviceFeeTotal,
                    'count' => $serviceFeeCount,
                    'hotel' => $serviceFeeHotel,
                    'flight' => $serviceFeeFlight
                ],
                'ccCard' => [
                    'total' => $ccTotal,
                    'count' => $ccCount,
                    'employees' => $ccEmployees
                ]
            ],
            'monthlyData' => $monthlyData,
            'recentTransactions' => $recentTransactions
        ]);
    }
}
