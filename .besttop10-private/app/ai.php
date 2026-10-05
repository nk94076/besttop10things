<?php
declare(strict_types=1);
// Claude API (official PHP SDK, installed with `composer install` in .besttop10-private/).
// The API key lives in .besttop10-private/storage/anthropic-key.txt (mode 600) or the ANTHROPIC_API_KEY env var.
// Everything the AI writes is a suggestion or a draft: an editor applies or publishes it.

const AI_KEY_FILE = ROOT . '/storage/anthropic-key.txt';
const AI_MODEL = 'claude-opus-5-5';

// Provider: 'claude' (Anthropic API, paid) or 'gemini' (Google AI Studio key; has a free tier).
function aiProvider(): string { return setting('ai_provider')==='gemini'?'gemini':'claude'; }
function aiAvailable(): bool { return geminiKey()!==''||(is_file(ROOT.'/vendor/autoload.php')&&aiKey()!==''); }

const GEMINI_KEY_FILE = ROOT . '/storage/gemini-key.txt';
function geminiKey(): string { return (string)(getenv('GEMINI_API_KEY')?:(is_file(GEMINI_KEY_FILE)?trim((string)file_get_contents(GEMINI_KEY_FILE)):'')); }
function geminiSaveKey(string $key): void {
 if(!preg_match('/^[A-Za-z0-9_\-.]{30,120}$/',$key))throw new RuntimeException('That does not look like a Gemini API key (from aistudio.google.com › Get API key).');
 if(!is_dir(dirname(GEMINI_KEY_FILE)))mkdir(dirname(GEMINI_KEY_FILE),0750,true);
 file_put_contents(GEMINI_KEY_FILE,$key);@chmod(GEMINI_KEY_FILE,0600);
}
function geminiModel(): string { $m=setting('gemini_model');return preg_match('/^[a-z0-9.\-]{3,60}$/',$m)?$m:'gemini-flash-latest'; }
// Tried in order when a model is busy (503), retired (404) or out of free quota (429).
function geminiModels(): array { return array_values(array_unique([geminiModel(),'gemini-flash-latest','gemini-3.7-flash','gemini-3.5-flash','gemini-flash-lite-latest'])); }

// Gemini's responseSchema is an OpenAPI subset: upper-case types, no additionalProperties/title.
function geminiSchema(array $s): array {
 $o=[];
 if(isset($s['type']))$o['type']=strtoupper($s['type']);
 if(isset($s['properties'])){$o['properties']=array_map('geminiSchema',$s['properties']);$o['required']=$s['required']??array_keys($s['properties']);$o['propertyOrdering']=array_keys($s['properties']);}
 if(isset($s['items']))$o['items']=geminiSchema($s['items']);
 return $o;
}
function geminiJson(string $prompt, array $schema, int $maxTokens): array {
 if(geminiKey()==='')throw new RuntimeException('Add your Gemini API key in Admin › SEO & Code › AI assistant.');
 unset($schema['title']);
 $body=['systemInstruction'=>['parts'=>[['text'=>AI_SYSTEM]]],'contents'=>[['role'=>'user','parts'=>[['text'=>$prompt]]]],
  'generationConfig'=>['responseMimeType'=>'application/json','responseSchema'=>geminiSchema($schema),'maxOutputTokens'=>max(8192,$maxTokens),'temperature'=>0.6]];
 $last='';
 foreach(geminiModels() as $model){
  $ch=curl_init((getenv('GEMINI_BASE_URL')?:'https://generativelanguage.googleapis.com').'/v1beta/models/'.rawurlencode($model).':generateContent');
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>240,CURLOPT_CONNECTTIMEOUT=>10,
   CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.geminiKey()],CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE)]);
  $raw=(string)curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $r=json_decode($raw,true);
  if(in_array($code,[404,429,500,503],true)){$last=$code===429?'Gemini free-tier limit reached for now. It resets automatically; queued work is retried by the hourly cron.':'Gemini is busy right now ('.($r['error']['message']??"HTTP $code").'). Try again in a few minutes.';continue;}
  if($code<200||$code>=300)throw new RuntimeException('Gemini API: '.($r['error']['message']??($err?:"HTTP $code")));
  $last='';$GLOBALS['aiModelUsed']=$model;break;
 }
 if($last!=='')throw new RuntimeException($last);
 if(!empty($r['promptFeedback']['blockReason']))throw new RuntimeException('Gemini declined this request ('.$r['promptFeedback']['blockReason'].').');
 $c=$r['candidates'][0]??[];
 if(($c['finishReason']??'')==='MAX_TOKENS')throw new RuntimeException('The AI answer was cut off. Try again.');
 $text='';foreach($c['content']['parts']??[] as $part)if(empty($part['thought']))$text.=(string)($part['text']??'');
 $data=json_decode($text,true);
 if(!is_array($data))throw new RuntimeException('Gemini returned an unreadable answer. Try again.');
 return $data;
}
function aiKey(): string { $k=getenv('ANTHROPIC_API_KEY')?:(is_file(AI_KEY_FILE)?trim((string)file_get_contents(AI_KEY_FILE)):'');return (string)$k; }
function aiSaveKey(string $key): void {
 if(!preg_match('/^sk-ant-[A-Za-z0-9_\-]{20,}$/',$key))throw new RuntimeException('That does not look like an Anthropic API key (sk-ant-…).');
 if(!is_dir(dirname(AI_KEY_FILE)))mkdir(dirname(AI_KEY_FILE),0750,true);
 file_put_contents(AI_KEY_FILE,$key);@chmod(AI_KEY_FILE,0600);
}

