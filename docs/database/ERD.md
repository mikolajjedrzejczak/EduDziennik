# ERD

PLANNED. Diagram pokazuje wszystkie relacje domenowe; pełne pola, indeksy i reguły przekrojowe są w [SCHEMA](SCHEMA.md). Wszystkie FK są NOT NULL i RESTRICT; soft delete nie usuwa relacji. Tabele sessions/cache/migrations/domain_locks są techniczne, nie częścią grafu ocen.

```mermaid
erDiagram
    users ||--o{ student_enrollments : "uczeń"
    users ||--o{ teacher_assignments : "nauczyciel"
    users ||--o{ assessments : "utworzył"
    users ||--o{ grades : "wystawił"
    users ||--o{ grade_audits : "wykonał"
    academic_years ||--o{ terms : "semestry"
    academic_years ||--o{ school_classes : "klasy roku"
    school_classes ||--o{ student_enrollments : "członkostwa"
    school_classes ||--o{ teacher_assignments : "przydziały"
    school_classes ||--o{ assessments : "kolumny"
    terms ||--o{ teacher_assignments : "okres"
    terms ||--o{ assessments : "okres"
    subjects ||--o{ teacher_assignments : "przedmiot"
    subjects ||--o{ assessments : "przedmiot"
    grade_categories ||--o{ assessments : "domyślna kategoria"
    grade_categories ||--o{ grades : "kategoria oceny"
    student_enrollments ||--o{ grades : "oceny ucznia"
    assessments ||--o{ grades : "komórki"
    grades ||--|{ grade_audits : "zdarzenia"
    users {
        bigint id PK
        varchar email UK
        varchar role
        varchar status
        datetime deleted_at
    }
    academic_years {
        bigint id PK
        varchar name UK
        date starts_on
        date ends_on
    }
    terms {
        bigint id PK
        bigint academic_year_id FK
        tinyint number
        datetime closed_at
    }
    school_classes {
        bigint id PK
        bigint academic_year_id FK
        varchar name
    }
    subjects {
        bigint id PK
        varchar name UK
    }
    student_enrollments {
        bigint id PK
        bigint student_id FK
        bigint school_class_id FK
        date starts_on
        date ends_on
    }
    teacher_assignments {
        bigint id PK
        bigint teacher_id FK
        bigint school_class_id FK
        bigint subject_id FK
        bigint term_id FK
        datetime revoked_at
    }
    grade_categories {
        bigint id PK
        varchar name UK
        tinyint default_weight
    }
    assessments {
        bigint id PK
        bigint school_class_id FK
        bigint subject_id FK
        bigint term_id FK
        bigint category_id FK
        bigint created_by FK
        tinyint default_weight
    }
    grades {
        bigint id PK
        bigint student_enrollment_id FK
        bigint assessment_id FK
        bigint category_id FK
        bigint issued_by FK
        tinyint value
        tinyint weight
        int version
        datetime deleted_at
    }
    grade_audits {
        bigint id PK
        bigint grade_id FK
        bigint actor_id FK
        int grade_version
        json old_values
        json new_values
    }
```

Krotność grade→audit minimum 1 jest invariantem usługi transakcyjnej, nie wymuszeniem zwykłego FK. Unikalności wielokolumnowe, np. enrollment+assessment, są opisane w SCHEMA, a nie sugerowane oddzielnym UK każdej kolumny diagramu.
