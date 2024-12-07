<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Refferal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
// use Firebase\JWT\JWT;
// use Firebase\JWT\Key;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // Register a new user
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);
        

        $token = JWTAuth::fromUser($user);

        return response()->json(['user' => $user, 'token' => $token], 201);
    }
    
    public function registerReferral(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'is_referral' => 1,
            'password' => Hash::make($request->password),
        ]);
        
        Refferal::create([
            'user_id' => $user->id,
            'refferal_id' => $request->id
        ]);
        

        $token = JWTAuth::fromUser($user);

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function update(Request $request)
    {
        $user = Auth::user(); // Get authenticated user

        $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user->name     = $request->username;
        $user->email    = $request->useremail;
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Information updated successfully!', 'user' => $user], 200);
    }
    
    public function getUserDetails(Request $request)
    {
        $user = Auth::user();
        return response()->json([
            'username' => $user->name,
            'email' => $user->email,
            'referral' => $user->is_referral,
            'admin' => $user->is_admin
        ], 200);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
    
        if (Auth::attempt($credentials)) {
            // Authentication passed, generate token
            $user = Auth::user();
            $token = auth()->login($user);
    
            // Send the token and admin status to the client
            return response()->json([
                'token' => $token,
                'is_admin' => $user->is_admin,
            ]);
        }
    
        return response()->json(['error' => 'Invalid credentials'], 401);
    }
    
    

    // Log out the user
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Successfully logged out']);
    }

    // Get the authenticated user
    public function me()
    {
        return response()->json(JWTAuth::user());
    }

    public function verifyToken(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            return response()->json(['valid' => true, 'user' => $user]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['valid' => false, 'error' => 'Token expired'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['valid' => false, 'error' => 'Token invalid'], 401);
        } catch (\Exception $e) {
            return response()->json(['valid' => false, 'error' => 'Token not found'], 401);
        }
    }

}