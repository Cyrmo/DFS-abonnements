-- DFS Abonnements — Table de journalisation
CREATE TABLE IF NOT EXISTS `PREFIX_dfs_abonnements_log` (
    `id_log`       INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_order`     INT(10) UNSIGNED NOT NULL,
    `id_customer`  INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `action`       ENUM('promo_generated','promo_email_sent','pret_email_sent','termine_email_sent') NOT NULL,
    `detail`       VARCHAR(255) NOT NULL DEFAULT '',
    `date_add`     DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    KEY `idx_id_order` (`id_order`),
    KEY `idx_action`   (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
