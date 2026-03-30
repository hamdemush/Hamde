<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function login(Request $request)
    {
        $response = Http::asForm()->post('https://quiztoxml.ucas.edu.ps/api/login', [
            'username' => $request->username,
            'password' => $request->password,
        ]);

        if ($response->body() == "Wrong username or password") {
            return "Wrong username or password";
        }

        if ($response->successful()) {
            $data = $response->json();

            $token = $data['Token'] ?? null;
            $studentData = $data['data'] ?? null;

            if ($token && $studentData) {
                DB::table('students')->updateOrInsert(
                    ['student_id' => $studentData['user_id']],
                    [
                        'name'  => $studentData['user_ar_name'],
                        'token' => $token,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            return response()->json($data);
        }

        return "Wrong username or password";
    }

    public function getSchedule(Request $request)
    {
        $response = Http::asForm()->post('https://quiztoxml.ucas.edu.ps/api/get-table', [
            'token'   => $request->token,
            'user_id' => $request->user_id,
        ]);

        if ($response->successful()) {
            $fullResponse = $response->json();

            $courses = [];

            if (isset($fullResponse['data']['data']) && is_array($fullResponse['data']['data'])) {
                $courses = $fullResponse['data']['data'];
            } elseif (isset($fullResponse['data']) && is_array($fullResponse['data'])) {
                $courses = $fullResponse['data'];
            } elseif (is_array($fullResponse)) {
                $courses = $fullResponse;
            }

            $insertedCount = 0;

            if (!empty($courses) && is_array($courses)) {
                foreach ($courses as $course) {
                    if (!is_array($course)) continue;

                    $days = [];
                    if (!empty($course['S'])) $days[] = "Saturday: " . $course['S'];
                    if (!empty($course['N'])) $days[] = "Sunday: " . $course['N'];
                    if (!empty($course['M'])) $days[] = "Monday: " . $course['M'];
                    if (!empty($course['T'])) $days[] = "Tuesday: " . $course['T'];
                    if (!empty($course['W'])) $days[] = "Wednesday: " . $course['W'];
                    if (!empty($course['R'])) $days[] = "Thursday: " . $course['R'];

                    DB::table('schedules')->insert([
                        'user_id'     => $request->user_id,
                        'course_name' => $course['subject_name'] ?? ($course['course_name'] ?? 'Unknown Course'),
                        'day'         => implode(' | ', $days),
                        'time'        => 'See day field for details',
                        'room'        => ($course['room_no'] ?? $course['hall_name']) ?: 'Room Not Specified',
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                    $insertedCount++;
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => $insertedCount > 0 ? 'Saved Successfully' : 'No courses array found in response',
                'debug_data_received' => $courses,
                'count'   => $insertedCount
            ]);
        }

        return response()->json(['error' => 'Failed to connect to server'], 400);
    }
}