function aiClient(): \Anthropic\Client {
 static $c=null;
 if($c)return $c;
 if(!is_file(ROOT.'/vendor/autoload.php'))throw new RuntimeException('AI is not installed: run `composer install --no-dev` in .besttop10-private/.');
 require_once ROOT.'/vendor/autoload.php';
 if(aiKey()==='')throw new RuntimeException('Add your Anthropic API key in Admin › SEO & Code › AI assistant.');
 return $c=new \Anthropic\Client(apiKey: aiKey());
}

const AI_SYSTEM = "You are the senior SEO editor of an independent review and buying-guide website. You write clear, practical, people-first content that helps readers decide.
Rules:
- Never invent facts you cannot support: no made-up prices, specifications, test results, statistics, dates, awards or quotes. When a number is needed, use general, commonly known guidance (e.g. \"2–3 times a week\") or phrase it as a range or rule of thumb.
- Never claim hands-on testing unless the article already says so.
- Keep existing affiliate links and brand names exactly as they are; do not add new external links.
- Match the article's language (English, German or French) and its tone.
- Write Markdown that the site supports: ## headings, ### subheadings, paragraphs, - bullet lists, **bold**, [text](url) links. No tables, no HTML.
- Prefer short paragraphs, direct answers first, question-style headings where natural, and concrete, actionable steps.";

// One structured call. $schema is a JSON schema; returns the decoded object.
// Which providers have a key (and, for Claude, the SDK).
function claudeReady(): bool { return is_file(ROOT.'/vendor/autoload.php')&&aiKey()!==''; }
function geminiReady(): bool { return geminiKey()!==''; }
function providerReady(string $p): bool { return $p==='gemini'?geminiReady():claudeReady(); }
function providerName(string $p): string { return $p==='gemini'?'Google Gemini':'Claude'; }
// Order to try for a task: the chosen provider first, then the other one as backup (if enabled and set up).
function aiOrder(string $task='general'): array {
 $first=$task==='draft'&&in_array(setting('ai_draft_provider'),['gemini','claude'],true)?setting('ai_draft_provider'):aiProvider();
 $order=[$first];if(setting('ai_fallback','1')==='1')$order[]=$first==='gemini'?'claude':'gemini';
 return array_values(array_filter($order,'providerReady'));
}

// One structured call. $schema is a JSON schema; returns the decoded object. $task 'draft' may use its own provider.
function aiJson(string $prompt, array $schema, int $maxTokens=16000, string $effort='medium', string $task='general', ?string $only=null): array {
 if($mock=getenv('AI_MOCK_DIR')){ // tests: fixture named after the schema's title
  $f=$mock.'/'.($schema['title']??'out').'.json';if(!is_file($f))throw new RuntimeException("No AI mock $f");return json_decode((string)file_get_contents($f),true);
 }
 $order=$only?[$only]:aiOrder($task);
 if(!$order)throw new RuntimeException('No AI is set up yet: add a Gemini or Claude key in Admin › SEO & Code › AI assistant.');
 $errors=[];
 foreach($order as $prov){
  try{$r=$prov==='gemini'?geminiJson($prompt,$schema,$maxTokens):claudeJson($prompt,$schema,$maxTokens,$effort);$GLOBALS['aiProviderUsed']=$prov;return $r;}
  catch(RuntimeException $e){$errors[]=$e->getMessage();}
 }
 throw new RuntimeException(implode(' — then: ',$errors));
}

function claudeJson(string $prompt, array $schema, int $maxTokens, string $effort): array {
 $title=$schema['title']??null;unset($schema['title']);
 try{
  $msg=aiClient()->beta->messages->create(
   maxTokens: $maxTokens,
   messages: [['role'=>'user','content'=>$prompt]],
   model: AI_MODEL,
   fallbacks: 'default',
   outputConfig: ['effort'=>$effort,'format'=>['type'=>'json_schema','schema'=>$schema]],
   system: AI_SYSTEM,
   betas: ['server-side-fallback-2026-07-01'],
   workspaceID: setting('ai_workspace_id')!==''?setting('ai_workspace_id'):null,
  );
 }catch(\Anthropic\Core\Exceptions\APIStatusException $e){
  $detail=preg_match('/"message":\s*"([^"]+)"/',$e->getMessage(),$m)?$m[1]:'request failed';
  throw new RuntimeException('Claude API: '.$detail.($e->type?' ('.$e->type->value.')':''));
 }catch(\Throwable $e){
  if($e instanceof RuntimeException)throw $e;
  throw new RuntimeException('Claude API could not be reached: '.$e->getMessage());
 }
 if($msg->stopReason==='refusal')throw new RuntimeException('Claude declined this request'.($msg->stopDetails?->explanation?': '.$msg->stopDetails->explanation:'.'));
 if($msg->stopReason==='max_tokens')throw new RuntimeException('The AI answer was cut off. Try again.');
 $text='';foreach($msg->content as $block)if($block->type==='text')$text.=$block->text;
 $data=json_decode($text,true);
 if(!is_array($data))throw new RuntimeException('Claude returned an unreadable answer'.($title?" ($title)":'').'. Try again.');
 return $data;
}

// Strict object schema helper: every property required, no extras (what structured outputs expect).
function aiObject(array $props, ?string $title=null): array {
 return array_filter(['title'=>$title,'type'=>'object','properties'=>$props,'required'=>array_keys($props),'additionalProperties'=>false],fn($v)=>$v!==null);
}

// Tiny request to prove the key and provider work. Returns a human-readable status.
function aiTest(string $prov): string {
 if(!providerReady($prov))throw new RuntimeException($prov==='gemini'?'No Gemini key saved.':(is_file(ROOT.'/vendor/autoload.php')?'No Claude key saved.':'Claude SDK not installed (composer install --no-dev).'));
 $t=microtime(true);
 $r=aiJson('Reply with ok = true.',aiObject(['ok'=>['type'=>'boolean']],'test'),200,'low','general',$prov);
 if(empty($r['ok']))throw new RuntimeException('It answered, but not as expected. Try again.');
 return ($prov==='gemini'?'Gemini ('.($GLOBALS['aiModelUsed']??geminiModel()).')':'Claude ('.AI_MODEL.')').' answered in '.round(microtime(true)-$t,1).'s';
}
