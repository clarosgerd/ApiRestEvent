<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OpsJobController extends Controller
{
    public function index(): View
    {
        $pending = DB::table('jobs')->orderByDesc('id')->limit(50)->get();
        $failed = DB::table('failed_jobs')->orderByDesc('id')->limit(50)->get();

        return view('ops.jobs', compact('pending', 'failed'));
    }

    public function retry(string $uuid): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return back()->with('status', 'Job reencolado.');
    }

    public function destroy(int $id): RedirectResponse
    {
        DB::table('jobs')->where('id', $id)->delete();

        return back()->with('status', 'Job pendiente eliminado.');
    }

    public function forget(string $uuid): RedirectResponse
    {
        Artisan::call('queue:forget', ['id' => $uuid]);

        return back()->with('status', 'Job fallido eliminado.');
    }
}
