<?php
/**
 * основной файл чата
 */
 error_reporting(E_ERROR);
 
 //include_once('inc/EpiCode.php');
 include_once('inc/redis.php');
 
 /*
 //определим таблицу роутинга
 $_['routes'] = Array(
 	//показывает список комнат
	'chat/get/rooms' => array('Chatd_Client', 'getRooms'),
	'chat/get/usersonline' => array('Chatd_Client', 'getOnlineUsers')
 
 
 );
 
 
 //инициализация механизма роутинга
 EpiCode::getRoute($_REQUEST['__route__'], $_['routes']); 
 
 */
 
 
 //основной класс
 class Chatd_Client
 {
 	static private $srv = null;
	static private $hos = 'localhost';
	static private $port = 6379;
	
	//сколько сообщений хранить
	static private $msg_store = 199;
	
	//максимальная длина сообщений
	static private $msg_len = 1024;
	
	//allowed tag
	static private $msg_tag_alloved = '<b><i><font><span><h3><h4><h5><br>';
	
	//timeout to un-login
	static private $time_to_away = 300; //секунд
	
	
	/**
	 * init function
	 * @return void
	 */
	static public function __init()
	{
		if (self::$srv == null)
		{
			self::$srv = new Redis(self::$hos, self::$port);
		}
	}
	
	/**
	 * @todo work with room list
	 * @see  /chat/get/rooms
	 * @return 
	 */
	static public function getRooms()
	{
		self::__init();
		
		$tmp = unserialize(self::$srv->get('_list_of_rooms'));
		
		//$json = '';
		var_dump($tmp);			
	}
	
	


	
	
	
	/**
	 * login user to default room
	 * @see chat/login
	 * @return 
	 */
	static public function userLogin($login, $password)
	{
		self::__init();
		
		//сначала проверим, есть ли такой юзер?
		if (self::$srv->exists('u_' . $login) == 1)
		{
			//юзер есть уже
			// проверить пароль
			$user = unserialize(self::$srv->get('u_' . $login));
			
			if ($user['password'] == md5($password))
			{
				//ок, это мы
				@header('HTTP/1.0 200 OK');
				return true;
			}
			else
				{
					@header('HTTP/1.0 500 Error');
					return false;
				}
		}
		
		//иначе создаем нового		
		//обьект юзера
		$__user = array(
		 	'name' => $login,
			'role' => 'user',
			'status' => 'online',
			'password' => md5($password),
			'at_chat' => date("d.m.Y H:i:s"),
			'last_activity' => time()			
		 );
		
		//добавим его
		self::$srv->set('u_' . $__user['name'], serialize($__user));
		//добавим в список онайн
		self::$srv->push('users_online', $__user['name'], true);
		
		//добавим в дефолтную комнату
		self::$srv->push('default', $__user['name'], true);
		
		@header('HTTP/1.0 200 OK');
		return true;		
	}
	
	/**
	 * show online user list
	 * @see /chat/get/usersonline
	 * @return json
	 */
	static public function getOnLineUser($room)
	{
		self::__init();
		
		$how_its_in_online = (int) self::$srv->llen($room);
		
		$_users = self::$srv->lrange($room, 0, $how_its_in_online);
		
		$u_online = Array(); //массив юзеров
		
		foreach ($_users as $user_name)
		{
			if (self::$srv->exists('u_' . $user_name) == 1)
			{
				$u_online[] = unserialize(self::$srv->get('u_' . $user_name));
			}
		}
		
		//надо создать массив JSON для дерева
		echo json_encode($u_online);		
	}
	
	/**
	 * gets all messages from room, if present - after msg_id
	 * @return 
	 * @param object $room
	 * @param object $msgid
	 */
	static public function getMsgs($room, $msgid)
	{
		self::__init();
		
		//очистим очередь
		self::$srv->ltrim('msgs_' . $room, 0, self::$msg_store);
		
		//получим все сообщения
		$how_its_in = (int) self::$srv->llen('msgs_' . $room);
		
		$_m = self::$srv->lrange('msgs_' . $room, 0, $how_its_in);
		
		$all_msgs = Array(); //массив сообщений
		
		foreach ($_m as $msg)
		{
			//!TODO: пока так, грубо, потом оптимизировать
			$tmp = unserialize($msg);
			if ($tmp['id'] > $msgid)
			{
				//$tmp['msg'] = $tmp['msg'], ENT_QUOTES);
				$all_msgs[] = $tmp;
			}			
		}
		
		
		//надо создать массив JSON для дерева
		echo json_encode($all_msgs);		
	}
	
	
	
	/**
	 * add new messages
	 * @return 
	 * @param object $msg
	 * @param object $author
	 * @param object $room
	 */
	static public function say($msg, $author, $room)
	{
		self::__init();
		
		$msg = strip_tags(substr($msg, 0, self::$msg_len), self::$msg_tag_alloved);
		
		$__msg = array(
		 	'id' => self::$srv->incr('mi_' . $room),
			'body' => $msg,
			'author' => $author,
			'time' => date("H:i:s")
		 );
		 
		 //добавим сообщение
		 self::$srv->push('msgs_' . $room, serialize($__msg), true);
		 
		 @header('HTTP/1.0 200 OK');
		 return true;		
	}
	
	/**
	 * remove users from online
	 * @return 
	 * @param object $login
	 */
	static public function goOut($login)
	{
		self::__init();
		
		if (self::$srv->exists('u_' . $login) == 1)
		{
			//удаляем профайл пользователя, остальное сделает само :) 
			self::$srv->delete('u_' . $login);
		}
		
		@header('HTTP/1.0 200 OK');
		return true;			
	}
	
	/**
	 * do ping and check user activity
	 * @return 
	 * @param object $login
	 */
	static public function setPing($login)
	{
		self::__init();
		
		if (self::$srv->exists('u_' . $login) == 1)
		{
			$i = unserialize(self::$srv->get('u_' . $login));
			$i['last_activity'] = time();
			
			self::$srv->set('u_' . $login, serialize($i));
		}
		
		// пройтись по всем онлан, если кто долго не был, удалить
		$how_its_in = (int) self::$srv->llen('users_online');		
		$tmp = self::$srv->lrange('users_online', 0, $how_its_in);
		
		foreach ($tmp as $user)
		{
			if (self::$srv->exists('u_' . $user) == 1)
			{
				$i = unserialize(self::$srv->get('u_' . $user));
				
				if (((time() - $i['last_activity']) > self::$time_to_away) && ($i['role'] == 'user'))
				{
					//если за 5 минут не поступило но одного пинга
					self::$srv->delete('u_' . $user);
				}
				
			}
		}
		
		
		
	}
	
	
	
 }
  
 
 
 
 //================================================================================================
 
 
 if (isset($_REQUEST['action']))
 {
 	$action = trim($_REQUEST['action']);
	
	
	if ($action == 'chat/get/rooms')
	{
		Chatd_Client::getRooms();
	}
	
	if ($action == 'chat/get/usersonline')
	{
		Chatd_Client::getOnlineUsers();
	}	
	
	if ($action == 'chat/login')
	{
		Chatd_Client::userLogin(trim($_REQUEST['user_login']), trim($_REQUEST['user_password']));  
	}
	
	if ($action == 'chat/get/useronline/atroom')
	{
		Chatd_Client::getOnLineUser(trim($_REQUEST['room']));
	}	
	
	if ($action == 'chat/get/msgs')
	{
		Chatd_Client::getMsgs(trim($_REQUEST['room']), (int) trim($_REQUEST['lastmsgid']));
	}		
	
	if ($action == 'chat/say')
	{
		Chatd_Client::say(trim($_REQUEST['msg']), trim($_REQUEST['author']), trim($_REQUEST['room']));
	}	
	
	if ($action == 'chat/gout')
	{
		Chatd_Client::goOut(trim($_REQUEST['user']));
	}	
	
	if ($action == 'chat/ping')
	{
		Chatd_Client::setPing(trim($_REQUEST['user']));
	}	
	
	
	
 }
 
 
 
 

 
 

