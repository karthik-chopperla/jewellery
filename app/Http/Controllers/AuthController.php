<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user instanceof User) {
                return redirect()->route($this->homeRoute($user));
            }
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->onlyInput('email');
        }

        $stageStartedAt = microtime(true);
        $authenticated = Auth::attempt($credentials, $request->boolean('remember'));
        $this->logSlowLoginStage('credential_check', $stageStartedAt);

        if ($authenticated) {
            $user = Auth::user();

            if (! $user instanceof User) {
                Auth::logout();
                abort(403);
            }

            if (! $user->is_active) {
                Auth::logout();
                $stageStartedAt = microtime(true);
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $this->logSlowLoginStage('inactive_session_cleanup', $stageStartedAt);

                $stageStartedAt = microtime(true);
                LoginHistory::create([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'status' => 'blocked_inactive',
                    'logged_in_at' => now(),
                ]);
                $this->logSlowLoginStage('inactive_login_history_write', $stageStartedAt);

                return back()->withErrors([
                    'email' => 'Your account has been deactivated. Please contact your administrator.',
                ])->onlyInput('email');
            }

            RateLimiter::clear($throttleKey);
            $stageStartedAt = microtime(true);
            $request->session()->regenerate();
            $this->logSlowLoginStage('session_regeneration', $stageStartedAt);

            $stageStartedAt = microtime(true);
            LoginHistory::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'success',
                'logged_in_at' => now(),
            ]);
            $this->logSlowLoginStage('successful_login_history_write', $stageStartedAt);

            $stageStartedAt = microtime(true);
            AuditService::log('Auth', 'login', "{$user->name} logged in");
            $this->logSlowLoginStage('login_audit_write', $stageStartedAt);

            return redirect()->intended(route($this->homeRoute($user)));
        }

        RateLimiter::hit($throttleKey, 60);

        $stageStartedAt = microtime(true);
        LoginHistory::create([
            'email' => $credentials['email'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'failed',
            'logged_in_at' => now(),
        ]);
        $this->logSlowLoginStage('failed_login_history_write', $stageStartedAt);

        return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            AuditService::log('Auth', 'logout', Auth::user()->name.' logged out');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }

    private function homeRoute(User $user): string
    {
        foreach ([
            'dashboard' => 'dashboard',
            'pos' => 'pos.index',
            'inventory' => 'inventory.index',
            'rates' => 'rates.index',
            'customers' => 'crm.index',
            'schemes' => 'schemes.index',
            'manufacturing' => 'workshop.index',
            'repairs' => 'workshop.index',
            'custom_orders' => 'workshop.index',
            'reports' => 'admin.reports',
            'purchases' => 'purchases.index',
            'accounts' => 'finance.index',
        ] as $permission => $route) {
            if ($user->hasPermission($permission)) {
                return $route;
            }
        }

        abort(403);
    }

    private function logSlowLoginStage(string $stage, float $startedAt): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        if ($durationMs >= 3000) {
            Log::warning('Slow login processing stage', [
                'stage' => $stage,
                'duration_ms' => round($durationMs),
            ]);
        }
    }
}
