#!/bin/bash

# 配置项
APP_NAME="simplyCalDav"          # 应用名称
BASE_ROOT=$(dirname $(dirname "$(readlink -f "$0")"))
APP_PID_FILE="$BASE_ROOT/run/$APP_NAME.pid"  # PID 文件路径
APP_SCRIPT="$BASE_ROOT/server.php"     # Swoole 启动脚本
APP_LOG_FILE="$BASE_ROOT/log/$APP_NAME.log" # 日志文件路径

PID_ROOT=`dirname $APP_PID_FILE`
if [ ! -d "$PID_ROOT" ]; then
    mkdir -p "$PID_ROOT"
fi

LOG_ROOT=`dirname $APP_LOG_FILE`
if [ ! -d "$LOG_ROOT" ]; then
    mkdir -p "$LOG_ROOT"
fi

# 启动 Swoole 服务
function start() {
    if [ -f $APP_PID_FILE ]; then
        if kill -0 $(cat $APP_PID_FILE) > /dev/null 2>&1; then
            echo "$APP_NAME is running."
            exit 1
        fi
    fi

    nohup php $APP_SCRIPT > $APP_LOG_FILE 2>&1 &
    echo $! > $APP_PID_FILE
    echo "$APP_NAME start success."
}

# 重载 Swoole 服务
function reload() {
    if [ ! -f $APP_PID_FILE ]; then
        echo "$APP_NAME is not running."
        exit 1
    fi
    if ! kill -0 $(cat $APP_PID_FILE) > /dev/null 2>&1; then
        echo "$APP_NAME is not running."
        rm -fr $APP_PID_FILE
        exit
    fi

    kill -USR1 $(cat $APP_PID_FILE)
    echo "$APP_NAME reload complete"
}

# 关闭 Swoole 服务
function stop() {
    if [ ! -f $APP_PID_FILE ]; then
        echo "$APP_NAME is not start"
        exit 1
    fi

    kill $(cat $APP_PID_FILE)
    rm -f $APP_PID_FILE
    echo "$APP_NAME is stop."
}

# 查看 Swoole 服务状态
function status() {
    if [ -f $APP_PID_FILE ]; then
        if kill -0 $(cat $APP_PID_FILE) > /dev/null 2>&1; then
            echo "$APP_NAME is running."
            exit
        else
            rm -fr $APP_PID_FILE
        fi
    fi
    echo "Swoole is not run"
}

# 脚本入口
case "$1" in
    start)
        start
        ;;
    reload)
        reload
        ;;
    stop)
        stop
        ;;
    status)
        status
        ;;
    restart)
        stop
        start
        ;;
    *)
        echo "使用方法: $0 {start|reload|stop|status|restart}"
        exit 1
        ;;
esac