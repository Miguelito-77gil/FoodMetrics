<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless(
            in_array($request->user()->role, ['admin', 'cashier'], true),
            403
        );

        $validated = $request->validate([
            'payment_method' => [
                'required',
                'string',
                Rule::in(['cash', 'gcash', 'bank_transfer', 'card', 'other']),
            ],
            'amount' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'max:9999999999.99',
            ],
            'transaction_reference' => [
                'nullable',
                'string',
                'max:120',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        DB::transaction(function () use ($order, $request, $validated) {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'amount' => 'Payments cannot be recorded for cancelled orders.',
                ]);
            }

            $totalCents = $this->toCents($lockedOrder->total_amount);

            $paidAmount = $lockedOrder->payments()
                ->where('status', 'completed')
                ->sum('amount');

            $paidCents = $this->toCents($paidAmount);
            $remainingCents = $totalCents - $paidCents;
            $amountCents = $this->toCents($validated['amount']);

            if ($remainingCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This order is already fully paid.',
                ]);
            }

            if ($amountCents > $remainingCents) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment exceeds the remaining balance of ₱'
                        . number_format($remainingCents / 100, 2) . '.',
                ]);
            }

            $payment = Payment::create([
                'payment_number' => 'TMP-' . Str::uuid(),
                'order_id' => $lockedOrder->id,
                'received_by' => $request->user()->id,
                'payment_method' => $validated['payment_method'],
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'status' => 'completed',
                'paid_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $payment->update([
                'payment_number' => 'PAY-' . str_pad(
                    (string) $payment->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
            ]);
        }, 3);

        return redirect()
            ->back()
            ->with('success', 'Payment recorded successfully.');
    }

    private function toCents(string|int|float $amount): int
    {
        $normalized = number_format((float) $amount, 2, '.', '');
        [$whole, $fraction] = explode('.', $normalized);

        return ((int) $whole * 100) + (int) $fraction;
    }
}