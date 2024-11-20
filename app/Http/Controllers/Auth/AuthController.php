<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
// use Firebase\JWT\JWT;
// use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
      // Register a new user
      public function register(Request $request)
      {
          $request->validate([
              'name' => 'required|string|max:255',
              'email' => 'required|string|email|max:255|unique:users',
              'password' => 'required|string|min:6|confirmed',
          ]);
  
          $user = User::create([
              'name' => $request->name,
              'email' => $request->email,
              'password' => Hash::make($request->password),
          ]);
  
          $token = JWTAuth::fromUser($user);
  
          return response()->json(['user' => $user, 'token' => $token], 201);
      }
  
      public function login(Request $request)
      {
          $credentials = $request->only('email', 'password');
      
          if (!$token = auth()->attempt($credentials)) {
              return response()->json(['error' => 'Invalid credentials'], 401);
          }
      
          return response()->json(['token' => $token]);
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