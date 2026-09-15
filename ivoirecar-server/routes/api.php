<?php

use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Company\StaffController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ParcelController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteStopController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

// ==========================================
// ROUTES PUBLIQUES (Sans Authentification)
// ==========================================
Route::get('parcels/track/{trackingNumber}', [ParcelController::class, 'track']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// ==========================================
// ZONE AUTHENTIFIÉE (Sanctum)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // --- Profil utilisateur ---
    Route::get('/me', [AuthController::class, 'profile']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ==========================================
    // 1. ESPACE SUPER-ADMINISTRATEUR (Plateforme)
    // ==========================================
    Route::middleware('role:super-admin')->prefix('admin')->group(function () {
        // Compagnies
        Route::get('/companies', [AdminCompanyController::class, 'index']);
        Route::post('/companies', [AdminCompanyController::class, 'store']);
        Route::get('/companies/{company}', [AdminCompanyController::class, 'show']);
        Route::patch('/companies/{company}/toggle-status', [AdminCompanyController::class, 'toggleStatus']);

        // Monitoring et Rapports Globaux
        Route::get('/tickets', [AdminReportController::class, 'tickets']);
        Route::get('/payments', [AdminReportController::class, 'payments']);
        Route::get('/parcels', [AdminReportController::class, 'parcels']); // Vue globale de tous les colis
    });

    // ==========================================
    // 2. ESPACE CLIENT COMPAGNIE (Opérationnel)
    // ==========================================
    // Toutes ces routes nécessitent un abonnement actif à la plateforme
    Route::middleware('company.active')->group(function () {

        // --- Gérance interne du personnel (Admin Compagnie uniquement) ---
        Route::middleware('role:admin')->prefix('company')->group(function () {
            Route::get('/staff', [StaffController::class, 'index']);
            Route::post('/staff', [StaffController::class, 'store']);
            Route::get('/staff/{user}', [StaffController::class, 'show']);
        });

        // --- Gares & Infrastructures ---
        Route::middleware('permission:manage-stations')->group(function () {
            Route::apiResource('stations', StationController::class)->except(['index', 'show']);
        });
        Route::apiResource('stations', StationController::class)->only(['index', 'show']);

        // --- Flotte de Véhicules ---
        Route::middleware('permission:manage-vehicles')->group(function () {
            Route::apiResource('vehicles', VehicleController::class)->except(['index', 'show']);
        });
        Route::middleware('permission:view-vehicles')->group(function () {
            Route::apiResource('vehicles', VehicleController::class)->only(['index', 'show']);
        });

        // --- Lignes de Transport & Arrêts ---
        Route::middleware('permission:manage-routes')->group(function () {
            Route::apiResource('routes', RouteController::class)->except(['index', 'show']);
            Route::apiResource('route-stops', RouteStopController::class)->except(['index', 'show']);
        });
        Route::middleware('permission:view-routes')->group(function () {
            Route::apiResource('routes', RouteController::class)->only(['index', 'show']);
            Route::apiResource('route-stops', RouteStopController::class)->only(['index', 'show']);
        });

        // --- Trajets ---
        Route::middleware('permission:manage-trips')->group(function () {
            Route::apiResource('trips', TripController::class)->except(['index', 'show']);
        });
        Route::middleware('permission:view-trips')->group(function () {
            Route::apiResource('trips', TripController::class)->only(['index', 'show']);
        });

        // --- Billetterie (Tickets) ---
        Route::middleware('permission:create-tickets')->group(function () {
            Route::post('tickets', [TicketController::class, 'store']);
        });
        Route::middleware('permission:view-tickets')->group(function () {
            Route::get('tickets', [TicketController::class, 'index']);
            Route::get('tickets/{ticket}', [TicketController::class, 'show']);
        });
        Route::middleware('permission:cancel-tickets')->group(function () {
            Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
            Route::delete('tickets/{ticket}', [TicketController::class, 'destroy']);
        });

        // --- Expédition de Colis ---
        Route::middleware('permission:create-parcels')->group(function () {
            Route::post('parcels', [ParcelController::class, 'store']);
        });
        Route::middleware('permission:view-parcels')->group(function () {
            Route::get('parcels', [ParcelController::class, 'index']);
            Route::get('parcels/{parcel}', [ParcelController::class, 'show']);
        });
        Route::middleware('permission:update-parcels')->group(function () {
            Route::patch('parcels/{parcel}/status', [ParcelController::class, 'updateStatus']);
            Route::delete('parcels/{parcel}', [ParcelController::class, 'destroy']);
        });

        // --- Transactions & Paiements ---
        Route::middleware('permission:process-payments')->group(function () {
            Route::post('payments', [PaymentController::class, 'store']);
            Route::patch('payments/{payment}/status', [PaymentController::class, 'updateStatus']);
        });
        Route::middleware('permission:view-payments')->group(function () {
            Route::get('payments', [PaymentController::class, 'index']);
            Route::get('payments/{payment}', [PaymentController::class, 'show']);
        });

        // --- Tableaux de Bord (Stats) ---
        Route::middleware('permission:view-dashboard')->group(function () {
            Route::get('dashboard/stats', [DashboardController::class, 'stats']);
            Route::get('dashboard/revenue-chart', [DashboardController::class, 'revenueChart']);
        });
    });
});
