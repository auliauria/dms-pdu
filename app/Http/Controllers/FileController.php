<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\File;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Resources\FileResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    public function myFiles(Request $request, string $folder = null)
    {
        try {
            Log::info('Authenticated User ID: ' . Auth::id());
            if (!Auth::check()) {
                return response()->json([
                    'message' => 'Unauthenticated'],
                    401);
            }

            $search = $request->get('search');

            if ($folder) {
                $folder = File::query()
                    ->where('created_by', Auth::id())
                    ->where('path', $folder)
                    ->firstOrFail();
            }
            if (!$folder) {
                $folder = $this->getRoot();
            }

            $query = File::query()
                ->select('files.*')
                ->where('created_by', Auth::id())
                ->where('_lft', '!=', 1)
                ->orderBy('is_folder', 'desc')
                ->orderBy('files.created_at', 'desc')
                ->orderBy('files.id', 'desc');

            if ($search) {
                $query->where('name', 'like', "%$search%");
            } else {
                $query->where('parent_id', $folder->id);
            }

            $files = $query->paginate(10);
            $files = FileResource::collection($files);

            $ancestors = FileResource::collection([...$folder->ancestors, $folder]);
            $folder = new FileResource($folder);

            return response()->json([
                'files'     => $files,
                'folder'    => $folder,
                'ancestors' => $ancestors,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch files',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function createFolder(StoreFolderRequest $request)
    {
        try {
            $data = $request->validated();
            $parent = $request->parent ?? $this->getRoot();

            $exists = File::where('parent_id', $parent->id)
                ->where('name', $data['name'])
                ->where('is_folder', 1)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'name' => ["Folder \"{$data['name']}\" already exists in this directory."],
                ]);
            }

            $file = new File();
            $file->is_folder = 1;
            $file->name = $data['name'];
            $parent->appendNode($file);

            return response()->json([
                'message' => 'Folder created successfully',
                'folder'  => new FileResource($file)
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to create folder',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreFileRequest $request)
    {
        try {
            $data = $request->validated();
            $parent = $request->parent ?? $this->getRoot();
            $user   = $request->user();
            $fileTree = $request->file_tree;

            if (!empty($fileTree)) {
                $this->saveFileTree($fileTree, $parent, $user);
            } else {
                foreach ($data['files'] as $file) {
                    $this->saveFile($file, $user, $parent);
                }
            }

            return response()->json([
                'message' => 'Files uploaded successfully',
                'parent'  => new FileResource($parent),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to upload files',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function getRoot()
    {
        return File::query()
            ->whereIsRoot()
            ->where('created_by', Auth::id())
            ->firstOrFail();
    }

    public function saveFileTree($fileTree, $parent, $user)
    {
        foreach ($fileTree as $name => $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $this->saveFile($file, $user, $parent);
            } elseif (is_array($file)) {
                $existing = File::where('parent_id', $parent->id)
                    ->where('name', $name)
                    ->where('is_folder', 1)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'name' => ["Folder \"$name\" already exists in this directory."]
                    ]);
                }

                $folder = new File();
                $folder->is_folder = 1;
                $folder->name = $name;
                $parent->appendNode($folder);

                $this->saveFileTree($file, $folder, $user);
            }
        }
    }

    private function saveFile($file, $user, $parent): void
    {
        $name = $file->getClientOriginalName();

        $existing = File::where('parent_id', $parent->id)
            ->where('name', $name)
            ->where('is_folder', 0)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'files' => ["File \"$name\" already exists in this directory."]
            ]);
        }

        $path = $file->store('/files/' . $user->id, 'public');

        $model = new File();
        $model->storage_path = $path;
        $model->is_folder = false;
        $model->name = $name;
        $model->mime = $file->getMimeType();
        $model->size = $file->getSize();

        $parent->appendNode($model);
    }
}
