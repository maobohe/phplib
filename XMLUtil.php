<?php
namespace Lib;

class XMLUtil
{
    /**
     * 使用  simplexml_load_string 对xml进行转化  对&进行过滤
     * @param unknown $string
     * @return mixed
     * @since 2017年1月3日
     * @copyright
     * @return mixed
     */
    public static function xmlToArray($string)
    {
        $xmlArray = json_decode(json_encode((array)simplexml_load_string($string, 'SimpleXMLElement', LIBXML_NOCDATA)), 1);

        return $xmlArray;
    }

    /**
     * 通过输入的键值生成xml代码
     * @param $key
     * @param $value
     * @return string
     */
    public static function makeXmlBody($key, $value)
    {
        $xmlbody = '<' . $key . '>';

        $xmlbody .= '<![CDATA[' . $value . ']]>';

        $xmlbody .= '</' . $key . '>';
        return $xmlbody;
    }


    /**
     * 转换XML文件编码，主要针对GBK[2312]或者GBK编码的进行处理, 同时去掉可能影响XML解析错误的字符
     *
     * @param string $xml_string 要转换的字符串
     * @param string $toEncode 要转换为的编码,默认为UTF-8
     * @access public
     * @return string
     */
    public static function convertXmlEncode($xml_string, $toEncode = 'UTF-8')
    {
        $now_encode = detect_encode($xml_string);
        $xml_string_arr = explode('?>', $xml_string);
        if (preg_match("/(^<\?xml.*?encoding=\")GB18030|GB2312|GBK(.*)/i", trim($xml_string_arr[0]))) {
            $xml_string_arr[0] = preg_replace("/(^<\?xml.*?encoding=\")GB18030|GB2312|GBK(.*)/i", '${1}' . $toEncode . '${2}', trim($xml_string_arr[0])); //如果头部声明是GB2312则替换为$encode
        }
        $xml_string = implode('?>', $xml_string_arr);
        $xml_string = iconv($now_encode, 'UTF-8//IGNORE', $xml_string);
        $xml_string = str_replace("&", "&amp;", $xml_string);

        return $xml_string;
    }


    /**
     * 将array转换为.net webservice xml参数
     * @param $arr
     * @return string
     */
    public static function arrayToXml($arr)
    {
        $xml = '<?xml version="1.0" encoding="gbk" standalone="yes" ?>';
        $xml .= '<inputObject>';
        $xml_list = '';
        $flag = false;
        foreach ($arr as $key => $value) {
            $type = gettype($value);
            if ($type == "array") {
                $flag = true;
                $xml_list .= '<row>';
                foreach ($value as $vkey => $v) {
                    $xml_list .= self::makeXmlBody($vkey, $v);
                }
                $xml_list .= '</row>';
            } else {
                $xml_list .= self::makeXmlBody($key, $value);
            }
        }
        if ($flag)
            $xml_list = '<dbvalue>' . $xml_list . '</dbvalue>';
        $xml .= $xml_list . '</inputObject>';
        return $xml;
    }
}

?>