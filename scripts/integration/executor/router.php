<?php
if (PHP_SAPI!=='cli-server' || getenv('EXECUTOR_QA_MODE')!=='1') {http_response_code(404);exit;}
$root=dirname(__DIR__,3);$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path!=='/' && is_file($root.'/public'.$path)) return false;
require __DIR__.'/bootstrap.php';
[$app,$kernel,$directory,$settings]=executorQaApp();
if (preg_match('#^/api/executor/executions/([a-f0-9-]+)/result$#D',$path,$match)) {
    $body=file_get_contents('php://input');
    file_put_contents($directory.'/callback-'.$match[1].'.json',$body);
    if (is_file($directory.'/fail-next-callback')) {
        unlink($directory.'/fail-next-callback');file_put_contents($directory.'/callback-retried','yes');http_response_code(503);echo '{}';exit;
    }
}
if (str_starts_with($path,'/insider')) {
    $actor=$_GET['actor']??$_COOKIE['executor_qa_actor']??'student';
    if (isset($_GET['actor'])) setcookie('executor_qa_actor',$actor,['path'=>'/']);
    Illuminate\Support\Facades\Auth::setUser(App\User::findOrFail(['student'=>1,'teacher'=>2,'other'=>3,'admin'=>5][$actor]??1));
}
$request=Illuminate\Http\Request::capture();$response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);
