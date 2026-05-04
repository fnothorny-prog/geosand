<?php

namespace App\Http\Controllers;

use App\Models\Extraction;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    use ApiResponse;

    /**
     * Get extraction volume over time.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function extractionVolume(Request $request)
    {
        $query = DB::table('extraction_records')
            ->select(
                DB::raw('DATE(extraction_timestamp) as date'),
                DB::raw('SUM(reported_quantity) as total_volume'),
                DB::raw('COUNT(*) as extraction_count')
            )
            ->where('status', 'verified');

        if ($request->has('start_date')) {
            $query->whereDate('extraction_timestamp', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('extraction_timestamp', '<=', $request->end_date);
        }

        $data = $query->groupBy('date')->orderBy('date', 'asc')->get();

        return $this->successResponse(['extraction_volume' => $data]);
    }

    /**
     * Get verification rates by checkpoint user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verificationRates(Request $request)
    {
        $data = DB::table('verifications')
            ->join('users', 'verifications.checkpoint_user_id', '=', 'users.id')
            ->select(
                'verifications.checkpoint_user_id',
                'users.name as verifier_name',
                'users.email as verifier_email',
                DB::raw('COUNT(*) as total_verifications')
            )
            ->groupBy('verifications.checkpoint_user_id', 'users.name', 'users.email')
            ->get();

        return $this->successResponse(['verification_rates' => $data]);
    }

    /**
     * Get extraction activity by quarry.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function extractionByQuarry(Request $request)
    {
        $data = DB::table('extraction_records')
            ->join('quarries', 'extraction_records.quarry_id', '=', 'quarries.id')
            ->select(
                'extraction_records.quarry_id',
                'quarries.name as quarry_name',
                'quarries.latitude',
                'quarries.longitude',
                DB::raw('COUNT(*) as extraction_count'),
                DB::raw('SUM(reported_quantity) as total_volume'),
                DB::raw('SUM(CASE WHEN extraction_records.status = "verified" THEN 1 ELSE 0 END) as verified_count'),
                DB::raw('SUM(CASE WHEN extraction_records.status = "pending" THEN 1 ELSE 0 END) as pending_count'),
                DB::raw('SUM(CASE WHEN extraction_records.status = "discrepancy" THEN 1 ELSE 0 END) as discrepancy_count')
            )
            ->groupBy('extraction_records.quarry_id', 'quarries.name', 'quarries.latitude', 'quarries.longitude')
            ->get();

        return $this->successResponse(['extraction_by_quarry' => $data]);
    }

    /**
     * Export analytics data.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function export(Request $request)
    {
        $request->validate([
            'type' => 'required|in:extraction_volume,verification_rates,extraction_by_quarry',
            'format' => 'required|in:csv,pdf',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Get the data based on type
        $data = [];
        $headers = [];
        $filename = '';

        switch ($request->type) {
            case 'extraction_volume':
                $data = $this->getExtractionVolumeData($request);
                $headers = ['Date', 'Total Volume', 'Extraction Count'];
                $filename = 'extraction_volume_' . now()->format('Y-m-d');
                break;

            case 'verification_rates':
                $data = $this->getVerificationRatesData($request);
                $headers = ['Verifier Name', 'Total Verifications', 'Verified Count', 'Rejected Count', 'Verification Rate (%)'];
                $filename = 'verification_rates_' . now()->format('Y-m-d');
                break;

            case 'extraction_by_quarry':
                $data = $this->getExtractionByQuarryData($request);
                $headers = ['Quarry Name', 'Extraction Count', 'Total Volume', 'Verified', 'Pending', 'Rejected'];
                $filename = 'extraction_by_quarry_' . now()->format('Y-m-d');
                break;
        }

        if ($request->format === 'csv') {
            return $this->exportCsv($data, $headers, $filename);
        } else {
            // For PDF, we'll return a simple text-based format
            // In a real application, you would use a library like dompdf or snappy
            return $this->exportPdf($data, $headers, $filename);
        }
    }

    /**
     * Get extraction volume data for export.
     *
     * @param Request $request
     * @return array
     */
    private function getExtractionVolumeData(Request $request)
    {
        $query = Extraction::select(
            DB::raw('DATE(extraction_date) as date'),
            DB::raw('SUM(quantity) as total_volume'),
            DB::raw('COUNT(*) as extraction_count')
        )
        ->where('status', 'verified');

        if ($request->has('start_date')) {
            $query->where('extraction_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('extraction_date', '<=', $request->end_date);
        }

        return $query->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    $item->date,
                    $item->total_volume,
                    $item->extraction_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get verification rates data for export.
     *
     * @param Request $request
     * @return array
     */
    private function getVerificationRatesData(Request $request)
    {
        $query = Extraction::select(
            'verified_by',
            DB::raw('COUNT(*) as total_verifications'),
            DB::raw('SUM(CASE WHEN status = "verified" THEN 1 ELSE 0 END) as verified_count'),
            DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected_count')
        )
        ->whereNotNull('verified_by');

        if ($request->has('start_date')) {
            $query->where('verified_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('verified_at', '<=', $request->end_date);
        }

        return $query->groupBy('verified_by')
            ->with('verifier:id,username')
            ->get()
            ->map(function ($item) {
                $rate = $item->total_verifications > 0 
                    ? round(($item->verified_count / $item->total_verifications) * 100, 2) 
                    : 0;
                
                return [
                    $item->verifier ? $item->verifier->username : 'Unknown',
                    $item->total_verifications,
                    $item->verified_count,
                    $item->rejected_count,
                    $rate,
                ];
            })
            ->toArray();
    }

    /**
     * Get extraction by quarry data for export.
     *
     * @param Request $request
     * @return array
     */
    private function getExtractionByQuarryData(Request $request)
    {
        $query = Extraction::select(
            'quarry_id',
            DB::raw('COUNT(*) as extraction_count'),
            DB::raw('SUM(quantity) as total_volume'),
            DB::raw('SUM(CASE WHEN status = "verified" THEN 1 ELSE 0 END) as verified_count'),
            DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count'),
            DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected_count')
        );

        if ($request->has('start_date')) {
            $query->where('extraction_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('extraction_date', '<=', $request->end_date);
        }

        return $query->groupBy('quarry_id')
            ->with('quarry:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    $item->quarry ? $item->quarry->name : 'Unknown',
                    $item->extraction_count,
                    $item->total_volume,
                    $item->verified_count,
                    $item->pending_count,
                    $item->rejected_count,
                ];
            })
            ->toArray();
    }

    /**
     * Export data as CSV.
     *
     * @param array $data
     * @param array $headers
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    private function exportCsv(array $data, array $headers, string $filename)
    {
        $csv = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($csv, $headers);
        
        // Write data
        foreach ($data as $row) {
            fputcsv($csv, $row);
        }
        
        rewind($csv);
        $output = stream_get_contents($csv);
        fclose($csv);

        return response($output, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '.csv"');
    }

    /**
     * Export data as PDF (simplified version).
     *
     * @param array $data
     * @param array $headers
     * @param string $filename
     * @return \Illuminate\Http\JsonResponse
     */
    private function exportPdf(array $data, array $headers, string $filename)
    {
        // For now, return a JSON response indicating PDF export would be implemented
        // In a real application, you would use a library like dompdf or snappy
        return $this->successResponse([
            'message' => 'PDF export functionality would be implemented here',
            'data' => $data,
            'headers' => $headers,
            'filename' => $filename . '.pdf',
        ], 'PDF export not yet implemented');
    }
}
