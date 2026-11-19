-- LiftEat — schema inicial
-- Compatível com MySQL 5.7+ / 8.x (InnoDB, utf8mb4).
-- Convenção de dia da semana: 1 = segunda ... 7 = domingo (ISO-8601, igual a date('N') do PHP).

-- ---------------------------------------------------------------------------
-- Acesso
-- ---------------------------------------------------------------------------

-- Personal (único administrador do sistema).
CREATE TABLE admins (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    last_login_at   DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alunos. Acesso liberado quando status = 'active' E access_until >= hoje.
CREATE TABLE students (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name            VARCHAR(120)  NOT NULL,
    email           VARCHAR(190)  NOT NULL,
    password_hash   VARCHAR(255)  NOT NULL,
    phone           VARCHAR(30)   NULL,
    birth_date      DATE          NULL,
    height_cm       SMALLINT UNSIGNED NULL,
    weight_kg       DECIMAL(5,2)  NULL,
    goal            VARCHAR(255)  NULL,
    notes           TEXT          NULL,
    photo_path      VARCHAR(255)  NULL,

    -- Financeiro / acesso
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active', -- chave manual do personal
    monthly_fee     DECIMAL(10,2) NOT NULL DEFAULT 0.00,                  -- quanto o aluno paga por mês
    access_until    DATE          NULL,                                   -- último dia de acesso pago
    auto_renew      TINYINT(1)    NOT NULL DEFAULT 0,                     -- renova +1 mês e lança cobrança pendente

    last_login_at   DATETIME      NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME      NULL,                                   -- exclusão lógica (preserva histórico)
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_email (email),
    KEY idx_students_access (status, access_until),
    KEY idx_students_renew (auto_renew, access_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proteção contra força bruta no login.
CREATE TABLE login_attempts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email           VARCHAR(190)    NOT NULL,
    ip_address      VARCHAR(45)     NOT NULL,
    succeeded       TINYINT(1)      NOT NULL DEFAULT 0,
    attempted_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_email (email, attempted_at),
    KEY idx_login_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Financeiro
-- ---------------------------------------------------------------------------

-- Cada linha = uma mensalidade (um período de acesso).
-- Pagamento manual: personal registra como 'paid' -> access_until avança 1 mês.
-- Renovação automática: sistema estende o acesso e cria a linha como 'pending'.
CREATE TABLE payments (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED  NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    period_start    DATE          NOT NULL,
    period_end      DATE          NOT NULL,
    due_date        DATE          NOT NULL,
    status          ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    paid_at         DATETIME      NULL,          -- base do "quanto recebi no mês"
    method          VARCHAR(30)   NULL,          -- pix, dinheiro, cartão, transferência...
    origin          ENUM('manual','auto_renewal') NOT NULL DEFAULT 'manual',
    notes           VARCHAR(255)  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payments_student (student_id, period_start),
    KEY idx_payments_received (status, paid_at),
    KEY idx_payments_due (status, due_date),
    CONSTRAINT fk_payments_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Treinos
-- ---------------------------------------------------------------------------

-- Biblioteca de exercícios do personal (reutilizável entre alunos).
CREATE TABLE exercises (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(120) NOT NULL,
    muscle_group    VARCHAR(60)  NOT NULL,        -- peito, costas, quadríceps...
    equipment       VARCHAR(60)  NULL,            -- halter, máquina, peso corporal...
    description     TEXT         NULL,            -- execução / dicas
    video_url       VARCHAR(255) NULL,
    image_path      VARCHAR(255) NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1, -- desativar em vez de excluir (há histórico)
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_exercises_group (muscle_group, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Treino de um aluno (ex.: "Treino A — Peito e tríceps").
CREATE TABLE workouts (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED NOT NULL,
    name            VARCHAR(120) NOT NULL,
    notes           TEXT         NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_workouts_student (student_id),
    CONSTRAINT fk_workouts_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qual treino o aluno faz em cada dia da semana (um por dia; o mesmo treino
-- pode cair em vários dias). Dia sem linha = descanso.
CREATE TABLE workout_schedule (
    student_id      INT UNSIGNED     NOT NULL,
    weekday         TINYINT UNSIGNED NOT NULL,    -- 1=seg ... 7=dom
    workout_id      INT UNSIGNED     NOT NULL,
    PRIMARY KEY (student_id, weekday),
    KEY idx_schedule_workout (workout_id),
    CONSTRAINT fk_schedule_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_schedule_workout FOREIGN KEY (workout_id) REFERENCES workouts (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exercícios prescritos dentro de um treino.
CREATE TABLE workout_exercises (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    workout_id      INT UNSIGNED      NOT NULL,
    exercise_id     INT UNSIGNED      NOT NULL,
    position        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    sets            TINYINT UNSIGNED  NOT NULL DEFAULT 3,
    reps            VARCHAR(20)       NOT NULL DEFAULT '10',  -- "10", "8-12", "até a falha"
    load_kg         DECIMAL(6,2)      NULL,                   -- carga sugerida
    rest_seconds    SMALLINT UNSIGNED NULL,
    technique       VARCHAR(60)       NULL,                   -- drop-set, bi-set...
    notes           VARCHAR(255)      NULL,
    PRIMARY KEY (id),
    KEY idx_workout_exercises_order (workout_id, position),
    KEY idx_workout_exercises_exercise (exercise_id),
    CONSTRAINT fk_wex_workout FOREIGN KEY (workout_id) REFERENCES workouts (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_wex_exercise FOREIGN KEY (exercise_id) REFERENCES exercises (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Execução de um treino pelo aluno (botão "Iniciar treino").
CREATE TABLE workout_sessions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED NOT NULL,
    workout_id      INT UNSIGNED NULL,            -- NULL se o treino for excluído depois
    workout_name    VARCHAR(120) NOT NULL,        -- cópia do nome, preserva o histórico
    status          ENUM('in_progress','completed','abandoned') NOT NULL DEFAULT 'in_progress',
    started_at      DATETIME     NOT NULL,
    finished_at     DATETIME     NULL,
    notes           TEXT         NULL,            -- comentário do aluno
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sessions_student (student_id, started_at),
    KEY idx_sessions_workout (workout_id),
    CONSTRAINT fk_sessions_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sessions_workout FOREIGN KEY (workout_id) REFERENCES workouts (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Séries realizadas na sessão (carga/reps reais -> evolução por exercício).
CREATE TABLE session_sets (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    session_id          INT UNSIGNED     NOT NULL,
    exercise_id         INT UNSIGNED     NOT NULL,
    workout_exercise_id INT UNSIGNED     NULL,     -- prescrição de origem, se ainda existir
    set_number          TINYINT UNSIGNED NOT NULL,
    reps                SMALLINT UNSIGNED NULL,
    load_kg             DECIMAL(6,2)     NULL,
    completed           TINYINT(1)       NOT NULL DEFAULT 1,
    created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_session_sets (session_id, exercise_id, set_number),
    KEY idx_session_sets_progress (exercise_id, created_at),
    CONSTRAINT fk_sets_session FOREIGN KEY (session_id) REFERENCES workout_sessions (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sets_exercise FOREIGN KEY (exercise_id) REFERENCES exercises (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_sets_wex FOREIGN KEY (workout_exercise_id) REFERENCES workout_exercises (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fotos opcionais anexadas à sessão.
CREATE TABLE session_photos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id      INT UNSIGNED NOT NULL,
    file_path       VARCHAR(255) NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_session_photos_session (session_id),
    CONSTRAINT fk_photos_session FOREIGN KEY (session_id) REFERENCES workout_sessions (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Dieta
-- ---------------------------------------------------------------------------

-- Plano alimentar diário do aluno (só um ativo por vez — garantido na aplicação).
CREATE TABLE diet_plans (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED NOT NULL,
    name            VARCHAR(120) NOT NULL,
    notes           TEXT         NULL,
    target_kcal     SMALLINT UNSIGNED NULL,
    target_protein_g SMALLINT UNSIGNED NULL,
    target_carbs_g  SMALLINT UNSIGNED NULL,
    target_fat_g    SMALLINT UNSIGNED NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_diet_plans_student (student_id, is_active),
    CONSTRAINT fk_diet_plans_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refeições do plano (café da manhã, almoço...).
CREATE TABLE diet_meals (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    diet_plan_id    INT UNSIGNED      NOT NULL,
    name            VARCHAR(80)       NOT NULL,
    meal_time       TIME              NULL,
    position        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    notes           VARCHAR(255)      NULL,
    PRIMARY KEY (id),
    KEY idx_diet_meals_order (diet_plan_id, position),
    CONSTRAINT fk_meals_plan FOREIGN KEY (diet_plan_id) REFERENCES diet_plans (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opções de cada refeição (aluno escolhe uma). Refeição simples = 1 opção.
CREATE TABLE diet_meal_options (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    meal_id         INT UNSIGNED      NOT NULL,
    name            VARCHAR(80)       NOT NULL DEFAULT 'Opção 1',
    position        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    notes           VARCHAR(255)      NULL,
    PRIMARY KEY (id),
    KEY idx_meal_options_order (meal_id, position),
    CONSTRAINT fk_options_meal FOREIGN KEY (meal_id) REFERENCES diet_meals (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alimentos de uma opção.
CREATE TABLE diet_meal_items (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    option_id       INT UNSIGNED      NOT NULL,
    food            VARCHAR(150)      NOT NULL,
    quantity        VARCHAR(50)       NOT NULL,     -- "100 g", "2 unidades", "1 colher de sopa"
    position        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    notes           VARCHAR(255)      NULL,
    PRIMARY KEY (id),
    KEY idx_meal_items_order (option_id, position),
    CONSTRAINT fk_items_option FOREIGN KEY (option_id) REFERENCES diet_meal_options (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
