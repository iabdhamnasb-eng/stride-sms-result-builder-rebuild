<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\AcademicSessionTerm;
use App\Services\ArabicReshaper;
use App\Models\Attendance;
use App\Models\GradeEntry;
use App\Models\GradingScale;
use App\Models\ResultTemplate;
use App\Models\School;
use App\Models\SchoolConfig;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Barryvdh\DomPDF\Facade\Pdf;

class ResultCardService
{
    /**
     * Return the rendered HTML for a student's result card (without creating a PDF).
     * Used for bulk printing — caller combines multiple HTML pages into one PDF.
     */
    public function getRenderedHtml(Student $student, AcademicSessionTerm $term, ?ResultTemplate $template = null): string
    {
        if (! $template) {
            $template = ResultTemplate::where('school_id', $student->school_id)
                ->where('is_default', true)
                ->first();
        }

        return $template?->compiled_html
            ? $this->renderTemplate($template->compiled_html, $student, $term)
            : $this->renderFallback($student, $term);
    }

    /**
     * Generate a PDF result card for a student.
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generate(
        Student $student,
        AcademicSessionTerm $term,
        ?ResultTemplate $template = null
    ) {
        // Load the default template if none supplied
        if (! $template) {
            $template = ResultTemplate::where('school_id', $student->school_id)
                ->where('is_default', true)
                ->first();
        }

        $html = $template?->compiled_html
            ? $this->renderTemplate($template->compiled_html, $student, $term)
            : $this->renderFallback($student, $term);

        $pdf = Pdf::loadHtml($html)
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);

        if ($template) {
            $pdf->setPaper(
                strtolower($template->paper_size) === 'letter' ? 'letter' : 'a4',
                $template->orientation
            );
        } else {
            $pdf->setPaper('a4', 'portrait');
        }

        return $pdf;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function renderTemplate(string $compiledHtml, Student $student, AcademicSessionTerm $term): string
    {
        $vars    = $this->buildVariables($student, $term);
        $entries = $this->loadGradeEntries($student, $term);
        $html    = $compiledHtml;

        // Inject dynamic HTML blocks first
        $html = str_replace('{{SCORES_TABLE}}',       $this->buildScoresTable($entries), $html);
        $html = str_replace('{{ATTENDANCE_SUMMARY}}', $this->buildAttendanceSummary($student, $term), $html);
        $html = str_replace('{{GRADING_SCALE}}',      $this->buildGradingScale($student->school_id), $html);

        // Replace all scalar variables
        foreach ($vars as $key => $value) {
            $safe = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            if (ArabicReshaper::containsArabic((string) $value)) {
                $reshaped = ArabicReshaper::reshape((string) $value);
                $safe = '<span style="font-family:Amiri,\'DejaVu Sans\',serif;direction:rtl;unicode-bidi:bidi-override;">'
                    . htmlspecialchars($reshaped, ENT_QUOTES, 'UTF-8')
                    . '</span>';
            }
            $html = str_replace($key, $safe, $html);
        }

        // Append teacher comments as a dedicated second page
        $commentsPage = $this->buildTeacherCommentsPage($entries, $student, $term);
        if ($commentsPage) {
            $html = str_replace('</body>', $commentsPage . '</body>', $html);
        }

        return $html;
    }

    /** Build the scalar variables map */
    private function buildVariables(Student $student, AcademicSessionTerm $term): array
    {
        $school = School::find($student->school_id);

        if (! $school) {
            throw new \RuntimeException(
                "Could not generate a report card for {$student->full_name}: their school record no longer exists."
            );
        }

        // Prefer active enrollment; fall back to any enrollment for the term.
        $enrollment = StudentEnrollment::where('student_id', $student->id)
            ->where('session_term_id', $term->id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->first();

        $logoUrl = $school->logo
            ? url('storage/' . $school->logo)
            : '';

        $motto = SchoolConfig::getValue($school->id, 'general', 'motto', '');

        $classSize = StudentEnrollment::where('session_term_id', $term->id)
            ->when($enrollment?->class_id, fn ($q) => $q->where('class_id', $enrollment->class_id))
            ->where('status', 'active')
            ->count();

        $position     = $enrollment?->position ?? '–';
        $subjectCount = GradeEntry::where('student_enrollment_id', $enrollment?->id)->count();
        $totalScore   = $enrollment?->total_score ?? 0;
        $average      = $subjectCount > 0 ? number_format($totalScore / $subjectCount, 1) : '–';

        return [
            // School
            '{{school_name}}'    => $school->name ?? '',
            '{{school_address}}' => $school->address ?? '',
            '{{school_phone}}'   => $school->phone ?? '',
            '{{school_email}}'   => $school->email ?? '',
            '{{school_motto}}'   => $motto,
            '{{school_logo}}'    => $logoUrl,
            // Student
            '{{student_name}}'   => strtoupper($student->full_name),
            '{{student_id}}'     => $student->id_number ?? 'N/A',
            '{{student_class}}'  => $student->class?->name ?? '',
            '{{student_arm}}'    => $student->arm?->name ?? '',
            '{{student_gender}}' => ucfirst($student->gender ?? 'N/A'),
            '{{student_dob}}'    => $student->date_of_birth
                                      ? \Illuminate\Support\Carbon::parse($student->date_of_birth)->format('d M Y')
                                      : 'N/A',
            '{{admission_date}}' => $student->admission_date
                                      ? \Illuminate\Support\Carbon::parse($student->admission_date)->format('d M Y')
                                      : 'N/A',
            '{{subject_count}}'  => $subjectCount,
            // Academic
            '{{session_name}}'   => $term->academicSession?->name ?? '',
            '{{term_name}}'      => $term->termType?->name ?? '',
            // Performance
            '{{total_score}}'    => number_format((float) $totalScore, 1),
            '{{average_score}}'  => $average,
            '{{class_position}}' => $position,
            '{{position_suffix}}'=> $this->ordinalSuffix((int) $position),
            '{{class_size}}'     => $classSize,
            // Dates
            '{{next_term_date}}' => SchoolConfig::getValue($school->id, 'general', 'next_term_date', '–'),
            '{{date_issued}}'    => now()->format('d M Y'),
            // Remark placeholders — blank lines for handwriting
            '{{teacher_remark}}'   => '',
            '{{principal_remark}}' => '',
            '{{teacher_name}}'     => '',
        ];
    }

    private function loadGradeEntries(Student $student, AcademicSessionTerm $term): \Illuminate\Support\Collection
    {
        $enrollment = StudentEnrollment::where('student_id', $student->id)
            ->where('session_term_id', $term->id)
            ->first();

        if (! $enrollment) return collect();

        return GradeEntry::with(['subject', 'details.component'])
            ->where('student_enrollment_id', $enrollment->id)
            ->orderBy('position')
            ->get();
    }

    /** Build the HTML scores table for injection */
    private function buildScoresTable(\Illuminate\Support\Collection $entries): string
    {
        if ($entries->isEmpty()) {
            return '<p style="color:#888;font-size:10px;">No scores recorded for this term.</p>';
        }

        // Collect all component names in order
        $components = $entries->flatMap(fn ($e) => $e->details->map(fn ($d) => [
            'id'    => $d->component_id,
            'title' => $d->component?->title ?? 'CA',
            'code'  => $d->component?->code ?? '',
        ]))->unique('id')->values();

        $thead = '<thead><tr style="background:#1a3a6b;color:#fff;">';
        $thead .= '<th style="padding:5px 8px;text-align:left;font-size:9px;border:1px solid #1a3a6b;">Subject</th>';
        foreach ($components as $comp) {
            $thead .= '<th style="padding:5px 6px;text-align:center;font-size:9px;border:1px solid #1a3a6b;">'
                . $this->safeText($comp['code'] ?: $comp['title']) . '</th>';
        }
        $thead .= '<th style="padding:5px 6px;text-align:center;font-size:9px;border:1px solid #1a3a6b;">Total</th>';
        $thead .= '<th style="padding:5px 6px;text-align:center;font-size:9px;border:1px solid #1a3a6b;">Grade</th>';
        $thead .= '<th style="padding:5px 8px;text-align:center;font-size:9px;border:1px solid #1a3a6b;">Remark</th>';
        $thead .= '<th style="padding:5px 6px;text-align:center;font-size:9px;border:1px solid #1a3a6b;">Pos.</th>';
        $thead .= '</tr></thead>';

        $tbody = '<tbody>';
        foreach ($entries as $i => $entry) {
            $bg = $i % 2 === 0 ? '#fff' : '#f4f7fb';
            $tbody .= "<tr style=\"background:{$bg}\">";
            $tbody .= '<td style="padding:4px 8px;border:1px solid #ddd;font-size:9px;font-weight:600;">'
                . $this->safeText($entry->subject?->name ?? '—') . '</td>';

            foreach ($components as $comp) {
                $detail = $entry->details->firstWhere('component_id', $comp['id']);
                $score  = $detail ? number_format((float) $detail->score, 1) : '–';
                $tbody .= "<td style=\"padding:4px 6px;border:1px solid #ddd;font-size:9px;text-align:center;\">{$score}</td>";
            }

            $grade = htmlspecialchars($entry->grade ?? '–', ENT_QUOTES, 'UTF-8');
            $gradeColor = match (strtoupper($entry->grade ?? '')) {
                'A1', 'A'  => '#0b6623',
                'B2', 'B3', 'B' => '#1a3a8f',
                'C4', 'C5', 'C6', 'C' => '#8a6f00',
                'F9', 'F'  => '#b30000',
                default    => '#333',
            };

            $tbody .= '<td style="padding:4px 6px;border:1px solid #ddd;font-size:9px;text-align:center;font-weight:700;">'
                . number_format((float) ($entry->total ?? 0), 1) . '</td>';
            $tbody .= "<td style=\"padding:4px 6px;border:1px solid #ddd;font-size:9px;text-align:center;font-weight:700;color:{$gradeColor};\">{$grade}</td>";
            $tbody .= '<td style="padding:4px 8px;border:1px solid #ddd;font-size:9px;text-align:center;">'
                . $this->safeText($entry->remark ?? '') . '</td>';
            $tbody .= '<td style="padding:4px 6px;border:1px solid #ddd;font-size:9px;text-align:center;">'
                . ($entry->position ?? '–') . '</td>';
            $tbody .= '</tr>';
        }
        $tbody .= '</tbody>';

        return '<table style="width:100%;border-collapse:collapse;font-size:9px;">' . $thead . $tbody . '</table>';
    }

    /** Build the HTML attendance summary */
    private function buildAttendanceSummary(Student $student, AcademicSessionTerm $term): string
    {
        $rows    = Attendance::where('student_id', $student->id)
            ->where('session_term_id', $term->id)
            ->get();

        $present = $rows->where('status', AttendanceStatus::Present)->count();
        $absent  = $rows->where('status', AttendanceStatus::Absent)->count();
        $late    = $rows->where('status', AttendanceStatus::Late)->count();
        $excused = $rows->where('status', AttendanceStatus::Excused)->count();
        $total   = $rows->count();

        if ($total === 0) {
            return '<p style="color:#888;font-size:10px;">No attendance records for this term.</p>';
        }

        $rate = $total > 0 ? round((($present + $late) / $total) * 100) : 0;

        return <<<HTML
<table style="width:100%;border-collapse:collapse;font-size:9px;">
  <thead>
    <tr style="background:#1a3a6b;color:#fff;">
      <th style="padding:4px 8px;border:1px solid #1a3a6b;">Total Days</th>
      <th style="padding:4px 8px;border:1px solid #1a3a6b;">Present</th>
      <th style="padding:4px 8px;border:1px solid #1a3a6b;">Absent</th>
      <th style="padding:4px 8px;border:1px solid #1a3a6b;">Late</th>
      <th style="padding:4px 8px;border:1px solid #1a3a6b;">Excused</th>
      <th style="padding:4px 8px;border:1px solid #1a3a6b;">Attendance Rate</th>
    </tr>
  </thead>
  <tbody>
    <tr style="text-align:center;background:#fff;">
      <td style="padding:5px 8px;border:1px solid #ddd;font-weight:700;">{$total}</td>
      <td style="padding:5px 8px;border:1px solid #ddd;color:#0b6623;font-weight:700;">{$present}</td>
      <td style="padding:5px 8px;border:1px solid #ddd;color:#b30000;font-weight:700;">{$absent}</td>
      <td style="padding:5px 8px;border:1px solid #ddd;color:#8a6f00;font-weight:700;">{$late}</td>
      <td style="padding:5px 8px;border:1px solid #ddd;font-weight:700;">{$excused}</td>
      <td style="padding:5px 8px;border:1px solid #ddd;font-weight:700;color:#1a3a6b;">{$rate}%</td>
    </tr>
  </tbody>
</table>
HTML;
    }

    /** Build the grading scale key table */
    private function buildGradingScale(int $schoolId): string
    {
        $scales = GradingScale::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->orderByDesc('min_score')
            ->get();

        if ($scales->isEmpty()) {
            return '<div class="grade-key"><div class="grade-key-title">Grading Scale</div>'
                . '<div class="grade-key-row"><em>Not configured for this school.</em></div></div>';
        }

        $cells = $scales->map(fn ($s) =>
            '<div class="grade-key-cell"><strong>' . $this->safeText($s->grade) . '</strong>'
            . ' &mdash; ' . $s->min_score . '&ndash;' . $s->max_score
            . ' (' . $this->safeText($s->remark) . ')</div>'
        )->implode('');

        return '<div class="grade-key">'
            . '<div class="grade-key-title">Grading Scale</div>'
            . '<div class="grade-key-row">' . $cells . '</div>'
            . '</div>';
    }

    /** Fallback: use the existing hardcoded blade template */
    private function renderFallback(Student $student, AcademicSessionTerm $term): string
    {
        $school = School::find($student->school_id);

        // Prefer active enrollment; fall back to any enrollment for the term.
        $enrollment = StudentEnrollment::where('student_id', $student->id)
            ->where('session_term_id', $term->id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->first();

        $gradeEntries = GradeEntry::with(['subject', 'details.component'])
            ->where('student_enrollment_id', $enrollment?->id)
            ->orderBy('position')
            ->get();

        // Unique assessment components in column order
        $components = $gradeEntries
            ->flatMap(fn ($e) => $e->details->map(fn ($d) => $d->component))
            ->filter()
            ->unique('id')
            ->values();

        // The arm the student is enrolled in for this term
        $arm = $enrollment?->arm ?? $student->arm;

        $classSize = StudentEnrollment::where('session_term_id', $term->id)
            ->when($enrollment?->class_id, fn ($q) => $q->where('class_id', $enrollment->class_id))
            ->where('status', 'active')
            ->count();

        $gradingScales = \App\Models\GradingScale::withoutGlobalScope('school')
            ->where('school_id', $student->school_id)
            ->orderByDesc('min_score')
            ->get();

        return view('pdf.result-sheet', compact(
            'student', 'school', 'term', 'enrollment',
            'gradeEntries', 'components', 'arm', 'classSize', 'gradingScales'
        ))->render();
    }

    /** Build the teacher comments second page */
    private function buildTeacherCommentsPage(\Illuminate\Support\Collection $entries, Student $student, AcademicSessionTerm $term): string
    {
        if ($entries->isEmpty()) {
            return '';
        }

        $studentName = $this->safeText(strtoupper($student->full_name));
        $sessionName = $this->safeText($term->academicSession?->name ?? '');
        $termName    = $this->safeText($term->termType?->name ?? '');

        $rows = '';
        foreach ($entries as $i => $entry) {
            $bg      = $i % 2 === 0 ? '#fff' : '#f4f7fb';
            $subject = $this->safeText($entry->subject?->name ?? '—');
            $comment = $this->safeText($entry->teacher_comment ?? '');
            $rows   .= "<tr style=\"background:{$bg};\">"
                . "<td style=\"padding:10px 12px;border:1px solid #ddd;font-size:10px;font-weight:600;width:28%;vertical-align:top;\">{$subject}</td>"
                . "<td style=\"padding:10px 12px;border:1px solid #ddd;font-size:10px;color:#444;\">{$comment}</td>"
                . '</tr>';
        }

        return <<<HTML
<div style="page-break-before:always;padding:24px 28px;">

  <div style="text-align:center;border-bottom:2px solid #1a3a6b;padding-bottom:10px;margin-bottom:20px;">
    <div style="font-size:14px;font-weight:700;color:#1a3a6b;text-transform:uppercase;letter-spacing:1px;">Subject Teachers&rsquo; Comments</div>
    <div style="font-size:9px;color:#555;margin-top:5px;">{$studentName} &mdash; {$sessionName}, {$termName}</div>
  </div>

  <table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr style="background:#1a3a6b;color:#fff;">
        <th style="padding:7px 12px;text-align:left;font-size:9px;border:1px solid #1a3a6b;width:28%;">Subject</th>
        <th style="padding:7px 12px;text-align:left;font-size:9px;border:1px solid #1a3a6b;">Teacher&rsquo;s Comment</th>
      </tr>
    </thead>
    <tbody>{$rows}</tbody>
  </table>

</div>
HTML;
    }

    /** Escape a value for HTML output, wrapping Arabic text with Amiri font and RTL direction. */
    private function safeText(string $value): string
    {
        if (ArabicReshaper::containsArabic($value)) {
            $reshaped = ArabicReshaper::reshape($value);
            return '<span style="font-family:Amiri,\'DejaVu Sans\',serif;direction:rtl;unicode-bidi:bidi-override;">'
                . htmlspecialchars($reshaped, ENT_QUOTES, 'UTF-8')
                . '</span>';
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function ordinalSuffix(int $n): string
    {
        if ($n <= 0) return '';
        $suffix = match (abs($n) % 100) {
            11, 12, 13 => 'th',
            default    => match (abs($n) % 10) {
                1       => 'st',
                2       => 'nd',
                3       => 'rd',
                default => 'th',
            }
        };
        return $suffix;
    }
}
