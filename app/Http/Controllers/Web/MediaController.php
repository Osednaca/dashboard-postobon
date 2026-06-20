<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignMediaRequest;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use App\Http\Requests\UploadMediaRequest;
use App\Models\Media;
use App\Services\MediaService;
use App\Services\Z2\Z2VideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MediaController extends Controller
{
    /**
     * @var MediaService
     */
    protected MediaService $mediaService;

    /**
     * @var Z2VideoService
     */
    protected Z2VideoService $z2VideoService;

    /**
     * MediaController constructor.
     */
    public function __construct(MediaService $mediaService, Z2VideoService $z2VideoService)
    {
        $this->mediaService = $mediaService;
        $this->z2VideoService = $z2VideoService;
    }

    /**
     * Display a listing of media.
     */
    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', Media::class);

        try {
            $media = Media::paginate(15);

            return view('media.index', compact('media'));
        } catch (\Exception $e) {
            Log::error('Error al listar medios: ' . $e->getMessage());

            return redirect()->route('dashboard.index')
                ->with('error', 'Ocurrió un error al cargar los medios.');
        }
    }

    /**
     * Show the form for creating a new media.
     */
    public function create(): View
    {
        $this->authorize('create', Media::class);

        return view('media.create');
    }

    /**
     * Store a newly created media.
     */
    public function store(StoreMediaRequest $request): RedirectResponse
    {
        $this->authorize('create', Media::class);

        try {
            $file = $request->file('file');
            $name = $request->input('name') ?: $file->getClientOriginalName();
            $localPath = $file->store('media', 'public');

            $data = array_merge($request->validated(), [
                'name' => $name,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $localPath,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            $media = $this->mediaService->create($data);

            // Upload to Z2 FanCloud server
            try {
                $storagePath = storage_path('app/public/' . $localPath);
                $z2Media = $this->z2VideoService->uploadVideo($storagePath, $name, (int) $request->input('duration', 0));

                if ($z2Media) {
                    // Update local record with Z2 uiCode as file_path for consistency with synced media
                    $media->update([
                        'file_path' => $z2Media->file_path,
                        'thumbnail' => $z2Media->thumbnail,
                        'duration' => $z2Media->duration ?: $media->duration,
                    ]);

                    // Clean up the duplicate record created by uploadVideo
                    if ($z2Media->id !== $media->id) {
                        $z2Media->forceDelete();
                    }

                    Log::info('Media uploaded to Z2 cloud successfully', ['media_id' => $media->id, 'uiCode' => $media->file_path]);
                } else {
                    Log::warning('Media saved locally but Z2 cloud upload failed', ['media_id' => $media->id]);
                }
            } catch (\Exception $z2Error) {
                Log::error('Z2 cloud upload error: ' . $z2Error->getMessage(), ['media_id' => $media->id]);
                // Media is saved locally, Z2 upload failed - user can retry via sync
            }

            return redirect()->route('media.index')
                ->with('success', 'Medio creado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al crear medio: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al crear el medio. Por favor intente nuevamente.');
        }
    }

    /**
     * Display the specified media.
     */
    public function show(Media $media): View
    {
        $this->authorize('view', $media);

        try {
            return view('media.show', compact('media'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar medio: ' . $e->getMessage());

            return redirect()->route('media.index')
                ->with('error', 'Ocurrió un error al cargar el medio.');
        }
    }

    /**
     * Show the form for editing the specified media.
     */
    public function edit(Media $media): View
    {
        $this->authorize('update', $media);

        return view('media.edit', compact('media'));
    }

    /**
     * Update the specified media.
     */
    public function update(UpdateMediaRequest $request, Media $media): RedirectResponse
    {
        $this->authorize('update', $media);

        try {
            $this->mediaService->update($media->id, $request->validated());

            return redirect()->route('media.index')
                ->with('success', 'Medio actualizado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al actualizar medio: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al actualizar el medio. Por favor intente nuevamente.');
        }
    }

    /**
     * Remove the specified media.
     */
    public function destroy(Media $media): RedirectResponse
    {
        $this->authorize('delete', $media);

        try {
            $this->z2VideoService->deleteVideo($media->file_path);
            $this->mediaService->delete($media->id);

            return redirect()->route('media.index')
                ->with('success', 'Medio eliminado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar medio: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al eliminar el medio. Por favor intente nuevamente.');
        }
    }

    /**
     * Upload a media file.
     */
    public function upload(UploadMediaRequest $request): RedirectResponse
    {
        $this->authorize('create', Media::class);

        try {
            $file = $request->file('file');
            $name = $request->input('name') ?? $file->getClientOriginalName();

            $media = $this->z2VideoService->uploadVideo($file->path(), $name, (int) $request->input('duration', 0));

            if ($media) {
                return redirect()->route('media.index')
                    ->with('success', 'Archivo subido exitosamente.');
            }

            return back()->with('error', 'Ocurrió un error al subir el archivo a la nube.');
        } catch (\Exception $e) {
            Log::error('Error al subir archivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al subir el archivo. Por favor intente nuevamente.');
        }
    }

    /**
     * Preview the media.
     */
    public function preview(Media $media): View
    {
        $this->authorize('view', $media);

        try {
            $url = $this->mediaService->getUrl($media->id);

            return view('media.preview', compact('media', 'url'));
        } catch (\Exception $e) {
            Log::error('Error al previsualizar medio: ' . $e->getMessage());

            return redirect()->route('media.index')
                ->with('error', 'Ocurrió un error al previsualizar el medio.');
        }
    }

    /**
     * Assign media to a campaign.
     */
    public function assignToCampaign(AssignMediaRequest $request, Media $media): RedirectResponse
    {
        $this->authorize('update', $media);

        try {
            $media->campaigns()->attach($request->validated()['campaign_id']);

            return back()->with('success', 'Medio asignado a la campaña exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al asignar medio a campaña: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al asignar el medio a la campaña.');
        }
    }
}
