<?php
/**
 * Redis_Server main class
 * 
 * 
 * @category   Redis
 * @package    Redis_Server
 * @copyright  Copyright (c) Aleksandr Lozovuk (http://abrdev.com)
 * @license    New BSD License http://www.opensource.org/licenses/bsd-license.php
 */

 //including Redis Exception defination
 // included in the end of this file
 
 class Redis_Server 
 {
 	/**
 	 * @var default Redis port
 	 */
	private $_defaultPort = 6379;
	
	/**
	 * @var default Redis host
	 */
	private $_defaultHost = 'localhost';
	
	/**
	 * using PHP-extension or pure PHP library
	 * http://code.google.com/p/phpredis
	 * @var 
	 */
	private $using = 'php'; //php or ext
	
	/**
	 * Last error
	 * @var
	 */
	private $_error = null; 
	
	/**
	 * Default timeout to using with sockets,detailed see manual for stream_set_timeout
	 * @var
	 */
	private $_socketTimeout = 30;
	
	
	/**
	 * Experimental - using persistent socket connection (see pfsockopen PHP function)
	 * @var
	 */
	private $_usingPsocketConnection = false;
	
	/**
	 * Experimental - save opened db before destruct object (! not close connection individually)
	 * @var
	 */
	public $_useSaveBeforeClose = false;
	private $_minimalTimeoutForSave = 300; //timeout between save in shutdown 
	private $_sendQuitCommandToServer = true; //using QUIT command before close connection
	
	/** 
	 * Default delimetr 
	 * @var
	 */
	private $_defaultDelimetr = "\r\n";
	
	
	/**
	 * Delimetr in bulk responce, e.g. INFO command
	 * @var
	 */
	private $_keyValueDelimetr = ':';
	
	
	/**
	 * Always using serialization before add
	 * @var
	 */
	public $useSerialization = false;
	
	
	/**
	 * Using if the multiple server instance is used by one application. Default using instance in index 0
	 * @var
	 */
	private $server = Array();
	
	
	
	
	
	/**
	 * 
	 * @return int - index of our server connection or Exception
	 * @param string $host[optional] Host for connect to Redis server
	 * @param integer $port[optional] Port for Redis server
	 * //Depricated @param integer $indexInstance[optional] If multiple instance, set the index (default 0)
	 * @param integer $timeout[optional]
	 * @param Boolean $pconnect[optional] Force using pesistent socket connection 
	 * @param string $using[optional] Option, force using PHP pure library or extension
	 */
	public function __construct($host = null, $port = null, $timeout = 30, $pconnect = false, $using = 'php')
	{
		if ((isset($using)) && ($using == 'ext'))
		{
			if ($this->__check_module() === FALSE)
			{
				//no extension
				throw new Redis_Server_ModuleException('You must compile and enabled redis module before using it');
			}
			else
				$this->using = $using;
		}
		
		
		if (($host == null) || (empty($host)))  $host = $this->_defaultHost;
		if (($port == null) || (empty($port)))  $port = $this->_defaultPort;
		if (($timeout == null) || (empty($timeout)))  $timeout = $this->_socketTimeout;
		if (($pconnect == false) || (empty($pconnect))) $pconnect = $this->_usingPsocketConnection;
		
		$connection_handler = null;
		
		
		if ($this->using == 'php')
		{
		
			if ($pconnect == false)
			{
				$tmp = fsockopen($host, $port, $errno, $this->_error, $timeout);			
			}
			else
				{
					$tmp = pfsockopen($host, $port, $errno, $this->_error, $timeout);	
				}
			
			if ($tmp !== FALSE)
				{
					//It's OK
					$count = array_push($this->server, $tmp);
					return ($count - 1); 
				}
				else
					throw new Redis_Server_SocketException('Can\'t connection from socket, host: ' . $host . ',:' . $port .'. ErrorMsg: ' . $this->_error);

		}
		else
			{
				$tmp = new Redis();
				$tmp->connect($host, $port);
				
				if (!is_bool($tmp))
				{
					$count = array_push($this->server, $tmp);
					return ($count - 1); 
				}
				else
					throw new Redis_Server_SocketException('Can\'t connection from PHP extension. host : ' . $host . ',:' . $port);
			}

		 //NOTE! if using native PHP extension, in the array $server we have Redis instance, in the PHP library - socket descriptor
	}
	
	
	/**
	 * Creating new server connection with options and return it's index or generate Exception
	 * @return 
	 * @param object $host[optional]
	 * @param object $port[optional]
	 * @param object $timeout[optional]
	 * @param object $pconnect[optional]
	 * @param object $using[optional]
	 */
	public function addServer($host = null, $port = null, $timeout = 30, $pconnect = false, $using = 'php')
	{
		return $this->__construct($host, $port, $timeout, $pconnect, $using);		
	}
	
	
	/**
	 * Destruct and closing all connection
	 * @return 
	 */
	public function __destruct()
	{
		foreach ($this->server as $k=>$item)
		{
			if ( (!is_bool($item)) && (isset($item)) )
			{
				if (is_object($item))
				{
					//using Extension
					if ($this->_sendQuitCommandToServer == true)
					{
						$this->server[$k]->close();
					}
					else
						$this->server[$k]->close();
				}
				else
				if (is_resource($item))
				{
					//using socket
					if ($this->_sendQuitCommandToServer == true)
					{
						 $command = 'QUIT' . $this->_defaultDelimetr;						 
						 fwrite($this->server[$k],$command, strlen($command));
					}
					
					if (!fclose($this->server[$k]))
						throw new Redis_Server_Exception('Can\'t closing connection for server instance at index '.$k .' in server\'s list.');
					
				}
			}
		}
		
		return true;
	}
	
	
	
	
	/**
	 * return count of server link or instance, opened at now
	 * @return 
	 */
	public function countServers()
	{
		// simple method - return count($this->server);
		
		//advanced method, with validation
		$count = 0;
		
		foreach ($this->server as $item)
		{
			if ( (!is_bool($item)) && ( (is_object($item)) || (is_resource($item)) ))
			{
				$count++;
			}
		}
		
		return $count;		
	}
	
	
	/**
	 * Commands for Redis server
	 * @see 
	 * 
	 */
	
	/**
	 * SET 
	 * if $useSerialization == true, using serialize()
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $value[optional]
	 * @param object $useSerialization[optional]
	 */
	public function set($instance = 0, $key = null, $value = null, $useSerialization = false)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		if (((empty($key)) || ($key == null)) || ((empty($value)) || ($value == null))) return false;		
		
		
		if ($useSerialization == true)
		{
			$value = serialize($value);
		}		
		
		$value_len = strlen($value . '');
		
		//SET mykey 6\r\nfoobar\r\n
		$this->_commandQuery('SET ' . $key . ' ' . $value_len . $this->_defaultDelimetr . $value . $this->_defaultDelimetr, $instance, null);
		
		$return = $this->_commandResponseSimple($instance);
		
		if ($return != false)
		{
			if ($return == '+OK') return true;
		}
		else
			return false;	
	}
	
	/**
	 * 
	 * @return false if error, null if no key exist, or string value
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 */
	public function get($instance = 0, $key = null, $useSerialization = false)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		if ((empty($key)) || ($key == null)) return false;		
		
		$this->_commandQuery('GET ' . $key . $this->_defaultDelimetr, $instance, null);
		
		$return = $this->_commandResponseSimple($instance);
		$this->_checkForError($instance, $return);

		if ($return != false)
		{
			if ($return == 'nil')  return null;
			else
				{
					$tmp = trim($this->_commandResponseSimple($instance));					
					
					if ($useSerialization == true)
					{
						return unserialize($tmp);
					}	
					else
						return $tmp;
				}
				
		}
		else
			return false;	
	}
	
	
	
	/**
	 * MGET 
	 * @return 
	 * @param object $instance[optional]
	 * @param object $keys[optional]
	 */
	public function mget($instance = 0, $keys = array(), $useSerialization = false)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ((empty($keys)) || (count($keys) == 0)) return false;
		
		$this->_commandQuery('MGET ' . implode(' ', $keys) . $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseSimple($instance);
		$this->_checkForError($instance, $result);
		
		if ($result != false)
		{
			if ($result == 'nil')  return null;
			else
				{
					$return = Array(); //return array
					
					$x = count($keys);
					
					while ($x > 0)
					{
						if ($useSerialization == true)
						{
							$return[] = unserialize($this->_commandResponseValue($instance));
						}
						else
							$return[] = $this->_commandResponseValue($instance);						
						
						$x--;
					}
					
					return $return;					
				}
		}
		else
			return false;		
	}
	
	
	/**
	 * Exists - if return true, keu is existing in db, else return false;
	 * @return Boolean
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 */
	public function exists($instance = 0, $key = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ((empty($key)) || ($key == null)) return false;
		
		$this->_commandQuery('EXISTS ' . $key . $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseInt($instance);
		$this->_checkForError($instance, $result);
		
		if ($result == '0') return false;
		else  
			return true;		
	}
	
	
	
	/**
	 * SETNX
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $value[optional]
	 */
	public function setnx($instance = 0, $key = null, $value = null, $useSerialization = false)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ((empty($key)) || ($key == null)) return false;
		
		if ($this->exists($instance, $key) === true)
		{
			return true;
		}
		else
			{
				return $this->set($instance, $key, $value, $useSerialization);
			}		
	}
	
	
	/**
	 * INKR
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 */
	public function incr($instance = 0, $key = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ((empty($key)) || ($key == null)) return false;
		
		$this->_commandQuery('INCR ' . $key . $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseInt($instance);
		$this->_checkForError($instance, $result);
		
		return $result;		
	}
	
	/**
	 * DECR
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 */
	public function decr($instance = 0, $key = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ((empty($key)) || ($key == null)) return false;
		
		$this->_commandQuery('DECR ' . $key . $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseInt($instance);
		$this->_checkForError($instance, $result);
		
		return $result;		
	}	
	
	/**
	 * INCRBY 
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $by[optional]
	 */
	public function incrby($instance = 0, $key = null, $by = 1)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ((empty($key)) || ($key == null)) return false;
		if (!is_int($by)) $by = 1;
		
		$this->_commandQuery('INCRBY ' . $key . ' ' . $by . $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseInt($instance);
		$this->_checkForError($instance, $result);
		
		return $result;		
	}
	
	/**
	 * DECRBY
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $by[optional]
	 */
	public function decrby($instance = 0, $key = null, $by = 1)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ((empty($key)) || ($key == null)) return false;
		if (!is_int($by)) $by = 1;
		
		$this->_commandQuery('DECRBY ' . $key . ' ' . $by . $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseInt($instance);
		$this->_checkForError($instance, $result);
		
		return $result;		
	}
	
	
	/**
	 * DELete key, alvays return true
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 */
	public function del($instance = 0, $key = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ((empty($key)) || ($key == null)) return true;
		
		$this->_commandQuery('DEL ' . $key . $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseInt($instance);
		$this->_checkForError($instance, $result);
		
		return true;		
	}
	
	/**
	 * Return type of value, associated with key.
	 * Possible: none, string, list, set
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 */
	public function type($instance = 0, $key = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ((empty($key)) || ($key == null)) return false;
		
		$this->_commandQuery('TYPE ' . $key . $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseSimple($instance);
		$this->_checkForError($instance, $result);
		
		return $result;		
	}
	
	
	
	
	
	
	
	
	//======== Service Commands
	
	//PING
	public function ping($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		try
		{
			$this->_commandQuery('PING'. $this->_defaultDelimetr, $instance, null);
		
			$result = $this->_commandResponseSimple($instance);
			
			$this->_checkForError($instance, $result);
			
			if (trim($result) == '+PONG')  return true;
				else
					return false;
		}
		catch (Redis_Exception $e) {
			return $e->getMassages();
		}
	}
	
	
	/**
	 * INFO command
	 * @return Array or false if error (or Exception generated)
	 * @param object $instance[optional]
	 */
	public function info($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$this->_commandQuery('INFO'. $this->_defaultDelimetr, $instance, null);
		
		$return = $this->_commandResponseBulk($instance);
		
		$this->_checkForError($instance, $return);
		
		
		$info = Array();
		
		foreach ($return as $item)
		{
			$tmp = explode($this->_keyValueDelimetr, $item);						
			$info[trim($tmp[0])] = trim($tmp[1]);
		}
		
		return $info;
	}	
	
	/**
	 * QUIT
	 * @return 
	 * @param object $instance[optional]
	 */
	public function quit($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$this->_commandQuery('QUIT '. $this->_defaultDelimetr, $instance, null);
		
		return true;		
	}
	
	/**
	 * DBSIZE - counts key in current db
	 * @return 
	 * @param object $instance[optional]
	 */
	public function dbsize($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$this->_commandQuery('DBSIZE'.$this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseInt($instance);
		$this->_checkForError($instance, $result);
		
		return $result;		
	}
	
	
	/**
	 * SHUTDOWN - Stop all the clients, save the DB, then quit the server. 
	 * This commands makes sure that the DB is switched off without the lost of any data. 
	 * This is not guaranteed if the client uses simply "SAVE" and then "QUIT" 
	 * because other clients may alter the DB data between the two commands. 
	 * @return Boolean
	 * @param object $instance[optional]
	 */
	public function shutdown($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$this->_commandQuery('SHUTDOWN'.$this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseSimple($instance);
		$this->_checkForError($instance, $result);
		
		return true;
	}
	
	/**
	 * LASTSAVE Return the UNIX TIME of the last DB save executed with success
	 * @return 
	 * @param object $instance[optional]
	 */
	public function lastsave($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$this->_commandQuery('LASTSAVE'.$this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseInt($instance);
		$this->_checkForError($instance, $result);
		
		return $result;			
	}
	
	/**
	 * BGSAVE Save the DB in background. The OK code is immediately returned. 
	 * Redis forks, the parent continues to server the clients, the child saves the DB on disk then exit.
	 * @return Boolean true if OK, false if error or Exception
	 * @param object $instance[optional]
	 */
	public function bgsave($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$this->_commandQuery('BGSAVE'.$this->_defaultDelimetr, $instance, null);
		$result = $this->_commandResponseSimple($instance);
		$this->_checkForError($instance, $result);
		
		if ($result == '+OK')  return true;
		else
			return false;
	}
	
	/**
	 * SAVE Save the DB on disk. 
	 * The server hangs while the saving is not completed, no connection is served in the meanwhile. 
	 * An OK code is returned when the DB was fully stored in disk. 
	 * @return 
	 * @param object $instance[optional]
	 */
	public function save($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$this->_commandQuery('SAVE'.$this->_defaultDelimetr, $instance, null);
		$result = $this->_commandResponseSimple($instance);
		$this->_checkForError($instance, $result);
		
		if ($result == '+OK')  return true;
		else
			return false;
	}
	
	/**
	 * FLUSHALL Delete all the keys of all the existing databases, not just the currently selected one. This command never fails. 
	 * @return 
	 * @param object $instance[optional]
	 */
	public function flushall($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$this->_commandQuery('FLUSHALL'.$this->_defaultDelimetr, $instance, null);
		$result = $this->_commandResponseSimple($instance);
		$this->_checkForError($instance, $result);
		
		if ($result == '+OK')  return true;
		else
			return false;
		
	}
	
	/**
	 * FLUSHDB Delete all the keys of the currently selected DB. This command never fails. 
	 * @return 
	 * @param object $instance[optional]
	 */
	public function flushdb($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$this->_commandQuery('FLUSHDB'.$this->_defaultDelimetr, $instance, null);
		$result = $this->_commandResponseSimple($instance);
		$this->_checkForError($instance, $result);
		
		if ($result == '+OK')  return true;
		else
			return false;
		
	}	
	
	/**
	 * SELECT db
	 * @return 
	 * @param object $instance[optional]
	 * @param object $db[optional]
	 */
	public function select($instance = 0, $db = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ((empty($db)) || (!isset($db))) $db = 0;
		
		$this->_commandQuery('SELECT '. $db . $this->_defaultDelimetr, $instance, null);
		$result = $this->_commandResponseSimple($instance);
		$this->_checkForError($instance, $result);
		
		if ($result == '+OK')  return true;
		else
			return false;		
	}	
	
	/**
	 * VERSION
	 * @return 
	 * @param object $instance[optional]
	 */
	public function version($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$this->_commandQuery('VERSION'. $this->_defaultDelimetr, $instance, null);
		$result = $this->_commandResponseSimple($instance);
		$this->_checkForError($instance, $result);
		
		return $result;
	}
		
	
		
	
	//experimental
	private function _checkForError($instance = 0, $resp = null)
	{
        if ((is_bool($resp)) && ($resp == FALSE))  return false;
		if ((is_array($resp)) && ($resp[0] != '-'))  return true;

        if (substr($resp, 0, 4) == '-ERR')
		{
			throw new Redis_Server_Exception("Redis error: " . $resp);
		}
		else
			return false;        
    }
	
	
	/**
	 * Low-level command query
	 * @return 
	 * @param object $command[optional]
	 * @param object $instance[optional]
	 */
	private function _commandQuery($command = 'PING', $instance = 0)
	{
		if ((!isset($command)) || (empty($command))) $command = 'PING' . $this->_defaultDelimetr;
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$command_len = strlen($command);
		
		if ($command_len > (1024 * 1024 * 1024))
			throw new Redis_Server_ValueLengthException('Length of command is too long');
		
		if (($this->using == 'php') && (is_resource($this->server[$instance])))
		{
			$res = fwrite($this->server[$instance], $command, $command_len);
			
			if ($res === FALSE)
				throw new Redis_Server_SocketException('Can\'t write commad in socket at index '.$instance.' in instance array');
			else
				return false;
		}
		else
			throw new Redis_Server_SocketException('Can\'t find open connection at index '.$instance.' in instance array');
	}
	
	
	
	
	/**
	 * Low-level API to get simple responce
	 * @return  false if end of command
	 * @param object $instance[optional]
	 */
	private function _commandResponseSimple($instance = 0)
    {
    	if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$returnBuffer = trim(fgets($this->server[$instance]));
		
		
		if (!empty($returnBuffer)) 
		{
			if (substr($returnBuffer, 0, 4) == '-ERR')
			{
				throw new Redis_Server_Exception('Server return error: ' . $returnBuffer);	
			}
			else
				return $returnBuffer;
		}
		else
			return false;
    }
	
	/**
	 * Integer responce or error
	 * @return 
	 * @param object $instance[optional]
	 */
	private function _commandResponseInt($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$result = (int)trim(fgets($this->server[$instance]));
		
		if ($result == -1)  throw new Redis_Server_KeyException('Key not found');
		if ($result == -2)  throw new Redis_Server_TypeException('Key contains a value of the wrong type');
		if ($result == -3)  throw new Redis_Server_TypeException('Source object and destination object are the same');
		if ($result == -4)  throw new Redis_Server_KeyException('Out of range argument');
		
		return $result;	
	}
	
	
	
	private function _commandResponseValue($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$result = trim(fgets($this->server[$instance]));
		
		if ((int)$result > 0)
		{
			return trim(fgets($this->server[$instance]));
		}
		else
			return null;
	}
	
	
	
	
	/**
	 * Bulk return's, eg. INFO command
	 * @return 
	 * @param object $instance[optional]
	 */
	private function _commandResponseBulk($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$returnBuffer = Array();
		
		$num = (int)trim($this->_commandResponseSimple($instance));
		
		while ($num)
		{
			$tmp = $this->_commandResponseSimple($instance);
			
			if ($tmp === false) break;
			
			$returnBuffer[] = $tmp;
			$num = $num - strlen($tmp);
		}
		
		return $returnBuffer;
	}
	
	
	
	/**
	 * For debug and experiments only! Prepare full command line and execute it, returned raw results
	 * @return 
	 * @param object $instance[optional]
	 * @param object $command[optional]
	 */
	public function _debug($instance = 0, $command = null, $useBulkResponce = false)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ((empty($command)) || (!isset($command))) $command = "PING\r\n";
		
		$command_len = strlen($command);
		
		$res = fwrite($this->server[$instance], $command, $command_len);
		
		if ($useBulkResponce === false)
		{
			return fgets($this->server[$instance]);
		}
		else
			return $this->_commandResponseBulk($instance);		
	}
	
	
	
	
	
	/**
	 * Check, if the extension presents, or use pure PHP
	 * @return 
	 */
	private function __check_module()
	{
		//check if module phpredis
		if (extension_loaded('redis') === FALSE)
		{
			$this->using = 'php'; //set using PHP
			return false;

		}
		else
			return true;		
	}
	
	
	
	
	
 }
 
 
 
 
 
 /**
 * Some Exception's class to Redis_Server
 * 
 * 
 * @category   Redis
 * @package    Redis_Server
 * @copyright  Copyright (c) Aleksandr Lozovuk (http://abrdev.com)
 * @license    New BSD License http://www.opensource.org/licenses/bsd-license.php
 */
 
//general
class Redis_Server_Exception extends Exception {}


//Auth error
class Redis_Server_AuthException extends Redis_Server_Exception {}


//network or socket connection error
class Redis_Server_SocketException extends Redis_Server_Exception {}


//Redis error - no key
class Redis_Server_KeyException extends Redis_Server_Exception {}


//Exception if PHP extension missing
class Redis_Server_ModuleException extends Redis_Server_Exception {}


//Type error - if unknown type or other (e.g. build-in serialize using)
class Redis_Server_TypeException extends Redis_Server_Exception {}

class Redis_Server_SerializeException extends Redis_Server_Exception {}


//If max value length is missing (of couse, one Gb is so big.. but...)
class Redis_Server_ValueLengthException extends Redis_Server_Exception {}


//No specific database selection in select or move commands
class Redis_Server_DatabaseException extends Redis_Server_Exception {}


//Unknown command exception
class Redis_Server_UnknownCommandException extends Redis_Server_Exception {}




