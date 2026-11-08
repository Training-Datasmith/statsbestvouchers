<?php

function pSQL($string, $htmlOK = false)
{
    return Db::getInstance()->escape($string, $htmlOK);
}

function bqSQL($string)
{
    return str_replace('`', '\`', pSQL($string));
}

class Configuration
{
    private static $values = array();

    public static function reset()
    {
        self::$values = array();
    }

    public static function set($key, $value)
    {
        self::$values[$key] = $value;
    }

    public static function get($key)
    {
        if (isset(self::$values[$key])) {
            return self::$values[$key];
        }

        return false;
    }
}

class Currency
{
    public $iso_code;
    private static $map = array();

    public function __construct($id)
    {
        $id = (int) $id;
        if (isset(self::$map[$id])) {
            $this->iso_code = self::$map[$id];
        } else {
            $this->iso_code = 'EUR';
        }
    }

    public static function reset()
    {
        self::$map = array();
    }

    public static function register($id, $isoCode)
    {
        self::$map[(int) $id] = $isoCode;
    }
}

class Validate
{
    public static function isName($name)
    {
        $validityPattern = '/^[^0-9!<>,;?=+()@#"°{}_$%:¤|]*$/u';

        return (bool) preg_match($validityPattern, $name);
    }

    public static function isUnsignedInt($value)
    {
        return (string) (int) $value === (string) $value && $value < 4294967296 && $value >= 0;
    }
}

class Shop
{
    const SHARE_ORDER = 'share_order';
    const CONTEXT_SHOP = 1;

    public static function addSqlRestriction($share = false, $alias = null)
    {
        if ($share === self::SHARE_ORDER && $alias === 'o') {
            return ' AND o.id_shop IN (7) ';
        }

        return ' AND 1=0 ';
    }
}

class Tools
{
    public static function getValue($key, $default_value = false)
    {
        if (empty($key) || !is_string($key)) {
            return false;
        }

        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        return $default_value;
    }

    public static function safeOutput($string, $html = false)
    {
        if (!$html) {
            $string = strip_tags($string);
        }

        return htmlentities((string) $string, ENT_QUOTES, 'UTF-8');
    }

    public static function strtoupper($str)
    {
        if (is_array($str)) {
            return false;
        }
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($str, 'utf-8');
        }

        return strtoupper($str);
    }
}

class Db
{
    private static $instances = array();
    private $link;
  private $slave;
    private static $executedSql = array();

    private function __construct($slave)
    {
        $this->slave = (bool) $slave;
        $host = getenv('STATSBESTVOUCHERS_DB_HOST');
        $port = getenv('STATSBESTVOUCHERS_DB_PORT');
        $user = getenv('STATSBESTVOUCHERS_DB_USER');
        $pass = getenv('STATSBESTVOUCHERS_DB_PASSWORD');
        $name = getenv('STATSBESTVOUCHERS_DB_NAME');

        $this->link = mysqli_connect($host, $user, $pass, $name, (int) $port);
        if (!$this->link) {
            throw new RuntimeException('mysqli connect failed: ' . mysqli_connect_error());
        }

        mysqli_query(
            $this->link,
            "SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"
        );

        self::ensureSchema($this->link);
    }

    public static function resetState()
    {
        self::$executedSql = array();
    }

    public static function getExecutedSql()
    {
        return self::$executedSql;
    }

    public static function truncateTables()
    {
        $db = self::getInstance(false);
        $prefix = _DB_PREFIX_;
        mysqli_query($db->link, 'SET FOREIGN_KEY_CHECKS=0');
        mysqli_query($db->link, 'TRUNCATE TABLE `' . $prefix . 'order_cart_rule`');
        mysqli_query($db->link, 'TRUNCATE TABLE `' . $prefix . 'orders`');
        mysqli_query($db->link, 'TRUNCATE TABLE `' . $prefix . 'cart_rule`');
        mysqli_query($db->link, 'SET FOREIGN_KEY_CHECKS=1');
        self::$executedSql = array();
    }

    public static function getInstance($slave = false)
    {
        $key = $slave ? '1' : '0';
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($slave);
        }

