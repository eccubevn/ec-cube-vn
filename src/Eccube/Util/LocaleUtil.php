<?php
/**
 * Author: lqdung1992@gmail.com
 * Date: 1/7/2019
 * Time: 2:21 PM
 */

namespace Eccube\Util;


class LocaleUtil
{
    /**
     * Convert path to multi lang
     *
     * @param $path
     * @param null $fileName
     * @param bool $isNeedDefaultFolder
     * @return string
     */
    public static function convertPath($path, $fileName = null, $isNeedDefaultFolder = false)
    {
        $locale = env('ECCUBE_LOCALE', 'ja_JP');
        $locale = str_replace('_', '-', $locale);
        $locales = \Locale::parseLocale($locale);

        if ($isNeedDefaultFolder) {
            $localeDir = is_null($locales) ? 'ja' : $locales['language'];
        } else {
            $localeDir = is_null($locales) ? '' : $locales['language'];
        }

        $lastPath = is_null($fileName) ? '' : DIRECTORY_SEPARATOR . $fileName;
        if (file_exists($localePath = $path . DIRECTORY_SEPARATOR . $localeDir)) {
            return $localePath . $lastPath;
        }

        return $path . $lastPath;
    }
}