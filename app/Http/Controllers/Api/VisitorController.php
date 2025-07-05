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

    // public function store(Request $request)
    // {
    //     $academicyeardata = DB::table('academic_yr')->where('active', 'Y')->first();
    //     $academic_yr = $academicyeardata->academic_yr ?? null;

    //     $validated = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'mobileno' => 'required|digits:10',
    //         'email' => 'required|string|max:50',
    //         'address' => 'required|string|max:1000',
    //         'purpose' => 'required|string|max:255',
    //         'whomtomeet' => 'required|string|max:255',
    //         'token' => 'required|string',
    //         'token_created_at' => 'required|date',
    //     ]);

    //     // Add system-generated values
    //     $validated['academic_yr'] = $academic_yr;
    //     $validated['visit_date'] = date('Y-m-d');
    //     $validated['visit_in_time'] = date('H:i:s');
    //     $validated['visit_out_time'] = null; // Initially null

    //     // Token already used
    //     if (Visitor::where('token', $validated['token'])->exists()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'This token has already been used.',
    //         ], 403);
    //     }

    //     // Token expired
    //     $createdAt = strtotime($validated['token_created_at']);
    //     if ((time() - $createdAt) > 60) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Token expired. Please scan the QR code again.',
    //         ], 403);
    //     }

    //     // Save visitor
    //     $visitor = new Visitor($validated);
    //     $visitor->save();

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Visitor data saved successfully.',
    //         'data' => $visitor
    //     ], 201);
    // }

    public function store(Request $request)
    {
        $academicyeardata = DB::table('academic_yr')->where('active', 'Y')->first();
        $academic_yr = $academicyeardata->academic_yr ?? null;

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

        // Add system-generated values
        $validated['academic_yr'] = $academic_yr;
        $validated['visit_date'] = date('Y-m-d');
        $validated['visit_in_time'] = date('H:i:s');
        $validated['visit_out_time'] = null; //null
        $validated['short_name'] = $request->short_name;
        // Token already used
        if (Visitor::where('token', $validated['token'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This token has already been used.',
            ], 403);
        }

        // Token expired
        $createdAt = strtotime($validated['token_created_at']);
        if ((time() - $createdAt) > 60) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token expired. Please scan the QR code again.',
            ], 403);
        }

        // Save visitor
        $visitor = new Visitor($validated);
        $visitor->save();

        // Invalidate the token after successful save
        DB::table('token')
            ->where('token', $validated['token'])
            ->update(['token' => null]);

        return response()->json([
            'status' => 'success',
            'message' => 'Visitor data saved successfully. Token invalidated.',
            'data' => $visitor
        ], 201);
    }



    // Recaptch
    // public function store(Request $request)
    // {
    //     //  Verify reCAPTCHA
    //     $recaptcha = $request->input('token');

    //     $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
    //         'secret' => '6LcimWcrAAAAAGga7nnRrHI-BLeCWBCBBPuEftzv',
    //         'response' => $recaptcha,
    //     ]);


    //     $result = $response->json();
    //     if (!$result['success']) {
    //         return response()->json(['message' => 'reCAPTCHA verification failed.'], 403);
    //     }

    //     // Fetch active academic year
    //     $academicyeardata = DB::table('academic_yr')->where('active', 'Y')->first();
    //     $academic_yr = $academicyeardata->academic_yr ?? null;

    //     // Validate form data
    //     $validated = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'mobileno' => 'required|digits:10',
    //         'email' => 'required|string|max:50',
    //         'address' => 'required|string|max:1000',
    //         'purpose' => 'required|string|max:255',
    //         'whomtomeet' => 'required|string|max:255',
    //         'token' => 'required|string',
    //         'token_created_at' => 'required|date',
    //     ]);

    //     // Append system data
    //     $validated['academic_yr'] = $academic_yr;
    //     $validated['visit_date'] = date('Y-m-d');
    //     $validated['visit_in_time'] = date('H:i:s');
    //     $validated['visit_out_time'] = null;

    //     // Check if token already used
    //     if (Visitor::where('token', $validated['token'])->exists()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'This token has already been used.',
    //         ], 403);
    //     }

    //     // Check token expiration
    //     $createdAt = strtotime($validated['token_created_at']);
    //     if ((time() - $createdAt) > 60) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Token expired. Please scan the QR code again.',
    //         ], 403);
    //     }

    //     // Save visitor
    //     $visitor = new Visitor($validated);
    //     $visitor->save();

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Visitor data saved successfully.',
    //         'data' => $visitor
    //     ], 201);
    // }


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
            'message' => 'Otp is incorrect.',
            'success' => false
        ]);
    }

    // public function checkVisitorStatus(Request $request)
    // {
    //     $email = $request->email;
    //     $mobileno = $request->mobileno;

    //     $visitor = Visitor::where(function ($q) use ($email, $mobileno) {
    //         $q->where('email', $email)->orWhere('mobileno', $mobileno);
    //     })
    //         ->whereNull('visit_out_time')
    //         ->latest()
    //         ->first();

    //     if ($visitor) {
    //         return response()->json(['alreadyInside' => true]);
    //     }

    //     return response()->json(['alreadyInside' => false]);
    // }

    public function checkVisitorStatus(Request $request)
    {
        $email = $request->email;
        $mobileno = $request->mobileno;

        $visitor = Visitor::where(function ($q) use ($email, $mobileno) {
            $q->where('email', $email)->orWhere('mobileno', $mobileno);
        })
            ->whereNull('visit_out_time')
            ->latest()
            ->first();

        if ($visitor) {
            // Invalidate the token in the token table
            DB::table('token')
                ->where('token', $visitor->token)
                ->update(['token' => null]);

            return response()->json(['alreadyInside' => true]);
        }

        return response()->json(['alreadyInside' => false]);
    }


    public function getAllVisitors(Request $request)
    {
        $short_name = $request->input('short_name');
        $visitors = DB::table('get_visitors')->where('short_name', $short_name)->get();
        return response()->json(['data' => $visitors]);
    }

    public function saveInTime(Request $request, $id)
    {
        $validated = $request->validate([
            'visit_in_time' => 'required|date_format:H:i:s',
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

    //     $visitor = Visitor::latest()->first();

    //     if (!$visitor) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'No visitors found'
    //         ], 404);
    //     }


    //     $token = Str::random(32);


    //     $visitor->update([
    //         'token' => $token,
    //         'token_created_at' => Carbon::now()
    //     ]);


    //     // $baseUrl = "https://yourdomain.com/visitor-form";
    //     $baseUrl = "localhost:5173";


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
        // Step 1: Get latest visitor (optional)
        $visitor = Visitor::latest()->first();

        if (!$visitor) {
            return response()->json([
                'success' => false,
                'message' => 'No visitors found'
            ], 404);
        }

        // Step 2: Generate new token
        $token = Str::random(32);
        $now = Carbon::now();

        // Step 3: Truncate (clear) token table and insert new token
        DB::table('token')->truncate();

        DB::table('token')->insert([
            'token' => $token
        ]);


        // Step 4: Optionally update visitor table with same token
        $visitor->update([
            'token' => $token,
            'token_created_at' => $now
        ]);

        // Step 5: Create frontend URL with token
        $baseUrl = "https://vms.evolvu/public/react";
        $urlWithToken = "{$baseUrl}?token={$token}";

        // Step 6: Return response
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

        $passedShortName = $request->query('short_name');

        if (!$passedShortName) {
            return response()->json([
                'status' => 400,
                'success' => false,
                'message' => 'Short Name not Found',

            ]);
        }
    }

    public function invalidateToken(Request $request)
    {
        $token = $request->token;

        DB::table('token')->where('token_id', '1')->update(['token' => null]);

        return response()->json(['success' => true, 'message' => 'Token invalidated.']);
    }
}
