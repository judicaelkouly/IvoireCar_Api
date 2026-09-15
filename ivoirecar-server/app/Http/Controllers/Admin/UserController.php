<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Liste globale de tous les utilisateurs de la plateforme
     * avec filtres facultatifs par rôle ou par compagnie.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['company', 'station', 'roles']);

        // Filtre par compagnie
        if ($request->has('company_id')) {
            $query->where('company_id', $request->query('company_id'));
        }

        // Filtre par rôle (ex: passenger, admin, station-manager, etc.)
        if ($request->has('role')) {
            $query->role($request->query('role'));
        }

        // Recherche par nom, téléphone ou email
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(20);

        return response()->json($users);
    }

    /**
     * Fiche détaillée d'un utilisateur quelconque.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => $user->load(['company', 'station', 'roles', 'permissions']),
        ]);
    }
}
