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
		
		$this->_commandQuery('SET ' . $key . ' ' .$value_len . $this->_defaultDelimetr . $value . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}
	
	/**
	 * GET
	 * @return false if error, null if no key exist, or string value
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 */
	public function get($instance = 0, $key = null, $useSerialization = false)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		if ((empty($key)) || ($key == null)) return false;		
		
		$this->_commandQuery('GET ' . $key . $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseRead($instance);
		
		if ($result != false)
		{
			if ($useSerialization == true)
			{
				return unserialize($result);
			}	
			else
				return $result;				
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
		
		$result = $this->_commandResponseRead($instance);
		
		if (($result != false) && (is_array($result)))
		{
			if ($useSerialization == false)
			{
				return $result;
			}
			else
				{
					foreach ($result as $k=>$i)
					{
						$result[$k] = unserialize($i);
					}
					
					return $result;
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
		
		$result = $this->_commandResponseRead($instance);
		
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
		
		return $this->_commandResponseRead($instance);
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
		return $this->_commandResponseRead($instance);
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
		
		return $this->_commandResponseRead($instance);	
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
		
		return $this->_commandResponseRead($instance);	
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
		
		$this->_commandResponseRead($instance);	
		
		return true; //always return true
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
		
		return $this->_commandResponseRead($instance);	
	}
	
	
	/**
	 * SORT - Sort the elements contained in the List or Set value at key. 
	 * By defaultsorting is numeric with elements being compared as double precisionfloating point numbers
	 * @return 	 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $by_pattern[optional]
	 * @param object $limit_start[optional]
	 * @param object $limit_end[optional]
	 * @param object $get_pattern[optional]
	 * @param object $ask_desk[optional]
	 * @param object $is_alpha[optional]
	 */
	public function sort($instance = 0, 
	                     $key = null, 
						 $by_pattern = null, 
						 $limit_start = null, 
						 $limit_end = null, 
						 $get_pattern = null, 
						 $ask_desk = null, 
						 $is_alpha = false)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ((empty($key)) || ($key == null)) return false;
		
		$command = 'SORT ' . $key;
		if ((isset($by_pattern)) && (!empty($by_pattern)))
		{
			$command = $command . ' BY ' . $by_pattern;
		}
		
		if ((isset($limit_end)) && (!empty($limit_end)))
		{
			if (empty($limit_start))  $limit_start = 0;
			
			$command = $command . ' LIMIT '. $limit_start .' ' . $limit_end;			
		}
		
		if ((isset($get_pattern)) && (!empty($get_pattern)))
		{
			$command = $command . ' GET ' . $get_pattern;
		}
		
		//default is DESC
		if ((isset($ask_desk)) && (!empty($ask_desk)))
		{
			if (($ask_desk != 'ASC') || ($ask_desk != 'DESC'))
			{
				$ask_desk = 'DESC';
			}
			
			$command = $command . ' ' . $ask_desk;
		}
		
		if ((isset($is_alpha)) && (is_bool($is_alpha)))
		{
			if ($is_alpha == true)
			{
				$command = $command . ' ALPHA';
			}
		}
		
		//
		$this->_commandQuery($command . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}
	
	//========== Commands on Set
	/**
	 * SADD - Add the specified member to the set value stored at key. 
	 * If memberis already a member of the set no operation is performed. 
	 * If keydoes not exist a new set with the specified member as sole member iscrated. 
	 * If the key exists but does not hold a set value an error isreturned.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $member[optional]
	 */
	public function sadd($instance = 0, $key = null, $member = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
		if (empty($member)) return false;
		
		$this->_commandQuery('SADD '. $key . ' ' . strlen($member) . $this->_defaultDelimetr . $member . $this->_defaultDelimetr, $instance, null);

		return $this->_commandResponseRead($instance);	
	}
	
	/**
	 * SREM - Remove the specified member from the set value stored at key. 
	 * If_member_ was not a member of the set no operation is performed. 
	 * If keydoes not exist or does not hold a set value an error is returned.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $member[optional]
	 */
	public function srem($instance = 0, $key = null, $member = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
		if (empty($member)) return false;
		
		$this->_commandQuery('SREM '. $key . ' ' .strlen($member) . $this->_defaultDelimetr . $member . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);		
	}
	
	/**
	 * SCARD - Return the set cardinality (number of elements). 
	 * If the key does notexist 0 is returned, like for empty sets. 
	 * If the key does not holda set value -1 is returned. 
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 */
	public function scard($instance = 0, $key = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
				
		$this->_commandQuery('SCARD '. $key . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);		
	}
	
	/**
	 * SISMEMBER - Return 1 if member is a member of the set stored at key, otherwise0 is returned. 
	 * On error a negative value is returned.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $member[optional]
	 */
	public function sismember($instance = 0, $key = null, $member = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
		if (empty($member)) return false;
		
		$this->_commandQuery('SREM '. $key . ' ' . strlen($member) . $this->_defaultDelimetr . $member . $this->_defaultDelimetr, $instance, null);
		
		$tmp = $this->_commandResponseRead($instance);	
		
		if ($tmp == 1) return true;
		else
			return false;			
	}
	
	/**
	 * SINTER - Time complexity O(NM) worst case where N is the cardinality of the smallest set and M the number of sets_
     * Return the members of a set resulting from the intersection of all thesets hold at the specified keys. 
     * Like in LRANGE the result is sent tothe client as a multi-bulk reply (see the protocol specification formore information). 
     * If just a single key is specified, then this commandproduces the same result as SELEMENTS. 
     * Actually SELEMENTS is just syntaxsugar for SINTERSECT.
	 * @return 
	 * @param object $instance[optional]
	 * @param Array $key[optional]
	 */
	public function sinter($instance = 0, $keys = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($keys)) return false;
		if ((is_array($keys)) && (count($keys) < 1)) return false;
		
		$str = implode(' ', $keys);
		
		$this->_commandQuery('SINTER '. $str . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);		
	}
	
	/**
	 * SINTERSTORE - Time complexity O(NM) worst case where N is the cardinality of the smallest set and M the number of sets_
     * This commnad works exactly like SINTER but instead of being returned the resulting set is sotred as _dstkey_.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $keys[optional]
	 */
	public function sinterstore($instance = 0, $dstkey = null, $keys = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($keys)) return false;
		if (empty($dstkey)) return false;
		if ((is_array($keys)) && (count($keys) < 1)) return false;
		
		$str = implode(' ', $keys);
		
		$this->_commandQuery('SINTERSTORE '. $dstkey . ' ' . $str . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
		// 
	}
	
	/**
	 * SMEMBERS - Return all the members (elements) of the set value stored at key.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 */
	public function smembers($instance = 0, $key = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
		
		$this->_commandQuery('SMEMBERS '. $key . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);	
	}
	
	
	//=============== List Command
	// RPUSH
	
	/**
	 * RPUSH - Add the string value to the head (RPUSH) or tail (LPUSH) of the liststored at key. 
	 * If the key does not exist an empty list is created just beforethe append operation. 
	 * If the key exists but is not a List an erroris returned.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $value[optional]
	 */
	public function rpush($instance = 0, $key = null, $value = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
		if (empty($value)) return false;
		
		$this->_commandQuery('RPUSH '. $key . ' ' . strlen($value) . $this->_defaultDelimetr . $value . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}
	
	
	/**
	 * LPUSH - Add the string value to the head (RPUSH) or tail (LPUSH) of the liststored at key. 
	 * If the key does not exist an empty list is created just beforethe append operation. 
	 * If the key exists but is not a List an erroris returned. 
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $value[optional]
	 */
	public function lpush($instance = 0, $key = null, $value = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
		if (empty($value)) return false;
		
		$this->_commandQuery('LPUSH '. $key . ' ' . strlen($value) . $this->_defaultDelimetr . $value . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}	
	
	/**
	 * LLEN - Return the length of the list stored at the specified key. 
	 * If thekey does not exist zero is returned (the same behaviour as forempty lists). 
	 * If the value stored at key is not a list an error is returned.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 */
	public function llen($instance = 0, $key = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
		
		$this->_commandQuery('LLEN '. $key . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}
	
	/**
	 * LRANGE - Return the specified elements of the list stored at the specifiedkey. 
	 * Start and end are zero-based indexes. 0 is the first elementof the list (the list head), 1 the next element and so on.
	 * For example LRANGE foobar 0 2 will return the first three elementsof the list.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $start[optional]
	 * @param object $end[optional]
	 */
	public function lrange($instance = 0, $key = null, $start = null, $end = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
		if ((!is_int($start)) || (!is_int($end))) return false;
		
		$this->_commandQuery('LRANGE '. $key . ' ' . $start . ' ' . $end . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}
	
	/**
	 * LTRIM - Trim an existing list so that it will contain only the specifiedrange of elements specified. 
	 * Start and end are zero-based indexes.0 is the first element of the list (the list head), 1 the next elementand so on.
	 * For example LTRIM foobar 0 2 will modify the list stored at foobarkey 
	 * so that only the first three elements of the list will remain.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $start[optional]
	 * @param object $end[optional]
	 */
	public function ltrim($instance = 0, $key = null, $start = null, $end = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
		if ((!is_int($start)) || (!is_int($end))) return false;
		
		$this->_commandQuery('LTRIM '. $key . ' ' . $start . ' ' . $end . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}	
	
	/**
	 * LINDEX - Return the specified element of the list stored at the specifiedkey. 
	 * 0 is the first element, 1 the second and so on. 
	 * Negative indexesare supported, for example -1 is the last element, -2 the penultimateand so on.
	 * If the value stored at key is not of list type an error is returned.
	 * If the index is out of range an empty string is returned.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $index[optional]
	 */
	public function lindex($instance = 0, $key = null, $index = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
		if (!is_int($index)) return false;
		
		$this->_commandQuery('LINDEX '. $key . ' ' . $index . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}	
	
	/**
	 * LSET - Set the list element at index (see LINDEX for information about the_index_ argument) with the new value.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $index[optional]
	 * @param object $value[optional]
	 */
	public function lset($instance = 0, $key = null, $index = null, $value = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
		if (!is_int($index)) return false;
		if (empty($value)) return false;
		
		$this->_commandQuery('LSET '. $key . ' ' . $index .' ' . strlen($value) . $this->_defaultDelimetr . $value . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}	
	
	/**
	 * LREM - Remove the first count occurrences of the value element from the list.
	 * If count is zero all the elements are removed. 
	 * If count is negativeelements are removed from tail to head, 
	 * instead to go from head to tailthat is the normal behaviour. 
	 * So for example LREM with count -2 and_hello_ as value to remove against the list (a,b,c,hello,x,hello,hello) 
	 * willlave the list (a,b,c,hello,x). The number of removed elements is returnedas an integer, 
	 * see below for more information aboht the returned value.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $count[optional]
	 * @param object $value[optional]
	 */
	public function lrem($instance = 0, $key = null, $count = null, $value = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
		if (!is_int($count)) return false;
		//if (empty($value)) return false;
		
		$this->_commandQuery('LREM '. $key . ' ' . $count .' ' . strlen($value) . $this->_defaultDelimetr . $value . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}	
	
	/**
	 * LPOP - Atomically return and remove the first (LPOP) or last (RPOP) elementof the list. 
	 * For example if the list contains the elements "a","b","c" LPOPwill return "a" and the list will become "b","c".
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 */
	public function lpop($instance = 0, $key = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
				
		$this->_commandQuery('LPOP '. $key . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}	
	/**
	 * RPOP - Atomically return and remove the first (LPOP) or last (RPOP) elementof the list. 
	 * For example if the list contains the elements "a","b","c" LPOPwill return "a" and the list will become "b","c".
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 */
	public function rpop($instance = 0, $key = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (empty($key)) return false;
				
		$this->_commandQuery('RPOP '. $key . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}		
	
	//======== Service Commands
	
	/**
	 * PING
	 * @return 
	 * @param object $instance[optional]
	 */
	public function ping($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;

		$this->_commandQuery('PING'. $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseRead($instance);	
			
		if (strtolower($result) == strtolower('PONG'))  return true;
		else
			return false;
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
		
		$result = $this->_commandResponseRead($instance);

		if (is_array($result))
		{		
			$info = Array();
			
			foreach ($result as $item)
			{
				$tmp = explode($this->_keyValueDelimetr, $item);						
				$info[trim($tmp[0])] = trim($tmp[1]);
			}
			
			return $info;
		}
		else
			return $result;
			
		
		

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
		
		return $this->_commandResponseRead($instance);
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
		
	    $this->_commandResponseRead($instance);
		
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
	
		return $this->_commandResponseRead($instance);		
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
		
		return $this->_commandResponseRead($instance);
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
		
		return $this->_commandResponseRead($instance);
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
		
		return $this->_commandResponseRead($instance);
		
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
		
		return $this->_commandResponseRead($instance);		
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
		
		return $this->_commandResponseRead($instance);		
	}	
	
	/**
	 * RANDOMKEY: Return a randomly selected key from the currently selected DB.
	 * @return String or Boolean (false)
	 * @param object $instance[optional]
	 */
	public function randomkey($instance = 0)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$this->_commandQuery('RANDOMKEY'. $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}
	
	/**
	 * KEYS
	 * @return Array of Boolean (false)
	 * @param object $instance[optional]
	 * @param object $pattern[optional]
	 */
	public function keys($instance = 0, $pattern = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if ($pattern == null) return false;
		
		$this->_commandQuery('KEYS '. $pattern . $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseRead($instance);
		
		if (empty($result)) return false;
		else
			{
				
				$tmp = explode(' ', $result);
				
				if ((count($tmp) > 0) && (is_array($tmp)))
				{
					foreach ($tmp  as $k=>$i)
					{
						$tmp[$k] = trim($i);
					}
					
					return $tmp;
				}
				else
					return false;
			}				
	}
	
	/**
	 * RENAME Atomically renames the key oldkey to newkey. 
	 * If the source anddestination name are the same an error is returned. 
	 * If newkeyalready exists it is overwritten.
	 * @return 
	 * @param object $instance[optional]
	 * @param object $old_key[optional]
	 * @param object $new_key[optional]
	 */
	public function rename($instance = 0, $old_key = null, $new_key = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (($old_key == null) || ($new_key == null)) return false;
		
		$this->_commandQuery('RENAME '. $old_key . ' ' . $new_key . $this->_defaultDelimetr, $instance, null);
		
		return $this->_commandResponseRead($instance);
	}
		

    /**
     * RENAMENX Rename oldkey into newkey but fails if the destination key newkey already exists.
     * @return Boolean
     * @param object $instance[optional]
     * @param object $old_key[optional]
     * @param object $new_key[optional]
     */
	public function renamenx($instance = 0, $old_key = null, $new_key = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (($old_key == null) || ($new_key == null)) return false;
		
		$this->_commandQuery('RENAMENX '. $old_key . ' ' . $new_key . $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseRead($instance);
		
		if ($result == 1) return true;
		else
			return false;		
	}
	
	/**
	 * MOVE Move the specified key from the currently selected DB to the specifieddestination DB. 
	 * Note that this command returns 1 only if the key wassuccessfully moved, 
	 * and 0 if the target key was already there or if thesource key was not found at all, 
	 * so it is possible to use MOVE as a lockingprimitive.
	 * @return Boolean
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $db_index[optional]
	 */
	public function move($instance = 0, $key = null, $db_index = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (($key == null) || ($db_index == null)) return false;
		
		$this->_commandQuery('MOVE '. $key . ' ' . $db_index . $this->_defaultDelimetr, $instance, null);
		
		$result = $this->_commandResponseRead($instance);
		
		if ($result == 1) return true;
		else
			return false;		
	}
	
	/**
	 * EXPIRE - not realize at now
	 * @return 
	 * @param object $instance[optional]
	 * @param object $key[optional]
	 * @param object $expire[optional]
	 */
	public function expire($instance = 0, $key = null, $expire = null)
	{
		if ((empty($instance)) || (!isset($instance))) $instance = 0;
		if (($key == null) || ($expire == null)) return false;
		
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
	 * Low-level implementation of new protocol
	 * @return 
	 * @param object $instance[optional]
	 */
	private function _commandResponseRead($instance = 0)
    {
    	if ((empty($instance)) || (!isset($instance))) $instance = 0;
		
		$returnBuffer = trim(fgets($this->server[$instance]));
		if (empty($returnBuffer))
		{
			$returnBuffer = trim(fgets($this->server[$instance]));
			
			if (empty($returnBuffer)) return false;
		} 

		$responceType = substr($returnBuffer, 0, 1);

		
		if ($responceType == '-')
		{
			//ERROR
			$responce = substr($returnBuffer, 1, (strlen($returnBuffer)-1));
			
			if ($responce == '-1')  throw new Redis_Server_KeyException('Key not found');
			if ($responce == '-2')  throw new Redis_Server_TypeException('Key contains a value of the wrong type');
			if ($responce == '-3')  throw new Redis_Server_TypeException('Source object and destination object are the same');
			if ($responce == '-4')  throw new Redis_Server_KeyException('Out of range argument');
			
			if (substr($returnBuffer,0, 4) == '-ERR')
			{
				throw new Redis_Server_Exception('Redis server error: ' . $returnBuffer);
			}

			return false;
		}
		if ($responceType == ':')
		{
			// simple integer responce
			$returnBuffer = (int)substr($returnBuffer, 1, strlen($returnBuffer));
			
			if ($returnBuffer < 0)
			{
				if ($returnBuffer == -1)  throw new Redis_Server_KeyException('Key not found');
				if ($returnBuffer == -2)  throw new Redis_Server_TypeException('Key contains a value of the wrong type');
				if ($returnBuffer == -3)  throw new Redis_Server_TypeException('Source object and destination object are the same');
				if ($returnBuffer == -4)  throw new Redis_Server_KeyException('Out of range argument');
			}
			
			return $returnBuffer;
		}
		if ($responceType == '+')
		{
			if ($returnBuffer == '+OK')  return true;
			else
				{
					$returnBuffer = substr($returnBuffer, 1, strlen($returnBuffer));
					
					return $returnBuffer;
				}
		}
		if ($responceType == '$')
		{
			//Bulk data
			$result_byte = (int)substr($returnBuffer, 1, strlen($returnBuffer));

			if ($result_byte < 0)   //error
			{
				if ($result_byte == -1)  throw new Redis_Server_KeyException('Key not found');
				if ($result_byte == -2)  throw new Redis_Server_TypeException('Key contains a value of the wrong type');
				if ($result_byte == -3)  throw new Redis_Server_TypeException('Source object and destination object are the same');
				if ($result_byte == -4)  throw new Redis_Server_KeyException('Out of range argument');
				
				return false;
			}
			else
				{
					$c = 0;
					$result = Array();
					
					while ($c < $result_byte)
					{
						$next_str = trim(fgets($this->server[$instance]));	
						
						if (!empty($next_str))
						{
							$result[] = $next_str;
						
							$c = $c + strlen($next_str);
							continue;
						}
						else
							break;
					}
					
					if (count($result) == 1)  return $result[0];
					else
						return $result;												
				}			
		}
		if ($responceType == '*')
		{
			//Multi-Bulk
			$result_byte = (int)substr($returnBuffer, 1, strlen($returnBuffer));
			if (($result_byte < 0) && ($result_byte == -1))  //error
			{
				return false; //!TODO: my be exception?
			}
			else
				{
					$c = 0;
					$result = Array(); //results array
					
					while ($c < $result_byte)
					{
						$next_str = trim(fgets($this->server[$instance]));
						
						$next_str_len = (int)substr($next_str, 1, strlen($next_str));
						
						if ((($next_str_len < 0) && ($next_str_len == -1)) || (empty($next_str)))
						{
							$result[] = false;
							//continue;
						}
						else
							{
								$next_str_data = trim(fgets($this->server[$instance]));
								
								if (!empty($next_str_data))
								{
									$result[] = $next_str_data;									
								}
								else
									{
										$result[] = false;
									}								
							}
						$c++;
					}
				
					return $result;
				}
		}
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




