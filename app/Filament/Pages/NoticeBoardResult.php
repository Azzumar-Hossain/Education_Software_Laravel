<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Exam;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\Mark;
use App\Models\ClassTeacher;
use App\Models\StudyGroup;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class NoticeBoardResult extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $title = 'Result Sheet on Notice Board';
    protected static string $view = 'filament.pages.notice-board-result';

    public ?array $data = [];
    public $students = [];
    public $subjects = [];

    // 🌟 1. DYNAMIC ACCESS CONTROL 🌟
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        return $user->type === 'super_admin'
            || $user->type === 'admin'
            || $user->hasRole(['super_admin', 'admin', 'class_teacher'])
            || $user->can('page_NoticeBoardResult');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                FormSection::make('Notice Board Result Filters')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 5,
                        ])->schema([
                            Select::make('academic_year_id')
                                ->label('Year')
                                ->options(AcademicYear::pluck('name', 'id'))
                                ->required(),

                            Select::make('school_class_id')
                                ->label('Class')
                                ->options(SchoolClass::pluck('name', 'id'))
                                ->required()
                                ->live(),

                            Select::make('exam_id')
                                ->label('Exam')
                                ->options(fn($get) => $get('school_class_id') ? Exam::where('school_class_id', $get('school_class_id'))->pluck('name', 'id') : [])
                                ->required(),

                            // 🌟 2. SECTION SELECTOR (FILTERED FOR CLASS TEACHERS) 🌟
                            Select::make('section_id')
                                ->label('Section')
                                ->options(function ($get) {
                                    $classId = $get('school_class_id');
                                    if (!$classId) return [];

                                    $user = auth()->user();

                                    if ($user && ($user->type === 'class_teacher' || $user->hasRole('class_teacher'))) {
                                        $assignedSectionIds = ClassTeacher::where('teacher_id', $user->id)
                                            ->pluck('section_id')
                                            ->unique()
                                            ->filter();

                                        return Section::whereIn('id', $assignedSectionIds)
                                            ->whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $classId))
                                            ->pluck('name', 'id');
                                    }

                                    return Section::whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $classId))
                                        ->pluck('name', 'id');
                                })
                                ->nullable(),

                            Select::make('study_group')
                                ->label('Study Group')
                                ->options([
                                    'Science'         => 'Science',
                                    'Arts/Humanities' => 'Arts / Humanities',
                                    'Commerce'        => 'Commerce',
                                    'General'         => 'General',
                                ])->nullable(),
                        ]),
                    ]),
            ]);
    }

    public function generateNoticeSheet()
    {
        $this->validate();
        $inputs = $this->data;

        $classId = $inputs['school_class_id'];
        $groupName = $inputs['study_group'];
        $user = auth()->user();

        // 🌟 3. FETCH & FILTER SUBJECTS STRICTLY BASED ON STUDY GROUP 🌟
        $boardSubjectOrder = [
            '101', '102', '107', '108', '109', '127', '150', '111', '112', '154', 
            '153', '140', '110', '126', '136', '137', '138', '134'
        ];

        $subjectQuery = Subject::whereHas('schoolClasses', fn($q) => $q->where('school_classes.id', $classId))
            ->where(function($query) use ($groupName) {
                $query->whereNull('study_group_id')
                      ->when($groupName, function($q) use ($groupName) {
                          $resolvedId = StudyGroup::where('name', $groupName)->first()?->id;
                          if($resolvedId) $q->orWhere('study_group_id', $resolvedId);
                      });
            });

        // Exclude General Science (127) for Science Group
        if (strtolower($groupName) === 'science') {
            $subjectQuery->where('code', '!=', '127')
                         ->where('name', 'not like', '%General Science%')
                         ->where('name', 'not like', '%সাধারণ বিজ্ঞান%');
        }

        // Exclude Pure Science subjects for Arts/Commerce
        if (in_array(strtolower($groupName), ['arts/humanities', 'arts', 'humanities', 'commerce'])) {
            $subjectQuery->whereNotIn('code', ['136', '137', '138']); // Physics, Chemistry, Biology
        }

        $allSubjects = $subjectQuery->get();

        // Sort subjects according to Bangladesh Board Sequence
        $this->subjects = $allSubjects->sortBy(function($sub) use ($boardSubjectOrder) {
            $code = (string) ($sub->code ?? '');
            $idx = array_search($code, $boardSubjectOrder);
            return $idx !== false ? $idx : 999;
        })->values();

        // 🌟 4. LOAD TARGETED STUDENT ROSTERS 🌟
        $query = Enrollment::with(['user', 'schoolClass', 'section'])
            ->where('school_class_id', $classId)
            ->where('academic_year_id', $inputs['academic_year_id'])
            ->when($groupName, fn($q, $g) => $q->where('study_group', $g));

        if (!empty($inputs['section_id'])) {
            $query->where('section_id', $inputs['section_id']);
        } elseif ($user && ($user->type === 'class_teacher' || $user->hasRole('class_teacher'))) {
            $assignedSectionIds = ClassTeacher::where('teacher_id', $user->id)->pluck('section_id')->unique()->filter();
            if ($assignedSectionIds->isNotEmpty()) {
                $query->whereIn('section_id', $assignedSectionIds);
            }
        }

        $enrollments = $query->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')->get();

        // 🌟 5. CALCULATE ACCURATE GPA, GRAND TOTAL, AND GRADE PER STUDENT 🌟
        $studentResults = [];

        foreach ($enrollments as $enrollment) {
            $marks = Mark::with('subject')
                ->where('student_id', $enrollment->user_id)
                ->where('academic_year_id', $inputs['academic_year_id'])
                ->where('school_class_id', $classId)
                ->where('exam_id', $inputs['exam_id'])
                ->get();

            $coreObtainedSum = 0.0;
            $coreGPAs = [];
            $hasCoreFail = false;
            
            $optionalBonusMarks = 0.0;
            $optionalBonusPoints = 0.00;

            foreach ($marks as $mark) {
                if (!$mark->subject) continue;

                $subName = strtolower($mark->subject->name ?? '');
                $subType = strtolower($mark->subject->subject_type ?? $mark->subject->type ?? '');
                $isOptional = (str_contains($subName, 'higher mathematics') || str_contains($subName, 'agriculture') || $subType === 'optional');

                $obt = (float) $mark->marks_obtained;
                $gp  = (float) $mark->gpa;

                if ($isOptional) {
                    // Rule 1: Add marks above 40 to Grand Total (Obtained - 40)
                    if ($obt > 40.0) {
                        $optionalBonusMarks = $obt - 40.0;
                    }

                    // Rule 2: Add GPA points above 2.00 to GPA accumulator (GP - 2.00)
                    if ($gp > 2.00) {
                        $optionalBonusPoints = $gp - 2.00;
                    }
                } else {
                    $coreObtainedSum += $obt;

                    if (trim($mark->grade) === 'F') {
                        $hasCoreFail = true;
                    }
                    $coreGPAs[] = $gp;
                }
            }

            // Calculate Grand Total Marks (Core Sum + 4th Sub Marks above 40)
            $grandTotalMarks = $coreObtainedSum + $optionalBonusMarks;

            // Calculate Final GPA & Grade
            $coreCount = count($coreGPAs);
            if ($hasCoreFail || $coreCount === 0) {
                $finalGPA = '0.00';
                $finalGrade = 'F';
            } else {
                $rawGpaSum = array_sum($coreGPAs) + $optionalBonusPoints;
                $calcGpa = min(5.00, $rawGpaSum / $coreCount);
                $finalGPA = number_format($calcGpa, 2);

                if ($calcGpa >= 5.00) $finalGrade = 'A+';
                elseif ($calcGpa >= 4.00) $finalGrade = 'A';
                elseif ($calcGpa >= 3.50) $finalGrade = 'A-';
                elseif ($calcGpa >= 3.00) $finalGrade = 'B';
                elseif ($calcGpa >= 2.00) $finalGrade = 'C';
                elseif ($calcGpa >= 1.00) $finalGrade = 'D';
                else $finalGrade = 'F';
            }

            $studentResults[] = [
                'enrollment'    => $enrollment,
                'marks'         => $marks->keyBy('subject_id'),
                'grand_total'   => $grandTotalMarks,
                'gpa'           => $finalGPA,
                'grade'         => $finalGrade,
                'has_core_fail' => $hasCoreFail,
            ];
        }

        // 🌟 6. DYNAMIC MERIT RANKING CALCULATION 🌟
        // Clone and sort students by GPA (DESC), then Grand Total (DESC)
        $rankedList = $studentResults;
        usort($rankedList, function($a, $b) {
            if ($a['has_core_fail'] !== $b['has_core_fail']) {
                return $a['has_core_fail'] ? 1 : -1;
            }
            if ((float)$a['gpa'] != (float)$b['gpa']) {
                return (float)$b['gpa'] <=> (float)$a['gpa'];
            }
            return (float)$b['grand_total'] <=> (float)$a['grand_total'];
        });

        // Map rank position back to the student results
        foreach ($studentResults as &$res) {
            if ($res['has_core_fail']) {
                $res['position'] = 'Fail';
            } else {
                $foundIndex = array_search($res['enrollment']->id, array_column(array_column($rankedList, 'enrollment'), 'id'));
                $res['position'] = ($foundIndex !== false) ? ($foundIndex + 1) : '--';
            }
        }

        $this->students = $studentResults;
    }

    // Add this right before the final closing brace of the NoticeBoardResult class
    public function printNoticeSheet()
    {
        if (empty($this->students)) {
            \Filament\Notifications\Notification::make()
                ->title('Generate Sheet First')
                ->warning()
                ->send();
            return;
        }

        $inputs = $this->data;

        $url = route('print.notice.sheet', [
            'year'    => $inputs['academic_year_id'],
            'class'   => $inputs['school_class_id'],
            'exam'    => $inputs['exam_id'],
            'section' => $inputs['section_id'] ?? null,
            'group'   => $inputs['study_group'] ?? null,
        ]);

        $this->js("window.open('{$url}', '_blank');");
    }
}