<?php

/**
 * Mysql_Simplebackup class helps to create and restore MySQL database backups
 * 
 * @author Roman Chvanikoff <chvanikoff@gmail.com>
 * @copyright 2011
 * @class Mysql_Simplebackup
 */
class Mysql_Simplebackup {
    
    /**
     * mysql config that will be used by default if no args given in __construct method
     * 
     * @var array
     */
    private $default_config = array(
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
    );
    
    /**
     * unique postfix of backups created with this tool
     * 
     * @var _bu_postfix
     */
    protected $_bu_postfix = '__mysqlsimplebu';
    
    /**
     * Singletone object instance
     * 
     * @var MySQL_Simplebackup
     */
    private static $instance = NULL;
    
    /**
     * MySQL connection initialization
     * 
     * @param array $config
     * @return Mysql_Simplebackup
     */
    private function __construct($config = array())
    {
        $host = Arr::get($config, 'host', $this->default_config['host']);
        $user = Arr::get($config, 'user', $this->default_config['user']);
        $password = Arr::get($config, 'password', $this->default_config['password']);
        
        mysql_connect($host, $user, $password);
    }
    
    /**
     * Cloning of the object is forbidden
     */
    private function __clone() {}
    
    /**
     * singletone
     * 
     * @param array $config
     * @return Mysql_Simplebackup
     */
    public static function factory($config = array())
    {
        (self::$instance === NULL) AND self::$instance = new Mysql_Simplebackup($config);
        
        return self::$instance;
    }
    
    /**
     * Drop database $to and copy database $from to database $to
     * 
     * @return Mysql_Simplebackup
     * @param string $from
     * @param string $to
     */
    private function copy($from, $to)
    {
        mysql_query('DROP DATABASE IF EXISTS `'.$to.'`');
        mysql_query('CREATE DATABASE `'.$to.'`');
        mysql_select_db($from);
        $tables = array();
        $result = mysql_query('SHOW TABLES');
        while ($row = mysql_fetch_row($result))
        {
            $create_query = mysql_fetch_row(mysql_query('SHOW CREATE TABLE `'.$row[0].'`'));
            $tables[$row[0]] = preg_replace(
                '#CREATE TABLE (\`'.$row[0].'\`)#',
                'CREATE TABLE `'.$to.'`.${1}',
                $create_query[1]);
        }
        mysql_query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table_name => $create_query)
        {
            mysql_query($create_query);
            mysql_query('INSERT INTO `'.$to.'`.`'.$table_name.'` SELECT * FROM `'.$from.'`.`'.$table_name.'`');
        }
        mysql_query('SET FOREIGN_KEY_CHECKS=1');
        
        return $this;
    }
    
    /**
     * Create backup of database $db
     * 
     * @param string $db
     * @return Mysql_Simplebackup
     */
    public function backup($db)
    {
        $backup_name = $this->get_bu_name($db);
        $this->copy($db, $backup_name);
        
        return $this;
    }
    
    /**
     * Restore $db database from backup
     * 
     * @param string $db
     * @return Mysql_Simplebackup
     */
    public function restore($db)
    {
        mysql_query('DROP DATABASE `'.$db.'`');
        $this->copy($this->get_bu_name($db), $db);
        mysql_query('DROP DATABASE `'.$this->get_bu_name($db).'`');
        
        return $this;
    }
    
    /**
     * get template name for backup
     * 
     * @param string
     * @return string
     */
    private function get_bu_name($db)
    {
        return $db.$this->_bu_postfix;
    }
    
    /**
     * get backups names
     * 
     * @return array
     */
    public function get_bu_names()
    {
        static $backups = array();
        
        if (empty($backups))
        {
            $databases = $this->get_databases();
            foreach ($databases as $db_name)
            {
                if (preg_match('#'.$this->_bu_postfix.'$#', $db_name))
                {
                    $backups[] = preg_replace('#'.$this->_bu_postfix.'$#', '', $db_name);
                }
            }
        }
        
        return $backups;
    }
    
    /**
     * get names of databases that are not backups
     * 
     * @return array
     */
    public function get_db_names()
    {
        static $db_names = array();
        
        if (empty($db_names))
        {
            $databases = $this->get_databases();
            foreach ($databases as $db_name)
            {
                if ( ! preg_match('#'.$this->_bu_postfix.'$#', $db_name))
                {
                    $db_names[] = preg_replace('#'.$this->_bu_postfix.'$#', '', $db_name);
                }
            }
        }
        
        return $db_names;
    }
    
    /**
     * get all database names
     * 
     * @return array
     */
    private function get_databases()
    {
        static $databases = array();
        
        if (empty($databases))
        {
            $query = mysql_query('SHOW DATABASES');
            while ($row = mysql_fetch_row($query))
            {
                $databases[] = $row[0];
            }
        }
        
        return $databases;
    }
}

