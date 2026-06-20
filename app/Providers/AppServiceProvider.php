<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\Group;
use App\Models\Location;
use App\Models\Media;
use App\Models\Notification;
use App\Models\Schedule;
use App\Models\Subscription;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\DevicePolicy;
use App\Policies\GroupPolicy;
use App\Policies\LocationPolicy;
use App\Policies\MediaPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\SchedulePolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Repositories\Contracts\BaseRepositoryInterface::class, \App\Repositories\BaseRepository::class);
        $this->app->bind(\App\Repositories\Contracts\UserRepositoryInterface::class, \App\Repositories\UserRepository::class);
        $this->app->bind(\App\Repositories\Contracts\LocationRepositoryInterface::class, \App\Repositories\LocationRepository::class);
        $this->app->bind(\App\Repositories\Contracts\DeviceRepositoryInterface::class, \App\Repositories\DeviceRepository::class);
        $this->app->bind(\App\Repositories\Contracts\GroupRepositoryInterface::class, \App\Repositories\GroupRepository::class);
        $this->app->bind(\App\Repositories\Contracts\MediaRepositoryInterface::class, \App\Repositories\MediaRepository::class);
        $this->app->bind(\App\Repositories\Contracts\CampaignRepositoryInterface::class, \App\Repositories\CampaignRepository::class);
        $this->app->bind(\App\Repositories\Contracts\ScheduleRepositoryInterface::class, \App\Repositories\ScheduleRepository::class);
        $this->app->bind(\App\Repositories\Contracts\SubscriptionRepositoryInterface::class, \App\Repositories\SubscriptionRepository::class);
        $this->app->bind(\App\Repositories\Contracts\NotificationRepositoryInterface::class, \App\Repositories\NotificationRepository::class);
        $this->app->bind(\App\Repositories\Contracts\AuditLogRepositoryInterface::class, \App\Repositories\AuditLogRepository::class);
        $this->app->bind(\App\Repositories\Contracts\DeviceHeartbeatRepositoryInterface::class, \App\Repositories\DeviceHeartbeatRepository::class);
        $this->app->bind(\App\Repositories\Contracts\CampaignStatisticRepositoryInterface::class, \App\Repositories\CampaignStatisticRepository::class);

        // Z2 Cloud Services
        $this->app->singleton(\App\Services\Z2\FanCloudService::class);
        $this->app->singleton(\App\Services\Z2\Z2AuthService::class);
        $this->app->singleton(\App\Services\Z2\Z2DeviceService::class);
        $this->app->singleton(\App\Services\Z2\Z2GroupService::class);
        $this->app->singleton(\App\Services\Z2\Z2VideoService::class);
        $this->app->singleton(\App\Services\Z2\Z2PlaylistService::class);
        $this->app->singleton(\App\Services\Z2\Z2CampaignSyncService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Device::class, DevicePolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(Schedule::class, SchedulePolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
    }
}
