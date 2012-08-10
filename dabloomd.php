<?php
require "debug_timer.php";

$bloom = new Dablooms(100000, 0.05, "/tmp/dab.bin", 0);

$parsers = array();
$nreads = array();
$tcp = uv_tcp_init();

uv_tcp_bind($tcp, uv_ip4_addr("127.0.0.1", 1224));
uv_listen($tcp, 100, function ($server, $status) use (&$bloom, &$parsers, &$nreads) {
    $client = uv_tcp_init();
    uv_accept($server, $client);
    $parsers[(int)$client] = memcache_parser_init();
    $nreads[(int)$client] = 0;

    uv_read_start($client, function($client, $nread, $buffer) use (&$bloom, &$parsers, &$nreads) {
        if ($nread < 0) {
            var_dump(uv_strerror(uv_last_error()));
            unset($parsers[(int)$client]);
            unset($nreads[(int)$client]);
            uv_shutdown($client, function($client){
                uv_close($client);
            });
            return;
        } else if ($nread == 0) {
            if (uv_last_error() == UV::EOF) {
                unset($parsers[(int)$client]);
                unset($nreads[(int)$client]);
                uv_shutdown($client, function($client){uv_close($client);});
            }
            return;
        }
        
        if (strpos($buffer, "get") === 0) {
            list($co,$m) = explode("\r\n",$buffer,2);
            $buffer = join("\r\n",array(trim($co),$m));
        }
        
        $ret = memcache_parser_execute($parsers[(int)$client], $buffer, $nreads[(int)$client], function($command, $key, $options) use (&$bloom, $client){
            switch($command){
                case "get":
                    if($bloom->check($key)) {
                        $res = sprintf("VALUE %s %d %d\r\n%s\r\nEND\r\n", $key, 1, strlen("1"), "1");
                        //fprintf(STDOUT, $res);
                        uv_write($client, $res, function($client, $status){
                            //var_dump($status);
                        });
                    } else {
                        //fprintf(STDOUT, "key %s\n", $key);
                        uv_write($client, "END\r\n",function($client){
                        });
                    }
                    break;
                case "delete":
                    fprintf(STDOUT, "%s %d\n", $key, $options['time']);
                    $bloom->delete($key, $options['time']);
                    uv_write($client, "DELETED\r\n",function($client){
                    });
                    break;
                case "set":
                    // this causes false positive. don't check.
                    //if(!$ret = $bloom->check($key)) {
                        $bloom->add($key, (int)$options['data']);
                    //} else {
                    //    fprintf(STDOUT, "damepo %s\n", $key);
                    //    var_dump($ret);
                    //}
                    uv_write($client, "STORED\r\n",function($client){
                    });
                    break;
                case "quit":
                default:
                    fprintf(STDOUT, $buffer);
                    uv_close($client);
                    break;
            }
        });
        
        if ($ret > 0) {
            $nreads[(int)$client] = 0;
        } else {
/*
            for($i = 0;$i<strlen($buffer);$i++) {
                fprintf(STDOUT, '"' . $buffer[$i] . '"   => ' . ord($buffer[$i]) . PHP_EOL);
            }
*/
            if (strpos($buffer, "quit") === 0) {
                unset($parsers[(int)$client]);
                unset($nreads[(int)$client]);
                uv_close($client);
            } else {
                uv_write($client, "ERROR\n",function($client){
                });
                $nreads[(int)$client] = 0;
            }
            //uv_close($client);
        }
    });
});

uv_run();