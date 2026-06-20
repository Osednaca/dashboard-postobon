<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignMediaRequest;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use App\Http\Requests\UploadMediaRequest;
use App\Models\Media;
use App\Services\CampaignService;
use App\Services\MediaService;
use App\Services\Z2\Z2VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class MediaController extends Controller
{
    /**
     * @var MediaService
     */
    protected MediaService $mediaService;

    /**
     * @var CampaignService
     */
    protected CampaignService $campaignService;

    /**
     * @var Z2VideoService
     */
    protected Z2VideoService $z2VideoService;

    /**
     * MediaController constructor.
     */
    public function __construct(MediaService $mediaService, CampaignService $campaignService, Z2VideoService $z2VideoService)
    {
        $this->mediaService = $mediaService;
        $this->campaignService = $campaignService;
        $this->z2VideoService = $z2VideoService;
    }

    /**
     * Display a paginated listing of media.
     */
    public function index(): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->redirectBrowserToWeb('media.index')) {
            return $redirect;
        }

        try {
            $this->authorize('viewAny', Media::class);
            $this->z2VideoService->syncVideos();
            $media = Media::paginate(request()->input('per_page', 15));

            return response()->json($media);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al listar medios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created media record.
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Media::class);
            $media = $this->mediaService->create($request->validated());

            return response()->json($media, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el medio.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified media.
     */
    public function show(Media $media): JsonResponse
    {
        try {
            $this->authorize('view', $media);
            return response()->json($media);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el medio.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified media.
     */
    public function update(UpdateMediaRequest $request, Media $media): JsonResponse
    {
        try {
            $this->authorize('update', $media);
            $updated = $this->mediaService->update($media->id, $request->validated());

            return response()->json($updated);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el medio.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified media.
     */
    public function destroy(Media $media): JsonResponse
    {
        try {
            $this->authorize('delete', $media);
            $this->z2VideoService->deleteVideo($media->file_path);
            $this->mediaService->delete($media->id);

            return response()->json([
                'message' => 'Medio eliminado correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el medio.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a file and create a media record.
     */
    public function upload(UploadMediaRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Media::class);
            $file = $request->file('file');
            $media = $this->z2VideoService->uploadVideo($file->path(), $file->getClientOriginalName(), (int) $request->input('duration', 0));

            if ($media) {
                return response()->json($media, 201);
            }

            return response()->json([
                'message' => 'Error al subir el archivo a la nube.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al subir el archivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign media to a campaign.
     */
    public function assignToCampaign(AssignMediaRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Media::class);

            $campaignId = $request->validated('campaign_id');
            $mediaIds = $request->validated('media_ids');

            foreach ($mediaIds as $mediaId) {
                $this->campaignService->attachMedia($campaignId, $mediaId);
            }

            return response()->json([
                'message' => 'Medios asignados correctamente a la campaña.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al asignar medios a la campaña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
