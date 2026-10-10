<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ParcelController;
use App\Http\Controllers\Api\SuperAdminDashboardController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\BusController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\CompanyDashboardController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\DepartureController;
use App\Http\Controllers\Api\ScheduleTemplateController;


// ROUTES PUBLIQUES
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/trips/search', [TripController::class, 'search']);
Route::get('/departures/search', [DepartureController::class, 'search']); // Recherche dynamique publique avec génération récurrente
Route::get('/parcels/track/{trackingCode}', [ParcelController::class, 'track']);

Route::get('/companies', [CompanyController::class, 'index']);
Route::get('/companies/{company}/stations', [CompanyController::class, 'getStations']);
Route::get('/company/schedule-templates', [ScheduleTemplateController::class, 'index']);
Route::get('/company/routes/search', [RouteController::class, 'search']);

// ROUTES PROTÉGÉES (AUTH REQUISE)

Route::middleware('auth:sanctum')->group(function () {

    // Authentification & Profil Commun
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/profile/update-password', [AuthController::class, 'updatePassword']);

    // Passager
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/parcels', [ParcelController::class, 'store']);
    Route::get('/my-parcels', [ParcelController::class, 'myParcels']);
    Route::get('/my-reservations', [BookingController::class, 'userReservations']);


    // ------------------------------------------
    // ESPACE SUPER ADMIN
    // ------------------------------------------
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/admin/stats', [SuperAdminDashboardController::class, 'stats']);
        Route::get('/admin/recent-operations', [SuperAdminDashboardController::class, 'recentOperations']);
        Route::get('/admin/buses', [SuperAdminDashboardController::class, 'allBuses']);
        Route::get('admin/users', [SuperAdminDashboardController::class, 'allUsers']);
        //Route::get('/admin/parcels', [SuperAdminDashboardController::class, 'allParcels']);
        //Route::get('/admin/bookings', [SuperAdminDashboardController::class, 'allBookings']);
        Route::get('/admin/reservations', [BookingController::class, 'allReservations']);
        Route::get('/admin/parcels', [ParcelController::class, 'allParcels']);

        Route::get('/companies/details/{company}', [CompanyController::class, 'show360']);
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy']);
        Route::match(['put', 'post'], '/companies/{company}', [CompanyController::class, 'update']);
        Route::patch('/companies/{company}/toggle-status', [CompanyController::class, 'toggleStatus']);
    });

    // ------------------------------------------
    // ESPACE COMPANY ADMIN
    // ------------------------------------------
    Route::middleware('role:company_admin')->group(function () {
        Route::get('/company/stats', [CompanyDashboardController::class, 'stats']);
        Route::get('/company/recent-operations', [CompanyDashboardController::class, 'recentOperations']);
        Route::patch('/company/parcels/{parcel}/status', [ParcelController::class, 'updateStatus']);
        Route::post('/company/parcels/{parcel}/collect', [ParcelController::class, 'collect']);

        // Gares CRUD
        Route::get('/company/station-managers', [StationController::class, 'getStationManagers']);
        Route::get('/company/stations', [StationController::class, 'index']);
        Route::post('/company/stations', [StationController::class, 'store']);
        Route::get('/company/stations/{id}', [StationController::class, 'show']);
        Route::put('/company/stations/{station}', [StationController::class, 'update']);
        Route::delete('/company/stations/{station}', [StationController::class, 'destroy']);

        // CRUD Véhicules / Bus
        Route::get('/company/drivers', [BusController::class, 'getDrivers']);
        Route::get('/company/buses', [BusController::class, 'index']);
        Route::post('/company/buses', [BusController::class, 'store']);
        Route::get('/company/vehicles/{id}', [BusController::class, 'show']);
        Route::put('/company/vehicles/{id}', [BusController::class, 'update']);
        Route::delete('/company/buses/{id}', [BusController::class, 'destroy']);

        // Gestion des Lignes et Escales
        Route::get('/company/routes', [RouteController::class, 'index']);
        Route::post('/company/routes', [RouteController::class, 'store']);
        Route::get('/company/routes/{route}', [RouteController::class, 'show']);
        Route::put('/company/routes/{route}', [RouteController::class, 'update']);
        Route::delete('/company/routes/{route}', [RouteController::class, 'destroy']);

        // Programmes Récurrents / Horaires Types (Schedule Templates)

        Route::post('/company/schedule-templates', [ScheduleTemplateController::class, 'store']);
        Route::put('/company/schedule-templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'update']);
        Route::patch('/company/schedule-templates/{scheduleTemplate}/toggle-active', [ScheduleTemplateController::class, 'toggleActive']);
        Route::delete('/company/schedule-templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'destroy']);
        Route::get('/company/schedule-templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'show']);


        // Planification des Départs Instanciés / Ponctuels
        Route::get('/company/departures', [DepartureController::class, 'index']);
        Route::post('/company/departures', [DepartureController::class, 'store']);
        Route::get('/company/departures/{departure}', [DepartureController::class, 'show']);
        Route::put('/company/departures/{departure}', [DepartureController::class, 'update']);
        Route::delete('/company/departures/{departure}', [DepartureController::class, 'destroy']);
        Route::patch('/company/departures/{departure}/toggle-active', [DepartureController::class, 'toggleActive']);

        // Personnel CRUD & Rôles
        Route::get('/company/staff', [StaffController::class, 'index']);
        Route::post('/company/staff', [StaffController::class, 'store']);
        Route::put('/company/staff/{user}', [StaffController::class, 'update']);
        Route::get('/company/staff/{user}', [StaffController::class, 'show']);
        Route::patch('/company/staff/{user}/role', [StaffController::class, 'updateRole']);
        Route::delete('/company/staff/{user}', [StaffController::class, 'destroy']);

        Route::get('/company/reservations', [BookingController::class, 'companyReservations']);
        Route::get('/company/parcels', [ParcelController::class, 'companyParcels']);
    });
});
