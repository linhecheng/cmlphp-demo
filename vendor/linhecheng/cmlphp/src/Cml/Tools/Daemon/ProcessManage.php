<?php namespace Cml\Tools\Daemon;
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 2016/01/23 17:30
 * @version  2.6
 * cml框架 守护进程工作进程
 * *********************************************************** */

/**
 * 守护进程工作进程工作类
 *
 * @package Cml\Tools\Daemon
 */
class ProcessManage
{
    private static $pidFile; //pid文件
    private static $log; //log文件
    private static $status; //状态文件
    private static $user = 'nobody'; //用户组

    /**
     *检查是否安装了相关扩展
     *
     */
    private static function checkExtension()
    {
        if(!extension_loaded('posix')) {
            die('error:need PHP posix extension!');
        }

        // 检查扩展
        if(!extension_loaded('pcntl')) {
            die('error:need PHP pcntl extension!');
        }

    }

    /**
     * 向shell输出一条消息
     *
     * @param string $message
     */
    private static function message($message = '')
    {
        printf(PHP_EOL . "%s %d %d %s". PHP_EOL, date('Y-m-d H:i:s'), posix_getpid(), posix_getppid(), $message);
    }

    /**
     * 获取进程id
     *
     * @return int
     */
    private static function getPid()
    {
        if (!is_file(self::$pidFile)) {
            return 0;
        }

        $pid = intval(file_get_contents(self::$pidFile));
        return $pid;
    }

