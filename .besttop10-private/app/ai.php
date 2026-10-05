<?php
declare(strict_types=1);
// Claude API (official PHP SDK, installed with `composer install` in .besttop10-private/).
// The API key lives in .besttop10-private/storage/anthropic-key.txt (mode 600) or the ANTHROPIC_API_KEY env var.
// Everything the AI writes is a suggestion or a draft: an editor applies or publishes it.

const AI_KEY_FILE = ROOT . '/storage/anthropic-key.txt';
const AI_MODEL = 'claude-opus-5-5';

function aiAvailable(): bool { return is_file(ROOT.'/vendor/autoload.php')&&aiKey()!==''; }
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
function aiJson(string $prompt, array $schema, int $maxTokens=16000, string $effort='medium'): array {
 if($mock=getenv('AI_MOCK_DIR')){ // tests: fixture named after the schema's title
  $f=$mock.'/'.($schema['title']??'out').'.json';if(!is_file($f))throw new RuntimeException("No AI mock $f");return json_decode((string)file_get_contents($f),true);
 }
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
 if(!is_array($data))throw new RuntimeException('The AI returned an unreadable answer'.($title?" ($title)":'').'. Try again.');
 return $data;
}

// Strict object schema helper: every property required, no extras (what structured outputs expect).
function aiObject(array $props, ?string $title=null): array {
 return array_filter(['title'=>$title,'type'=>'object','properties'=>$props,'required'=>array_keys($props),'additionalProperties'=>false],fn($v)=>$v!==null);
}
