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

        if ($response->body() == "كلمة المرور او اسم المستخدم خطا") {
            return "كلمة المرور او اسم المستخدم خطا";
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

        return "كلمة المرور او اسم المستخدم خطا";
    }

    public function getSchedule(Request $request)
    {
        $response = Http::asForm()->post('https://quiztoxml.ucas.edu.ps/api/get-table', [
            'token'   => $request->token,
            'user_id' => $request->user_id,
        ]);

        if ($response->successful()) {
            $scheduleData = $response->json();

            if (is_array($scheduleData)) {
                foreach ($scheduleData as $course) {
                    DB::table('schedules')->insert([
                        'user_id'     => $request->user_id,
                        'course_name' => $course['course_ar_name'] ?? $course['course_name'] ?? 'مادة غير معروفة',
                        'day'         => $course['day_name'] ?? '',
                        'time'        => $course['lecture_time'] ?? '',
                        'room'        => $course['hall_name'] ?? '',
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $scheduleData
            ]);
        }

        return response()->json(['error' => 'فشل في جلب الجدول'], 400);
    }
}
