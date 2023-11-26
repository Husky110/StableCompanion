<?php

namespace App\Http\Helpers;

use App\Models\CivitDownload;

class Aria2Connector
{
    private static \Aria2|null $instance = null;

    public static function getInstance() : \Aria2
    {
        if(self::$instance == null){
            self::$instance = new \Aria2();
        }
        return self::$instance;
    }

    public static function sendDownloadToAria2(CivitDownload $download)
    {
        if(CivitDownload::where('status', 'active')->count() == 0){
            $aria2Result = self::getInstance()->addUri([$download->url], ['dir' => '/download_tmp']);
            $download->aria_id = $aria2Result['result'];
            $download->save();
        }
    }

    public static function sendNextDownloadToAria2()
    {

    }
}
