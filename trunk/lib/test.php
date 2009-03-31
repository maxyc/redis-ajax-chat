<pre>
<?php

 include_once('Redis_Server.php');
 
 
 $redis = new Redis_Server('localhost', 6379, 10, false, 'php');
 
 //var_dump($redis);
 

 
 //var_dump($redis->ping());
 var_dump($redis->info());


 var_dump($redis->set(0, 'test_key', 'qwerty_str1'));

 $tmp = $redis->get(0, 'adfgadfgadfgad');

 var_dump($tmp);

 
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
  var_dump($redis->exists(0, 'test_key1'));
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
  
  $redis->incr(0,'test_inkr');
  $redis->incr(0,'test_inkr');
  $redis->incr(0,'test_inkr');
  $redis->incr(0,'test_inkr');
  $redis->incr(0,'test_inkr');
  
  var_dump($redis->get(0, 'test_inkr'));
  
  $redis->incr(0,'test_inkr');
  var_dump($redis->get(0, 'test_inkr'));
  
  
  $redis->decr(0,'test_inkr');
  $redis->decr(0,'test_inkr');
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
  var_dump($redis->get(0, 'test_keZZZ2'));
  
  
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
 
 //version - Exception generate
 //var_dump($redis->version());  
  
  
  
  
  
  
  
  
  
  
