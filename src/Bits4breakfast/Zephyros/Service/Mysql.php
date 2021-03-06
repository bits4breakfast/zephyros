<?php
namespace Bits4breakfast\Zephyros\Service;

use Bits4breakfast\Zephyros\ServiceContainer;
use Bits4breakfast\Zephyros\ServiceInterface;

class Mysql implements ServiceInterface
{
    const first = 0;
    const all = 1;
    const last = 2;
    const random = 3;
    const notnull = 'zephyros_ActiveRecord_notnull';
    const isnull = 'zephyros_ActiveRecord_isnull';
    const now = 'zephyros_ActiveRecord_now';
    const current_date = 'zephyros_ActiveRecord_current_date';

    private static $now = null;

    private $connections = [];
    protected $container = null;

    private $use_shard = '';

    private $cache = [];

    public function __construct(ServiceContainer $container)
    {
        $shards = array_keys((array)$container->config()->get('database_shards'));
        $this->use_shard = $shards[0];
        $this->container = $container;

        $this->connect('read');
    }

    public function pick($shard)
    {
        $this->use_shard = $shard;
        return $this;
    }

    public function ensure_connection($read_or_write = 'write')
    {
        if (!isset($this->connections[$this->use_shard][$read_or_write])) {
            $this->connect($read_or_write);
        }
        return $this;
    }

    public function connect($read_or_write)
    {
        $available_shards = $this->container->config()->get('database_shards');
        $host = $available_shards[$this->use_shard]['master'];
        
        if ($read_or_write == 'read' && isset($available_shards[$this->use_shard]['replicas']) && !empty($available_shards[$this->use_shard]['replicas'])) {
            shuffle($available_shards[$this->use_shard]['replicas']);
            $replicas = array_merge(array($host), $available_shards[$this->use_shard]['replicas']);
            $host = $replicas[rand(0,count($replicas)-1)];
        }

        $database = (isset($available_shards[$this->use_shard]['database']) ? $available_shards[$this->use_shard]['database'] : $this->container->config()->get('database_database'));
        
        $this->connections[$this->use_shard][$read_or_write] = new \mysqli($host, $this->container->config()->get('database_username'), $this->container->config()->get('database_password'), $database);
        $this->connections[$this->use_shard][$read_or_write]->set_charset('utf8');
    }

    public function read($query, $forceMaster = false)
    {
        $timeStart = microtime();
        if ($forceMaster) {
            if (!isset($this->connections[$this->use_shard]['write'])) {
                $this->connect('write');
            }

            $handler = $this->connections[$this->use_shard]['write'];
        } else {
            if (!isset($this->connections[$this->use_shard]['read'])) {
                $this->connect('read');
            }

            $handler = $this->connections[$this->use_shard]['read'];
        }

        $result = $handler->query($query);
        $timeEnd = microtime();

        if ($result === false) {
            throw new \Exception(
                sprintf('Database error n° %s: %s.', $handler->errno, $handler->error. " SQL=$query")
           );
        } else {
            $this->container->logger()->info(round(($timeEnd - $timeStart) * 1000, 0).': '.$query);
        }

        return $result;
    }

    public function write($query)
    {
        if (!isset($this->connections[$this->use_shard]['write'])) {
            $this->connect('write');
        }

        $timeStart = microtime(true);
        $result = $this->connections[$this->use_shard]['write']->query($query);
        $timeEnd = microtime(true);

        if ($result === false) {
            throw new \Exception(
                sprintf('Database error n° %s: %s.', $this->connections[$this->use_shard]['write']->errno, $this->connections[$this->use_shard]['write']->error. " SQL=".$query)
           );
        } else {
            $this->container->logger()->info(round(($timeEnd - $timeStart) * 1000, 0).': '.$query);
        }

        return $result;
    }

