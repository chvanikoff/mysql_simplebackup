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
     * MySQL connection initialization
     * 
     * @param array $config
     * @return Mysql_Simplebackup
     */
    public function __construct($config = array())
    {
        $host = Arr::get($config, 'host', $this->default_config['host']);
        $user = Arr::get($config, 'user', $this->default_config['user']);
        $password = Arr::get($config, 'password', $this->default_config['password']);
        
        mysql_connect($host, $user, $password);
    }
    
    /**
     * simple factory wrapper
     * 
     * @param array $config
     * @return Mysql_Simplebackup
     */
    public function factory($config = array())
    {
        return new Mysql_Simplebackup($config);
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
            $tables[$row[0]] = $create_query[1];
        }
        mysql_query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table_name => $create_query)
        {
            $create_query = preg_replace(
                '#CREATE TABLE (\`'.$table_name.'\`)#',
                'CREATE TABLE `'.$to.'`.${1}',
                $create_query);
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
        return $db.'_backup';
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
    if (isset($_POST['action']) AND isset($_POST['db']) AND ! empty($_POST['db']))
    {
        if ($_POST['action'] === 'backup')
        {
            Mysql_Simplebackup::factory()
                ->backup($_POST['db']);
            die('Backup created');
        }
        elseif ($_POST['action'] === 'restore')
        {
            Mysql_Simplebackup::factory()
                ->restore($_POST['db']);
            die('Database was restored from backup. Backup removed.');
        }
    }
    die();
}
    
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
$(document).ready(function(){
    var url = window.location.pathname;
    
    $("#response").ajaxStart(function(){
        $(this).html('<img src="ajax-loader.gif" alt="loading..."/>');
    });
    
    $('#backup').click(function(){
        $.post(url, {action:"backup", db:$('input[name="db"]').val()}, function(response){
            $('#response').html(response);
        })
        return false;
    });
    $('#restore').click(function(){
        $.post(url, {action:"restore", db:$('input[name="db"]').val()}, function(response){
            $('#response').html(response);
        })
        return false;
    });
});
</script>

<form method="POST">
<p>Database: <input name="db" /></p>
<p>
    <button id="backup">Create backup</button>
    <button id="restore">Restore backup</button>
</p>
</form>
<div id="response"></div>

</body>
</html>