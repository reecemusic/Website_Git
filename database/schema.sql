CREATE TABLE mailing_list_subscribers (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	email VARCHAR(254) NOT NULL,
	source VARCHAR(100) NOT NULL DEFAULT 'homepage',
	subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_mailing_list_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;