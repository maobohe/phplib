<?php
namespace Lib;

use \MongoDB\Driver\Manager;
use \MongoDB\Driver\Command;
use \MongoDB\Driver\Query;
use \MongoDB\Driver\BulkWrite;
use \MongoDB\Driver\WriteConcern;
use \MongoDB\Driver\ReadPreference;
use \MongoDB\Driver\ReadConcern;
use \MongoDB\Driver\Cursor;
use \MongoDB\Driver\CursorId;
use \MongoDB\Driver\Server;
use \MongoDB\Driver\WriteConcernError;
use \MongoDB\Driver\WriteError;
use \MongoDB\Driver\WriteResult;
use \MongoDB\BSON\ObjectID;

/**
 * Created by PhpStorm.
 * Date: 16/3/28
 * Time: 下午3:36
 * mongo类
 */
class Mongo
{
    //默认端口
    const DEFAULT_PORT = '27017';

    //默认超时时间
    const DEFAULT_TIMEOUT = 200;

    /**
     *
     * @var array
     */
    protected static $_bulkWriteOptions = array(
        'ordered' => true, //默认批量写入设置  true为串行写入       false为并行写入
    );

    /**
     * 单例池子
     * @var array
     */
    protected static $_mongos = array();

    /**
     * 连接
     * @var Manager
     */
    protected $_manager = null;

    /**
     * 配置
     * @var array
     */
    protected $_config = array();

    //批量写入对象
    protected $_bulkWrite = null;

    protected $_writeConcern = null;

    /**
     * @var array
     */
    protected $_sql = array();

    protected $_db = NULL;

    protected $_exeResult = null;

    //是否读写分类
    protected $_needReplica = false;

    /**
     * cache conf collection
     *
     * @var array
     */
    private static $_adapters = [];

    public function __construct($adapterName)
    {
        if (empty($adapterName)) {
            throw new \Exception('This adapter conf not exist.');
        }
        $this->_createManager($adapterName);
    }

    /**
     *
     * @param string $adapterName
     * @param array $config
     */
    public static function factory($adapterName, $config)
    {
        if (empty(self::$_adapters[$adapterName])) {
            self::$_adapters[$adapterName]['conf'] = $config;
        }
    }

    /**
     * 单例工程 获取实例
     * @param string $adapterName
     * @return array
     */
    public static function getInstance($adapterName)
    {
        if (empty(self::$_mongos[$adapterName])) {
            self::$_mongos[$adapterName] = new self(self::$_adapters[$adapterName]['conf']);
        }
        return self::$_mongos[$adapterName];
    }

    /**
     * 生产连接串
     * @return string
     * @throws Exception
     */
    private function _proManagerString()
    {
        $mongodbUri = "mongodb://{$this->_config['username']}:{$this->_config['password']}@";
        if (!is_array($this->_config['servers'])) {
            throw new Exception('mongo don\'t has server and port');
        }
        foreach ($this->_config['servers'] as $val) {
            $mongodbUri .= $val . ',';
        }
        $mongodbUri = rtrim($mongodbUri, ',');
        $mongodbUri .= "/{$this->_config['database']}";
        //读写分离
        if ($this->_needReplica) {
            $mongodbUri .= '?readPreference=secondaryPreferred';
        }
        return $mongodbUri;
    }

    /**
     * 创建连接对象
     * @param array $config
     */
    protected function _createManager($config)
    {
        if ($this->_manager === null) {

            $this->_config = $config;
            $this->_db = $this->_config['database'];
            $this->_manager = new Manager($this->_proManagerString());
        }
    }

    /**
     * 设置是否读写分离设置
     * @param $need
     */
    public function setReplica($need)
    {
        $this->_needReplica = $need;
    }

