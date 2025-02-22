#minCalDav

## Description

**minCalDav** 是一个简单的 CalDAV 服务器实现，提供了基本的 CalDAV 协议支持，适用于轻量级的日历管理需求。它支持日历的创建、事件管理以及基本的 CalDAV 操作。

## Installation

### Steps

1. 下载全部代码到你的linux服务器的工作目录;
2. 执行composer install
3. 编辑器打开config/caldav.php 进行本地IP和端口号以及数据库的配置;
4. 执行php install.php 导入数据库和初始化.
5. 执行 /bin/caldav.sh useradd 添加运行用户，如：xinghan;
6. 执行 /bin/caldav.sh start 可打开程序运行.
7. 程序运行成功开启后， caldav客户端（如手机日历类app）可通过 url: http://IP:端口/，用户名和密码，连接操作。
