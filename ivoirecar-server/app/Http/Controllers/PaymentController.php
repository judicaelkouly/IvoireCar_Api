<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentStatusRequest;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['paymentable', 'user']);

        // Filtrer par méthode de paiement
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->query('payment_method'));
        }

        // Filtrer par statut du paiement
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Recherche par référence de transaction
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where('transaction_reference', 'like', "%{$search}%");
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $payment = DB::transaction(function () use ($validated, $request) {
                // Instancier l'entité concernée (Ticket ou Parcel)
                $modelClass = $validated['paymentable_type'];
                $payable = $modelClass::findOrFail($validated['paymentable_id']);

                // Génération automatique d'une référence si non fournie
                $reference = $validated['transaction_reference'] ?? 'PAY-' . strtoupper(Str::random(10));

                // 1. Enregistrement de la transaction
                $payment = Payment::create([
                    'paymentable_type' => $validated['paymentable_type'],
                    'paymentable_id' => $validated['paymentable_id'],
                    'user_id' => $request->user()->id ?? null,
                    'amount' => $validated['amount'],
                    'payment_method' => $validated['payment_method'],
                    'provider' => $validated['provider'] ?? null,
                    'transaction_reference' => $reference,
                    'status' => 'completed',
                ]);

                // 2. Mise à jour automatique du statut de paiement de la ressource liée
                $payable->update([
                    'payment_status' => 'completed',
                    'payment_method' => $validated['payment_method'],
                ]);

                // 3. Passer le billet en statut payé si applicable
                if ($modelClass === 'App\Models\Ticket' && $payable->status === 'reserved') {
                    $payable->update(['status' => 'paid']);
                }

                return $payment;
            });

            return response()->json([
                'message' => 'Paiement enregistré et validé avec succès.',
                'payment' => $payment->load('paymentable'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du traitement du paiement.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json($payment->load(['paymentable', 'user']));
    }

    public function updateStatus(UpdatePaymentStatusRequest $request, Payment $payment): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $payment) {
            $payment->update($validated);

            // Synchroniser le statut de paiement sur l'élément lié (Ticket ou Parcel)
            if (isset($validated['status'])) {
                $payment->paymentable()->update([
                    'payment_status' => $validated['status'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Statut du paiement mis à jour avec succès.',
            'payment' => $payment->load('paymentable'),
        ]);
    }
}
