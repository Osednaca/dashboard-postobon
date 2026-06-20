<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$media = App\Models\Media::first();
if ($media) {
    try {
        $view = view('media.show', compact('media'));
        $html = $view->render();
        echo "Success: View rendered\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "No media found\n";
}
