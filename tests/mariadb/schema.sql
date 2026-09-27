-- Phase 6.2 MariaDB harness — production tablo şekilleri (WordPress dbDelta çıktısı ile uyumlu).
-- Yalnızca test veritabanında kullanılır.

SET NAMES utf8mb4;

DROP TABLE IF EXISTS wp_qmo_chatbot_oneri_log;
DROP TABLE IF EXISTS wp_qmo_chatbot_recommendation_events;
DROP TABLE IF EXISTS wp_rma_analytics;

CREATE TABLE wp_qmo_chatbot_oneri_log (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	oturum_id varchar(64) NOT NULL DEFAULT '',
	masa_no varchar(32) NOT NULL DEFAULT '',
	urun_id bigint(20) unsigned NOT NULL,
	kaynak varchar(20) NOT NULL DEFAULT 'ai',
	durum varchar(20) NOT NULL DEFAULT 'gosterildi',
	created_at datetime NOT NULL,
	PRIMARY KEY (id),
	KEY oturum (oturum_id),
	KEY urun (urun_id),
	KEY durum_tarih (durum, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wp_rma_analytics (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	event_type varchar(30) NOT NULL,
	item_id bigint(20) unsigned NOT NULL DEFAULT 0,
	item_name varchar(255) NOT NULL DEFAULT '',
	category_name varchar(255) NOT NULL DEFAULT '',
	qty smallint(5) unsigned NOT NULL DEFAULT 1,
	price decimal(10,2) NOT NULL DEFAULT 0,
	unit_price decimal(10,2) DEFAULT NULL,
	masa_no varchar(64) NOT NULL DEFAULT '',
	order_id varchar(36) DEFAULT NULL,
	session_id varchar(64) DEFAULT NULL,
	reason varchar(32) DEFAULT NULL,
	ip_hash varchar(32) NOT NULL DEFAULT '',
	created_at datetime NOT NULL,
	PRIMARY KEY (id),
	KEY idx_type (event_type),
	KEY idx_item (item_id),
	KEY idx_date (created_at),
	KEY idx_td (event_type,created_at),
	KEY idx_masa (masa_no),
	KEY idx_masa_td (masa_no,event_type,created_at),
	KEY idx_order_id (order_id),
	KEY idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wp_qmo_chatbot_recommendation_events (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	ref_id varchar(36) NOT NULL DEFAULT '',
	event_type varchar(20) NOT NULL DEFAULT '',
	product_id bigint(20) unsigned NOT NULL,
	session_id varchar(36) NOT NULL DEFAULT '',
	source varchar(20) DEFAULT NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY (id),
	KEY idx_ref_id (ref_id),
	KEY idx_session_product_time (session_id, product_id, created_at),
	KEY idx_event_time (event_type, created_at),
	UNIQUE KEY uniq_ref_event (ref_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
