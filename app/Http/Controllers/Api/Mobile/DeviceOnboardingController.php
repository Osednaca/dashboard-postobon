<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\DeviceOnboardingActionRequest;
use App\Http\Requests\Mobile\DeviceOnboardingEventRequest;
use App\Http\Requests\Mobile\DeviceOnboardingRequest;
use App\Http\Resources\Mobile\DeviceOnboardingResource;
use App\Models\DeviceOnboarding;
use App\Services\Mobile\DeviceOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceOnboardingController extends Controller
{
    public function __construct(private readonly DeviceOnboardingService $onboardings) {}

    public function store(DeviceOnboardingRequest $request): JsonResponse
    {
        $session = $this->onboardings->prepare($request->user(), $request->validated(), (string) $request->header('Idempotency-Key'));

        return (new DeviceOnboardingResource($session))->response()->setStatusCode(201);
    }

    public function show(Request $request, DeviceOnboarding $onboarding): DeviceOnboardingResource
    {
        return new DeviceOnboardingResource($this->onboardings->status($onboarding, $request->user()));
    }

    public function update(DeviceOnboardingRequest $request, DeviceOnboarding $onboarding): DeviceOnboardingResource
    {
        return new DeviceOnboardingResource($this->onboardings->update($onboarding, $request->user(), $request->validated()));
    }

    public function event(DeviceOnboardingEventRequest $request, DeviceOnboarding $onboarding): DeviceOnboardingResource
    {
        return new DeviceOnboardingResource($this->onboardings->event($onboarding, $request->user(), $request->validated()));
    }

    public function resumeVerification(DeviceOnboardingActionRequest $request, DeviceOnboarding $onboarding): DeviceOnboardingResource
    {
        return new DeviceOnboardingResource($this->onboardings->resumeVerification($onboarding, $request->user()));
    }

    public function complete(DeviceOnboardingActionRequest $request, DeviceOnboarding $onboarding): DeviceOnboardingResource
    {
        return new DeviceOnboardingResource($this->onboardings->complete($onboarding, $request->user()));
    }

    public function cancel(DeviceOnboardingActionRequest $request, DeviceOnboarding $onboarding): DeviceOnboardingResource
    {
        return new DeviceOnboardingResource($this->onboardings->cancel($onboarding, $request->user()));
    }
}
