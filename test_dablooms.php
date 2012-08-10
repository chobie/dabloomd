<?php
printf("dablooms version: %s\n", Dablooms::VERSION);

define("DABLOOMS_FILE", "/tmp/bloom2.bin");
define("DABLOOMS_CAPACITY", 100000);
define("DABLOOMS_ERROR_RATE", 0.05);

if ($_SERVER['argc'] != 2) {  
    printf("Usage: %s <words_file>\n  e.g) php test_dablooms.php /usr/share/dict/words\n", $_SERVER['argv'][0]);
    return;
} 
$filepath = $_SERVER['argv'][1];

$bloom = new Memcached();
$bloom->addServer("127.0.0.1", 1224);

$fp = fopen($filepath, "r");

$begin = microtime(true);
for ($i = 1; $word = trim(fgets($fp, 128)); $i++) {
    if ($word != "") {
        $bloom->set($word, $i);
        echo ".";
        //sleep(3);
    }
}
$end = microtime(true);

echo "finished save" . PHP_EOL;
var_dump($end - $begin);
var_dump($i);



fseek($fp, 0, SEEK_SET);
for ($iremove = 1; $word = trim(fgets($fp, 128)); $iremove++) {
    if ($word != "") {
        if ($iremove % 5 == 0) {
            $bloom->delete($word, $iremove);
        }
    }
}

$not_exist_fail = 0;
$not_exist_pass = 0;
$exist_pass = 0;
$exist_fail = 0;

//$bloom->bitmapFlush();

//unset($bloom);

//$bloom = Dablooms::loadFromFile(100000, .05, "/tmp/bloom2.bin");
fseek($fp, 0, SEEK_SET);
for ($i = 1; $word = trim(fgets($fp, 128)); $i++) {
    if ($word != NULL) {
        echo $word . PHP_EOL;
        $retval = $bloom->get($word);
        usleep(100);

        if ($i % 5 == 0) {
            if (!$retval) {
                $not_exist_pass++;
            } else {
                $not_exist_fail++;
            }
        } else {
            if ($retval) {
                $exist_pass++;
            } else {
                printf("%s\n", $word);
                $exist_fail++;
            }
        }
    }
}

    
printf("\nElements Added:   %d\n", $i);
printf("Elements Removed: %d\n\n", $i/5);
printf("True positives:   %d\n", $exist_pass);
printf("True negatives:   %d\n", $not_exist_pass);
printf("False positives:  %d\n", $not_exist_fail);
printf("False negatives:  %d\n\n", $exist_fail);
//printf("Total size: %d kB\n", $bloom->getSize()/1024);
