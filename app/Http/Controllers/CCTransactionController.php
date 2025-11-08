<?php

namespace App\Http\Controllers;

use App\Models\CCTransaction;
use App\Models\SheetAdditionalFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CCTransactionController extends Controller
{
    /**
     * Get autocomplete suggestions for employee names
     */
    public function autocomplete(Request $request)
    {
        $search = $request->get('q', '');
        
        $employees = CCTransaction::select('employee_name', 'personel_number')
            ->where('employee_name', 'like', '%' . $search . '%')
            ->distinct()
            ->limit(10)
            ->get()
            ->map(function($item) {
                return [
                    'label' => $item->employee_name . ' (' . $item->personel_number . ')',
                    'value' => $item->employee_name,
                    'personel_number' => $item->personel_number
                ];
            });
        
        return response()->json($employees);
    }
    
    /**
     * Store manual transaction
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_name' => 'required|string|max:255',
            'personel_number' => 'required|string|max:50',
            'trip_number' => 'required|string|max:50',
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'departure_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'payment_amount' => 'required|numeric|min:0',
            'transaction_type' => 'required|in:payment,refund',
            'custom_month' => 'required|string',
            'custom_year' => 'required|string',
            'cc_number' => 'required|string|in:5657,9386',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = $validator->validated();
        
        // Build sheet name from month, year, and cc_number (cc_number is required now)
        $sheetName = $data['custom_month'] . ' ' . $data['custom_year'] . ' - CC ' . $data['cc_number'];
        $data['sheet'] = $sheetName;
        
        // Auto-generate booking ID
        $bookingId = time() . rand(1000, 9999);
        if ($data['transaction_type'] === 'refund') {
            $bookingId .= '-REFUND';
        }
        
        // Calculate duration
        $departureDate = new \DateTime($data['departure_date']);
        $returnDate = new \DateTime($data['return_date']);
        $duration = $returnDate->diff($departureDate)->days;
        
        // Full destination
        $tripDestination = $data['origin'] . ' - ' . $data['destination'];
        
        // Get next transaction number (handle NULL case)
        $maxTransactionNumber = CCTransaction::max('transaction_number');
        $nextTransactionNumber = $maxTransactionNumber ? $maxTransactionNumber + 1 : 1;
        
        $transaction = CCTransaction::create([
            'transaction_number' => $nextTransactionNumber,
            'booking_id' => $bookingId,
            'employee_name' => $data['employee_name'],
            'personel_number' => $data['personel_number'],
            'trip_number' => $data['trip_number'],
            'origin' => $data['origin'],
            'destination' => $data['destination'],
            'trip_destination_full' => $tripDestination,
            'departure_date' => $data['departure_date'],
            'return_date' => $data['return_date'],
            'duration_days' => $duration,
            'payment_amount' => $data['payment_amount'],
            'transaction_type' => $data['transaction_type'],
            'sheet' => $data['sheet'],
            'status' => 'Complete',
        ]);
        
        // Auto-create sheet in sheet_additional_fees if not exists
        $this->ensureSheetExists($data['sheet']);
        
        return response()->json([
            'message' => 'Transaction created successfully!',
            'transaction' => $transaction,
            'sheet' => $data['sheet']
        ]);
    }
    
    /**
     * Import transactions from CSV
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'update_existing' => 'boolean',
            'override_sheet_name' => 'nullable|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }
        
        $file = $request->file('csv_file');
        $updateExisting = $request->boolean('update_existing', false);
        $overrideSheetName = $request->input('override_sheet_name');
        
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle); // Skip header
        
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        
        DB::beginTransaction();
        
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 14) {
                    $skipped++;
                    continue;
                }
                
                $bookingId = trim($row[1]);
                $transactionType = strtolower(trim($row[12]));
                $paymentAmount = (float) trim($row[11]);
                
                // For refunds, add suffix with payment amount to make it unique
                if ($transactionType === 'refund' && !str_contains($bookingId, '-REFUND')) {
                    // Check if this booking already has refund(s)
                    $existingRefundCount = CCTransaction::where('booking_id', 'LIKE', $bookingId . '-REFUND%')->count();
                    
                    if ($existingRefundCount > 0) {
                        // Multiple refunds for same booking - add sequence number
                        $bookingId .= '-REFUND-' . ($existingRefundCount + 1);
                    } else {
                        $bookingId .= '-REFUND';
                    }
                }
                
                $existing = CCTransaction::where('booking_id', $bookingId)->first();
                
                if ($existing && !$updateExisting) {
                    $skipped++;
                    continue;
                }
                
                // Use override sheet name if provided, otherwise use from CSV
                $sheetName = !empty($overrideSheetName) ? $overrideSheetName : trim($row[13]);
                
                $data = [
                    'transaction_number' => (int) trim($row[0]),
                    'booking_id' => $bookingId,
                    'employee_name' => trim($row[2]),
                    'personel_number' => trim($row[3]),
                    'trip_number' => trim($row[4]),
                    'origin' => trim($row[5]),
                    'destination' => trim($row[6]),
                    'trip_destination_full' => trim($row[7]),
                    'departure_date' => trim($row[8]),
                    'return_date' => trim($row[9]),
                    'duration_days' => (int) trim($row[10]),
                    'payment_amount' => (float) trim($row[11]),
                    'transaction_type' => $transactionType,
                    'sheet' => $sheetName,
                    'status' => 'active',
                ];
                
                if ($existing && $updateExisting) {
                    $existing->update($data);
                    $updated++;
                } else {
                    CCTransaction::create($data);
                    $imported++;
                }
            }
            
            DB::commit();
            fclose($handle);
            
            // Auto-create sheets in sheet_additional_fees
            $uniqueSheets = CCTransaction::select('sheet')->distinct()->pluck('sheet');
            foreach ($uniqueSheets as $sheetName) {
                $this->ensureSheetExists($sheetName);
            }
            
            $message = "Import completed! Imported: $imported, Updated: $updated, Skipped: $skipped";
            
            return redirect('/cc-card')->with('success', $message);
            
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            
            return back()->withErrors(['error' => 'Import failed: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Get all sheet additional fees
     */
    public function getFees()
    {
        $fees = SheetAdditionalFee::all();
        return response()->json($fees);
    }
    
    /**
     * Update sheet additional fees
     */
    public function updateFees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fees' => 'required|array',
            'fees.*.sheet_name' => 'required|string',
            'fees.*.biaya_adm_bunga' => 'nullable|numeric|min:0',
            'fees.*.biaya_transfer' => 'nullable|numeric|min:0',
            'fees.*.iuran_tahunan' => 'nullable|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        DB::beginTransaction();
        
        try {
            foreach ($request->fees as $feeData) {
                SheetAdditionalFee::updateOrCreate(
                    ['sheet_name' => $feeData['sheet_name']],
                    [
                        'biaya_adm_bunga' => $feeData['biaya_adm_bunga'] ?? 0,
                        'biaya_transfer' => $feeData['biaya_transfer'] ?? 0,
                        'iuran_tahunan' => $feeData['iuran_tahunan'] ?? 0,
                    ]
                );
            }
            
            DB::commit();
            
            return response()->json(['message' => 'Fees updated successfully!']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Delete sheet additional fees (reset to 0)
     */
    public function deleteFees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sheet_name' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        try {
            $fee = SheetAdditionalFee::where('sheet_name', $request->sheet_name)->first();
            
            if ($fee) {
                $fee->delete();
                return response()->json(['message' => 'Additional fees deleted successfully!']);
            }
            
            return response()->json(['message' => 'No fees found for this sheet.'], 404);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Delete failed: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Get single transaction detail
     */
    public function show($id)
    {
        $transaction = CCTransaction::findOrFail($id);
        return response()->json($transaction);
    }
    
    /**
     * Update transaction
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'employee_name' => 'required|string|max:255',
            'personel_number' => 'required|string|max:50',
            'trip_number' => 'required|string|max:50',
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'departure_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'payment_amount' => 'required|numeric|min:0',
            'transaction_type' => 'required|in:payment,refund',
            'custom_month' => 'required',
            'custom_year' => 'required',
            'cc_number' => 'nullable|string|max:50',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $transaction = CCTransaction::findOrFail($id);
        $data = $validator->validated();
        
        // Build sheet name
        $sheetName = $data['custom_month'] . ' ' . $data['custom_year'];
        if (!empty($data['cc_number'])) {
            $sheetName .= ' - CC ' . $data['cc_number'];
        }
        
        // Calculate duration
        $departureDate = new \DateTime($data['departure_date']);
        $returnDate = new \DateTime($data['return_date']);
        $duration = $returnDate->diff($departureDate)->days;
        
        // Full destination
        $tripDestination = $data['origin'] . ' - ' . $data['destination'];
        
        // Update booking ID if transaction type changed
        $bookingId = $transaction->booking_id;
        if ($data['transaction_type'] === 'refund' && !str_contains($bookingId, '-REFUND')) {
            $bookingId .= '-REFUND';
        } else if ($data['transaction_type'] === 'payment' && str_contains($bookingId, '-REFUND')) {
            $bookingId = str_replace('-REFUND', '', $bookingId);
        }
        
        $transaction->update([
            'booking_id' => $bookingId,
            'employee_name' => $data['employee_name'],
            'personel_number' => $data['personel_number'],
            'trip_number' => $data['trip_number'],
            'origin' => $data['origin'],
            'destination' => $data['destination'],
            'trip_destination_full' => $tripDestination,
            'departure_date' => $data['departure_date'],
            'return_date' => $data['return_date'],
            'duration_days' => $duration,
            'payment_amount' => $data['payment_amount'],
            'transaction_type' => $data['transaction_type'],
            'sheet' => $sheetName,
        ]);
        
        return response()->json([
            'message' => 'Transaction updated successfully!',
            'transaction' => $transaction
        ]);
    }
    
    /**
     * Delete single transaction
     */
    public function destroy($id)
    {
        $transaction = CCTransaction::findOrFail($id);
        $transaction->delete();
        
        return redirect()->back()->with('success', 'Transaction deleted successfully!');
    }
    
    /**
     * Delete entire sheet (all transactions in a month/sheet)
     */
    public function destroySheet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sheet_name' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $sheetName = $request->input('sheet_name');
        
        DB::beginTransaction();
        
        try {
            // Delete all transactions for this sheet
            $deletedCount = CCTransaction::where('sheet', $sheetName)->delete();
            
            // Optionally delete the sheet's additional fees
            SheetAdditionalFee::where('sheet_name', $sheetName)->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => "Sheet '{$sheetName}' deleted successfully!",
                'deleted_transactions' => $deletedCount
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Delete failed: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Ensure sheet exists in sheet_additional_fees table
     * Create with default values (0) if not exists
     */
    private function ensureSheetExists($sheetName)
    {
        SheetAdditionalFee::firstOrCreate(
            ['sheet_name' => $sheetName],
            [
                'biaya_adm_bunga' => 0,
                'biaya_transfer' => 0,
                'iuran_tahunan' => 0,
            ]
        );
    }
}
