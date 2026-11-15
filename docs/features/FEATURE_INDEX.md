# Indeks funkcji

Stan implementacji: F-001 i F-002 `In Progress` (implementacje gotowe do przeglądu), pozostałe `Planned`. P0 = fundament/MVP, P1 = rozszerzenie lub jakość wymagana przed przekazaniem; P2 = odroczone, poza tym planem (Dark i tryb serii). Nie mylić gotowej specyfikacji z działającą funkcją.

Wspólne mechanizmy F-402/F-404/F-501 powstają wcześnie, a każda funkcja dodaje własną walidację, stany i testy. F-403 rozwija istniejącą bazową paginację, nie blokuje MVP. [Roadmapa](../product/ROADMAP.md) ma kolejność topologiczną.

| ID | Nazwa | Priorytet | Status | Zależności | Specyfikacja | Wymaganie akademickie |
|---|---|---|---|---|---|---|
| F-001 | Project Foundation | P0 | In Progress | — | [spec](F-001.md) | A6/A7/A8 |
| F-002 | Docker Environment | P0 | In Progress | F-001 | [spec](F-002.md) | A6/A7/A8 |
| F-003 | Database Foundation | P0 | Planned | F-001, F-002 | [spec](F-003.md) | A6/A7/A8 |
| F-004 | Authentication | P0 | Planned | F-003, F-402 | [spec](F-004.md) | A2/A3 |
| F-005 | Role-Based Authorization | P0 | Planned | F-004 | [spec](F-005.md) | A2/A3 |
| F-006 | Design System | P0 | Planned | F-001 | [spec](F-006.md) | wsparcie A1–A8 |
| F-007 | Application Layouts | P0 | Planned | F-004, F-005, F-006, F-404 | [spec](F-007.md) | A2/A3 |
| F-101 | Student Dashboard | P1 | Planned | F-007, F-102 | [spec](F-101.md) | A2/A3 |
| F-102 | Student Grades | P0 | Planned | F-007, F-003 | [spec](F-102.md) | A1 |
| F-103 | Grade Details | P0 | Planned | F-102 | [spec](F-103.md) | A1 |
| F-104 | Grade History | P0 | Planned | F-103 | [spec](F-104.md) | A1 |
| F-201 | Teacher Dashboard | P1 | Planned | F-007, F-202, F-203 | [spec](F-201.md) | A2/A3 |
| F-202 | Teacher Classes | P0 | Planned | F-007, F-308 | [spec](F-202.md) | A1 |
| F-203 | Class Gradebook | P0 | Planned | F-202, F-306, F-103, F-104 | [spec](F-203.md) | A1 |
| F-204 | Grade Creation | P0 | Planned | F-203, F-402 | [spec](F-204.md) | A1 |
| F-205 | Grade Editing | P0 | Planned | F-204 | [spec](F-205.md) | A1 |
| F-206 | Grade Deletion | P0 | Planned | F-205 | [spec](F-206.md) | A1 |
| F-301 | Administrator Dashboard | P1 | Planned | F-007, F-302, F-306, F-307, F-308, F-104 | [spec](F-301.md) | A2/A3 |
| F-302 | User List | P0 | Planned | F-007, F-402 | [spec](F-302.md) | A4 |
| F-303 | User Creation | P0 | Planned | F-302, F-306, F-308, F-402 | [spec](F-303.md) | A4 |
| F-304 | User Editing | P0 | Planned | F-303 | [spec](F-304.md) | A4 |
| F-305 | User Deletion | P0 | Planned | F-304 | [spec](F-305.md) | A4 |
| F-306 | Class Management | P0 | Planned | F-007, F-402, F-003 | [spec](F-306.md) | wsparcie A1–A8 |
| F-307 | Subject Management | P0 | Planned | F-007, F-402, F-003 | [spec](F-307.md) | wsparcie A1–A8 |
| F-308 | Teacher Assignments | P0 | Planned | F-306, F-307, F-005, F-402 | [spec](F-308.md) | wsparcie A1–A8 |
| F-401 | Profile Management | P1 | Planned | F-007, F-402 | [spec](F-401.md) | wsparcie A1–A8 |
| F-402 | Form Validation | P0 | Planned | F-001 | [spec](F-402.md) | A5 |
| F-403 | Search, Filtering and Pagination | P1 | Planned | F-102, F-104, F-202, F-302 | [spec](F-403.md) | wsparcie A1–A8 |
| F-404 | Application States and Error Handling | P0 | Planned | F-001, F-006 | [spec](F-404.md) | wsparcie A1–A8 |
| F-501 | Automated Testing | P0 | Planned | F-001, F-002, F-003 | [spec](F-501.md) | wsparcie A1–A8 |
| F-502 | GitHub Actions CI | P1 | Planned | F-501 | [spec](F-502.md) | wsparcie A1–A8 |
| F-503 | Deployment Readiness | P1 | Planned | F-502, F-101, F-201, F-301, F-305, F-206, F-401, F-403 | [spec](F-503.md) | wsparcie A1–A8 |

Administrator zarządza ocenami poprzez F-203–206 oraz wspólne F-103–104. Szczegóły użytkownika są w F-302; enrollment/transfer w F-306; assessment w F-203. Nie powstają dodatkowe równoległe implementacje tych funkcji.