    public function to_clauses($conditions = null)
    {
        if ($conditions === null) {
            return ' 1';
        } else if ( is_string($conditions) ) {
            return $conditions;
        } else {
            $query = '';        
            foreach ( (array)$conditions as $field => $value ) {
                if ( is_numeric($field) && is_array($value) ) {
                    $query .= '(';
                    foreach ( $value as $field => $value ) {
                        if ( $value == self::notnull ) {
                            $query .= '`'.$field.'` IS NOT NULL OR ';
                        } else if ( $value == self::isnull ) {
                            $query .= '`'.$field.'` IS NULL OR ';
                        } else if ( $value != null ) {
                            if ( is_array($value) ) {
                                $query .= '`'.$field.'` IN ("'.implode('","',$value).'") OR ';              
                            } else {
                                $query .= '`'.$field.'` = "'.$this->escape($value).'" OR ';
                            }
                        }   
                    }
                    $query = substr($query,0,-4). ') AND ';
                } else {
                    if ( $value == self::notnull ) {
                        $query .= '`'.$field.'` IS NOT NULL AND ';
                    } else if ( $value == self::isnull ) {
                        $query .= '`'.$field.'` IS NULL AND ';
                    } else if ( $value != null ) {
                        if ( is_array($value) ) {
                            $query .= '`'.$field.'` IN ("'.implode('","',$value).'") AND ';             
                        } else {
                            $query .= '`'.$field.'` = "'.$this->escape($value).'" AND ';
                        }
                    }
                }
            }
            return substr($query,0,-5);
        }
    }

    public function upsert($table, $data, $incrementColumns = null)
    {
        if (!isset($this->connections[$this->use_shard]['write'])) {
            $this->connect('write');
        }
        
        $fields = '';
        $values = '';
        $update = '';
        foreach ((array)$data as $key => $value) {
            $fields .= '`'.$key.'`,';
            if ($value === self::now || $value === self::current_date) {
                $values .= "'" . self::utc_timestamp() . "',";
            } else if ($value === null) {
                $values .= 'NULL,';
            } else {
                $values .= '"'.$this->escape($value).'",';
            }

            if (isset($incrementColumns[$key])) {
                $update .= '`'.$key.'` = `'.$key.'` + '.((float)$value).',';
            } else if ($value === null) {
                $update .= '`'.$key.'` = NULL,';
            } else if ($value === self::now|| $value === self::current_date) {
                $update .= '`'.$key."` = '" . self::utc_timestamp() . "',";
            } else {
                $update .= '`'.$key.'` = "'.$this->escape($value).'",';
            }
        }
        $fields = substr($fields, 0, -1);
        $values = substr($values, 0, -1);
        $update = substr($update, 0, -1);

        return $this->write('INSERT INTO '.$table.' ('.$fields.') VALUES ('.$values.') ON DUPLICATE KEY UPDATE '.$update);
    }

    public function insert($table, $data)
    {
        if (!isset($this->connections[$this->use_shard]['write'])) {
            $this->connect('write');
        }

        $fields = '';
        $values = '';
        foreach ($data as $key => $value) {
            if (!is_numeric($key) && $fields !== false) {
                $fields .= '`'.$key.'`,';
            } else {
                $fields = false;
            }
            if ($value === self::now || $value === self::current_date) {
                $values .= '"'.self::utc_timestamp().'",';
            } else {
                $values .= '"'.$this->escape($value).'",';
            }
        }
        if ($fields != '' && $fields !== false) {
            $fields = substr($fields, 0, -1);
        }
        $values = substr($values, 0, -1);

        return $this->write('INSERT INTO '.$table.($fields != '' ? ' ('.$fields.')' : '').' VALUES ('.$values.')');
    }

    public function update($table, $data, $fields, $limit = 1)
    {
        if (!isset($this->connections[$this->use_shard]['write'])) {
            $this->connect('write');
        }

        $query = 'UPDATE '.$table.' SET';

        foreach ($data as $key => $value) {
            if ($value === self::now || $value === self::current_date) {
                $update .= '`'.$this->escape($key).'` = "'.self::utc_timestamp().'",';
            } else {
                $query .= ' `'.$this->escape($key).'` = "'.$this->escape($value).'",';
            }
        }
        $query = substr($query, 0, -1);

        $query .= ' WHERE '.$this->to_clauses($fields);

        if ($limit > 0) {
            $query .= ' LIMIT '.$limit;
        }

        return $this->write($query);
    }

