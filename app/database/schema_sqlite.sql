-- Dibuat otomatis dari schema.sql untuk mode DB_DRIVER=sqlite (demo, tanpa MySQL).

CREATE TABLE IF NOT EXISTS posyandu (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  posyandu_code   VARCHAR(20)  NOT NULL UNIQUE,
  name            VARCHAR(100) NOT NULL,
  credential_hash VARCHAR(255) NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT (datetime('now','+7 hours')),
  code_rotated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS login_attempts (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  ip            VARCHAR(45) NOT NULL,
  posyandu_code VARCHAR(20) NOT NULL,
  attempted_at  DATETIME NOT NULL DEFAULT (datetime('now','+7 hours'))
);

CREATE TABLE IF NOT EXISTS sesi_foto (
  id                INTEGER PRIMARY KEY AUTOINCREMENT,
  posyandu_id       INT NOT NULL,
  activity_date     DATE NULL,                       -- tanggal kegiatan Posyandu (jika diketahui)
  form_type         VARCHAR(50) NOT NULL DEFAULT 'buku_penimbangan_balita',
  photo_path        VARCHAR(255) NULL,               -- nama file internal (privat)
  original_filename VARCHAR(255) NULL,
  photo_mime        VARCHAR(50) NULL,
  notes             VARCHAR(500) NULL,
  status            TEXT NOT NULL DEFAULT 'uploaded',
  ocr_engine        VARCHAR(30) NULL,                -- gemini | mock | manual
  error_message     VARCHAR(500) NULL,
  created_at        DATETIME NOT NULL DEFAULT (datetime('now','+7 hours')),   -- waktu unggah
  confirmed_at      DATETIME NULL,
  reopened_at       DATETIME NULL,                   -- terakhir dibuka kembali untuk revisi
  CONSTRAINT fk_sesi_posyandu FOREIGN KEY (posyandu_id) REFERENCES posyandu(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS peserta (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  posyandu_id     INT NOT NULL,
  local_reference VARCHAR(30) NULL,                  -- nomor pendaftaran/urut lokal
  name            VARCHAR(100) NOT NULL,
  date_of_birth   DATE NULL,
  sex             TEXT NULL,
  form_type       VARCHAR(50) NOT NULL DEFAULT 'buku_penimbangan_balita',  -- jenis formulir asal; daftar tiap formulir terpisah
  created_at      DATETIME NOT NULL DEFAULT (datetime('now','+7 hours')),
  CONSTRAINT fk_peserta_posyandu FOREIGN KEY (posyandu_id) REFERENCES posyandu(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pengukuran (
  id                    INTEGER PRIMARY KEY AUTOINCREMENT,
  posyandu_id           INT NOT NULL,
  sesi_foto_id          BIGINT NOT NULL,
  peserta_id            INT NULL,
  nomor_pendaftaran     VARCHAR(30) NULL,
  nama_peserta          VARCHAR(100) NULL,
  tanggal_lahir         DATE NULL,
  jenis_kelamin         TEXT NULL,
  measurement_period    CHAR(7) NULL,                -- YYYY-MM, periode di tabel (bukan tanggal unggah)
  weight_kg             DECIMAL(5,2) NULL,
  height_cm             DECIMAL(5,1) NULL,
  head_circumference_cm DECIMAL(5,1) NULL,
  lila_cm               DECIMAL(5,1) NULL,
  gestational_weeks     SMALLINT NULL,       -- usia kehamilan (minggu), register ibu hamil
  bp_systolic           SMALLINT NULL,       -- tekanan darah sistolik (mmHg)
  bp_diastolic          SMALLINT NULL,       -- tekanan darah diastolik (mmHg)
  blood_glucose         SMALLINT NULL,       -- gula darah (mg/dL), register lansia
  keterangan            VARCHAR(255) NULL,
  needs_review          TINYINT(1) NOT NULL DEFAULT 0,
  review_reason         VARCHAR(255) NULL,
  flag_fields           VARCHAR(120) NULL,           -- kolom yang ragu (dipisah koma), untuk penanda di layar review
  ocr_snapshot          TEXT NULL,                   -- nilai hasil baca OCR sebelum dikoreksi (JSON)
  corrected_fields      VARCHAR(160) NULL,           -- kolom yang diubah kader dibanding hasil OCR
  human_checked         TINYINT(1) NOT NULL DEFAULT 0, -- kader menandai baris ini sudah diperiksa
  verification_status   TEXT NOT NULL DEFAULT 'draft',
  verified_at           DATETIME NULL,
  created_at            DATETIME NOT NULL DEFAULT (datetime('now','+7 hours')),
  CONSTRAINT fk_ukur_posyandu FOREIGN KEY (posyandu_id) REFERENCES posyandu(id) ON DELETE CASCADE,
  CONSTRAINT fk_ukur_sesi FOREIGN KEY (sesi_foto_id) REFERENCES sesi_foto(id) ON DELETE CASCADE,
  CONSTRAINT fk_ukur_peserta FOREIGN KEY (peserta_id) REFERENCES peserta(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS ekstraksi (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  sesi_foto_id BIGINT NOT NULL,
  engine_name  VARCHAR(30) NOT NULL,
  raw_result   MEDIUMTEXT NULL,
  status       TEXT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT (datetime('now','+7 hours')),
  CONSTRAINT fk_ekstraksi_sesi FOREIGN KEY (sesi_foto_id) REFERENCES sesi_foto(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS koreksi_log (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  posyandu_id  INT NOT NULL,
  sesi_foto_id BIGINT NOT NULL,
  action       TEXT NOT NULL,
  detail       VARCHAR(500) NULL,
  created_at   DATETIME NOT NULL DEFAULT (datetime('now','+7 hours')),
  CONSTRAINT fk_log_posyandu FOREIGN KEY (posyandu_id) REFERENCES posyandu(id) ON DELETE CASCADE,
  CONSTRAINT fk_log_sesi FOREIGN KEY (sesi_foto_id) REFERENCES sesi_foto(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sesi_foto_data (
  sesi_id BIGINT NOT NULL PRIMARY KEY,
  data    LONGBLOB NOT NULL,
  CONSTRAINT fk_foto_sesi FOREIGN KEY (sesi_id) REFERENCES sesi_foto(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS php_sessions (
  id         VARCHAR(128) NOT NULL PRIMARY KEY,
  data       MEDIUMBLOB NOT NULL,
  expires_at INT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_ip_time ON login_attempts (ip, attempted_at);
CREATE INDEX IF NOT EXISTS idx_code_time ON login_attempts (posyandu_code, attempted_at);
CREATE INDEX IF NOT EXISTS idx_posyandu_status ON sesi_foto (posyandu_id, status);
CREATE INDEX IF NOT EXISTS idx_posyandu_created ON sesi_foto (posyandu_id, created_at);
CREATE INDEX IF NOT EXISTS idx_peserta_form ON peserta (posyandu_id, form_type, local_reference);
CREATE INDEX IF NOT EXISTS idx_peserta_ref ON peserta (posyandu_id, local_reference);
CREATE INDEX IF NOT EXISTS idx_peserta_name ON peserta (posyandu_id, name, date_of_birth);
CREATE INDEX IF NOT EXISTS idx_sesi ON pengukuran (sesi_foto_id);
CREATE INDEX IF NOT EXISTS idx_recap ON pengukuran (posyandu_id, verification_status, measurement_period);
CREATE INDEX IF NOT EXISTS idx_peserta_period ON pengukuran (posyandu_id, peserta_id, measurement_period);
CREATE INDEX IF NOT EXISTS idx_ekstraksi_sesi ON ekstraksi (sesi_foto_id);
CREATE INDEX IF NOT EXISTS idx_log_sesi ON koreksi_log (sesi_foto_id);
CREATE INDEX IF NOT EXISTS idx_sess_exp ON php_sessions (expires_at);
