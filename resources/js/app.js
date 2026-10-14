import './bootstrap';
import Alpine from 'alpinejs';
import { createDevicePreviews, createPreviewPlayer } from './device-previews';
import { createMediaDurationStore, createMediaPreview } from './media-preview';
import { createLibraryPlayback } from './library-playback';
import { createDeviceList } from './device-list';
import { setupPwa } from './pwa';

window.Alpine = Alpine;
Alpine.data('devicePreviews', createDevicePreviews);
Alpine.data('previewPlayer', createPreviewPlayer);
Alpine.data('mediaPreview', createMediaPreview);
Alpine.data('libraryPlayback', createLibraryPlayback);
Alpine.data('deviceList', createDeviceList);
Alpine.store('mediaDurations', createMediaDurationStore());

Alpine.start();
setupPwa();
