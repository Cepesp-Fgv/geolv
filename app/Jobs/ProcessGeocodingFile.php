<?php

namespace GeoLV\Jobs;

use GeoLV\Geocode\CannotProcessFileException;
use GeoLV\Geocode\GeocodingFileProcessor;
use GeoLV\GeocodingFile;
use GeoLV\Mail\DoneGeocodingFile;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;

class ProcessGeocodingFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $file;

    public function __construct()
    {
        $this->file = static::getNextQueueFile();
    }

    public function handle(GeocodingFileProcessor $processor)
    {
        if (empty($this->file))
            return;

        if ($processor->process($this->file, 10) == 0)
            $this->notifyUser();

        dispatch(new ProcessGeocodingFile());
    }

    private function notifyUser()
    {
        Mail::to($this->file->user->email)
            ->send(new DoneGeocodingFile($this->file));
    }

    /**
     * @return mixed
     */
    public static function getNextQueueFile()
    {
        return GeocodingFile::query()
            ->where('done', false)
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->first();
    }

    public static function isProcessing(GeocodingFile $file)
    {
        $next = static::getNextQueueFile();
        return $next && $next->id == $file->id;
    }
}
