<pre>
<?php

 include_once('Redis_Server.php');
 
 
 $redis = new Redis_Server('localhost', 6379, 10, false, 'php');
 
 //var_dump($redis);
 

 
 //var_dump($redis->ping());
 var_dump($redis->info());



 var_dump($redis->set(0, 'test_key', 'qwerty_str1'));

try
{
 $tmp = $redis->get(0, 'adfgadfgadfgad');

 var_dump($tmp);
}
catch (Redis_Server_Exception $e)
{
	echo 'error: ' . $e->getMessage();
}

 
 var_dump($redis->info());
 
 
  var_dump($redis->get(0, 'test_key'));


  
  //mget test
  $redis->set(0, 'test_key1', 'qwerty_str1');
  $redis->set(0, 'test_ke2', 'qwerty_str2');
  $redis->set(0, 'test_ke3', 'qwerty_str3');
  $redis->set(0, 'test_ke4', 'qwerty_str4');
  $redis->set(0, 'test_ke5', 'qwerty_str5');
  
  
  var_dump($redis->mget(0, array('test_key1','test_ke2','test_ke33', 'test_ke4', 'test_ke5', 'test_ke3', 'test_ke577')));
 
 
  //exists test

  var_dump($redis->exists(0, 'test_key'));
  var_dump($redis->exists(0, 'test_ke4'));
  var_dump($redis->exists(0, 'tesdsvkjagjh'));
  var_dump($redis->exists(0, 'test_ke2'));
 

 
  // setnx test
  var_dump($redis->setnx(0, 'test_ke2', 'aaax'));
  var_dump($redis->setnx(0, 'test_keZZZ2', 'dark knight'));
  
  var_dump($redis->get(0, 'test_ke2'));
  var_dump($redis->get(0, 'test_keZZZ2'));

 
  
  //inkr/deck test
  $redis->set(0, 'test_inkr', 1);
  
  var_dump($redis->incr(0,'test_inkr'));
  var_dump($redis->incr(0,'test_inkr'));
  var_dump($redis->incr(0,'test_inkr'));
  var_dump($redis->incr(0,'test_inkr'));
  var_dump($redis->incr(0,'test_inkr'));
  
  var_dump($redis->get(0, 'test_inkr'));
  
  var_dump($redis->incr(0,'test_inkr'));
  var_dump($redis->get(0, 'test_inkr'));
  
  
  var_dump($redis->decr(0,'test_inkr'));
  var_dump($redis->decr(0,'test_inkr'));
  var_dump($redis->get(0, 'test_inkr'));
  
 
  //incrby/decrby  test
  echo 'Base: '; 
  var_dump($redis->get(0, 'test_inkr'));
  
  $redis->incrby(0,'test_inkr', 99);
  $redis->incrby(0,'test_inkr', 1);
  $redis->incrby(0,'test_inkr', 5);
  
  echo '+105: ';
  var_dump($redis->get(0, 'test_inkr'));
  
  $redis->decrby(0,'test_inkr', 1);
  var_dump($redis->get(0, 'test_inkr'));
  $redis->decrby(0,'test_inkr', 5);
  var_dump($redis->get(0, 'test_inkr'));
  $redis->decrby(0,'test_inkr', 99);
  var_dump($redis->get(0, 'test_inkr'));
  
  
  //del test  
  var_dump($redis->get(0, 'test_keZZZ2'));
  var_dump($redis->del(0, 'test_keZZZ2'));
 // var_dump($redis->exists(0, 'test_keZZZ2'));
 // var_dump($redis->get(0, 'test_keZZZ2'));
  

  //type test
  var_dump($redis->type(0, 'test_keZZZ2'));
  var_dump($redis->type(0, 'test_inkr'));
  var_dump($redis->type(0, 'default'));
  
 
  //dbsize
  var_dump($redis->dbsize());


 //lastsave
 var_dump($redis->lastsave()); 
 
 //bgsave
 var_dump($redis->bgsave()); 
 
 var_dump($redis->lastsave());
 
 //randomkey
 var_dump($redis->randomkey()); 
 var_dump($redis->randomkey()); 
 var_dump($redis->randomkey());  
 
 
 //keys
 var_dump($redis->keys(0, 'test_*'));  
 var_dump($redis->keys(0, '*')); 
 var_dump($redis->keys(0, 'abrakadabra*'));  
  
  
 //rename
 $redis->set(0, 'test_key_rename', 'abra_kadabra_hello!');
 var_dump($redis->get(0, 'test_key_rename'));
 
 var_dump($redis->rename(0, 'test_key_rename', 'test_key_renameXXX'));
 
 var_dump($redis->exists(0, 'test_key_rename'));
 var_dump($redis->exists(0, 'test_key_renameXXX')); 
  
 var_dump($redis->get(0, 'test_key_renameXXX')); 
 
 
 
 //renamenx
 //var_dump($redis->renamenx(0, 'test_key_renameXXXQQ', 'sdfsafs')); 
 var_dump($redis->renamenx(0, 'test_key_renameXXX', 'test_key_renameXXX1'));
 
 
 //sort  !Untested
 //var_dump($redis->sort(0, 'default', null, null, null, null, DESC, true)); 
 
 
 //sadd
 var_dump($redis->sadd(0, 'test_set_data', 'lkdshgdlksjghskldhg'));
 var_dump($redis->sadd(0, 'test_set_data', 'test1sd'));
 var_dump($redis->sadd(0, 'test_set_data', 'lkdshgdkklksjghskldhg'));
 var_dump($redis->exists(0, 'test_set_data'));  
 var_dump($redis->type(0, 'test_set_data')); 
 var_dump($redis->smembers(0, 'test_set_data'));
 
 //sismember
 echo "Test SISMEBER \r\n";
 var_dump($redis->sismember(0, 'test_set_data', 'test1sd'));
 
 
 
 //R/Lpush test
 var_dump($redis->del(0, 'qwerty_log')); 
  
 var_dump($redis->lpush(0, 'qwerty_log', 'lkdshgdlksffjghskldhg'));
 var_dump($redis->type(0, 'qwerty_log'));
 
 var_dump($redis->lpush(0, 'qwerty_log', 'lkdshgdlksffjghskldhg'));
 var_dump($redis->type(0, 'qwerty_log')); 
 
 var_dump($redis->llen(0, 'qwerty_log'));
 
 //lrange
 var_dump($redis->lrange(0, 'qwerty_log', 0, 1));
 
 //lindex
 var_dump($redis->lindex(0, 'qwerty_log', 1));
 
 //lset
 var_dump($redis->lset(0, 'qwerty_log', 1, 'newlsetval'));
 
 var_dump($redis->lrange(0, 'qwerty_log', 0, 3));
 
 //lrem
 var_dump($redis->lrem(0, 'qwerty_log', 1, 'newlsetval')); 
 var_dump($redis->llen(0, 'qwerty_log'));
 var_dump($redis->lrange(0, 'qwerty_log', 0, 3));
 
 //r/l pop
 var_dump($redis->lpush(0, 'qwerty_log', 'Just a man'));
 
 var_dump($redis->lpop(0, 'qwerty_log'));
 var_dump($redis->rpop(0, 'qwerty_log'));
 