    /**
     * 超时时间
     * @param string $mode
     * @param int $timeout
     * @return WriteConcern|null
     */
    public function getWriteConcern($mode = '', $timeout = 0)
    {
        if ($this->_writeConcern === null) {
            $mode = $mode === '' ? WriteConcern::MAJORITY : $mode;
            $timeout = $timeout === '' ? self::DEFAULT_TIMEOUT : $timeout;
            $this->_writeConcern = new WriteConcern($mode, $timeout);
        }
        return $this->_writeConcern;
    }

    /**
     * 批量写入对象
     * @param $option
     * @return BulkWrite
     */
    public function getBuldWrite($option = array())
    {
        if ($this->_bulkWrite === null) {
            $option = $option === '' ? self::$_bulkWriteOptions : $option;
            $this->_bulkWrite = new BulkWrite($option);
        }

        return $this->_bulkWrite;
    }

    /*********-------------------查找-------------------*********/

    /**
     * 查询
     * @param $collection
     * @param $filter
     * @param $options
     * @return array
     */
    public function query($collection, $filter, $options)
    {
        return $this->_manager->executeQuery(
            "{$this->_config['database']}.{$collection}",
            new Query($filter, $options)
        )->toArray();
    }

    /**
     * @param $collection
     * @param array $argv
     * @param array $fields
     * @param array $sort
     * @param int $skip
     * @param int $limit
     * @return array
     */
    public function find($collection, $argv = array(), $fields = array(), $sort = array(), $skip = 0, $limit = 0)
    {
        $options = array();

        if ($skip) {
            $options['skip'] = (int)$skip;
        }

        if ($limit) {
            $options['limit'] = (int)$limit;
        }

        if ($sort) {
            $options['sort'] = $sort;
        }

        if ($fields) {
            if (is_string($fields)) {
                $fields = explode(',', $fields);
            }

            foreach ((array)$fields as $v) {
                $options['projection'][$v] = 1;
            }
        }

        return $this->_manager->executeQuery(
            $this->_config['database'] . '.' . $collection,
            new Query($argv, $options)
        )->toArray();
    }

    /**
     * 获取个数
     * @param $collection
     * @param array $argv
     * @return bool|mixed
     * @throws Exception
     */
    function findCount($collection, $argv = array())
    {

        $rp = new ReadPreference(
            ReadPreference::RP_SECONDARY_PREFERRED,
            [
                ["country" => "iceland", "datacenter" => "west"],
                ["country" => "iceland"],
                [],
            ]
        );
        $count = new Command(["count" => $collection, "query" => $argv]);

        try {
            $result = $this->_manager->executeCommand($this->_db, $count, $rp);
            $response = (array)current($result->toArray());
            if ($response["ok"]) {
                return $response["n"];
            }
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            throw new Exception('Mongo findCount Exception:', $e->getMessage());
        }

        return false;
    }

    /**
     * 据主键获取
     * @param $collection
     * @param string $_id
     * @param array $fields
     * @return mixed|null
     */
    public function findById($collection, $_id = '', $fields = array())
    {
        if (is_string($_id)) {
            return $this->findOne($collection, array('_id' => new ObjectID($_id)), $fields);
        }
    }

    /**
     * 根据主键获取
     * @param $collection
     * @param string $_id
     * @param array $fields
     * @return mixed|null
     */
    public function findId($collection, $_id = '', $fields = array())
    {
        return $this->findOne($collection, array('_id' => $_id), $fields);
    }

    /**
     * 查询获取一行数据
     * @param $collection
     * @param array $argv
     * @param array $fields
     * @param array $sort
     * @return mixed|null
     */
    public function findOne($collection, $argv = array(), $fields = array(), $sort = array())
    {
        $result = $this->find($collection, $argv, $fields, $sort, 0, 1);

        if ($result) {
            return $result[0];
        } else {
            return NULL;
        }
    }

    /*********-------------------增加-------------------*********/

