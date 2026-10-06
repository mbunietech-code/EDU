<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\OptimizationRecommendation;
use App\Models\OptimizationScan;
use App\Services\DatabaseOptimizationService;
use App\Services\OptimizationExecutor;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Read-only database analysis + a reviewable, backup-before-execute cleanup
 * workflow. Nothing here ever touches data without an explicit approve step
 * (see OptimizationRecommendation::isExecutable()), and every destructive
 * action is preceded by a scoped backup of exactly the rows it will change.
 */
class OptimizationController extends Controller
{
    public function __construct(private DatabaseOptimizationService $optimizer)
    {
    }

    public function index()
    {
        $scan = OptimizationScan::with('recommendations')->latest('id')->first();
        $history = OptimizationScan::latest('id')->take(10)->get();

        return view('admin.optimization.index', [
            'scan' => $scan,
            'history' => $history,
            'supported' => $this->optimizer->isSupported(),
        ]);
    }

    public function scan()
    {
        $scan = $this->optimizer->scan(auth()->id());

        ActivityLog::log('optimization_scan_run', 'OptimizationScan', $scan->id, [
            'recommendations' => $scan->recommendations->count(),
        ]);

        return back()->with('success', 'Scan complete — '.$scan->recommendations->count().' recommendation(s) found.');
    }

    public function approve(OptimizationRecommendation $recommendation)
    {
        abort_unless($recommendation->status === 'pending', 400, 'Only pending recommendations can be approved.');

        $recommendation->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        ActivityLog::log('optimization_recommendation_approved', 'OptimizationRecommendation', $recommendation->id, [
            'table' => $recommendation->table_name,
            'category' => $recommendation->category,
        ]);

        return back()->with('success', 'Recommendation approved. You can now execute it.');
    }

    public function reject(Request $request, OptimizationRecommendation $recommendation)
    {
        abort_unless(in_array($recommendation->status, ['pending', 'approved'], true), 400, 'This recommendation cannot be rejected.');

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $recommendation->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        ActivityLog::log('optimization_recommendation_rejected', 'OptimizationRecommendation', $recommendation->id, [
            'table' => $recommendation->table_name,
        ]);

        return back()->with('success', 'Recommendation rejected.');
    }

    public function execute(OptimizationRecommendation $recommendation, OptimizationExecutor $executor)
    {
        try {
            $result = $executor->execute($recommendation, auth()->id());
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            return back()->with('error', 'Execution failed: '.$e->getMessage().'. No changes were made beyond the backup step, if reached.');
        }

        return back()->with('success', "Executed. {$result['count']} row(s) affected.".($result['backup_path'] ? ' Backup saved.' : ''));
    }
}