    public function delete($table, $fields)
    {
        if (!isset($this->connections[$this->use_shard]['write'])) {
            $this->connect('write');
        }
        
        return $this->write('DELETE FROM '.$table.' WHERE '.$this->to_clauses($fields));
    }

    public function select($table, $restrictions, $options = null)
    {
        if (!isset($this->connections[$this->use_shard]['read'])) {
            $this->connect('read');
        }

        $field = isset($options['field']) ? $options['field'] : '*';

        $query = 'SELECT '.$field.' FROM '.$table;

        if (!empty($restrictions)) {
            $query .= ' WHERE '.$this->to_clauses($restrictions);
        }

        if (isset($options['orderby'])) {
            $query .= ' ORDER BY '.$options['orderby'];
        }

        $start = isset($options['start']) ? (int)$options['start'] : 0;
        $limit = (isset($options['limit']) ? (int)$options['limit'] : 0);
        if ($limit > 0) {
            $query .= ' LIMIT '.$start.','.$limit;
        }

        if ($limit == 1) {
            return $this->read($query)->fetch_object();
        } else {
            return $this->read($query);
        }
    }

    public function count($table, $fields = null)
    {
        if (!isset($this->connections[$this->use_shard]['read'])) {
            $this->connect('read');
        }

        $query = 'SELECT COUNT(*) FROM '.$table.' WHERE '.$this->to_clauses($fields);
        
        return (int) $this->result($query);
    }

    public function result($query, $field = false, $forceMaster = true)
    {
        $fingerPrint = md5($query);
        if (!isset($this->cache[$fingerPrint])) {
            $query = $this->read($query, $forceMaster);

            if ($query and $query->num_rows) {
                if ($field) {
                    $temp = $query->fetch_assoc();
                    $this->cache[$fingerPrint] = $temp[$field];
                } else {
                    $temp = $query->fetch_row();
                    $this->cache[$fingerPrint] = $temp[0];
                }
            } else {
                $this->cache[$fingerPrint] = false;
            }
        } else {
            ++$this->hits;
        }

        return $this->cache[$fingerPrint];
    }

    public function affected_rows()
    {
        return $this->connections[$this->use_shard]['write']->affected_rows;
    }

    public function escape($string)
    {
        if ($string == null || is_scalar($string)) {
            return $this->connections[$this->use_shard]['read']->real_escape_string($string);
        } else {
            throw new \InvalidArgumentException('escape function only accepts scalar values. Passed value was: ' . var_export($string, true));
        }
    }

    public function last_id()
    {
        return (int) $this->connections[$this->use_shard]['write']->insert_id;
    }

    public function start_transaction()
    {
        if (!isset($this->connections[$this->use_shard]['write'])) {
            $this->connect('write');
        }

        $this->connections[$this->use_shard]['write']->autocommit(FALSE);
    }

    // FALSE => START TRANSACTION
    public function autocommit($mode) 
    {
        if (!isset($this->connections[$this->use_shard]['write'])) {
            $this->connect('write');
        }

        $this->connections[$this->use_shard]['write']->autocommit($mode);
    }

    public function commit()
    {
        $this->connections[$this->use_shard]['write']->commit();
    }

    public function rollback()
    {
        $this->connections[$this->use_shard]['write']->rollback();
    }

    public function general_rollback()
    {
        foreach ($this->connections as $shard => $connection) {
            if (isset($connection['write'])) {
                $connection['write']->rollback();
            }
        }
    }

    public function read_error()
    {
        return $this->connections[$this->use_shard]['read']->error;
    }

    public function read_errno()
    {
        return $this->connections[$this->use_shard]['read']->errno;
    }

    public function write_error()
    {
        return $this->connections[$this->use_shard]['write']->error;
    }

    public function write_errno()
    {
        return $this->connections[$this->use_shard]['write']->errno;
    }

    public function __destruct() {
        $this->connections = [];
    }

    public static function utc_timestamp()
    {
        if (self::$now == null) {
            self::$now = new \DateTime(null, new \DateTimeZone('UTC'));
        }

        return self::$now->format('Y-m-d H:i:s');
    }
}