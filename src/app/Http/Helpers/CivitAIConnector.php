<?php

namespace App\Http\Helpers;

use Illuminate\Support\Facades\Storage;

class CivitAIConnector
{
    private static string $baseURL = 'https://civitai.com/api/v1/';

    public static function sendRequest(string $url) : string
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
            if($statusCode == 404){
                return '404';
            }
            return 'Error: CivitAI is not available at the moment! Please try again later!';
        }
        $body = substr($raw_response, $header_size);
        if(str_contains($body, '<title>We\'ll be right back')){
            // the civitAI-API does not give us a 503 or 50x when they are doing maintenance... instead we get a 200...
            // if any of the civitai-staff reads this: IDIOTS! FIX YOUR API! NOW I HAVE TO DO THIS UNPERFORMANT CRAP! -.-
            return 'Error: CivitAI is not available at the moment! Please try again later!';
        }
        if(curl_errno($ch) === 0){
            return $body;
        } else {
            return 'Error: Something went wrong connecting to CivitAI!';
        }
    }

    public static function getModelMetaByID(string $id) : array | string
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
            $requestResponse = self::sendRequest(self::$baseURL.'models/'.$id);
            if($requestResponse != '404' && !str_starts_with($requestResponse, 'Error')){
                $cache->put($cacheFilename, $requestResponse);
            } else {
                return $requestResponse;
            }
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
        if(is_string($modelData)){
            if($modelData == 404 || str_starts_with($modelData, 'Error')){
                return $modelData;
            }
        }
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
        $modelVersions = self::getModelMetaByID($modelID);
        if(is_array($modelVersions)){
            $modelVersions = $modelVersions['modelVersions'];
        } else {
            return [];
        }
        foreach ($modelVersions as $version){
            if($version['id'] == $versionID){
                return $version;
            }
        }
        return [];
    }

    public static function buildCivitAILinkByModelAndVersionID(string $modelID, string $versionID = '') : string
    {
        return 'https://civitai.com/models/'.$modelID.($versionID == '' ? $versionID : '?modelVersionId='.$versionID);
    }

}
