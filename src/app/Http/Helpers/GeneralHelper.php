<?php

namespace App\Http\Helpers;

class GeneralHelper
{
    public static function getFilePathWithoutExtension(string $filepath) : string
    {
        $filepath = explode('.', $filepath);
        if(count($filepath) > 1){
            unset($filepath[count($filepath) - 1]);
        }
        $filepath = implode('.', $filepath);
        return $filepath;
    }
}
