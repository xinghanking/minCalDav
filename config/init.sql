START TRANSACTION;
CREATE TABLE IF NOT EXISTS `user` (
    `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` varchar(64) NOT NULL DEFAULT '' COMMENT '用户名',
    `password` varbinary(255) NOT NULL DEFAULT '' COMMENT '密码',
    `email` varchar(255) NOT NULL DEFAULT '' COMMENT '邮箱',
    `status` tinyint UNSIGNED NOT NULL DEFAULT '0' COMMENT '状态',
    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='用户表';
CREATE TABLE IF NOT EXISTS  `prop_ns` (
    `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
    `prefix` varchar(10) NOT NULL DEFAULT 'D',
    `uri` varchar(255) NOT NULL DEFAULT 'DAV:',
    `user_agent` varbinary(1024) NOT NULL DEFAULT '',
    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `prefix`(`prefix`),
    UNIQUE KEY `uri` (`uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='命名空间表';
INSERT IGNORE INTO `prop_ns` (`id`, `prefix`, `uri`) VALUES (1, 'd', 'DAV:'),(2, 'c', 'urn:ietf:params:xml:ns:caldav'),(3, 'cs', 'http://calendarserver.org/ns/'),(4, 'ics', 'http://icalendar.org/ns/'),(5, 'card', 'urn:ietf:params:xml:ns:carddav'),(6, 'vc', 'urn:ietf:params:xml:ns:vcard'),(7, 'ical', 'http://apple.com/ns/ical/'),(8, 'dp', 'DAV:Push');
CREATE TABLE IF NOT EXISTS `calendar` (
    `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
    `uri` varbinary(255) NOT NULL DEFAULT '' COMMENT '日历地址',
    `owner_id` int UNSIGNED NOT NULL DEFAULT '0' COMMENT '用户id',
    `displayname` varchar(255) GENERATED ALWAYS AS (json_unquote(json_extract(`prop`,_utf8mb4'$."d:displayname"'))) STORED,
    `calscale` varchar(255) NOT NULL DEFAULT 'GREGORIAN',
    `tzid` varchar(20) NOT NULL DEFAULT 'Asia/Shanghai',
    `component_set` varchar(255) NOT NULL DEFAULT 'vevent,vtodo' COMMENT '支持的组件',
    `prop` json NOT NULL,
    `comp_prop` varchar(1024) NOT NULL DEFAULT '',
    `ics_data` mediumblob,
    `last_modified` char(40) GENERATED ALWAYS AS (json_unquote(json_extract(`prop`,_utf8mb4'$."d:getlastmodified"'))) STORED,
    `etag` varchar(255) GENERATED ALWAYS AS (json_unquote(json_extract(`prop`,_utf8mb4'$."d:getetag"'))) STORED COMMENT '修改标识',
    `sync_token` varchar(255) GENERATED ALWAYS AS (json_unquote(json_extract(`prop`,_utf8mb4'$."d:sync-token"'))) STORED,
    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uri` (`uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='日历表';
CREATE TABLE IF NOT EXISTS `comp` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `uid` varchar(255) NOT NULL DEFAULT '',
    `recurrence_id` varchar(255) NOT NULL DEFAULT '',
    `uri` varchar(255) NOT NULL DEFAULT '',
    `calendar_id` bigint unsigned NOT NULL DEFAULT '0',
    `comp_type` tinyint unsigned NOT NULL DEFAULT '0',
    `dtstamp` int unsigned NOT NULL DEFAULT '0',
    `dtstart` int unsigned NOT NULL DEFAULT '0',
    `dtend` int unsigned NOT NULL DEFAULT '0',
    `prop` json DEFAULT NULL,
    `comp_prop` json NOT NULL,
    `ics_data` text,
    `last_modified` char(40) GENERATED ALWAYS AS (json_unquote(json_extract(`prop`,_utf8mb4'$."d:getlastmodified"'))) STORED,
    `etag` varchar(255) GENERATED ALWAYS AS (json_unquote(json_extract(`prop`,_utf8mb4'$."d:getetag"'))) STORED,
    `sequence` bigint unsigned NOT NULL DEFAULT '0',
    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uid` (`calendar_id`,`uid`,`recurrence_id`) USING BTREE,
    KEY `calendar_id` (`calendar_id`),
    KEY `uri` (`uri`),
    FOREIGN KEY (`calendar_id`) REFERENCES `calendar`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='日历组件表';
CREATE TABLE IF NOT EXISTS  `timezone` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '唯一标识符',
    `calendar_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT '日历id',
    `tzid` varchar(50) NOT NULL DEFAULT '',
    `standard` text,
    `daylight` text,
    `last_modified` varchar(16) NOT NULL DEFAULT '',
    `ics_data` text NOT NULL,
    `sequence` int unsigned NOT NULL DEFAULT '0',
    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `calendar_id` (`calendar_id`,`tzid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='时区表';
CREATE TABLE IF NOT EXISTS `change` (
    `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
    `calendar_id` bigint UNSIGNED NOT NULL DEFAULT '0' COMMENT '日历id',
    `ics` json NOT NULL COMMENT '所包含组件修改对应的次号记录对象',
    `sync_token` varchar(255) NOT NULL DEFAULT '',
    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `calendar_id` (`calendar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='版本记录表';
COMMIT;