<?php

use App\Http\Controllers\Media\FileController;
use App\Http\Controllers\Media\UploadController;
use App\Http\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__ . '/auth.php';

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/user', fn() => auth()->user())->name('user');
    Route::post('/upload', [UploadController::class, 'store'])->name('upload');
    Route::post('/upload/{identifier}/pause', [UploadController::class, 'pause'])->name('upload.pause');
    Route::delete('/upload/{identifier}', [UploadController::class, 'delete'])->name('upload.delete');

    // Merge the following routes into a single route group
    Route::get('/uploads', [UploadController::class, 'index']);
    Route::get('/files', [FileController::class, 'index']);
    Route::delete('/file/{id}', [FileController::class, 'delete']);

    Route::get('/storage/{path}', StorageController::class)->where('path', '.*')->name('storage.path');

    Route::get('/test', function () {
        function readStreamOutput($t, array $current_bucket, float $samplesPerSecond, array $output, int $num_of_samples): array
        {
            while (!feof($t)) {
                $data = fread($t, 4096); // Adjust the buffer size as needed
                $dataLength = strlen($data);

                for ($i = 0; $i < $dataLength; $i += 2) {
                    $current_bucket[] = unpack('s', substr($data, $i, 2))[1];

                    if (count($current_bucket) == $samplesPerSecond) {
                        $output[] = compute_value($current_bucket, $samplesPerSecond, 1, 1);
                        $current_bucket = [];

                        if (count($output) == $num_of_samples) {
                            break;
                        }
                    }
                }
            }

            fclose($t);
            return $output;
        }

        $output = [];
        $current_bucket = [];

        $num_of_samples = 1800;
        $duration = 3670;

        $SR = 44100;
        $nChan = 1;
        $bits = 16;

        $amount_of_samples = ($duration * $SR * $nChan * $bits / 8) / 2; // in bytes
        $spp = floor($amount_of_samples / $num_of_samples); // Samples per pixel

        function compute_value($bucket, $spp, $chans, $channel)
        {
            $max = 0;

            for ($i = 0; $i < $spp; $i += $chans) {
                switch ($channel) {
                    case 1:
                        $av = abs($bucket[$i]);
                        break;
                    case 2:
                        $av = abs($bucket[$i + 1]);
                        break;
                }

                $max = max($max, $av);
            }
            return $max;
        }

        function getPCMStream()
        {
            $streamingOutput = shell_exec("ffmpeg -i "
                . "'/home/maikel/Downloads/barber-harmony-of-hardcore-mashup.wav' "
                . " -ac 1 -ar 44100 -c:a pcm_s16le -f s16le pipe:");

            file_put_contents('pcm_stream.raw', $streamingOutput);

            return fopen('pcm_stream.raw', 'rb');
        }

        $t = getPCMStream();

        if ($t) {
            $output = readStreamOutput($t, $current_bucket, $spp, $output, $num_of_samples);

        }

        return response()->json([
            'output' => $output,
            'meta' => [
                'size' => count($output),
                'samples' => $num_of_samples,
                'duration' => $duration,
                'sample_rate' => $SR,
                'channels' => $nChan,
                'bits' => $bits,
                'amount_of_samples' => $amount_of_samples,
                'samples_per_pixel' => $spp
            ]
        ]);
    });

});

// 01:29:54 in secs is: 5394
