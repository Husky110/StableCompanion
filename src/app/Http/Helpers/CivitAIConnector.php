<?php

namespace App\Http\Helpers;

use Illuminate\Support\Facades\Storage;

class CivitAIConnector
{
    private static string $baseURL = 'https://civitai.com/api/v1/';

    private static function sendRequest(string $url) : string
    {
        $options = array(
            CURLOPT_URL            => $url,
            CURLOPT_HEADER         => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,    // for https
            CURLOPT_VERBOSE        => false,
            CURLOPT_TIMEOUT        => 60,
        );

        $ch = curl_init();

        curl_setopt_array( $ch, $options );
        $raw_response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        if($statusCode >= 400 && $statusCode <= 600){
            throw new \Exception('CivitAI is not available at the moment! Please try again later!');
        }
        $body = substr($raw_response, $header_size);
        if(curl_errno($ch) === 0){
            return $body;
        } else {
            throw new \Exception('Something went wrong connecting to CivitAI!');
        }
    }

    public static function getModelMetaByID(string $id) : array
    {
        $cache = Storage::disk('civitai_cache');
        $cacheFilename = $id.'.json';
        $buildCache = false;
        if(! $cache->exists($cacheFilename)){
            $buildCache = true;
        } else {
            if(!$cache->lastModified($cacheFilename) >= now()->getTimestamp() - 3600){
                $buildCache = true;
                $cache->delete($cacheFilename);
            }
        }
        if($buildCache){
            $cache->put($cacheFilename, self::sendRequest(self::$baseURL.'models/'.$id));
        }
        return json_decode($cache->get($cacheFilename), true);
    }

    public static function extractModelIDFromCivitAIURL(string $url) : string | false
    {
        if(str_starts_with($url, 'https://civitai.com/models/') == false){
            return false;
        }
        $modelID = explode('/', str_replace('https://civitai.com/models/', '', $url))[0];
        if(str_contains($modelID, '?')){
            $modelID = substr($modelID, 0, strpos($modelID, '?'));
        }
        return $modelID;
    }

    public static function getModelTypeByModelID(string $modelID) : string
    {
        $modelData = self::getModelMetaByID($modelID);
        return $modelData['type'];
    }

    public static function getModelVersionsByModelID(string $modelID) : array
    {
        $meta = self::getModelMetaByID($modelID);
        $retval = [];
        foreach ($meta['modelVersions'] as $modelVersion){
            $retval[$modelVersion['id']] = (str_contains($modelVersion['baseModel'], 'XL') ? 'XL: ' : 'SD: '). $modelVersion['name'];
        }
        krsort($retval);
        return $retval;
    }

    public static function getSpecificModelVersionByModelIDAndVersionID(string $modelID, string $versionID) : array
    {
        $modelVersions = self::getModelMetaByID($modelID)['modelVersions'];
        foreach ($modelVersions as $version){
            if($version['id'] == $versionID){
                return $version;
            }
        }
        return [];
    }

}
