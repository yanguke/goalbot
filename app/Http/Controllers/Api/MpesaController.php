<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MpesaTransaction;
use App\Models\Subscriber;
use App\Services\WhatsApp\MessageSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MpesaController extends Controller
{
    private MessageSender $messageSender;

    public function __construct(MessageSender $messageSender)
    {
        $this->messageSender = $messageSender;
    }

    /**
     * Handle M-Pesa callback
     */
    public function callback(Request $request)
    {
        $callbackData = $request->all();
        
        Log::info('M-Pesa Callback Received', ['data' => $callbackData]);

        try {
            $resultCode = $callbackData['Body']['stkCallback']['ResultCode'] ?? null;
            $resultDesc = $callbackData['Body']['stkCallback']['ResultDesc'] ?? '';
            $checkoutRequestId = $callbackData['Body']['stkCallback']['CheckoutRequestID'] ?? null;
            $merchantRequestId = $callbackData['Body']['stkCallback']['MerchantRequestID'] ?? null;

            if (!$checkoutRequestId) {
                return response()->json([
                    'ResultCode' => 1,
                    'ResultDesc' => 'Invalid callback data'
                ]);
            }

            // Find transaction
            $transaction = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)->first();

            if (!$transaction) {
                Log::error('Transaction not found for callback', ['checkout_request_id' => $checkoutRequestId]);
                return response()->json([
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success'
                ]);
            }

            if ($resultCode == 0) {
                // Payment successful
                $metadata = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'] ?? [];
                $mpesaReceipt = null;
                $transactionDate = null;
                $phoneNumber = null;
                $amount = null;

                foreach ($metadata as $item) {
                    switch ($item['Name']) {
                        case 'MpesaReceiptNumber':
                            $mpesaReceipt = $item['Value'];
                            break;
                        case 'TransactionDate':
                            $transactionDate = Carbon::createFromFormat('YmdHis', (string) $item['Value'])->format('Y-m-d H:i:s');
                            break;
                        case 'PhoneNumber':
                            $phoneNumber = $item['Value'];
                            break;
                        case 'Amount':
                            $amount = $item['Value'];
                            break;
                    }
                }

                $transaction->update([
                    'status' => 'completed',
                    'mpesa_receipt_number' => $mpesaReceipt,
                    'transaction_date' => $transactionDate,
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc
                ]);

                // Confirm subscription
                $this->confirmSubscription($transaction);

                Log::info('M-Pesa Payment Successful', [
                    'transaction_id' => $transaction->id,
                    'receipt' => $mpesaReceipt
                ]);
            } else {
                // Payment failed
                $transaction->update([
                    'status' => 'failed',
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc
                ]);

                // Notify user of failure
                $this->messageSender->sendText(
                    $transaction->phone_number,
                    "❌ *Payment Failed*\n\n" .
                    "Reason: {$resultDesc}\n\n" .
                    "Please try again by sending */pay*"
                );

                Log::warning('M-Pesa Payment Failed', [
                    'transaction_id' => $transaction->id,
                    'reason' => $resultDesc
                ]);
            }

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Success'
            ]);

        } catch (\Exception $e) {
            Log::error('M-Pesa Callback Processing Exception', [
                'message' => $e->getMessage(),
                'data' => $callbackData
            ]);

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Success'
            ]);
        }
    }

    /**
     * Confirm subscription after successful payment
     */
    private function confirmSubscription(MpesaTransaction $transaction)
    {
        $subscriber = Subscriber::where('phone_number', $transaction->phone_number)->first();

        if (!$subscriber) {
            $subscriber = Subscriber::create([
                'phone_number' => $transaction->phone_number,
                'is_active' => true,
                'notifications_enabled' => true,
                'notify_all_matches' => true,
                'demo_mode' => false
            ]);
        } else {
            $subscriber->update([
                'is_active' => true,
                'notifications_enabled' => true,
                'notify_all_matches' => true
            ]);
        }

        $paymentType = $transaction->payment_type === 'full_tournament' ? 'Full Tournament' : 'Per Match';

        $this->messageSender->sendText(
            $transaction->phone_number,
            "✅ *Payment Successful!*\n\n" .
            "Receipt: {$transaction->mpesa_receipt_number}\n" .
            "Amount: KES {$transaction->amount}\n" .
            "Plan: {$paymentType}\n\n" .
            "🎉 *You're now subscribed to GoalBot!*\n\n" .
            "You'll receive AI-powered alerts for all World Cup 2026 matches.\n\n" .
            "World Cup begins June 11, 2026 🏆"
        );
    }
}
