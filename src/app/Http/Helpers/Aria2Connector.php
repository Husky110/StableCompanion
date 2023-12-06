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
        if(CivitDownload::where('status', 'active')->count() < 5){
            $aria2Result = self::getInstance()->addUri([$download->url], ['dir' => '/download_tmp']);
            $download->aria_id = $aria2Result['result'];
            $download->save();
        } else {
            $download->status = 'pending';
            $download->save();
        }
    }

    public static function abortDownloadInAria2(CivitDownload $download)
    {
        $aria2 = self::getInstance();
        $status = $aria2->tellStatus($download->aria_id);
        self::getInstance()->forceRemove($download->aria_id);
        sleep(1); // is here, so that aria2 can complete the request - otherwise the aria2-file might not be deleted.
        if($status['result']){
            $file = $status['result']['files'][0]['path'];
            if(file_exists($file)){
                unlink($file);
            }
            $aria2File = $file.'.aria2';
            if(file_exists($aria2File)){
                unlink($aria2File);
            }
        }
    }
}
