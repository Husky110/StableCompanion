<?php

namespace App\Http\Helpers;

class WebUIConnector
{
    private static self|null $instance = null;

    public static function getInstance() : self
    {
        if(self::$instance == null){
            self::$instance = new self();
        }
        return self::$instance;
    }


}
