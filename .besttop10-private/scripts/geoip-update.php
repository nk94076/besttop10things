<?php
declare(strict_types=1);
// Usage: php scripts/geoip-update.php [file.csv.gz]
// Downloads the free DB-IP country database (or imports a local copy) for Admin › Analytics countries.
// The hourly content-advisor cron already runs this once a month.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
try{echo 'Country database updated: '.number_format(geoipUpdate($argv[1]??null))." IP ranges\n";}
catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
