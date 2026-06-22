<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LeadCaptureController extends Controller
{
    public function store(Request $request)
    {
        // Validate the phone number
        $validator = Validator::make($request->all(), [
            'phone_number' => ['required', 'string', 'regex:/^[0-9]{10,15}$/'],
        ], [
            'phone_number.required' => 'Please enter your phone number',
            'phone_number.regex' => 'Please enter a valid phone number',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        // Clean and format phone number
        $phone = preg_replace('/[^0-9]/', '', $request->phone_number);
        
        // Add country code if missing (assume Kenya if 10 digits)
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (strlen($phone) === 9 && !str_starts_with($phone, '254')) {
            $phone = '254' . $phone;
        }

        // Detect country (simplified - can be enhanced)
        $country = str_starts_with($phone, '254') ? 'KE' : 'XX';

        // Create or update the lead
        $lead = Lead::updateOrCreate(
            ['phone_number' => $phone],
            [
                'country' => $country,
                'utm_source' => $request->utm_source,
                'utm_medium' => $request->utm_medium,
                'utm_campaign' => $request->utm_campaign,
                'utm_term' => $request->utm_term,
                'utm_content' => $request->utm_content,
                'fbclid' => $request->fbclid,
                'referrer' => $request->headers->get('referer'),
                'user_agent' => substr($request->userAgent(), 0, 500),
                'ip_address' => $request->ip(),
            ]
        );

        Log::info('Lead captured', [
            'lead_id' => $lead->id,
            'phone' => $phone,
            'country' => $country,
            'utm_source' => $request->utm_source,
        ]);

        // Build WhatsApp URL with UTMs
        $whatsappPhone = config('services.whatsapp.phone_number', '254715333355');
        $message = 'Goal';
        
        // Add lead ID for tracking
        $message .= " r={$lead->id}";
        
        $params = array_filter([
            'utm_source' => $request->utm_source,
            'utm_medium' => $request->utm_medium,
            'utm_campaign' => $request->utm_campaign,
            'utm_term' => $request->utm_term,
            'utm_content' => $request->utm_content,
        ]);

        $url = "https://wa.me/{$whatsappPhone}?text=" . urlencode($message);
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }

        return redirect()->away($url);
    }
}
