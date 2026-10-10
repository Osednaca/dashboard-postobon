import './bootstrap';
import Alpine from 'alpinejs';
import { createDevicePreviews, createPreviewPlayer } from './device-previews';

window.Alpine = Alpine;
Alpine.data('devicePreviews', createDevicePreviews);
Alpine.data('previewPlayer', createPreviewPlayer);

Alpine.start();
