import './bootstrap';
import Alpine from 'alpinejs';
import { createDevicePreviews, createPreviewPlayer } from './device-previews';
import { createMediaDurationStore, createMediaPreview } from './media-preview';
import { createLibraryPlayback } from './library-playback';
import { setupPwa } from './pwa';
import { setupDashboardMap } from './dashboard-map';

window.Alpine = Alpine;
Alpine.data('devicePreviews', createDevicePreviews);
Alpine.data('previewPlayer', createPreviewPlayer);
Alpine.data('mediaPreview', createMediaPreview);
Alpine.data('libraryPlayback', createLibraryPlayback);
Alpine.store('mediaDurations', createMediaDurationStore());

Alpine.start();
setupPwa();
document.addEventListener('DOMContentLoaded', () => setupDashboardMap());
