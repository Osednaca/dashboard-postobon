import './bootstrap';
import Alpine from 'alpinejs';
import { createDevicePreviews, createPreviewPlayer } from './device-previews';
import { createMediaDurationStore, createMediaPreview } from './media-preview';
import { setupPwa } from './pwa';

window.Alpine = Alpine;
Alpine.data('devicePreviews', createDevicePreviews);
Alpine.data('previewPlayer', createPreviewPlayer);
Alpine.data('mediaPreview', createMediaPreview);
Alpine.store('mediaDurations', createMediaDurationStore());

Alpine.start();
setupPwa();
