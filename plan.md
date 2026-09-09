# Grading System Overhaul — Per-Subject, Per-Term, Semestral, GWA

## Objective

Transform the current flat grading system into a structured per-subject, per-term grading system with:
- **Semestral**: Each subject has grades for either **1st Semester** or **2nd Semester**
- **3 term grades per subject per semester**: Prelim, Midterm, Finals
- **Final Grade** per subject = (Prelim × 0.20) + (Midterm × 0.30) + (Finals × 0.50)
- **General Average** = weighted by credit units across all subjects for the selected semester
- **Semestral GWA** = CHED standard 1.0–5.0 conversion per semester
- **Annual General Average** = weighted average of both semesters' final grades
- **Annual GWA** = CHED conversion of the annual general average

---

## Table Layout (Admin / Teacher / Parent)

### Admin & Teacher grades table — 5 columns

| Student Photo | Student Name | Student ID | GWA | Action |
|:---:|---|---|---|---|
| [photo] | Juan Dela Cruz | STU-2026-JMWW3G | 1.75 | View |

- **Photo**: circular avatar from `user.photo` (storage URL), fallback to initials badge
- **Student Name**: `firstName + lastName`
- **Student ID**: `user.user_id`
- **GWA**: computed from published grades for the **active semester** (defaults to current semester)
- **View**: button → opens modal with full grade breakdown

### Parent grades table — 4 columns

| Child Photo | Child Name | GWA | Action |
|:---:|---|---|---|
| [photo] | Juan Dela Cruz | 1.75 | View |

- Same as admin/teacher but without Student ID column (parent sees their own children only)

### Student grades view — own grades (no table of students)

Shows the authenticated student's own grades in a detailed table:
- **Semester selector**: 1st Semester | 2nd Semester (tab toggle)
- **Summary section**: General Average (0-100), GWA (1.0-5.0), School Year, Semester
- **Per-subject table**: Subject | Units | Prelim | Midterm | Finals | Final Grade

---

## Grade Detail Modal (shared across Admin / Teacher / Parent)

When "View" is clicked, a modal opens showing:

```
┌──────────────────────────────────────────────────────┐
│  [Photo]  Juan Dela Cruz                             │
│           STU-2026-JMWW3G                            │
│           Grade 12 - ICT                             │
├──────────────────────────────────────────────────────┤
│  School Year: 2026-2027    Semester: [1st ▾]        │
├──────────────────────────────────────────────────────┤
│  Subject     | Units | Prelim | Midterm | Finals | Final  │
│  Mathematics | 3.00  | 88     | 91      | 85     | 87.00  │
│  Science     | 3.00  | 92     | 89      | 90     | 90.10  │
│  English     | 2.00  | 85     | 87      | 82     | 84.40  │
│  Filipino    | 2.00  | 90     | 88      | 86     | 87.60  │
│  PE          | 2.00  | 95     | 93      | 91     | 92.40  │
│  TLE         | 3.00  | 88     | 90      | 87     | 88.30  │
├──────────────────────────────────────────────────────┤
│  Semester: 1st Semester                               │
│  Total Units: 15    General Average: 87.27           │
│  GWA: 2.00 (CHED Scale)                               │
├──────────────────────────────────────────────────────┤
│  Annual Summary (if both semesters complete):         │
│  1st Sem GWA: 2.00    2nd Sem GWA: 1.75              │
│  Annual General Average: 88.50    Annual GWA: 1.75    │
└──────────────────────────────────────────────────────┘
```

---

## Database Changes

### New migration: `2026_09_10_100000_restructure_grades_for_term_grading.php`

**grades table changes:**