    /**
     * 插入
     * @param $collection
     * @param $data
     * @return mixed
     * @throws Exception
     */
    public function insert($collection, $data)
    {
        $this->getBuldWrite()->insert($data);
        $this->execute($collection)->getModifiedCount();
        return $this;
    }

    /*********-------------------更新-------------------*********/

    /**
     * 通过主键ID更新
     * @param $collection
     * @param $_id
     * @param array $set
     * @return int
     */
    public function updateById($collection, $_id, $set = array())
    {
        return $this->update(
            array('_id' => new ObjectID($_id)),
            $set
        )->execute($collection)->getModifiedCount();
    }

    /**
     * Update MongoDB By Id 通过主键ID更新
     * @param string $collection
     * @param $_id
     * @param array $set
     * @return int
     * @throws Exception
     */
    public function updateId($collection, $_id, $set = array())
    {
        return $this->update(
            array('_id' => $_id),
            $set
        )->execute($collection)->getModifiedCount();
    }


    /**
     * update子句
     * @param option multi 为true则更新全部符合条件的文档,否则只更新一个符合条件的文档
     *              upsert 为true则当没有符合条件的文档时将更新过后的数据插入到集合中
     * 参考连接:http://blog.csdn.net/qq1355541448/article/details/9082225
     * 第二个参数有以下的做法:
     *  修改更新
     *      使用set关键字: $set:让某节点等于给定值 ,字段不变,内容变了
     *  替换更新:
     *      第一个参数$where=array(‘column_name’=>’col709′),第二个参数:$newdata=array(‘column_exp’=>’HHHHHHHHH’,'column_fid’=>123);
     *      那么指定的column_name字段将会替换成成column_exp(=HHHHHHHHH)和column_fid(123)
     *  自动累加或自动累减
     *      array(‘$set’=>$newdata,’$inc’=>array(’91u’=>-5),第二个参数,在找到的91u字段的参数会自动在原值减5
     *  删除指定字段
     *      $where=array(‘column_name’=>’col685′);
     *      $result=$collection->update($where,array(‘$unset’=>’column_exp’));column_exp字段将会被删除
     * 参考文档:https://docs.mongodb.org/manual/reference/operator/update/
     */
    public function update($filter, $set, $options = array('multi' => true, 'upsert' => false))
    {
        $this->getBuldWrite()->update($filter, array('$set' => $set), $options);
        return $this;
    }

    /*********-------------------删除-------------------*********/

    /**
     * 删除
     * @param $condition
     * @param $options
     */
    public function delete($condition, $options = array())
    {
        $this->getBuldWrite()->delete($condition, $options);
    }

    /**
     * 根据主键删除行
     * @param $collection
     * @param $_id
     * @return mixed
     */
    public function removeById($collection, $_id)
    {
        return $this->removeOne(array('_id' => new ObjectID($_id)));
    }

    /**
     * 根据条件删除一行
     * @param $collection
     * @param array $argv
     * @return mixed
     */
    public function removeOne($collection, $argv = array())
    {
        return $this->delete($argv, array('limit' => 1))->execute($collection)->getDeletedCount();
    }

