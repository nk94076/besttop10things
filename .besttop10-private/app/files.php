<?php
declare(strict_types=1);
// File manager (Admin › Files). Three places:
//  - "Public files" (www/files/, not in Git): upload, edit, delete. Served at /files/… and at the site root
//    (e.g. /google123.html), so verification files and similar survive every `git pull`.
//  - "Uploads" (www/uploads/, not in Git): the media library images.
//  - "Website code" (everything from Git): view only. Edits here would be overwritten or block the next
//    `git pull`, so code changes go through the repository instead.
// Secrets (API keys, .env, .git, the databases) are never listed or shown.

const ADS_TXT_DEFAULT = 'google.com, pub-7878101504701354, DIRECT, f08c47fec0942fa0';
const FILE_TYPES = ['txt'=>'text/plain','html'=>'text/html','htm'=>'text/html','css'=>'text/css','js'=>'text/javascript','json'=>'application/json','xml'=>'application/xml',
 'md'=>'text/markdown','csv'=>'text/csv','pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif',
 'svg'=>'image/svg+xml','ico'=>'image/x-icon','mp4'=>'video/mp4','webm'=>'video/webm','mp3'=>'audio/mpeg','woff2'=>'font/woff2','zip'=>'application/zip'];
const TEXT_TYPES = ['txt','html','htm','css','js','json','xml','md','csv','svg'];

function fileExt(string $name): string { return strtolower(pathinfo($name,PATHINFO_EXTENSION)); }
function publicFileType(string $path): ?string { $t=FILE_TYPES[fileExt($path)]??null; return $t&&str_starts_with($t,'text/')?$t.'; charset=utf-8':$t; }

