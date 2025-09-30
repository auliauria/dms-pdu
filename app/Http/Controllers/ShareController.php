<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Shareable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ShareController extends Controller
{
    public function index(File $fileId){

        Log::info($fileId->id);

        try {
            if (!Auth::check()) {
                return response()->json([
                    'message' => 'Unauthenticated'],
                    401);
            }

            $sharedfile = Shareable::where('file_id', $fileId->id)
                ->with(['user', 'permission'])
                ->get()
                ->map(function ($share) {
                    return [
                        'user' => $share->user->fullname,
                        'email' => $share->user->email,
                        'permission' => $share->permission->name,
                    ];
                });

            return response()->json([
                'success' => true,
                'file_id' => $fileId->id,
                'file_name' => $fileId->name,
                'file_owner' => [
                    'id' => $fileId->user->id,
                    'name' => $fileId->user->fullname,
                    'email' => $fileId->user->email,
                ],
                'data' => $sharedfile,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve shared users: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request, File $file){
        try {
            if (Auth::id() !== $file->created_by) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to share this file.',
            ], 403);
            }

            $validated = $request->validate([
                'permission_id' => 'required|exists:permissions,id',
                'file_id' => 'required|exists:files,id',
                'emails' => 'required|array|min:1',
                'emails.*' => 'email|exists:users,email',
            ]);

            foreach ($validated['emails'] as $email) {
                $user = User::where('email', $email)->first();

                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => "User with email $email not found.",
                    ], 404);
                };

                $user_id = $user->id;

                $existingShare = $file->shares()
                    ->wherePivot('user_id', $user_id)
                    ->wherePivot('permission_id', $validated['permission_id'])
                    ->first();

                if ($existingShare) {
                    $file->shares()->updateExistingPivot(
                        $user_id,
                        [
                            'permission_id' => $validated['permission_id'],
                            'created_by' => Auth::id(),
                        ]
                    );
                } else {
                    $file->shares()->attach(
                        $user_id,
                        [
                            'permission_id' => $validated['permission_id'],
                            'created_by' => Auth::id(),
                        ]
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'File shared successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to share file: ' . $e->getMessage(),
            ], 500);
        }
    }


}
