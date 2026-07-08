<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
            'address' => 'required',
            'purpose' => 'required',
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
        $validated['user_id'] = $request->user_id;

        // Step 3: Check if token is already used
        if (Visitor::where('token', $validated['token'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This token has already been used.',
            ], 403);
        }

        // Step 4: Validate token expiration (UTC to match frontend)
        // try {
        //     $createdAt = Carbon::parse($validated['token_created_at'])->timezone('UTC');
        //     $now = now('UTC');
        //     // $createdAt = Carbon::parse($validated['token_created_at'])->timezone('Asia/Kolkata');
        //     // $now = now('Asia/Kolkata');

        //     if ($now->diffInSeconds($createdAt) > 600) {
        //         return response()->json([
        //             'status' => 'error',
        //             'message' => 'Token expired. Please scan the QR code again.',
        //         ], 403);
        //     }
        // } catch (\Exception $e) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Invalid token timestamp.',
        //     ], 400);
        // }

        try {
            $createdAt = Carbon::parse($validated['token_created_at'], 'Asia/Kolkata');
            $now = Carbon::now('Asia/Kolkata');

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

        // Step 5: Generate custom visit_id like SACS1, SACS2
        $prefix = strtoupper($validated['short_name']);  // e.g., 'SACS'

        $latestVisitor = Visitor::where('short_name', $prefix)
            ->whereNotNull('visit_id')
            ->where('visit_id', 'LIKE', $prefix . '%')
            ->orderByDesc('visitor_id')  // Use visitor_id, not id
            ->first();

        $nextNumber = 1;
        if ($latestVisitor && preg_match('/\d+$/', $latestVisitor->visit_id, $matches)) {
            $nextNumber = (int) $matches[0] + 1;
        }

        $validated['visit_id'] = $prefix . $nextNumber;

        // Step 6: Save visitor
        $visitor = new Visitor($validated);
        $visitor->save();

        // Step 7: Invalidate token
        DB::table('token')
            ->where('token', $validated['token'])
            ->update(['token' => null]);

        // Step 8: Return response
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
            ['email' => $email],
            [
                'name' => $request->name,
                'mobileno' => $request->mobileno,
                'address' => $request->address,
                'purpose' => $request->purpose,
                'otp' => $otp
            ]
        );

        // Mail::html("<h2>Your OTP is: $otp</h2>", function ($message) use ($email) {
        //     $message->to($email)
        //         ->subject('Your OTP Code');
        // });

        smart_mail(
            $email,
            'Your OTP Code',
            'emails.otp_mail',
            ['otp' => $otp]
        );

        return response()->json([
            'status' => '200',
            'message' => 'Otp Sended successfully.',
            'success' => true
        ]);
    }

    // public function getEmailOtp(Request $request)
    // {
    //     try {

    //         // 1. Validate Request
    //         $request->validate([
    //             'name'     => 'required|string|max:100',
    //             'email'    => 'required|email',
    //             'mobileno' => 'required|digits_between:10,15',
    //             'address'  => 'nullable|string|max:255',
    //             'purpose'  => 'nullable|string|max:255',
    //         ]);

    //         // 2. Generate OTP
    //         $otp = random_int(1000, 9999);
    //         $email = $request->email;

    //         // 3. Save OTP with expiry (5 minutes)
    //         DB::table('users')->updateOrInsert(
    //             ['email' => $email],
    //             [
    //                 'name'       => $request->name,
    //                 'mobileno'   => $request->mobileno,
    //                 'address'    => $request->address,
    //                 'purpose'    => $request->purpose,
    //                 'otp'        => $otp,
    //                 'otp_expiry' => now()->addMinutes(5),
    //                 'updated_at' => now(),
    //                 'created_at' => now()
    //             ]
    //         );

    //         // 4. Send Email
    //         smart_mail(
    //             $email,
    //             'Your OTP Code',
    //             'emails.otp_mail',
    //             ['otp' => $otp]
    //         );

    //         // 5. Success Response
    //         return response()->json([
    //             'status'  => true,
    //             'message' => 'OTP sent successfully.',
    //             'code'    => 200
    //         ], 200);
    //     } catch (\Exception $e) {

    //         // 6. Log Error
    //         \Log::error('OTP Email Error: ' . $e->getMessage());

    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Something went wrong while sending OTP.',
    //             'code'    => 500
    //         ], 500);
    //     }
    // }

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
        $shortName = $request->short_name;

        // Check if a visitor with the same email/mobile and same school (short_name) is already inside (not checked out)
        $existingVisitor = Visitor::where(function ($q) use ($email, $mobileno) {
            $q
                ->where('email', $email)
                ->orWhere('mobileno', $mobileno);
        })
            ->where('short_name', $shortName)
            ->where(function ($q) {
                $q->whereNull('visit_in_time')->orWhereNull('visit_out_time');
            })
            ->latest()
            ->first();

        if ($existingVisitor) {
            DB::table('token')
                ->where('token', $existingVisitor->token)
                ->update(['token' => null]);

            return response()->json(['alreadyInside' => true]);
        }

        return response()->json(['alreadyInside' => false]);
    }

    // public function getAllVisitors(Request $request)
    // {
    //     $short_name = $request->input('short_name');
    //     $visitors = DB::table('get_visitors')->where('short_name', $short_name)->get();
    //     return response()->json(['data' => $visitors]);
    // }

    // public function getAllVisitor(Request $request)
    // {
    //     // Validate input
    //     $request->validate([
    //         'short_name' => 'required|string',
    //     ]);

    //     // Fetch visitors from database
    //     $short_name = $request->input('short_name');

    //     $visitors = DB::table('get_visitors')
    //         ->where('short_name', $short_name)
    //         ->get();

    //     return response()->json([
    //         'success' => true,
    //         'data' => $visitors,
    //     ]);
    // }

    public function getAllVisitors(Request $request)
    {
        $short_name = $request->input('short_name');
        $visit_date = $request->input('visit_date');

        $query = DB::table('get_visitors')
            ->where('short_name', $short_name);

        if (!empty($visit_date)) {
            $query->whereDate('visit_date', $visit_date);
        }

        $visitors = $query->get();

        return response()->json([
            'data' => $visitors
        ]);
    }

    public function getAllVisitor(Request $request)
    {
        $request->validate([
            'short_name' => 'required|string',
        ]);

        $short_name = $request->input('short_name');

        $visitors = DB::table('get_visitors')
            ->where('short_name', $short_name)
            ->orderByDesc('visit_in_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $visitors,
        ]);
    }

    public function getTodayVisitorsCount(Request $request)
    {
        $request->validate([
            'short_name' => 'required'
        ]);

        $short_name = strtoupper($request->short_name);

        $count = DB::table('get_visitors')
            ->where('short_name', $short_name)
            ->whereDate('visit_date', today())
            ->count();

        return response()->json([
            'short_name' => $short_name,
            'today_visitors_count' => $count
        ]);
    }

    public function saveInTime(Request $request, $id)
    {
        $validated = $request->validate([
            'visit_in_time' => 'required|date_format:Y-m-d H:i:s',
        ]);
        $validated['short_name'] = $request->short_name;
        $validated['user_id'] = $request->user_id;

        $visitor = Visitor::find($id);
        $visitor->visit_in_time = $validated['visit_in_time'];
        $visitor->short_name = $validated['short_name'];
        $visitor->user_id = $validated['user_id'];
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
        $validated['user_id'] = $request->user_id;

        $visitor = Visitor::find($id);
        $visitor->visit_out_time = $validated['visit_out_time'];
        $visitor->short_name = $validated['short_name'];
        $visitor->user_id = $validated['user_id'];
        $visitor->save();

        return response()->json([
            'status' => '200',
            'message' => 'Visit Out time save successfully.',
            'success' => true,
            'data' => $visitor,
        ]);
    }

    // genereate a QR code api with Url and token
    // public function generateTokenAndUrl()
    // {
    //     // Step 2: Generate new token
    //     $token = Str::random(32);
    //     $now = Carbon::now();

    //     // Step 3: Truncate (clear) token table and insert new token
    //     DB::table('token')->truncate();

    //     DB::table('token')->insert([
    //         'token' => $token
    //     ]);

    //     // Step 5: Create frontend URL with token
    //     // $baseUrl = "https://vms.evolvu.in/public/react";  //live
    //     // $baseUrl = "http://localhost:5173";   //local

    //     $baseUrl = "https://vmstest.evolvu.in/public/react";   //test

    //     $urlWithToken = "{$baseUrl}?token={$token}";

    //     return response()->json([
    //         'success' => true,
    //         'base_url' => $baseUrl,
    //         'token' => $token,
    //         'url_with_token' => $urlWithToken
    //     ]);
    // }
    public function generateTokenAndUrl()
    {
        // Generate new token
        $token = Str::random(32);

        // Clear token table and insert new token
        DB::table('token')->truncate();

        DB::table('token')->insert([
            'token' => $token
        ]);

        // Get base URL from .env
        $baseUrl = env('BASE_URL');

        // Create frontend URL with token
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

    public function visitorReport(Request $request)
    {
        $request->validate([
            'short_name' => 'required|string',
            'academic_yr' => 'nullable|string',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
        ]);

        $query = DB::table('get_visitors')
            ->where('short_name', $request->short_name);

        // Filter by Academic Year
        if ($request->filled('academic_yr')) {
            $query->where('academic_yr', $request->academic_yr);
        }

        // Filter by Date Range
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('visit_date', [
                $request->from_date,
                $request->to_date
            ]);
        } elseif ($request->filled('from_date')) {
            $query->whereDate('visit_date', '>=', $request->from_date);
        } elseif ($request->filled('to_date')) {
            $query->whereDate('visit_date', '<=', $request->to_date);
        }

        $visitors = $query
            ->orderBy('visit_date', 'desc')
            ->orderBy('visit_in_time', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count' => $visitors->count(),
            'data' => $visitors,
        ]);
    }
}
