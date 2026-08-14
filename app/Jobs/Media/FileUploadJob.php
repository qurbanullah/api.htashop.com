<?php

namespace App\Jobs\Media;

use App\Actions\Uploads\Idrivee2ImageUploadActions;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class FileUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $path;
    public $image;
    public $name;

    /**
     * Read Data.
     *
     * @param $path  destination path to store file
     * @param $image local complete path of file to be stored
     * @param $name  name of the file by which file to be stored
     */
    public function __construct(string $path, string $image, string $name)
    {
        $this->path = $path;
        $this->image = $image;
        $this->name = $name;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try{
            $result = (new Idrivee2ImageUploadActions)->upload(
                $this->path,
                $this->image,
                $this->name,
            );

            if ($result) {
                Log::info("$result: has been uploaded");

                if (File::delete($this->image)) {
                    Log::info("$result: has been deleted from local storage");
                }
            }
        } catch(Exception $e){
            Log::error(json_encode($e->getMessage()));
        }
    }
}
