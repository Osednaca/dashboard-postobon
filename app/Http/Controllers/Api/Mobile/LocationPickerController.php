<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\LocationPickerRequest;
use App\Models\Establishment;
use Illuminate\Http\Response;

class LocationPickerController extends Controller
{
    public function __invoke(LocationPickerRequest $request): Response
    {
        $this->authorize('viewAny', Establishment::class);
        $data = $request->validated();

        return response()->view('mobile.location-picker', [
            'nonce' => $data['nonce'],
            'address' => $data['address'] ?? '',
            'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
            'mapsKey' => config('services.google_maps.key'),
        ])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
