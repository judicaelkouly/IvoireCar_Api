<?php


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // 1. Si aucun utilisateur n'est connecté ou si c'est un Super-Admin, on laisse passer
        if (! $user || $user->hasRole('super-admin')) {
            return $next($request);
        }

        // 2. Vérification si l'utilisateur est lié à une compagnie et si elle est désactivée
        if ($user->company && ! $user->company->is_active) {

            // Si l'utilisateur est authentifié via un token Sanctum, on détruit le token
            if ($user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            return response()->json([
                'error' => 'Compte suspendu',
                'message' => 'L\'accès de votre compagnie a été temporairement désactivé. Veuillez contacter l\'administrateur du système.'
            ], 403);
        }

        return $next($request);
    }
}

