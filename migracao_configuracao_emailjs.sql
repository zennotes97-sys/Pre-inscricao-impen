CREATE TABLE IF NOT EXISTS configuracao_emailjs (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    public_key VARCHAR(255) NOT NULL DEFAULT '',
    private_key VARCHAR(255) NOT NULL DEFAULT '',
    service_primary VARCHAR(100) NOT NULL DEFAULT '',
    service_fallback VARCHAR(100) NOT NULL DEFAULT '',
    template_admin VARCHAR(100) NOT NULL DEFAULT '',
    template_applicant VARCHAR(100) NOT NULL DEFAULT '',
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracao_emailjs (id)
VALUES (1)
ON DUPLICATE KEY UPDATE id = VALUES(id);