        return self::$instances[$key];
    }

    public function escape($string, $htmlOK = false)
    {
        return mysqli_real_escape_string($this->link, (string) $string);
    }

    public function executeS($sql, $array = true, $use_cache = true)
    {
        self::$executedSql[] = $sql;
        $result = mysqli_query($this->link, $sql);
        if ($result === false) {
            return false;
        }
        if (!$array) {
            return $result;
        }
        $rows = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);

        return $rows;
    }

    public function getValue($sql, $use_cache = true)
    {
        self::$executedSql[] = $sql;
        $result = mysqli_query($this->link, $sql);
        if ($result === false) {
            return false;
        }
        $row = mysqli_fetch_array($result, MYSQLI_NUM);
        mysqli_free_result($result);
        if ($row === null) {
            return false;
        }

        return $row[0];
    }

    public function queryRaw($sql)
    {
        return mysqli_query($this->link, $sql);
    }

    private static function ensureSchema($link)
    {
        $prefix = _DB_PREFIX_;
        $queries = array(
            'CREATE TABLE IF NOT EXISTS `' . $prefix . 'orders` (
                `id_order` INT NOT NULL PRIMARY KEY,
                `valid` TINYINT NOT NULL,
                `total_paid_real` DECIMAL(20,6) NOT NULL,
                `conversion_rate` DECIMAL(13,6) NOT NULL,
                `invoice_date` DATETIME NOT NULL,
                `id_shop` INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'CREATE TABLE IF NOT EXISTS `' . $prefix . 'cart_rule` (
                `id_cart_rule` INT NOT NULL PRIMARY KEY,
                `code` VARCHAR(254) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'CREATE TABLE IF NOT EXISTS `' . $prefix . 'order_cart_rule` (
                `id_order_cart_rule` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `id_order` INT NOT NULL,
                `id_cart_rule` INT NOT NULL,
                `name` VARCHAR(254) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
        );
        foreach ($queries as $sql) {
            mysqli_query($link, $sql);
        }
    }
}

class LocaleStub
{
    public function formatPrice($price, $iso)
    {
        if (!is_numeric($price)) {
            return '';
        }

        return number_format((float) $price, 2, '.', '') . ' ' . $iso;
    }
}

class Context
{
  private static $instance;
    public $locale;

    public function __construct()
    {
        $this->locale = new LocaleStub();
    }

    public static function getContext()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset()
    {
        self::$instance = new self();
    }

    public function getCurrentLocale()
    {
        return $this->locale;
    }
}

class Module
{
    public $name;
    public $context;
    protected static $parentInstallResult = true;
    protected static $registerHookResult = true;
    protected static $registeredHooks = array();

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    public static function resetSpies()
    {
        self::$parentInstallResult = true;
        self::$registerHookResult = true;
        self::$registeredHooks = array();
    }

    public static function setParentInstallResult($value)
    {
        self::$parentInstallResult = (bool) $value;
    }

    public static function setRegisterHookResult($value)
    {
        self::$registerHookResult = (bool) $value;
    }

    public static function getRegisteredHooks()
    {
        return self::$registeredHooks;
    }

    public function install()
    {
        return self::$parentInstallResult;
    }

    public function registerHook($hookName)
    {
        self::$registeredHooks[] = $hookName;

        return self::$registerHookResult;
    }

    public function unregisterHook($hookName)
    {
        return true;
    }

    protected function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        $translated = $id;
        if (strpos((string) $domain, 'Modules.') !== 0) {
            $translated = htmlspecialchars($id, ENT_NOQUOTES, 'UTF-8');
        }

        $regexSprintf = '#(?:%%|%(?:[0-9]+\$)?[+-]?(?:[ 0]|\'.)?-?[0-9]*(?:\.[0-9]+)?[bcdeufFosxX])#';
        if (preg_match($regexSprintf, $id)
            && !preg_match('/%\w+%/', $id)
            && !empty($parameters)) {
            $translated = vsprintf($translated, $parameters);
        } elseif (!empty($parameters)) {
            $translated = strtr($translated, $parameters);
        }

        return $translated;
    }
}

class ModuleGrid extends Module
{
    protected $_values = array();
    protected $_totalCount = 0;
    protected $_sort = null;
    protected $_direction = null;
    protected $_start;
    protected $_limit;
    protected $_csv = '';
    public $displayName;
    public $ps_versions_compliancy;

    public function getDate()
    {
        return " '2020-01-01 00:00:00' AND '2020-01-31 23:59:59' ";
    }

    public function engine($params)
    {
        $parts = array('ENGINE', isset($params['title']) ? $params['title'] : '');
        $columnParts = array();
        if (isset($params['columns']) && is_array($params['columns'])) {
            foreach ($params['columns'] as $column) {
                $columnParts[] = $column['id'] . ':' . $column['dataIndex'] . ':' . $column['align'] . ':' . $column['header'];
            }
        }
        $parts[] = implode(',', $columnParts);
        $parts[] = isset($params['defaultSortColumn']) ? $params['defaultSortColumn'] : '';
        $parts[] = isset($params['defaultSortDirection']) ? $params['defaultSortDirection'] : '';
        $parts[] = isset($params['emptyMessage']) ? $params['emptyMessage'] : '';
        $parts[] = isset($params['pagingMessage']) ? $params['pagingMessage'] : '';

        return implode('|', $parts);
    }

    protected function csvExport($datas)
    {
        $this->_sort = $datas['defaultSortColumn'];
        $this->getData();

        $this->_csv = '';
        if (count($datas['columns'])) {
            foreach ($datas['columns'] as $column) {
                $this->_csv .= $column['header'] . ';';
            }
            $this->_csv = rtrim($this->_csv, ';') . "\n";

            foreach ($this->_values as $value) {
                foreach ($datas['columns'] as $column) {
                    $this->_csv .= $value[$column['dataIndex']] . ';';
                }
                $this->_csv = rtrim($this->_csv, ';') . "\n";
            }
        }
        echo $this->_csv;
        exit;
    }
}