// Roots: key => [label, absolute dir, writable].
function fileRoots(): array {
 $repo=dirname((string)realpath(ROOT));$web=$repo.'/www.besttop10things.com';
 return ['public'=>['Public files',$web.'/files',true],'uploads'=>['Uploads',$web.'/uploads',true],'code'=>['Website code',$repo,false]];
}
function fileSecret(string $rel): bool {
 return (bool)preg_match('~(^|/)(\.git|\.ssh|\.env|vendor|node_modules)(/|$)|(^|/)\.besttop10-private/storage(/|$)|\.(sqlite|sqlite-wal|sqlite-shm|pem|key)$|key\.(txt|json)$~i',$rel);
}
// Resolves root + relative path safely. Returns [root key, root info, absolute path, clean relative path].
function filePath(string $root, string $rel): array {
 $roots=fileRoots();if(!isset($roots[$root]))throw new RuntimeException('Unknown folder.');
 [$label,$base,$writable]=$roots[$root];
 if($writable&&!is_dir($base))mkdir($base,0755,true);
 $rel=trim(str_replace('\\','/',$rel),'/');
 if($rel!==''&&(preg_match('~(^|/)\.\.?(/|$)~',$rel)||str_contains($rel,"\0")))throw new RuntimeException('Invalid path.');
 if(fileSecret($rel))throw new RuntimeException('This file is private and cannot be opened here.');
 $abs=$rel===''?$base:$base.'/'.$rel;
 $real=realpath($abs);$realBase=realpath($base);
 if($real!==false&&($realBase===false||($real!==$realBase&&!str_starts_with($real,$realBase.'/'))))throw new RuntimeException('Invalid path.');
 return [$root,$roots[$root],$abs,$rel];
}
function fileList(string $root, string $rel): array {
 [, , $abs,$rel]=filePath($root,$rel);
 if(!is_dir($abs))throw new RuntimeException('Folder not found.');
 $out=[];
 foreach(scandir($abs)?:[] as $n){
  if($n==='.'||$n==='..')continue;
  $r=ltrim("$rel/$n",'/');if(fileSecret($r)||($root==='code'&&in_array($n,['files','uploads'],true)&&basename($abs)==='www.besttop10things.com'))continue;
  $p="$abs/$n";$out[]=['name'=>$n,'rel'=>$r,'dir'=>is_dir($p),'size'=>is_file($p)?filesize($p):0,'time'=>filemtime($p)];
 }
 usort($out,fn($a,$b)=>[$b['dir'],strtolower($a['name'])]<=>[$a['dir'],strtolower($b['name'])]);
 return $out;
}
function cleanFileName(string $name): string {
 $name=preg_replace('/[^A-Za-z0-9._-]+/','-',trim($name));$name=trim($name,'.-');
 if($name===''||strlen($name)>120)throw new RuntimeException('Use a file name of 1–120 letters, numbers, dots, - or _.');
 return $name;
}
function checkWritable(string $root): void { if(!fileRoots()[$root][2])throw new RuntimeException('Website code is view-only here: it comes from Git and would be overwritten by the next git pull.'); }
function checkFileType(string $name): void {
 if(!isset(FILE_TYPES[fileExt($name)]))throw new RuntimeException('This file type is not allowed. Allowed: '.implode(', ',array_keys(FILE_TYPES)).'.');
}
function fileUpload(string $root, string $rel, array $file): string {
 checkWritable($root);[, , $dir]=filePath($root,$rel);
 if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Upload failed. Files must be under '.ini_get('upload_max_filesize').'.');
 $name=cleanFileName((string)$file['name']);checkFileType($name);
 if(!is_dir($dir))throw new RuntimeException('Folder not found.');
 if(!move_uploaded_file($file['tmp_name'],"$dir/$name"))throw new RuntimeException('The file could not be saved (check folder permissions).');
 @chmod("$dir/$name",0644);
 return $name;
}
function fileSave(string $root, string $rel, string $content): void {
 checkWritable($root);[, , $abs,$rel]=filePath($root,$rel);
 checkFileType($rel);if(!in_array(fileExt($rel),TEXT_TYPES,true))throw new RuntimeException('Only text files can be edited here.');
 if(strlen($content)>2*1024*1024)throw new RuntimeException('The file is too big to edit here (2 MB max).');
 if(!is_dir(dirname($abs)))throw new RuntimeException('Folder not found.');
 if(file_put_contents($abs,str_replace("\r\n","\n",$content))===false)throw new RuntimeException('The file could not be saved (check folder permissions).');
}
function fileMkdir(string $root, string $rel, string $name): void {
 checkWritable($root);[, , $dir]=filePath($root,$rel);$name=cleanFileName($name);
 if(file_exists("$dir/$name"))throw new RuntimeException('Something with this name already exists.');
 mkdir("$dir/$name",0755);
}
function fileDelete(string $root, string $rel): void {
 checkWritable($root);[, , $abs,$rel]=filePath($root,$rel);
 if($rel==='')throw new RuntimeException('The main folder cannot be deleted.');
 if(is_dir($abs)){if(count(scandir($abs))>2)throw new RuntimeException('Empty the folder first.');rmdir($abs);return;}
 if($root==='uploads'&&function_exists('imageInUse')&&imageInUse('/uploads/'.$rel))throw new RuntimeException('This image is used in a post. Remove it from the post first.');
 if(!is_file($abs)||!unlink($abs))throw new RuntimeException('The file could not be deleted.');
}
function fileRename(string $root, string $rel, string $to): string {
 checkWritable($root);[, , $abs,$rel]=filePath($root,$rel);
 if($rel===''||!file_exists($abs))throw new RuntimeException('File not found.');
 $to=cleanFileName($to);if(!is_dir($abs))checkFileType($to);
 if(file_exists(dirname($abs)."/$to"))throw new RuntimeException('Something with this name already exists.');
 rename($abs,dirname($abs)."/$to");
 return ltrim(dirname($rel)==='.'?$to:dirname($rel)."/$to",'/');
}
// Public URL for a file, if the web server can show it.
function fileUrl(string $root, string $rel): ?string {
 return match($root){'public'=>'/files/'.$rel,'uploads'=>'/uploads/'.$rel,default=>str_starts_with($rel,'www.besttop10things.com/assets/')?substr($rel,strlen('www.besttop10things.com')):null};
}
