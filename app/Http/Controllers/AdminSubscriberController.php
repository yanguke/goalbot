<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Services\WhatsApp\MessageSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminSubscriberController extends Controller
{
    private function auth(Request $request): void
    {
        // Access is enforced by the 'admin' middleware (session login OR ?key=).
        // This inline guard remains as defense-in-depth.
        if (\Illuminate\Support\Facades\Auth::check()) {
            return;
        }

        $expected = config('app.admin_key', env('ADMIN_KEY'));
        if (!$expected || $request->query('key') !== $expected) {
            abort(403, 'Forbidden');
        }
    }

    public function index(Request $request)
    {
        $this->auth($request);

        $query = Subscriber::query()->orderByDesc('created_at');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                  ->orWhere('favorite_team', 'like', "%{$search}%");
            });
        }

        if ($filter = $request->query('filter')) {
            if ($filter === 'paid') {
                $query->whereIn('subscription_type', ['full_tournament', 'per_match']);
            } elseif ($filter === 'free') {
                $query->where(function ($q) { $q->where('subscription_type', 'free')->orWhereNull('subscription_type'); });
            } elseif ($filter === 'active') {
                $query->where('is_active', true)->where('notifications_enabled', true);
            } elseif ($filter === 'inactive') {
                $query->where(function ($q) { $q->where('is_active', false)->orWhere('notifications_enabled', false); });
            } elseif ($filter === 'mbm') {
                $query->where('commentary_mode', 'minute_by_minute');
            }
        }

        $subscribers = $query->paginate(25)->withQueryString();

        // Summary stats
        $stats = [
            'total'       => Subscriber::count(),
            'paid'        => Subscriber::whereIn('subscription_type', ['full_tournament', 'per_match'])->count(),
            'free'        => Subscriber::where('subscription_type', 'free')->orWhereNull('subscription_type')->count(),
            'active'      => Subscriber::where('is_active', true)->where('notifications_enabled', true)->count(),
            'mbm'         => Subscriber::where('commentary_mode', 'minute_by_minute')->count(),
            'notify_all'  => Subscriber::where('notify_all_matches', true)->count(),
            'new_today'   => Subscriber::whereDate('created_at', today())->count(),
            'new_week'    => Subscriber::where('created_at', '>=', now()->subWeek())->count(),
        ];

        // Mode breakdown
        $modes = Subscriber::selectRaw('COALESCE(commentary_mode, "digest") as mode, COUNT(*) as n')
            ->groupBy('mode')->get()->pluck('n', 'mode');

        // Sub type breakdown
        $types = Subscriber::selectRaw('COALESCE(subscription_type, "free") as type, COUNT(*) as n')
            ->groupBy('type')->get()->pluck('n', 'type');

        return view('admin.subscribers', compact('subscribers', 'stats', 'modes', 'types', 'request'));
    }

    public function update(Request $request, int $id)
    {
        $this->auth($request);

        $subscriber = Subscriber::findOrFail($id);

        $subscriber->update([
            'subscription_type'    => $request->input('subscription_type', $subscriber->subscription_type),
            'notifications_enabled'=> (bool) $request->input('notifications_enabled', $subscriber->notifications_enabled),
            'notify_all_matches'   => (bool) $request->input('notify_all_matches', $subscriber->notify_all_matches),
            'commentary_mode'      => $request->input('commentary_mode', $subscriber->commentary_mode),
            'is_active'            => (bool) $request->input('is_active', $subscriber->is_active),
            'favorite_team'        => $request->input('favorite_team', $subscriber->favorite_team),
        ]);

        if ($request->input('subscription_type') === 'full_tournament' && !$subscriber->paid_at) {
            $subscriber->update(['paid_at' => now()]);
        }

        return redirect()->back()->with('success', "Subscriber #{$id} updated.");
    }

    public function broadcast(Request $request)
    {
        $this->auth($request);

        $request->validate([
            'message'   => 'required|string|max:1000',
            'audience'  => 'required|in:all,paid,free,active',
        ]);

        $message  = $request->input('message');
        $audience = $request->input('audience');

        $query = Subscriber::where('is_active', true)->where('notifications_enabled', true);

        if ($audience === 'paid') {
            $query->whereIn('subscription_type', ['full_tournament', 'per_match']);
        } elseif ($audience === 'free') {
            $query->where(function ($q) { $q->where('subscription_type', 'free')->orWhereNull('subscription_type'); });
        }

        $subscribers = $query->get();
        $sender      = app(MessageSender::class);
        $sent        = 0;

        foreach ($subscribers as $sub) {
            try {
                $sender->sendText($sub->phone_number, $message);
                usleep(200000);
                $sent++;
            } catch (\Exception $e) {
                Log::error('Broadcast failed', ['phone' => $sub->phone_number, 'error' => $e->getMessage()]);
            }
        }

        return redirect()->back()->with('success', "Broadcast sent to {$sent} subscribers.");
    }
}
