import './bootstrap';
import Alpine from 'alpinejs';
import { createDevicePreviews, createPreviewPlayer } from './device-previews';
import { createMediaDurationStore, createMediaPreview } from './media-preview';
import { createLibraryPlayback } from './library-playback';
import { setupPwa } from './pwa';
import { setupDashboardMap } from './dashboard-map';
import { createCampaignMediaSelector } from './campaign-media-selector';
import { createCampaignTargetSelector } from './campaign-target-selector';

window.Alpine = Alpine;
Alpine.data('devicePreviews', createDevicePreviews);
Alpine.data('previewPlayer', createPreviewPlayer);
Alpine.data('mediaPreview', createMediaPreview);
Alpine.data('libraryPlayback', createLibraryPlayback);
Alpine.data('campaignMediaSelector', createCampaignMediaSelector);
Alpine.data('campaignTargetSelector', createCampaignTargetSelector);
Alpine.store('mediaDurations', createMediaDurationStore());

Alpine.start();
setupPwa();
document.addEventListener('DOMContentLoaded', () => setupDashboardMap());
