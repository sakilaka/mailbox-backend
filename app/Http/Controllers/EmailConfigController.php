<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EmailConfigController extends Controller
{
    
    public function store(Request $request)
    {
        $request->validate([
            'mail_driver' => 'required|string',
            'mail_host' => 'required|string',
            'outgoing_port' => 'required|string',
            'incoming_port' => 'required|string',
            // 'mail_username' => 'required|string',
            // 'mail_password' => 'required|string',
            'mail_encryption' => 'required|string',
            // 'mail_from_address' => 'required|array', 
            'mail_from_name' => 'required|string',
        ]);
        
        // Ensure mail_from_address is converted to a string
        // $mailFromAddress = implode(',', $request->mail_from_address);
        // dd(Auth::id());
        
        $mailUsername = json_encode($request->mail_username);

        EmailConfig::create([
            'user_id' => auth()->user()->id,
            'driver' => $request->mail_driver,
            'host' => $request->mail_host,
            'smtp_host' => $request->mail_smtp_host,
            'outgoing_port' => $request->outgoing_port,
            'incoming_port' => $request->incoming_port,
            'username' => $mailUsername, // Store as JSON
            'encryption' => $request->mail_encryption,
            'from_address' => $request->mail_from_address,
            'from_name' => $request->mail_from_name
        ]);
        
        return response()->json(['message' => 'Configuration saved successfully'], 201);
    }

    public function show($id)
    {
        $config = EmailConfig::find($id);
        if ($config) {
            return response()->json($config);
        }
        return response()->json(['message' => 'Config not found'], 404);
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'mail_driver' => 'required|string',
            'mail_host' => 'required|string',
            'outgoing_port' => 'required|string',
            'incoming_port' => 'required|string',
            'mail_encryption' => 'required|string',
            'mail_from_address' => 'required|string',
            'mail_from_name' => 'required|string',
            'mail_username' => 'required|json', // Expect JSON string
        ]);
    
        $config = EmailConfig::find($id);
        if (!$config) {
            return response()->json(['message' => 'Config not found'], 404);
        }
    
        $config->update([
            'driver' => $request->mail_driver,
            'host' => $request->mail_host,
            'smtp_host' => $request->mail_smtp_host,
            'outgoing_port' => $request->outgoing_port,
            'incoming_port' => $request->incoming_port,
            'username' => $request->mail_username, // Already JSON
            'encryption' => $request->mail_encryption,
            'from_address' => $request->mail_from_address,
            'from_name' => $request->mail_from_name,
        ]);
    
        return response()->json(['message' => 'Configuration updated successfully']);
    }
    


    // Delete email configuration by ID
    public function destroy($id)
    {
        $config = EmailConfig::find($id);
        if ($config) {
            $config->delete();
            return response()->json(['message' => 'Config deleted successfully']);
        }
        return response()->json(['message' => 'Config not found'], 404);
    }
    
  
   
}