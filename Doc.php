<?php
/**
 * Created by PhpStorm.
 * Date: 15/12/2
 * Time: 下午6:31
 * 使用方法
 * $phparray = array();
 * $phparray['pageinfo']['total'] = 100;
 * $notes = array();
 * $notes[':pageinfo'] = '分页信息';
 * $notes[':pageinfo:total'] = '总数';
 * $convertJson = new Dang_Convert_Json($notes);
 * $content = $convertJson->convert($phparray);
 * echo $content;
 */

namespace Lib;


class Doc
{
    private $at = 0;
    private $ch = '';
    private $text = '';
    private $_notes;
    private $_htmlNote = '<span style="color: #aaa;">//%s</span>';

    function __construct($notes)
    {
        $this->_notes = $notes;
    }

    public function convert($phparray)
    {
        $result = $this->encode($phparray, 0, "");
        return $result;
    }

    public function encode($arg, $depth = 0, $namespace = '')
    {
        $indentStr = "";
        for ($j = 0; $j < $depth + 1; $j++) {
            $indentStr .= "  ";
        }

        $depth++;

        $returnValue = '';
        $c = '';
        $i = '';
        $l = '';
        $s = '';
        $v = '';
        $numeric = true;

        switch (gettype($arg)) {
            case 'object':
                $arg = (array)($arg);
            case 'array':
                foreach ($arg AS $i => $v) {
                    if (!is_numeric($i)) {
                        $numeric = false;
                        break;
                    }
                }

                //键值为数字型的数组
                if ($numeric) {
                    foreach ($arg AS $i => $v) {
                        if (strlen($s) > 0) {
                            $s .= ',';
                        }

                        //对于字符串类型的一维数组,　单独提供注释方法
                        if (gettype($v) == 'string' || gettype($v) == 'integer') {
                            if (isset($this->_notes[$namespace . ":" . $v])) { //正常的节点,此逻辑必须在第一位
                                $_namespace = $namespace;
                            } elseif (isset($this->_notes[$namespace . ":"])) { //转向的节点
                                $_namespace = $this->_notes[$namespace . ":"];
                            } else { //没有配制的节点,在这里手工配制
                                $_namespace = $namespace;
                            }
                            if (isset($this->_notes[$_namespace . ":" . $v])) {
                                $s .= "\n" . $indentStr . "  " . sprintf($this->_htmlNote, $this->_notes[$_namespace . ":" . $v]);
                            }
                        }

                        //加换行和空格
                        $_s = $this->encode($arg[$i], $depth, $namespace);
                        if (gettype($v) == 'array') {
                            $s .= $_s;
                        } else {
                            $s .= "\n{$indentStr}  " . $_s;
                        }
                    }
                    $returnValue = "\n{$indentStr}[" . $s . "\n$indentStr]";
                    //键值为字符串型的数组
                } else {

                    foreach ($arg AS $i => $v) {
                        if (strlen($s) > 0) {
                            $s .= ',';
                        }

                        if (isset($this->_notes[$namespace . ":" . $i])) { //正常的节点,此逻辑必须在第一位
                            $_namespace = $namespace;
                        } elseif (isset($this->_notes[$namespace . ":"])) { //转向的节点
                            $_namespace = $this->_notes[$namespace . ":"];
                        } else { //没有配制的节点,在这里手工配制
                            $_namespace = $namespace;
                        }

                        //加注释
                        if (isset($this->_notes[$_namespace . ":" . $i])) {
                            $s .= "\n" . $indentStr . "  " . sprintf($this->_htmlNote, $this->_notes[$_namespace . ":" . $i]);
                        }
                        $s .= "\n" . $indentStr . "  " . $this->encode($i, $depth, $_namespace . ":" . $i) . ':' . $this->encode($arg[$i], $depth, $_namespace . ":" . $i);
                    }

                    $returnValue = "\n" . $indentStr . "{" . $s . "\n$indentStr}";
                }
                break;
            case 'integer':
            case 'double':
                $returnValue = is_numeric($arg) ? (string)$arg : 'null';
                break;

            case 'string':
                $returnValue = '"' . strtr($arg, array(
                        //"\r"   => '\\r',    "\n"   => '\\n',    "\t"   => '\\t',
                        "\b" => '\\b',
                        "\f" => '\\f', '\\' => '\\\\', '"' => '\"',
                        "\x00" => '\u0000', "\x01" => '\u0001', "\x02" => '\u0002', "\x03" => '\u0003',
                        "\x04" => '\u0004', "\x05" => '\u0005', "\x06" => '\u0006', "\x07" => '\u0007',
                        "\x08" => '\b', "\x0b" => '\u000b', "\x0c" => '\f', "\x0e" => '\u000e',
                        "\x0f" => '\u000f', "\x10" => '\u0010', "\x11" => '\u0011', "\x12" => '\u0012',
                        "\x13" => '\u0013', "\x14" => '\u0014', "\x15" => '\u0015', "\x16" => '\u0016',
                        "\x17" => '\u0017', "\x18" => '\u0018', "\x19" => '\u0019', "\x1a" => '\u001a',
                        "\x1b" => '\u001b', "\x1c" => '\u001c', "\x1d" => '\u001d', "\x1e" => '\u001e',
                        "\x1f" => '\u001f'
                    )) . '"';
                //内容中有的时候是html内容,需要转码一下
                $returnValue = htmlspecialchars($returnValue);
                break;

            case 'boolean':
                $returnValue = $arg ? 'true' : 'false';
                break;
            default:
//                print_r(gettype($arg));exit;
//                $returnValue = 'null';
        }

        return $returnValue;
    }

}

/*
 * 使用方法
 */
/*
$phparray = array();
$phparray['pageinfo']['total'] = 100;

$notes = array();
$notes[':pageinfo'] = '分页信息';
$notes[':pageinfo:total'] = '总数';

$convertJson = new Dang_Convert_Json($notes);
$content = $convertJson->convert($phparray);
echo $content;
 *
 */

?>