/**
 * Array helper.
 *
 * @package    Kohana
 * @category   Helpers
 * @author     Kohana Team
 * @copyright  (c) 2007-2011 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Arr {
    
    /**
	 * Retrieve a single key from an array. If the key does not exist in the
	 * array, the default value will be returned instead.
	 *
	 *     // Get the value "username" from $_POST, if it exists
	 *     $username = Arr::get($_POST, 'username');
	 *
	 *     // Get the value "sorting" from $_GET, if it exists
	 *     $sorting = Arr::get($_GET, 'sorting');
	 *
	 * @param   array   array to extract from
	 * @param   string  key name
	 * @param   mixed   default value
	 * @return  mixed
	 */
    public static function get($array, $key, $default = NULL)
    {
        return (isset($array[$key]))
            ? $array[$key]
            : $default;
    }
}

if ($_POST)
{
    if (isset($_POST['action']))
    {
        if ($_POST['action'] === 'backup' AND isset($_POST['db']) AND ! empty($_POST['db']))
        {
            Mysql_Simplebackup::factory()
                ->backup($_POST['db']);
            echo 'Backup created';
        }
        elseif ($_POST['action'] === 'restore' AND isset($_POST['bu']) AND ! empty($_POST['bu']))
        {
            Mysql_Simplebackup::factory()
                ->restore($_POST['bu']);
            echo 'Database was restored from backup. Backup removed.';
        }
        elseif ($_POST['action'] === 'get_names')
        {
            echo json_encode(Mysql_Simplebackup::factory()->get_bu_names());
        }
    }
    die();
}

$databases = Mysql_Simplebackup::factory()->get_db_names();
$backups = Mysql_Simplebackup::factory()->get_bu_names();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="author" content="Roman Chvanikoff" />

	<title>Mysql SimpleBackup</title>
</head>

<body>

<script type="text/javascript" src="jquery.js"></script>
<script type="text/javascript">

function array_search (needle, haystack, arg_strict) {
    var strict = ! ! arg_strict;
    var key = '';
    for (key in haystack) {
        if ((strict && haystack[key] === needle) || ( ! strict && haystack[key] == needle)) {
            return key;
        }
    }
    return false;
}

$(document).ready(function(){
    
    var url = window.location.pathname;
    
    $("#response").ajaxStart(function(){
        $(this).html('<img src="ajax-loader.gif" alt="loading..."/>');
    });
    
    $('#backup').click(function(){
        var db_name = $('select[name="db"]').val();
        backup_names = new Array;
        $('#backups').find('option').each(function(){
            backup_names.push($(this).val());
        });
        if (array_search(db_name, backup_names)) {
            if ( ! window.confirm('Backup of the database "' + db_name + '" already exists\nDo you want to overwrite it?')) {
                return false;
            }
        }
        $.post(url, {action:"backup", db:db_name}, function(response){
            refresh_backups();
            $('#response').html(response);
        })
        return false;
    });
    $('#restore').click(function(){
        $.post(url, {action:"restore", bu:$('select[name="bu"]').val()}, function(response){
            refresh_backups();
            $('#response').html(response);
        })
        return false;
    });
    
    function refresh_backups() {
        $.post(url, {action:'get_names'}, function(db_names){
            var options = '';
            for (key in db_names) {
                options += '<option value="' + db_names[key] + '">' + db_names[key] + '</option>';
            }
    		$("#backups").html(options);
        }, "json");
    }
});
</script>

<form method="POST">
<p>
    Database:
    &nbsp;
    <select name="db">
        <?php foreach ($databases as $db_name) : ?>
        <option value="<?php echo $db_name; ?>"><?php echo $db_name; ?></option>
        <?php endforeach; ?>
    </select>
    <button id="backup">Create backup</button>
</p>
<p>
    Backup:
    &nbsp;
    <select name="bu" id="backups">
        <?php foreach ($backups as $db_name) : ?>
        <option value="<?php echo $db_name; ?>"><?php echo $db_name; ?></option>
        <?php endforeach; ?>
    </select>
    <button id="restore">Restore backup</button>
</p>
</form>
<div id="response"></div>
</body>
</html>