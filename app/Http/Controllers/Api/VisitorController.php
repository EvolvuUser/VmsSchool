<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;



class VisitorController extends Controller
{

    public function store(Request $request)
    {
        $academicyeardata = DB::table('academic_yr')->where('active', 'Y')->first();
        $academic_yr = $academicyeardata->academic_yr ?? null;

        // Step 1: Validate input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mobileno' => 'required|digits:10',
            'email' => 'required|string|max:50',
            'address' => 'required|string|max:1000',
            'purpose' => 'required|string|max:255',
            'whomtomeet' => 'required|string|max:255',
            'token' => 'required|string',
            'token_created_at' => 'required|date',
        ]);

        // Step 2: Add additional fields
        $validated['academic_yr'] = $academic_yr;
        $validated['visit_date'] = now()->format('Y-m-d');
        $validated['visit_in_time'] = null;
        $validated['visit_out_time'] = null;
        $validated['short_name'] = $request->short_name;

        // Step 3: Check if token is already used
        if (Visitor::where('token', $validated['token'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This token has already been used.',
            ], 403);
        }

        // Step 4: Validate token expiration (UTC to match frontend)
        try {
            $createdAt = Carbon::parse($validated['token_created_at'])->timezone('UTC');
            $now = now('UTC');

            if ($now->diffInSeconds($createdAt) > 600) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token expired. Please scan the QR code again.',
                ], 403);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid token timestamp.',
            ], 400);
        }

        // Step 5: Save visitor
        $visitor = new Visitor($validated);
        $visitor->save();

        // Step 6: Invalidate token
        DB::table('token')
            ->where('token', $validated['token'])
            ->update(['token' => null]);

        // Step 7: Return response
        return response()->json([
            'status' => 'success',
            'message' => 'Visitor data saved successfully. Token invalidated.',
            'data' => $visitor
        ], 201);
    }

    public function show($id)
    {
        $visitor = Visitor::find($id);


        if (!$visitor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Visitor not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $visitor
        ]);
    }

    public function findByMobile($mobileno)
    {
        $visitor = Visitor::where('mobileno', $mobileno)->latest()->first();

        if (!$visitor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Visitor not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $visitor
        ]);
    }

    public function role(Request $request)
    {
        $roles = DB::table('role_master')->get();
        return response()->json(['data' => $roles]);
    }

    public function getEmailOtp(Request $request)
    {
        $otp = rand(1000, 9999);
        $email = $request->email;
        DB::table('users')->updateOrInsert(
            ['email' => $email], // Condition to check (like WHERE email = ?)
            [
                'name' => $request->name,
                'mobileno' => $request->mobileno,
                'address' => $request->address,
                'purpose' => $request->purpose,
                'otp' => $otp
            ]
        );
        Mail::html("<h2>Your OTP is: $otp</h2>", function ($message) use ($email) {
            $message->to($email)
                ->subject('Your OTP Code');
        });

        return response()->json([
            'status' => '200',
            'message' => 'Otp Sended successfully.',
            'success' => true
        ]);
    }

    public function Verifyemailotp(Request $request)
    {
        $otp = $request->otp;
        $email = $request->email;
        $otprecord = DB::table('users')->where('email', $email)->first();
        $otpsaved = $otprecord->otp;
        if ($otpsaved == $otp) {
            return response()->json([
                'status' => '200',
                'message' => 'Pass is generated.',
                'success' => true
            ]);
        }

        return response()->json([
            'status' => '400',
            'message' => 'Otp is Incorrect.',
            'success' => false
        ]);
    }

    public function checkVisitorStatus(Request $request)
    {
        $email = $request->email;
        $mobileno = $request->mobileno;

        $visitor = Visitor::where(function ($q) use ($email, $mobileno) {
            $q->where('email', $email)->orWhere('mobileno', $mobileno);
        })
            ->latest()
            ->first();

        if ($visitor) {

            if (!is_null($visitor->visit_in_time) && !is_null($visitor->visit_out_time)) {

                return response()->json([
                    'alreadyInside' => false
                ]);
            } else {
                DB::table('token')
                    ->where('token', $visitor->token)
                    ->update(['token' => null]);
                return response()->json(['alreadyInside' => true]);
            }
        }

        // $visitor = Visitor::where(function ($q) use ($email, $mobileno) {
        //     $q->where('email', $email)->orWhere('mobileno', $mobileno);
        // })
        //     ->whereNull('visit_out_time')
        //     ->latest()
        //     ->first();

        // if ($visitor) {
        //     // Invalidate the token in the token table
        //     DB::table('token')
        //         ->where('token', $visitor->token)
        //         ->update(['token' => null]);

        //     return response()->json(['alreadyInside' => true]);
        // }


        return response()->json(['alreadyInside' => false]);
    }

    public function getAllVisitors(Request $request)
    {
        $short_name = $request->input('short_name');
        $visitors = DB::table('get_visitors')->where('short_name', $short_name)->get();
        return response()->json(['data' => $visitors]);
    }

    public function getAllVisitor(Request $request)
    {
        // Validate input
        $request->validate([
            'short_name' => 'required|string',
        ]);

        // Fetch visitors from database
        $short_name = $request->input('short_name');

        $visitors = DB::table('get_visitors')
            ->where('short_name', $short_name)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $visitors,
        ]);
    }

    public function saveInTime(Request $request, $id)
    {
        // dd($request->all());
        $validated = $request->validate([
            'visit_in_time' => 'required|date_format:Y-m-d H:i:s',
        ]);
        $validated['short_name'] = $request->short_name;

        $visitor = Visitor::find($id);
        $visitor->visit_in_time = $validated['visit_in_time'];
        $visitor->short_name = $validated['short_name'];
        $visitor->save();

        return response()->json([
            'status' => '200',
            'message' => 'Visit In time save successfully.',
            'success' => true,
            'data' => $visitor,
        ]);
    }

    public function saveOutTime(Request $request, $id)
    {

        $validated = $request->validate([
            'visit_out_time' => 'required|date_format:Y-m-d H:i:s'
        ]);
        $validated['short_name'] = $request->short_name;

        $visitor = Visitor::find($id);
        $visitor->visit_out_time = $validated['visit_out_time'];
        $visitor->short_name = $validated['short_name'];
        $visitor->save();

        return response()->json([
            'status' => '200',
            'message' => 'Visit Out time save successfully.',
            'success' => true,
            'data' => $visitor,
        ]);
    }

    // genereate a QR code api with Url and token
    public function generateTokenAndUrl()
    {
        // Step 2: Generate new token
        $token = Str::random(32);
        $now = Carbon::now();

        // Step 3: Truncate (clear) token table and insert new token
        DB::table('token')->truncate();

        DB::table('token')->insert([
            'token' => $token
        ]);

        // Step 5: Create frontend URL with token
        $baseUrl = "https://vms.evolvu.in/public/react";
        // $baseUrl = "http://localhost:5173";
        $urlWithToken = "{$baseUrl}?token={$token}";

        return response()->json([
            'success' => true,
            'base_url' => $baseUrl,
            'token' => $token,
            'url_with_token' => $urlWithToken
        ]);
    }

    public function verifyToken(Request $request)
    {
        $passedToken = $request->query('token');

        if (!$passedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided'
            ], 400);
        }

        $validToken = DB::table('token')->value('token');

        if ($passedToken === $validToken) {
            return response()->json([
                'success' => true,
                'message' => 'Token is valid'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Token is invalid or expired'
            ], 403);
        }
    }

    public function invalidateToken(Request $request)
    {
        $token = $request->token;

        DB::table('token')->where('token_id', '1')->update(['token' => null]);

        return response()->json(['success' => true, 'message' => 'Token invalidated.']);
    }
}
