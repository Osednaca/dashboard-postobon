<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $videoNames = [
            'Coca-Cola Summer Vibes', 'Pepsi Max Refresh', 'Sprite Fizz',
            'Fanta Tropical Burst', 'Red Bull Energy Rush', 'Nike Just Do It',
            'Adidas Impossible is Nothing', 'Samsung Galaxy Promo', 'Apple iPhone Launch',
            'McDonalds Big Mac', 'Burger King Flame Grilled', 'KFC Finger Lickin',
            'Toyota Adventure', 'Honda Dreams', 'Cerveza Aguila Chiva',
            'Poker Noche', 'Club Colombia Premium', 'Bavaria Heritage',
            'Postobon Manzana', 'Postobon Uva', 'Colombiana', 'Soda Dietetica',
            'Speed Max Turbo', 'Jet Cola', 'Quatro Frutas', 'Mr Tea',
            'Malta Leona', 'Costeña Refresca', 'Pony Malta', 'Bilz y Pap',
        ];

        $resolutions = ['1920x1080', '1080x1920', '3840x2160', '720x1280', '2560x1440'];
        $resolution = fake()->randomElement($resolutions);
        $extension = fake()->randomElement(['mp4', 'mov', 'avi', 'webm', 'mkv']);
        $fileName = fake()->slug(3) . '_' . $resolution . '.' . $extension;

        $mimeTypes = [
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
        ];

        $duration = fake()->numberBetween(5, 300);
        $size = (int) ($duration * fake()->numberBetween(500, 5000));

        return [
            'name' => fake()->randomElement($videoNames) . ' ' . fake()->year(),
            'original_name' => $fileName,
            'file_path' => 'media/campaigns/' . date('Y/m') . '/' . $fileName,
            'mime_type' => $mimeTypes[$extension],
            'size' => $size,
            'duration' => $duration,
            'thumbnail' => 'media/thumbnails/' . date('Y/m') . '/' . str_replace('.' . $extension, '.jpg', $fileName),
        ];
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => 'video/mp4',
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => 'image/jpeg',
            'original_name' => str_replace(['.mp4', '.mov', '.avi', '.webm', '.mkv'], '.jpg', $attributes['original_name']),
            'file_path' => str_replace(['.mp4', '.mov', '.avi', '.webm', '.mkv'], '.jpg', $attributes['file_path']),
            'duration' => null,
        ]);
    }
}
