<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    // Récupérer l'utilisateur connecté (/api/me)
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'nom' => $user->nom ?? $user->name,
            'prenoms' => $user->prenoms ?? '',
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->getRoleNames()->first() ?? 'passenger',
            'avatar_url' => $user->avatar_path ? Storage::url($user->avatar_path) : ($user->avatar ?? null),
            'company_id' => $user->company_id ?? null,
        ]);
    }

    // Inscription Passager / Client Final
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'nom' => $request->nom ?? $request->name,
            'prenoms' => $request->prenoms ?? null,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // Attribution du rôle passager par défaut
        $user->assignRole('passenger');

        // 1. Connecter l'utilisateur dans la session Web Sanctum
        Auth::login($user);
        $request->session()->regenerate();

        // 2. Générer également un token Sanctum si besoin
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Inscription réussie !',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->getRoleNames()->first() ?? 'passenger',
                'avatar_url' => $user->avatar_path ? Storage::url($user->avatar_path) : null,
            ]
        ], 201);
    }

    // Connexion (Accepte E-mail ou Numéro de téléphone)
    public function login(LoginRequest $request)
    {
        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::where($loginType, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Identifiants ou mot de passe incorrects.'
            ], 401);
        }

        // 1. Créer la session pour le cookie SPA Sanctum
        Auth::login($user);
        $request->session()->regenerate();

        // 2. Générer le token d'accès
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'nom' => $user->nom ?? $user->name,
                'prenoms' => $user->prenoms ?? '',
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->getRoleNames()->first(),
                'avatar_url' => $user->avatar_path ? Storage::url($user->avatar_path) : ($user->avatar_url ?? null),
                'company_id' => $user->company_id ?? null,
            ]
        ]);
    }

    // Demande de réinitialisation de mot de passe
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Un lien de réinitialisation a été envoyé à votre adresse e-mail.'
            ], 200);
        }

        return response()->json([
            'message' => 'Impossible d\'envoyer le lien de réinitialisation.'
        ], 400);
    }

    // Validation et changement du mot de passe (via lien reçu par email)
    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Votre mot de passe a été réinitialisé avec succès.'
            ], 200);
        }

        return response()->json([
            'message' => 'Le jeton de réinitialisation est invalide ou a expiré.'
        ], 400);
    }

    // Mise à jour du profil utilisateur avec prise en charge de l'avatar
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        // 1. Validation des champs réellement présentés par le client
        $validated = $request->validate([
            'nom'      => 'nullable|string|max:255',
            'prenoms'  => 'nullable|string|max:255',
            'name'     => 'nullable|string|max:255',
            'email'    => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone'    => ['sometimes', 'required', 'string', Rule::unique('users')->ignore($user->id)],
            'avatar'   => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048', // Max 2 Mo
        ], [
            'email.unique'  => 'Cette adresse e-mail est déjà utilisée.',
            'phone.unique'  => 'Ce numéro de téléphone est déjà associé à un compte.',
            'avatar.image'  => 'Le fichier doit être une image.',
            'avatar.mimes'  => 'Formats acceptés : jpeg, jpg, png, webp.',
            'avatar.max'    => 'L\'image ne doit pas dépasser 2 Mo.',
        ]);

        // 2. Gestion de l'avatar (enregistré dans la colonne 'avatar')
        if ($request->hasFile('avatar')) {
            // Supprimer l'ancienne image si elle existe
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Enregistrer l'image et sauvegarder son chemin dans $user->avatar
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        // 3. Traitement du champ 'name' (combinaison de nom + prenoms du formulaire React)
        $nomInput = $request->input('nom');
        $prenomsInput = $request->input('prenoms');

        if (!empty($nomInput) || !empty($prenomsInput)) {
            $user->name = trim(($nomInput ?? '') . ' ' . ($prenomsInput ?? ''));
        } elseif (!empty($validated['name'])) {
            $user->name = $validated['name'];
        }

        // 4. Mise à jour des autres champs
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        if (isset($validated['phone'])) {
            $user->phone = $validated['phone'];
        }

        // Sauvegarde des modifications en base de données
        $user->save();

        // 5. Réponse adaptée au frontend
        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'nom'        => $nomInput ?? $user->name,
                'prenoms'    => $prenomsInput ?? '',
                'email'      => $user->email,
                'phone'      => $user->phone,
                'role'       => $user->getRoleNames()->first() ?? 'passenger',
                'avatar_url' => $user->avatar ? Storage::url($user->avatar) : null,
                'company_id' => $user->company_id,
            ]
        ]);
    }

    // Modification du mot de passe de l'utilisateur connecté
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        // 1. Validation de la demande
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'Le mot de passe actuel est obligatoire.',
            'password.required'         => 'Le nouveau mot de passe est obligatoire.',
            'password.min'              => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'        => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        // 2. Vérification de l'ancien mot de passe
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel saisi est incorrect.'
            ], 422);
        }

        // 3. Mise à jour du nouveau mot de passe
        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return response()->json([
            'message' => 'Votre mot de passe a été modifié avec succès.'
        ], 200);
    }

    // Déconnexion
    public function logout(Request $request)
    {
        // 1. Invalider la session cookie
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 2. Supprimer les jetons Sanctum si présents
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Déconnexion réussie.'
        ]);
    }
}
