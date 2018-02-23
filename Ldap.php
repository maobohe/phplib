<?php
namespace Lib;
class Ldap
{

    private $connenct;

    private $base_cn;

    private $attributes;

    private $filterStr;

    private $filterFindStrSymbol;

    private $callbackFun;

    public function __construct($ldap_server)
    {
        $this->connenct = ldap_connect($ldap_server) or die("ldap server connect error!");
    }

    function ldap_link($user, $pass)
    {
        return ldap_bind($this->connenct, $user, $pass);
    }

    /**
     *
     */
    function setBaseCn($base_cn)
    {
        $this->base_cn = $base_cn;
    }

    function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    function setFilter($filterStr, $filterFindStrSymbol = '<>')
    {
        $this->filterFindStrSymbol = $filterFindStrSymbol;
        $this->filterStr = $filterStr;
    }

    function ldap_find($findStr)
    {
        $filter = str_replace($this->filterFindStrSymbol, $findStr, $this->filterStr);
        $result = ldap_search($this->connenct, $this->base_cn, $filter, $this->attributes) or die("服务器忙，请稍候再试！");
        $info = ldap_get_entries($this->connenct, $result);
        return call_user_func($this->callbackFun,$info);
    }

    function setCallBack($function){
        $this->callbackFun=$function;
    }
}
