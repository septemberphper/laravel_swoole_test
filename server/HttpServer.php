<?php

namespace Server;

use Swoole\Http\Server;
use App\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use App\Console\Kernel as CKernel;
use App\Http\Kernel as HKernel;
use Illuminate\Http\Request;
use Illuminate\Contracts\Console\Kernel as CCKernel;

class HttpServer
{
    private $host = "192.168.88.194";

    private $port = "9001";

    private $serverInstance;

    public function __construct()
    {
        $this->serverInstance = new Server($this->host, $this->port);
        $this->configSet();
        $this->serverInstance->on("WorkerStart", [$this, "onWorkStart"]);
        $this->serverInstance->on("request", [$this, "onRequest"]);
        $this->run();
    }

    public function configSet() {
        $this->serverInstance->set([
            "enable_static_handler" => true,
            "document_root" => "/var/www/laravel_swoole_test/resources/views",
            "work_num" => 4
        ]);
    }

    public function onWorkStart(Server $server, $workId)
    {
        // 加载laravel框架引导文件
        define('LARAVEL_START', microtime(true));
        define('APP_DIR', '/var/www/laravel_swoole_test');

        require APP_DIR . '/vendor/autoload.php';
        $this->loadApplication();
    }

    // 接受客户端请求
    public function onRequest($request, $response)
    {
        // 超全局变量常驻内存 需要手动释放
        $_SERVER = [];
        if (isset($request->server)) {
            foreach ($request->server as $key => $v) {
                $_SERVER[strtoupper($key)] = $v;
            }
        }

        $_GET = [];
        if (isset($request->get)) {
            foreach ($request->get as $key => $v) {
                $_GET[$key] = $v;
            }
        }

        $_POST = [];
        if (isset($request->post)) {
            foreach ($request->post as $key => $v) {
                $_POST[$key] = $v;
            }
        }

        // 接受请求并响应
        try {
            ob_start();

            $app = $this->loadApplication();
            $kernel = $app->make(Kernel::class);

            $resp = $kernel->handle(
                $req = Request::capture()
            );

            $resp->send();
            $kernel->terminate($req, $resp);

            $returnContent = ob_get_contents();
            ob_end_clean();
            $response->end($returnContent);
//            $this->serverInstance->close();

        } catch (\Exception $e) {

        }
    }

    public function run()
    {
        $this->serverInstance->start();
    }


    public function loadApplication()
    {
        $app = new Application(
            realpath(APP_DIR)
        );

        $app->singleton(
            Kernel::class,
            HKernel::class
        );

        $app->singleton(
            CCKernel::class,
            CKernel::class
        );

        $app->singleton(
            ExceptionHandler::class,
            Handler::class
        );

        return $app;
    }
}

new HttpServer();
