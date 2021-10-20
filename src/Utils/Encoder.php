<?php

namespace FileHandler\Utils;

class Encoder {
    public static function getUTFTypes () {
        return [
            'UTF32_BIG_ENDIAN_BOM'    => chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF),
            'UTF32_LITTLE_ENDIAN_BOM' => chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00),
            'UTF16_BIG_ENDIAN_BOM'    => chr(0xFE) . chr(0xFF),
            'UTF16_LITTLE_ENDIAN_BOM' => chr(0xFF) . chr(0xFE),
            'UTF8_BOM'                => chr(0xEF) . chr(0xBB) . chr(0xBF),
        ];
    }

    public static function getUTFType ($key) {
        return Encoder::getUTFTypes()[$key] ?? false;
    }

    public static function getEncoding ($filepath) {
        $text = file_get_contents($filepath);

        $first2 = substr($text, 0, 2);
        $first3 = substr($text, 0, 3);
        $first4 = substr($text, 0, 3);
    
        if ($first3 == Encoder::getUTFType('UTF8_BOM')) return 'UTF-8';
        elseif ($first4 == Encoder::getUTFType('UTF32_BIG_ENDIAN_BOM')) return 'UTF-32BE';
        elseif ($first4 == Encoder::getUTFType('UTF32_LITTLE_ENDIAN_BOM')) return 'UTF-32LE';
        elseif ($first2 == Encoder::getUTFType('UTF16_BIG_ENDIAN_BOM')) return 'UTF-16BE';
        elseif ($first2 == Encoder::getUTFType('UTF16_LITTLE_ENDIAN_BOM')) return 'UTF-16LE';

        if (($enc = mb_detect_encoding($text, ['UTF-7', 'UTF-8', 'ISO-8859-1'], true)) !== false) {
            return $enc;
        }
        return false;
    }

    public static function getDecodedData ($filepath, $to_encoding = "UTF-8", $from_encoding = null) {
        if (($data = @file_get_contents($filepath)) !== false) {
            $str =  mb_convert_encoding($data, $to_encoding, $from_encoding ?? Encoder::getEncoding($filepath));
            return str_ireplace("\xef\xbb\xbf", '', $str);
        }
        return false;
    } 
}

?>