    /**
     * 设置进程名称
     *
     * @param $title
     */
    protected static function setProcessName($title)
    {
        $title = "cmlphp_daemon_{$title}";
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($title);
        } elseif(extension_loaded('proctitle') && function_exists('setproctitle')) {
            setproctitle($title);
        }
    }

    /**
     * 初始化守护进程
     *
     */
    private static function demonize()
    {
        php_sapi_name() != 'cli' && die('should run in cli');

        umask(0);
        $pid = pcntl_fork();
        if($pid < 0) {
            die("can't Fork!");
        } else if ($pid > 0) {
            exit();
        }

        if (posix_setsid() === -1) {//使进程成为会话组长。让进程摆脱原会话的控制；让进程摆脱原进程组的控制；
            die('could not detach');
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            die("can't fork2!");
        } elseif ($pid > 0) {
            self::message('start success!');exit;
        }

        defined('STDIN') && fclose(STDIN);
        defined('STDOUT') && fclose(STDOUT);
        defined('STDERR') && fclose(STDERR);
        $stdin  = fopen(self::$log, 'r');
        $stdout = fopen(self::$log, 'a');
        $stderr = fopen(self::$log, 'a');

        self::setUser(self::$user);

        file_put_contents(self::$pidFile, posix_getpid()) || die("can't create pid file");
        self::setProcessName('master');

        pcntl_signal(SIGINT,  array('\\'.__CLASS__, 'signalHandler'), false);
        pcntl_signal(SIGUSR1, array('\\'.__CLASS__, 'signalHandler'), false);

        file_put_contents(self::$status, '<?php return '.var_export(array(), true).';', LOCK_EX );
        self::createChildrenProcess();

        while (true) {
            pcntl_signal_dispatch();
            $pid = pcntl_wait($status, WUNTRACED);
            pcntl_signal_dispatch();

            if ($pid > 0) {
                $status = self::getStatus();
                if (isset($status['pid'][$pid])) {
                    unset($status['pid'][$pid]);
                    file_put_contents(self::$status, '<?php return '.var_export($status, true).';', LOCK_EX );
                }
                self::createChildrenProcess();
            }
            sleep(1);
        }

        return ;
    }

    /**
     * 设置运行的用户
     *
     * @param string $name
     *
     * @return bool
     */
    private static function setUser($name)
    {
        $result = false;
        if (empty($name)){
            return true;
        }
        $user = posix_getpwnam($name);
        if ($user) {
            $uid = $user['uid'];
            $gid = $user['gid'];
            $result = posix_setuid($uid);
            posix_setgid($gid);
        }
        return $result;

    }

    /**
     * 信号处理
     *
     * @param int $signo
     *
     */
    private static function signalHandler($signo)
    {
        switch($signo) {
            // stop
            case SIGINT:
                self::signStop();
                break;
            // reload
            case SIGUSR1:
                self::signReload();
                break;
        }
    }

    /**
     * reload
     *
     */
    private static function signReload()
    {
        $pid = self::getPid();
        if ($pid == posix_getpid()) {
            $status = self::getStatus();
            foreach($status['pid'] as $cid) {
                posix_kill($cid, SIGUSR1);
            }
            $status['pid'] = array();
            file_put_contents(self::$status, '<?php return '.var_export($status, true).';', LOCK_EX );
        } else {
            exit(posix_getpid().'reload...');
        }
    }

    /**
     * stop
     *
     */
    private static function signStop()
    {
        $pid = self::getPid();
        if ($pid == posix_getpid()) {
            $status = self::getStatus();
            foreach($status['pid'] as $cid) {
                posix_kill($cid, SIGINT);
            }
            sleep(3);
            unlink(self::$pidFile);
            unlink(self::$status);
            echo 'stoped'.PHP_EOL;
        }
        exit(posix_getpid() . 'exit...');
    }

    /**
     * 添加任务
     *
     * @param string $task 任务的类名带命名空间
     * @param int $frequency 执行的频率
     *
     * @return void
     */
    public static function addTask($task, $frequency = 60)
    {
        $task || self::message('task is empty');

        $status = self::getStatus();

        isset($status['task']) || $status['task'] = array();

        $key = md5($task);
        isset($status['task'][$key]) || $status['task'][$key] = array(
            'last_runtime' => 0,//上一次运行时间
            'frequency' => $frequency,//执行的频率
            'task' => $task
        );
        file_put_contents(self::$status, '<?php return '.var_export($status, true).';', LOCK_EX );

        self::message('task nums (' . count($status['task']) . ') list  ['.json_encode($status['task'], PHP_VERSION >= '5.4.0' ? JSON_UNESCAPED_UNICODE : 0).']');
    }

    /**
     * 删除任务
     *
     * @param string $task 任务的类名带命名空间
     *
     * @return void
     */
    public static function rmTask($task)
    {
        $task || self::message('task name is empty');

        $status = self::getStatus();

        if (!isset($status['task']) || count($status['task']) < 1) {
            self::message('task is empty');
            return;
        }

        $key = md5($task);
        if (isset($status['task'][$key])) {
            unset($status['task'][$key]);
        } else {
            self::message($task . 'task not found');
            return;
        }

        self::message("rm task [{$task}] success");
        file_put_contents(self::$status, '<?php return '.var_export($status, true).';', LOCK_EX );

        self::message('task nums (' . count($status['task']) . ') list  ['.json_encode($status['task'], PHP_VERSION >= '5.4.0' ? JSON_UNESCAPED_UNICODE : 0).']');
    }

    /**
     * 开始运行
     *
     */
    public static function start()
    {
        if (self::getPid() > 0) {
            self::message('already running...');
        } else {
            self::message('start');
            self::demonize();
        }
    }

    /**
     * 检查脚本运气状态
     *
     * @param bool $showInfo 是否直接显示状态
     *
     * @return array
     */
    public static function getStatus($showInfo = false) {

        $status = is_file(self::$status) ? require(self::$status) : array();
        if (!$showInfo) {
            return $status;
        }

        if (self::getPid() > 0) {
            self::message('is running');
            self::message('master pid is '.self::getPid());
            self::message('worker pid is ['.implode($status['pid'], ',').']');
            self::message('task nums (' . count($status['task']) . ') list  ['.json_encode($status['task'], PHP_VERSION >= '5.4.0' ? JSON_UNESCAPED_UNICODE : 0).']');
        } else {
            echo 'not running' .PHP_EOL;
        }
    }

    /**
     * shell参数处理并启动守护进程
     *
     * @param string $cmd
     */
    public static function run($cmd)
    {
        self::$pidFile = CML_RUNTIME_PATH.DIRECTORY_SEPARATOR.'DaemonProcess_.pid';
        self::$log = CML_RUNTIME_PATH.DIRECTORY_SEPARATOR.'DaemonProcess_.log';
        self::$status = CML_RUNTIME_PATH.DIRECTORY_SEPARATOR.'DaemonProcessStatus.php';
        self::checkExtension();

        $param = is_array($cmd) && count($cmd) == 2 ? $cmd[1] : $cmd;
        switch ($param) {
            case 'start':
                self::start();
                break;
            case 'stop':
                posix_kill(self::getPid(), SIGINT);
                self::message('stop....');
                break;
            case 'reload':
                posix_kill(self::getPid(), SIGUSR1);
                self::message('reloading....');
                break;
            case 'status':
                self::getStatus(true);
                break;
            case 'addtask':
                if (func_num_args() < 1) {
                    self::message('please input task name');
                    break;
                }
                $args = func_get_args();
                $frequency = isset($args[2]) ? intval($args[2]) : 60;
                $frequency < 1 || $frequency = 60;
                self::addTask($args[1], $frequency);
                break;
            case 'rmtask':
                if (func_num_args() < 1) {
                    self::message('please input task name');
                    break;
                }
                $args = func_get_args();
                self::rmTask($args[1]);
                break;
            default:
                self::message('Usage: xxx.php cml.cmd DaemonWorker::run {start|stop|status|addtask|rmtask}');
                break;
        }
    }

    /**
     * 创建一个子进程
     *
     */
    protected static function createChildrenProcess()
    {
        $pid = pcntl_fork();

        if($pid > 0) {
            $status = self::getStatus();
            $status['pid'][$pid] = $pid;
            isset($status['task']) || $status['task'] = array();
            file_put_contents(self::$status, '<?php return '.var_export($status, true).';', LOCK_EX );
        } elseif($pid === 0) {
            self::setProcessName('worker');
            while (true) {
                pcntl_signal_dispatch();
                $status = self::getStatus();
                if ($status['task']) {
                    foreach($status['task'] as $key => $task) {
                        if (time() > ($task['last_runtime'] + $task['frequency']) ) {
                            $status['task'][$key]['last_runtime'] = time();
                            file_put_contents(self::$status, '<?php return '.var_export($status, true).';', LOCK_EX );
                            call_user_func($task['task']);
                        }
                    }
                    sleep(3);
                } else {
                    sleep(5);
                }
            }
        }  else {
            exit('create process error');
        }
    }
}