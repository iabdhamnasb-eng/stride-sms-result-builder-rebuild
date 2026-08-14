<?php

namespace App\Services;

use App\Models\ResultTemplate;
use App\Models\SchoolConfig;
use App\Models\Student;
use App\Models\AcademicSessionTerm;
use Illuminate\Support\Arr;

/**
 * Resolves result-template placeholders into real values and renders
 * the final printable result card HTML (DomPDF-compatible).
 */
class ResultCardService
{
    /**
     * @return array<string, string> placeholder => value
     */
    public function buildVariables(Student $student, AcademicSessionTerm $term): array
    {
        $school   = $student->school;
        $schoolId = $school?->id;
        $config   = fn (string $key, mixed $default = '') => SchoolConfig::getValue($schoolId, 'general', $key, $default);

        $nextTermDate  = $config('next_term_date', '–');
        $nextTermStart = $config('next_term_start_date', $nextTermDate);
        $nextTermEnd   = $config('next_term_end_date', '–');
        $resultIssue   = $config('result_issue_date', now()->format('d M Y'));

        $scores   = $this->scoresFor($student, $term);
        $total    = (float) collect($scores)->sum('score');
        $average  = count($scores) > 0 ? round($total / count($scores), 1) : 0;
        $position = $this->classPositionFor($student, $term, $scores);

        return [
            // School
            '{{school_name}}'       => $config('school_name', $school?->name ?? ''),
            '{{school_address}}'    => $config('school_address', ''),
            '{{school_phone}}'      => $config('school_phone', ''),
            '{{school_email}}'      => $config('school_email', ''),
            '{{school_motto}}'      => $config('school_motto', ''),
            '{{school_logo}}'       => $config('school_logo', $school?->logo_url ?? ''),
            '{{principal_name}}'    => $config('principal_name', ''),
            '{{head_teacher_name}}' => $config('head_teacher_name', ''),
            '{{school_stamp}}'      => $config('school_stamp', ''),

            // Student
            '{{student_name}}'    => $student->name ?? '',
            '{{student_id}}'      => $student->reg_no ?? $student->admission_no ?? '',
            '{{student_class}}'   => $student->classRoom?->name ?? $student->class_name ?? '',
            '{{student_arm}}'     => $student->arm ?? $student->stream ?? '',
            '{{student_gender}}'  => $student->gender ?? '',
            '{{student_dob}}'     => optional($student->dob)->format('d M Y') ?? '',
            '{{admission_date}}'  => optional($student->admission_date)->format('d M Y') ?? '',
            '{{subject_count}}'   => (string) count($scores),

            // Academic
            '{{session_name}}'    => $term->session?->name ?? $term->session_name ?? '',
            '{{term_name}}'       => $term->name ?? $term->term_name ?? '',

            // Performance
            '{{total_score}}'     => number_format($total, 0),
            '{{average_score}}'   => number_format($average, 1),
            '{{class_position}}'  => (string) $position['position'],
            '{{position_suffix}}' => $position['suffix'],
            '{{class_size}}'      => (string) $position['classSize'],

            // Dates
            '{{next_term_date}}'       => $nextTermDate,
            '{{next_term_start_date}}' => $nextTermStart,
            '{{next_term_end_date}}'   => $nextTermEnd,
            '{{date_issued}}'          => now()->format('d M Y'),
            '{{result_issue_date}}'    => $resultIssue,

            // Remarks / signatures
            '{{teacher_remark}}'   => $this->teacherRemarkFor($student, $term),
            '{{principal_remark}}' => $this->principalRemarkFor($student, $term),
            '{{teacher_name}}'     => $student->classTeacher?->name ?? '',
        ];
    }

