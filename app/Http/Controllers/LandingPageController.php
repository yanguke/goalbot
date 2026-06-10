<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    /**
     * Show landing page with A/B testing support
     * 
     * Versions:
     * - v1: Dark gradient (default) - welcome.blade.php
     * - v2: Light/clean - welcome-v2-light.blade.php
     * - v3: Bold/energetic - welcome-v3-bold.blade.php
     * - v4: Minimal/premium - welcome-v4-minimal.blade.php
     */
    public function index(Request $request)
    {
        // Allow ?v=2, ?v=3, ?v=4 to test different versions
        $version = $request->query('v', '1');
        
        $view = match($version) {
            '2' => 'welcome-v2-light',
            '3' => 'welcome-v3-bold',
            '4' => 'welcome-v4-minimal',
            default => 'welcome',
        };
        
        // Log version view for analytics
        \Illuminate\Support\Facades\Log::info('Landing page view', [
            'version' => $version,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        return view($view);
    }
}
