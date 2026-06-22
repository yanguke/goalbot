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
        // Validate the form
        $validator = Validator::make($request->all(), [
            'country' => ['required', 'string', 'in:KE,UG,TZ,NG,ZA,GH,OTHER'],
            'phone_number' => ['required', 'string', 'regex:/^[0-9]{7,15}$/'],
        ], [
            'country.required' => 'Please select your country',
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
        $country = $request->country;

        // Country code mapping
        $countryCodes = [
            'KE' => '254',
            'UG' => '256', 
            'TZ' => '255',
            'NG' => '234',
            'ZA' => '27',
            'GH' => '233',
        ];

        // Format phone number based on selected country
        if ($country === 'OTHER') {
            // For "Other", assume user entered full number with country code
            if (strlen($phone) < 10) {
                return back()
                    ->withErrors(['phone_number' => 'Please enter your full phone number with country code'])
                    ->withInput();
            }
        } else {
            // For specific countries, add country code if missing
            $countryCode = $countryCodes[$country];
            
            // Remove leading zeros and add country code
            $phone = ltrim($phone, '0');
            
            // If country code not already present, add it
            if (!str_starts_with($phone, $countryCode)) {
                $phone = $countryCode . $phone;
            }
        }

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
