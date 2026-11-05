-- Ciclo 2: tratamientos ligados a historias médicas. Ejecutar después de 005_security.sql.
CREATE TABLE IF NOT EXISTS treatment_plans (
 id BIGSERIAL PRIMARY KEY,
 clinical_record_id INTEGER NOT NULL REFERENCES clinical_records(id),
 patient_id INTEGER NOT NULL REFERENCES patients(id),
 doctor_id INTEGER NOT NULL REFERENCES doctors(id),
 title VARCHAR(160) NOT NULL CHECK(length(trim(title))>=3),
 instructions TEXT NOT NULL CHECK(length(trim(instructions))>=5),
 start_date DATE NOT NULL,
 end_date DATE,
 status VARCHAR(20) NOT NULL DEFAULT 'activo' CHECK(status IN ('activo','suspendido','finalizado')),
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 CONSTRAINT treatment_dates_ok CHECK(end_date IS NULL OR end_date>=start_date)
);
CREATE INDEX IF NOT EXISTS treatment_plans_patient_idx ON treatment_plans(patient_id,created_at DESC);
CREATE INDEX IF NOT EXISTS treatment_plans_doctor_idx ON treatment_plans(doctor_id,created_at DESC);
DROP TRIGGER IF EXISTS tr_saludcita_audit ON treatment_plans;
CREATE TRIGGER tr_saludcita_audit AFTER INSERT OR UPDATE OR DELETE ON treatment_plans
FOR EACH ROW EXECUTE FUNCTION saludcita_audit_trigger();
