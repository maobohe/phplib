<?php
namespace Lib;

/**
 * 分页类
 */
class Pager
{

    // 每页记录数
    public $pageRow = 10;

    /**
     * 总记录数
     */
    protected $totalCount = 0;

    /**
     * 共多少页
     *
     * @var int
     */
    protected $pageCount = 0;

    /**
     * 当前页的页码
     */
    public $currentPage = 1;

    /**
     * 当前页面URL
     * @var string
     */
    protected $pagerLocation;

    /**
     * @var string
     */
    protected $pagerName = null;

    /**
     * 当前页面请求的参数
     * @param string $pagerName
     */
    function __construct($pagerName = "page")
    {
        $this->pagerName = $pagerName;
        if (!empty($_GET[$this->pagerName])) {
            $this->currentPage = $_GET[$this->pagerName];
        }
    }

    public function setPageRow($rowNum)
    {
        $this->pageRow = $rowNum;
    }

    public function setTotalCount($count)
    {
        $this->totalCount = $count;
    }

    function initPagerData()
    {
        // 总页数
        if ($this->totalCount) {
            $this->pageCount = ceil($this->totalCount / $this->pageRow);
        }
    }

    /**
     * 初始化当前页面的URL
     */
    function initPagerLocation()
    {
        $locationArr = explode('?', "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 2);
        $pagerLocation = trim($locationArr[0]);
        if (isset($locationArr[1]) && trim($locationArr[1])) {
            $paraArr = explode('&', $locationArr[1]);
        } else {
            $paraArr = array();
        }
        if ($paraArr) {
            foreach ($paraArr as $key => $para) {
                $_para = explode('=', $para);
                if ($_para[0] == $this->pagerName) {
                    unset($paraArr[$key]);
                }
            }
        }
        if ($paraArr) {
            $this->pagerLocation = $pagerLocation . '?' . implode('&', $paraArr) . "&";
        } else {
            $this->pagerLocation = $pagerLocation . "?";
        }
    }

    function getPrevPageNum()
    {
        $this->initPagerLocation();
        $this->initPagerData();
        if ($this->currentPage > 1) {
            return $this->pagerLocation . $this->pagerName . "=" . ($this->currentPage - 1);
        } else {
            return false;
        }
    }

    function getNextPageNum()
    {
        $this->initPagerLocation();
        $this->initPagerData();
        if ($this->currentPage < $this->pageCount) {
            return $this->pagerLocation . $this->pagerName . "=" . ($this->currentPage + 1);
        } else {
            return false;
        }
    }

    /**
     * 调用分页模板
     * @param string $divClassName
     * @return string
     */
    function tpl($divClassName = 'pagination')
    {
        $startPage = 0;
        $pageNums = null;
        $showPage = null;
        $nextPage = null;
        $first = null;
        $prePage = null;
        $lastPage = null;
        $this->initPagerLocation();
        $this->initPagerData();
        if ($this->pageCount > 0) {
            if ($this->pageCount == 1) {
                return "<div class=\"{$divClassName}\"><p>共 " . $this->pageCount . " 页 (" . $this->totalCount . " 条)</p></div>";
            }
            if ($this->currentPage != 1) {
                $first = "<a href=\"" . $this->pagerLocation . $this->pagerName . "=1\">最前</a>";
                $prePage = "<a href=\"" . $this->pagerLocation . $this->pagerName . "=" . ($this->currentPage - 1) . "\">上一页</a>";
            }
            // 只显示最近的5页
            ($this->currentPage - 2) >= 2 ? $startPage = $this->currentPage - 2 : $startPage = 1;
            ($this->pageCount - $this->currentPage) >= 2 ? $stopPage = $this->currentPage + 2 : $stopPage = $this->pageCount;

            if ($this->pageCount <= 5) {
                $startPage = 1;
                $stopPage = $this->pageCount;
            }
            while ($startPage <= $stopPage) {
                if ($startPage == $this->currentPage) {
                    $showPage = "<li class=\"active\"><a>" . $startPage . "</a></li>";
                } else {
                    $showPage = "<li><a href=\"" . $this->pagerLocation . $this->pagerName . "=" . $startPage . "\">" . $startPage . "</a><li>";
                }
                $pageNums .= $showPage;
                $startPage++;
            }
            if ($this->currentPage != $this->pageCount) {
                $nextPage .= "<a href=\"" . $this->pagerLocation . $this->pagerName . "=" . ($this->currentPage + 1) . "\">下一页</a>";
                $lastPage .= "<a href=\"" . $this->pagerLocation . $this->pagerName . "=" . $this->pageCount . "\">最后</a>";
            }
            return '<div class="' . $divClassName . '">
                    <ul>
                        <li>' . $first . '</li>
                        <li>' . $prePage . '</li>
                        ' . $pageNums . '
                        <li>' . $nextPage . '</li>
                        <li>' . $lastPage . '</li>
                    </ul>
                    <p>共' . $this->pageCount . ' 页 (共 ' . $this->totalCount . ' 条)</p>
                            </div>
            ';
        }
        return null;
    }

    public function limit()
    {
        return $this->pageRow;
    }

    public function offset()
    {
        return ($this->currentPage - 1) * $this->pageRow;
    }

}

?>