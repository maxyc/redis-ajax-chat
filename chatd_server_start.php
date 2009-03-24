<pre>
<?php
/**
 * Перва версия сервера чата, использует Redis сервер
 */
 error_reporting(E_ERROR);
 
 include_once('inc/redis.php');

 //конфигурация
 $config = array(
 	'host' => 'localhost',
	'port' => 6379,
	'db' => 0,
	'db_name' => 'default'
 );
 
 
 //нам надо при старте сервера создать основные все переменные в памяти
 $item = array(
 	'users' => 'users_' . md5($config['db_name']), //идентификатор List of users profils
 	'rooms' => 'rooms_' . md5($config['db_name']),
	'admins' => 'admins_' . md5($config['db_name']),
	'users_at_room' => '',
	'msgs_at_room' => ''	
 );
 
 
 //основной пользователь (админ)
 $_init_user = array(
 	'name' => 'admin',
	'role' => 'admin',
	'status' => 'online',
	'password' => md5('qwerty'),
	'at_chat' => date("d.m.Y H:i:s"),
	'last_activity' => time()
 );
 
 $_first_msg = array(
 	'id' => 1,
	'body' => 'Hello, its a first messages on this room: ',
	'author' => $_init_user['name'],
	'time' => date("H:i:s")
 );
 
 
 
 $srv = new Redis($config['host'], $config['port']);
 echo "Creating server object Redis   \r\n\r\n";

 
 //подключение
 $srv->connect();
 echo "connecting to server...   \r\n\r\n";
 //пинг
 $srv->ping();
 echo "Command: ping   \r\n\r\n";
 
 //очистка всего
 $srv->flishdb();
 echo "Command: flush db \r\n\r\n";
 
 //создаем список комнат
 $_rooms = Array('default','moders','all');
 
 //сохраним в переменной '_list_of_rooms'
 $srv->set('_list_of_rooms', serialize($_rooms));
 echo "Setting up the room's list   \r\n\r\n";
 
 //создадим для всех комнат списки пустые
 foreach($_rooms as $r)
 {
 	$srv->push($r, $_init_user['name'], true); //добавляем сериализированный массив юзера в комнату
 	echo "Room: " . $r . " - added user " . $_init_user['name'] . " \r\n\r\n";
 	//создаем пустые списки сообщений для каждой комнаты
	$srv->push('msgs_' . $r, serialize($_first_msg), true);
	echo "Added first messages in the room   \r\n\r\n";
	
	//установим глобальный счетчик сообщений в комнате в 1
	$srv->incr('mi_' . $r);
	echo "Set msg counter at 1  \r\n\r\n";
 }
 
 //теперь добавим в список юзеров онлайн админа
 $srv->push('users_online', $_init_user['name'], true);
 echo "add default admin user to on-line list   \r\n\r\n";
 
 
 //добавляем юзера в массив
 $srv->set('u_' . $_init_user['name'], serialize($_init_user));
 
 
 
 //гм, вроде все
  echo "Init successfull! go to /index_ajax.html   \r\n\r\n";
 
 
 
 
 
 