| Action | Column | Type | Notes |
|--------|--------|------|-------|
| ADD | `semester` | `string NOT NULL DEFAULT '1st Semester'` | '1st Semester' or '2nd Semester' |
| ADD | `prelim` | `decimal(5,2) NULL` | Preliminary grade (0–100) |
| ADD | `midterm` | `decimal(5,2) NULL` | Midterm grade (0–100) |
| ADD | `finals` | `decimal(5,2) NULL` | Final grade (0–100) |
| ADD | `finalGrade` | `decimal(5,2) NULL` | Computed: prelim×0.20 + midterm×0.30 + finals×0.50 |
| ADD | `units` | `decimal(4,2) DEFAULT 1.00` | Credit units for weighted average |
| ADD | `schoolYear` | `string NULL` | e.g. "2026-2027" |
| RENAME | `grade` → `finalGrade` | (if exists) | Semantic clarity |
| DROP | `term` | (column) | Replaced by prelim/midterm/finals columns |
| DROP | `period` | (column) | Was always a mirror of `term` |

**Data migration:** Existing rows with `term` + `grade` → map `term` to the appropriate new column (best-effort).

**Unique constraint:** `(studentId, subject, schoolYear, semester)` — one grade record per student per subject per semester per school year.

---

## Model Changes

### `app/Models/Grade.php`

**Constants:**
```php
const SEMESTER_FIRST = '1st Semester';
const SEMESTER_SECOND = '2nd Semester';

const TERM_PRELIM  = 'Prelim';
const TERM_MIDTERM = 'Midterm';
const TERM_FINALS  = 'Finals';

const PRELIM_WEIGHT  = 0.20;
const MIDTERM_WEIGHT = 0.30;
const FINALS_WEIGHT  = 0.50;
```

**New fillable:** `prelim`, `midterm`, `finals`, `units`, `schoolYear`, `semester`

**Removed from fillable:** `term`, `period`, `teacher` (virtual, not stored)

**New casts:** `'units' => 'decimal:2'`

**Methods:**
- `recalculateFinalGrade(): ?float` — computes and sets `finalGrade`
- `scopeForSchoolYear($query, string $schoolYear)`
- `scopeForSemester($query, string $semester)`
- `scopePublished($query)`
- `static function convertToGwa(float $average): float` — CHED table
- `static function generalAverage(Collection $grades): ?float` — weighted by units
- `static function gwaForStudent(string $studentId, string $schoolYear, ?string $semester = null): array` — returns `['generalAverage' => float, 'gwa' => float, 'totalUnits' => float]`. If semester is null, computes across all semesters.
- `static function annualGwa(string $studentId, string $schoolYear): array` — returns `['1stSemester' => [...], '2ndSemester' => [...], 'annualGeneralAverage' => float, 'annualGwa' => float]`

**CHED GWA conversion:**
```php
public static function convertToGwa(float $average): float
{
    return match(true) {
        $average >= 97 => 1.00,
        $average >= 94 => 1.25,
        $average >= 91 => 1.50,
        $average >= 88 => 1.75,
        $average >= 85 => 2.00,
        $average >= 82 => 2.25,
        $average >= 79 => 2.50,
        $average >= 76 => 2.75,
        $average >= 75 => 3.00,
        default         => 5.00,
    };
}
```

---

## PortalDataService Changes

### Serializer (`serializeDomain('grades', $row)`)
Add `prelim`, `midterm`, `finals`, `units`, `schoolYear`, `semester`. Remove `term`, `period`.

### Write Fields
```php
'grades' => ['id', 'studentId', 'subject', 'teacherId', 'semester', 'prelim', 'midterm', 'finals', 'finalGrade', 'units', 'schoolYear', 'published', 'publishedAt', 'publishedBy', 'notes', 'updatedBy'],
```

### Scoped Domain Fields
- Teacher: force `teacherId` to their own; recompute `finalGrade` on write.
- Admin: full access; recompute `finalGrade` on write.

---

## Controller Changes

### `app/Http/Controllers/Student/GradeController.php`

Add endpoint:
- `GET /student/api/grades/summary` — accepts optional `?semester=1st Semester` query param; returns `{ subjects: [...], generalAverage, gwa, totalUnits, schoolYear, semester, annual: {...} }` for the authenticated student's published grades

---

## JavaScript Changes

### Admin grades (`public/js/admin/grades.js`)

**Table:** One row per student. Columns: Photo, Name, Student ID, GWA, View button.

**Semester filter:** Dropdown at the top: All | 1st Semester | 2nd Semester. Defaults to active semester.

