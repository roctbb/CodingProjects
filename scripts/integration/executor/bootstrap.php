<?php
// Test entry point only: every database, credential and filesystem is supplied by the disposable harness.
function executorQaApp(bool $console=false) {
    if (getenv('EXECUTOR_QA_MODE')!=='1' || !in_array(PHP_SAPI,['cli','cli-server'],true)) exit(1);
    $directory=getenv('EXECUTOR_QA_DIRECTORY');
    if (!$directory || !is_file($directory.'/settings.json') || !is_file($directory.'/submissions.sqlite')) exit(1);
    $settings=json_decode(file_get_contents($directory.'/settings.json'),true,64,JSON_THROW_ON_ERROR);
    $root=dirname(__DIR__,3);
    require_once $root.'/vendor/autoload.php';
    $app=require $root.'/bootstrap/app.php';
    if (!$console) $app->instance('request',Illuminate\Http\Request::capture());
    $kernel=$app->make($console?Illuminate\Contracts\Console\Kernel::class:Illuminate\Contracts\Http\Kernel::class);
    $kernel->bootstrap();
    config(['app.env'=>'testing','app.url'=>$settings['cp_url'],'app.debug'=>false,
        'database.default'=>'sqlite','database.connections.sqlite.database'=>$directory.'/submissions.sqlite',
        'database.connections.sqlite.busy_timeout'=>30000,
        'cache.default'=>'array','queue.default'=>'database','queue.failed.database'=>'sqlite',
        'session.driver'=>'file','session.files'=>$directory.'/sessions','session.cookie'=>'executor_qa_session','session.domain'=>null,'session.secure'=>false,
        'filesystems.disks.code_checking'=>['driver'=>'local','root'=>$directory.'/code-checking','throw'=>true],
        'filesystems.disks.code_originals'=>['driver'=>'s3','key'=>$settings['s3_key'],'secret'=>$settings['s3_secret'],
            'region'=>'garage','bucket'=>$settings['s3_bucket'],'endpoint'=>$settings['s3_url'],'use_path_style_endpoint'=>true,'throw'=>true],
        'services.geekexecutor_url'=>$settings['executor_url'],'services.geekexecutor_socket_url'=>$settings['executor_url'],
        'services.geekexecutor_public_url'=>$settings['executor_url'],'services.geekexecutor_secret'=>$settings['cp_secret'],
        'services.code_executor_callback_url'=>$settings['cp_url'],'code_checking.ai_detection_enabled'=>false,
        'logging.default'=>'single','logging.channels.single.path'=>$directory.'/cp-errors.log']);
    Illuminate\Support\Facades\DB::purge('sqlite');
    Illuminate\Support\Facades\Notification::fake();
    return [$app,$kernel,$directory,$settings];
}