    /**
     * 执行操作
     * @param string $collection
     * @return $this
     * @throws Exception
     */
    public function execute($collection)
    {
        try {
            $this->_exeResult = $this->_manager->executeBulkWrite(
                "{$this->_config['database']}.{$collection}",
                $this->_bulkWrite,
                $this->getWriteConcern()
            );
            $this->_bulkWrite = null;
            $this->_writeConcern = null;
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            $result = $e->getWriteResult();

            // Check if the write concern could not be fulfilled
            $concernError = '';
            if ($writeConcernError = $result->getWriteConcernError()) {
                $concernError = sprintf("%s (%d): %s\n",
                    $writeConcernError->getMessage(),
                    $writeConcernError->getCode(),
                    var_export($writeConcernError->getInfo(), true)
                );
            }

            // Check if any write operations did not complete at all
            $writeError = '';
            foreach ($result->getWriteErrors() as $writeError) {
                $writeError = sprintf("Operation#%d: %s (%d)\n",
                    $writeError->getIndex(),
                    $writeError->getMessage(),
                    $writeError->getCode()
                );
            }

            throw new Exception(sprintf("%s %s", $concernError, $writeError));

        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            throw new Exception('MongoDB argument Exception:' . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\RuntimeException $e) {
            throw new Exception('MongoDB runtime Exception:' . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\ExecutionTimeoutException $e) {
            throw new Exception('MongoDB execution is timeout:' . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
            throw new Exception("MongoDB connection is timeout:\n" . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            throw new Exception("MongoDB Other error: %s\n", $e->getMessage());
        } catch (\Exception $e) {
            throw new Exception("System Excpetion:\n" . $e->getMessage());
        } finally {
            $this->_writeConcern = null;
            $this->_bulkWrite = null;
        }

        return $this;
    }

    /*********-------------------统计响应行数-------------------*********/

    /**
     * 获取删除的行数
     */
    public function getDeletedCount()
    {
        if ($this->_exeResult) {
            return $this->_exeResult->getDeletedCount();
        } else {
            return 0;
        }
    }

    /**
     * 获取实际更新的行数
     */
    public function getModifiedCount()
    {
        if ($this->_exeResult) {
            return $this->_exeResult->getModifiedCount();
        } else {
            return 0;
        }
    }

    /**
     * 一次最多插入9万条以下.耗时
     * 获取实际插入的行数
     */
    public function getInsertedCount()
    {
        if ($this->_exeResult) {
            return $this->_exeResult->getInsertedCount();
        } else {
            return 0;
        }
    }

    /**
     * 获取实际匹配的行数
     */
    public function getMatchedCount()
    {
        if ($this->_exeResult) {
            return $this->_exeResult->getMatchedCount();
        } else {
            return 0;
        }
    }

    /**
     * 获取实际更新失败然后新插入的行数
     *
     */
    public function getUpsertedCount()
    {
        if ($this->_exeResult) {
            return $this->_exeResult->getUpsertedCount();
        } else {
            return 0;
        }
    }

    /**
     * 获取实际更新失败然后新插入的ID列表
     */
    public function getUpsertedIds()
    {
        if ($this->_exeResult) {
            return $this->_exeResult->getUpsertedIds();
        } else {
            return 0;
        }
    }

    /*********-------------------命令行-------------------*********/

    /**
     * 执行命令
     * @param array $command
     * @return bool
     * @throws Exception
     */
    public function runCommand($command = array())
    {
        if (!$command) {
            return false;
        }
        $commandObj = new Command($command);

        try {
            $response = $this->_manager->executeCommand($this->_db, $commandObj)->toArray();
        } catch (\MongoDB\Driver\Exception $e) {
            throw new Exception('Mongo runCommand Exception:', $e->getMessage());
        }

        if (count($response) > 1) {
            return $response;
        } else {
            return $response[0];
        }
    }

    /**
     * MongoDB服务器的相关信息
     * @return bool
     * @throws Exception
     */
    public function buildInfo()
    {
        return $this->runCommand(array('buildinfo' => 1));
    }

    /**
     * 返回指定集合的统计信息，包括数据大小、已分配的存储空间和索引的大小。
     * @param $collection
     * @return bool
     * @throws Exception
     */
    public function collStats($collection)
    {
        return $this->runCommand(array('collstats' => $collection));
    }

    /**
     * 列出指定集合中满足查询条件的文档的指定键的所有不同值
     * @param string $collection
     * @param string $field
     * @param array $filter
     * @return bool
     * @throws Exception
     */
    public function distinct($collection, $field, $filter = array())
    {
        return $this->runCommand(array('key' => $field, 'query' => $filter, 'distinct' => $collection));
    }

    /**
     * 删除集合的所有数据
     * @param string $collection
     * @return bool
     * @throws Exception
     */
    public function drop($collection)
    {
        return $this->runCommand(array('drop' => $collection));
    }

    /**
     * 删除当前数据库中的所有数据
     */
    public function dropDatabase()
    {
        return $this->runCommand(array('dropdatabase' => 1));
    }

    /**
     * 删除集合里面名称为name的索引，如果名称为"*"，则删除全部索引。
     * @param $collection
     * @param string $index
     * @return bool
     * @throws Exception
     */
    public function dropIndexes($collection, $index = '*')
    {
        return $this->runCommand(array('dropIndexes' => $collection, 'index' => $index));
    }

    /**
     * 列出某个集合下所有的索引
     * @param $collection
     * @return bool
     * @throws Exception
     */
    public function listIndexes($collection)
    {
        return $this->runCommand(array('listIndexes' => $collection));
    }

    /**
     * 查找并修改
     * @param $collection
     * @param array $update
     * @param array $filter
     * @return bool
     * @throws Exception
     */
    public function findAndModify($collection, $update = array(), $filter = array())
    {
        return $this->runCommand(array('findAndModify' => $collection, 'query' => $filter, 'update' => $update));
    }

    /**
     * 查看对本集合执行的最后一次操作的错误信息或者其它状态信息。在w台服务器复制集合的最后操作之前，这个命令会阻塞
     */
    public function getLastError()
    {
        return $this->runCommand(array('getLastError' => 1));
    }

    /**
     * 检查本服务器是主服务器还是从服务器
     */
    public function isMaster()
    {
        return $this->runCommand(array('ismaster' => 1));
    }

    /**
     * 返回所有可以在服务器上运行的命令及相关信息。
     */
    public function listCommands()
    {
        return $this->runCommand(array('listCommands' => 1));
    }

    /**
     * 管理专用命令，列出服务器上所有的数据库
     */
    public function listDatabases()
    {
        return $this->setDb('admin')->runCommand(array('listDatabases' => 1));
    }

    /**
     * 检查服务器链接是否正常。即便服务器上锁了，这条命令也会立刻返回
     */
    public function ping()
    {
        return $this->runCommand(array('ping' => 1));
    }

    /**
     * 将集合a重命名为b，其中a和b都必须是完整的集合命名空间（例如"test.foo"代表test数据库中的foo集合）
     * 用法. $fromCollection = 'test.demo' , $toCollection = 'test.demo1' ,一定要加数据库前缀
     * @param $fromCollection
     * @param $toCollection
     * @param bool $dropTarget Optional. If true, mongod will drop the target of renameCollection prior to renaming the collection. The default value is false.
     * @return mixed
     */
    public function renameCollection($fromCollection, $toCollection, $dropTarget = false)
    {
        return $this->setDb('admin')->runCommand(array('renameCollection' => $fromCollection, 'to' => $toCollection, 'dropTarget' => $dropTarget));
    }

    /**
     * 修复并压缩当前数据库，这个操作可能非常耗时。
     */
    public function repairDatabase()
    {
        return $this->setDb('admin')->runCommand(array('repairdatabase' => 1));
    }

    /**
     * 返回这台服务器的管理统计信息。
     * @return mixed
     * @throws Exception
     */
    public function serverStatus()
    {
        return $this->runCommand(array('serverStatus' => 1));
    }

    /**
     * @param string $collection 创建集合（表名）
     * @param array $options = array('autoIndexId','capped','size','max','flags','...');选项很多，请参考API文档
     * @return bool
     * @throws Exception
     */
    public function createCollection($collection, $options = array())
    {
        $options['create'] = $collection;

        return $this->runCommand($options);
    }

    /**
     * 删除集合
     * @param string $collection 集合名称、表名
     * @return bool
     * @throws Exception
     */
    public function dropCollection($collection)
    {
        return $this->runCommand(array('drop' => $collection));
    }


}