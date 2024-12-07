<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Models\Members;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class MemberController extends Controller
{
    public function index()
    {
        return response()->json(Members::where('admin_id', Auth::id())->get(), 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:members,email',
        ]);

        $member = Members::create([
            'admin_id' => Auth::id(),
            'email' => $request->email,      
        ]);

        $userId = Auth::id(); 
        $link   = url("http://localhost:8080/register/{$userId}");

        // Send the invitation email
        Mail::to($validated['email'])->send(new InvitationMail($link));

        return response()->json(['message' => 'Member created and invitation sent!', 'member' => $member], 201);
    }

    public function show($id)
    {
        $member = Members::find($id);

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        return response()->json($member, 200);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:members,email,' . $id,
        ]);

        $member = Members::find($id);

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        $member->update($validated);

        return response()->json($member, 200);
    }

    public function destroy($id)
    {
        $member = Members::find($id);

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        $member->delete();

        return response()->json(['message' => 'Member deleted successfully'], 200);
    }
}