**Data source:** Group all grades by `studentId`. For each student, compute GWA for the selected semester using `gwaForStudent()` logic (JS-side).

**View modal:** On click, open a modal with:
- Student header (photo, name, ID)
- Semester selector inside modal (1st / 2nd)
- Grade table: Subject | Units | Prelim | Midterm | Finals | Final Grade
- Footer: Total Units, General Average, GWA
- Annual summary (if both semesters have grades): 1st Sem GWA, 2nd Sem GWA, Annual General Average, Annual GWA

**Add/Edit grade:** Teacher selects a student, subject, semester, units, and enters three term grades. System auto-computes final grade.

### Teacher grades (`public/js/teacher/teacher.js`)

**Table:** Same as admin — one row per student, filtered to students assigned to this teacher.

**Semester filter:** Same as admin.

**Add grade:** Teacher selects a student (from assigned list), subject, semester, units, enters term grades.

**View modal:** Same detail modal as admin.

### Parent grades (`public/js/parent/parent.js`)

**Table:** One row per child. Columns: Photo, Name, GWA, View button.

**View modal:** Same detail modal showing child's full grade breakdown with semester selector.

### Student grades (`public/js/student/student.js`)

**Own view:** No student table — shows own grades directly:
- **Semester tabs**: 1st Semester | 2nd Semester
- Summary: General Average (large), GWA (large), School Year, Semester
- Table: Subject | Units | Prelim | Midterm | Finals | Final Grade
- Annual summary at bottom (if both semesters complete)

---

## Blade View Changes

### `resources/views/admin/grades.blade.php`
- Redesign table header: Photo | Student Name | Student ID | GWA | Action
- Add semester filter dropdown
- Add grade detail modal structure with semester selector
- Add "Add Grade" dialog with subject/semester/units/term inputs

### `resources/views/teacher/grades.blade.php`
- Same layout as admin (filtered to assigned students)
- Add semester filter

### `resources/views/parent/grades.blade.php`
- Redesign table header: Photo | Child Name | GWA | Action
- Add grade detail modal structure with semester selector

### `resources/views/student/grades.blade.php`
- Semester tab toggle (1st / 2nd)
- Summary cards: General Average, GWA, School Year, Semester
- Per-subject table with term columns
- Annual summary section

---

## Tests

### `tests/Feature/GradingSystemTest.php`
- Final Grade computed correctly from prelim/midterm/finals weights
- General Average computed correctly weighted by units per semester
- GWA conversion matches CHED scale for boundary cases
- Annual GWA computed correctly across both semesters
- Student can only see published grades
- Teacher cannot change another teacher's grades
- Admin can publish/unpublish grades
- Grade validation rejects values outside 0–100
- Duplicate student+subject+schoolYear+semester rejected by unique constraint

### `tests/Unit/GradeTest.php`
- `recalculateFinalGrade()` returns correct weighted average
- `convertToGwa()` returns correct CHED values for all boundary cases
- `generalAverage()` handles null/missing grades gracefully
- `gwaForStudent()` filters by semester correctly
- `annualGwa()` combines both semesters correctly

---

## Files to Create/Modify

| File | Action |
|------|--------|
| `database/migrations/2026_09_10_100000_restructure_grades_for_term_grading.php` | CREATE |
| `app/Models/Grade.php` | MODIFY |
| `app/Services/PortalDataService.php` | MODIFY |
| `app/Http/Controllers/Student/GradeController.php` | MODIFY |
| `public/js/admin/grades.js` | MODIFY |
| `public/js/student/student.js` | MODIFY (grade sections) |
| `public/js/teacher/teacher.js` | MODIFY (grade sections) |
| `public/js/parent/parent.js` | MODIFY (grade sections) |
| `resources/views/admin/grades.blade.php` | MODIFY |
| `resources/views/student/grades.blade.php` | MODIFY |
| `resources/views/teacher/grades.blade.php` | MODIFY |
| `resources/views/parent/grades.blade.php` | MODIFY |
| `tests/Feature/GradingSystemTest.php` | CREATE |
| `tests/Unit/GradeTest.php` | CREATE |
| `database/factories/GradeFactory.php` | CREATE |
