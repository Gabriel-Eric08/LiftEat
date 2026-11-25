-- Check-in da dieta: refeições que o aluno marcou como feitas em cada dia.
--
-- O montador do plano regrava os ids das refeições a cada salvamento, então o
-- registro guarda o NOME da refeição e da opção (e uma chave normalizada do nome)
-- em vez do id. plan_meal_count guarda quantas refeições o plano tinha no
-- momento do check-in, para calcular a adesão de dias passados mesmo que o
-- plano mude depois.

CREATE TABLE diet_meal_logs (
    id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    student_id       INT UNSIGNED      NOT NULL,
    diet_plan_id     INT UNSIGNED      NULL,
    log_date         DATE              NOT NULL,
    meal_key         VARCHAR(80)       NOT NULL,     -- nome da refeição normalizado (minúsculas, espaços únicos)
    meal_name        VARCHAR(80)       NOT NULL,
    option_name      VARCHAR(80)       NULL,
    plan_meal_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_meal_logs_day (student_id, log_date, meal_key),
    KEY idx_meal_logs_plan (diet_plan_id),
    CONSTRAINT fk_meal_logs_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_meal_logs_plan FOREIGN KEY (diet_plan_id) REFERENCES diet_plans (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
