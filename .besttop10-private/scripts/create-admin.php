<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
$email=$argv[1]??'';
if(!$email){echo 'Admin email: ';$email=trim(fgets(STDIN));}
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Invalid email.\n");exit(1);}
$password=getenv('ADMIN_PASSWORD')?:'';
if(!$password){$password=bin2hex(random_bytes(12));echo "Generated password (save securely): $password\n";}
if(strlen($password)<12){fwrite(STDERR,"Password must be at least 12 characters.\n");exit(1);}
if(query('SELECT id FROM admins WHERE email=?',[strtolower($email)])){fwrite(STDERR,"Account exists. Change its password in the CMS.\n");exit(1);}
run('INSERT INTO admins(email,password) VALUES (?,?)',[strtolower($email),password_hash($password,PASSWORD_DEFAULT)]);
echo "Admin created. Sign in at /admin.php\n";