    /**
     * Renders a stored template into a printable page.
     */
    public function renderTemplate(ResultTemplate $template, Student $student, AcademicSessionTerm $term): string
    {
        $html = (string) $template->compiled_html;

        // Dynamic blocks are generated server-side and injected first.
        $blocks = [
            '{{SCORES_TABLE}}'       => $this->buildScoresTable($student, $term),
            '{{ATTENDANCE_SUMMARY}}' => $this->buildAttendanceSummary($student, $term),
        ];

        $gradingKey = $this->buildGradingScale($student->school_id);
        $blocks['{{GRADING_SCALE}}'] = $gradingKey;
        // Alias: both {{GRADING_SCALE}} and {{GRADING_KEY}} resolve to the same key.
        $blocks['{{GRADING_KEY}}'] = $gradingKey;

        $html = strtr($html, $blocks);

        // Remaining scalar placeholders.
        $html = strtr($html, $this->buildVariables($student, $term));

        // Collapse any unresolved placeholders so nothing leaks into the PDF.
        $html = preg_replace('/\{\{[A-Z_a-z0-9]+\}\}/', '', $html);

        return $this->wrapForPdf($html, $template);
    }

    protected function wrapForPdf(string $body, ResultTemplate $template): string
    {
        $paper      = strtolower($template->paper_size ?? 'a4');
        $orientation = ($template->orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $sizes      = [
            'a4'     => ['portrait' => ['210mm', '297mm'], 'landscape' => ['297mm', '210mm']],
            'a5'     => ['portrait' => ['148mm', '210mm'], 'landscape' => ['210mm', '148mm']],
            'legal'  => ['portrait' => ['216mm', '356mm'], 'landscape' => ['356mm', '216mm']],
            'letter' => ['portrait' => ['216mm', '279mm'], 'landscape' => ['279mm', '216mm']],
        ];
        [$width, $height] = $sizes[$paper][$orientation] ?? $sizes['a4']['portrait'];

        return <<<HTML
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { size: {$width} {$height}; margin: 0; }
    html, body { margin: 0; padding: 0; }
    .result-sheet {
        width: {$width};
        min-height: {$height};
        margin: 0 auto;
        padding: 14mm 12mm;
        box-sizing: border-box;
        background: #fff;
        color: #111;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
        line-height: 1.45;
    }
    .result-sheet table { width: 100%; border-collapse: collapse; }
    .result-sheet table td, .result-sheet table th { padding: 4px 6px; }
    [data-hidden-print="true"] { display: none !important; }
    {$template->compiled_css}
</style>
</head>
<body>
    <div class="result-sheet">{$body}</div>
</body>
</html>
HTML;
    }

    // ---- Dynamic block builders (DomPDF-friendly, table-based) ------------

    protected function buildGradingScale(?int $schoolId): string
    {
        $scale = SchoolConfig::getValue($schoolId, 'general', 'grading_scale', [
            ['min' => 70, 'grade' => 'A'], ['min' => 60, 'grade' => 'B'],
            ['min' => 50, 'grade' => 'C'], ['min' => 40, 'grade' => 'D'], ['min' => 0, 'grade' => 'F'],
        ]);

        if (is_string($scale)) {
            return $scale;
        }

        $rows = '';
        foreach ((array) $scale as $item) {
            $rows .= '<tr><td>' . e((string) ($item['min'] ?? '')) . ' and above</td><td>' . e((string) ($item['grade'] ?? '')) . '</td></tr>';
        }

        return '<table class="result-grading-key" style="width:60%;border-collapse:collapse;"><tr><th style="border-bottom:1px solid #333;text-align:left;padding:2px 6px;">Score</th><th style="border-bottom:1px solid #333;text-align:left;padding:2px 6px;">Grade</th></tr>' . $rows . '</table>';
    }

    protected function buildScoresTable(Student $student, AcademicSessionTerm $term): string
    {
        $scores = $this->scoresFor($student, $term);

        $rows = '';
        foreach ($scores as $score) {
            $rows .= '<tr>'
                . '<td style="padding:3px 6px;border:1px solid #333;">' . e((string) $score['subject']) . '</td>'
                . '<td style="padding:3px 6px;border:1px solid #333;text-align:center;">' . e((string) $score['score']) . '</td>'
                . '<td style="padding:3px 6px;border:1px solid #333;text-align:center;">' . e((string) $score['grade']) . '</td>'
                . '<td style="padding:3px 6px;border:1px solid #333;text-align:center;">' . e((string) $score['position']) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            return '<p>No scores recorded for this term.</p>';
        }

        return '<table class="result-scores-table" style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="padding:4px 6px;border:1px solid #333;text-align:left;">Subject</th>'
            . '<th style="padding:4px 6px;border:1px solid #333;">Score</th>'
            . '<th style="padding:4px 6px;border:1px solid #333;">Grade</th>'
            . '<th style="padding:4px 6px;border:1px solid #333;">Position</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    protected function buildAttendanceSummary(Student $student, AcademicSessionTerm $term): string
    {
        $present = $student->attendances()
            ->where('term_id', $term->id)
            ->where('status', 'present')
            ->count();
        $absent = $student->attendances()
            ->where('term_id', $term->id)
            ->where('status', 'absent')
            ->count();

        return '<table class="result-attendance-summary" style="width:100%;border-collapse:collapse;">'
            . '<tr><th style="padding:3px 6px;border:1px solid #333;text-align:left;">Attendance</th><th style="padding:3px 6px;border:1px solid #333;">Days Present</th><th style="padding:3px 6px;border:1px solid #333;">Days Absent</th></tr>'
            . '<tr><td style="padding:3px 6px;border:1px solid #333;">Term</td><td style="padding:3px 6px;border:1px solid #333;text-align:center;">' . $present . '</td><td style="padding:3px 6px;border:1px solid #333;text-align:center;">' . $absent . '</td></tr>'
            . '</table>';
    }

    // ---- Data helpers: adapt to your actual STRIDE schema ----------------

    protected function scoresFor(Student $student, AcademicSessionTerm $term): array
    {
        // Adjust to the real relation name (e.g. $student->resultScores).
        $records = method_exists($student, 'scores')
            ? $student->scores()->where('term_id', $term->id)->get()
            : collect();

        return $records->map(function ($record) {
            return [
                'subject'  => $record->subject?->name ?? $record->subject_name ?? '—',
                'score'    => (float) ($record->score ?? 0),
                'grade'    => $record->grade ?? $this->gradeForScore((float) ($record->score ?? 0)),
                'position' => $record->position ?? '—',
            ];
        })->values()->all();
    }

    protected function gradeForScore(float $score): string
    {
        return match (true) {
            $score >= 70 => 'A',
            $score >= 60 => 'B',
            $score >= 50 => 'C',
            $score >= 40 => 'D',
            default      => 'F',
        };
    }

    protected function classPositionFor(Student $student, AcademicSessionTerm $term, array $scores): array
    {
        $average  = collect($scores)->avg('score');
        $classScores = $student->classRoom?->students()
            ->with(['scores' => fn ($q) => $q->where('term_id', $term->id)])
            ->get()
            ->map(fn ($s) => (float) $s->scores->avg('score'))
            ->sortDesc()
            ->values();

        $position = $classScores->search(fn ($avg) => $avg <= $average) + 1 ?: 1;
        $suffix   = match (true) {
            $position % 100 >= 11 && $position % 100 <= 13 => 'th',
            $position % 10 == 1 => 'st',
            $position % 10 == 2 => 'nd',
            $position % 10 == 3 => 'rd',
            default => 'th',
        };

        return [
            'position'  => $position,
            'suffix'    => $suffix,
            'classSize' => $classScores->count(),
        ];
    }

    protected function teacherRemarkFor(Student $student, AcademicSessionTerm $term): string
    {
        return SchoolConfig::getValue($student->school_id, 'remarks', 'teacher_default', '')
            ?: Arr::get($this->scoresFor($student, $term), '0.remark', '');
    }

    protected function principalRemarkFor(Student $student, AcademicSessionTerm $term): string
    {
        return SchoolConfig::getValue($student->school_id, 'remarks', 'principal_default', '');
    }
}
