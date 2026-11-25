-- Peso e medidas corporais registrados pelo aluno no app.
-- Um registro por dia: registrar de novo na mesma data atualiza o registro.

CREATE TABLE body_measurements (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    student_id    INT UNSIGNED  NOT NULL,
    measured_on   DATE          NOT NULL,
    weight_kg     DECIMAL(5,2)  NULL,
    body_fat_pct  DECIMAL(4,1)  NULL,
    chest_cm      DECIMAL(5,1)  NULL,
    waist_cm      DECIMAL(5,1)  NULL,
    hip_cm        DECIMAL(5,1)  NULL,
    arm_cm        DECIMAL(5,1)  NULL,
    thigh_cm      DECIMAL(5,1)  NULL,
    notes         VARCHAR(255)  NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_measurements_day (student_id, measured_on),
    CONSTRAINT fk_measurements_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
