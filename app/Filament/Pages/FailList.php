<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Subject;
use App\Models\ClassTeacher;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;

class FailList extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Exam';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationIcon = 'heroicon-o-x-circle';
    protected static string $view = 'filament.pages.fail-list';

    public ?array $data = [];
    public array $failRecords = []; 

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        return $user->type === 'super_admin'
            || $user->type === 'admin'
            || $user->hasRole(['super_admin', 'admin', 'class_teacher'])
            || $user->can('page_FailList');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear_filters')
                ->label('Reset Form')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(function () {
                    $this->form->fill();
                    $this->failRecords = [];
                }),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                FormSection::make('Generate Failed Students List')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 5,
                        ])->schema([
                            Select::make('academic_year_id')
                                ->label('Academic Year')
                                ->options(AcademicYear::pluck('name', 'id'))
                                ->required(),

                            Select::make('school_class_id')
                                ->label('Class')
                                ->options(SchoolClass::pluck('name', 'id'))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($set) {
                                    $set('section_id', null);
                                    $set('study_group', null);
                                }),

                            Select::make('merit_scope')
                                ->label('Ranking Filter')
                                ->options(function($get) {
                                    $classId = $get('school_class_id');
                                    if (!$classId) return ['class' => 'Class-wise (Full Grade)'];
                                    
                                    $className = SchoolClass::find($classId)?->name ?? '';
                                    
                                    if (str_contains($className, '9') || str_contains($className, '10')) {
                                        return [
                                            'class' => 'Class-wise (Full Grade)',
                                            'group' => 'Group-wise (Stream Focus)',
                                            'section' => 'Section-wise (Classroom Focus)'
                                        ];
                                    }
                                    
                                    return [
                                        'class' => 'Class-wise (Full Grade)',
                                        'section' => 'Section-wise (Classroom Focus)'
                                    ];
                                })
                                ->required()
                                ->live(),

                            Select::make('section_id')
                                ->label('Select Section')
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
                                ->visible(fn($get) => $get('merit_scope') === 'section')
                                ->required(),

                            Select::make('study_group')
                                ->label('Select Group')
                                ->options([
                                    'Science' => 'Science',
                                    'Arts/Humanities' => 'Arts / Humanities',
                                    'Commerce' => 'Commerce',
                                ])
                                ->visible(fn($get) => $get('merit_scope') === 'group')
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    public function generateFailList()
    {
        $this->validate();
        $inputs = $this->data;

        $user = auth()->user();
        $query = Enrollment::where('school_class_id', $inputs['school_class_id'])
            ->where('academic_year_id', $inputs['academic_year_id']);

        if (($inputs['merit_scope'] ?? null) === 'section') {
            $query->where('section_id', $inputs['section_id'] ?? null);
        } elseif (($inputs['merit_scope'] ?? null) === 'group') {
            $query->where('study_group', $inputs['study_group'] ?? null);
        } elseif ($user && ($user->type === 'class_teacher' || $user->hasRole('class_teacher'))) {
            $assignedSectionIds = ClassTeacher::where('teacher_id', $user->id)->pluck('section_id')->unique()->filter();
            if ($assignedSectionIds->isNotEmpty()) {
                $query->whereIn('section_id', $assignedSectionIds);
            }
        }

        $enrollments = $query->get();
        $calculatedFailRecords = [];

        foreach ($enrollments as $enrollment) {
            // Fetch ALL marks for this student
            $allMarks = Mark::with('subject')
                ->where('student_id', $enrollment->user_id)
                ->where('academic_year_id', $inputs['academic_year_id'])
                ->where('school_class_id', $inputs['school_class_id'])
                ->get();

            if ($allMarks->isEmpty()) {
                continue;
            }

            // 1. Filter out stream-incompatible subjects (e.g., General Science for Science students)
            $groupName = strtolower($enrollment->study_group ?? '');
            $filteredMarks = $allMarks->filter(function($mark) use ($groupName) {
                if (!$mark->subject) return false;
                $subCode = (string) $mark->subject->code;
                $subName = strtolower($mark->subject->name);
                
                if ($groupName === 'science') {
                    if ($subCode === '127' || str_contains($subName, 'general science') || str_contains($subName, 'সাধারণ বিজ্ঞান')) return false;
                }
                if (in_array($groupName, ['arts/humanities', 'arts', 'humanities', 'commerce'])) {
                    if (in_array($subCode, ['136', '137', '138'])) return false;
                }
                return true;
            })->values();

            // 2. Separate Core Total and 4th Subject Bonus Marks (> 40 rule)
            $coreTotalObtained = 0.0;
            $optionalBonusMarks = 0.0;

            $failedSubjectNames = [];
            $processedIds = [];

            foreach ($filteredMarks as $mark) {
                if (in_array($mark->id, $processedIds)) continue;

                $partnerMark = null;
                if ($mark->subject->linked_subject_id) {
                    $partnerMark = $filteredMarks->firstWhere('subject_id', $mark->subject->linked_subject_id);
                } else {
                    $partnerMark = $filteredMarks->where('subject.linked_subject_id', $mark->subject_id)->first();
                    if ($partnerMark) {
                        $temp = $mark; $mark = $partnerMark; $partnerMark = $temp;
                    }
                }

                $subName = strtolower($mark->subject->name ?? '');
                $subType = strtolower($mark->subject->subject_type ?? $mark->subject->type ?? '');
                
                // Identify Optional / 4th subject choice
                $isOptional = (
                    str_contains($subName, 'higher mathematics') || 
                    str_contains($subName, 'agriculture') || 
                    $subType === 'optional' ||
                    (int) $enrollment->optional_subject_id === (int) $mark->subject_id
                );

                if ($partnerMark) {
                    $rules1 = $mark->subject->getMarksForExam($mark->exam_id);
                    $rules2 = $partnerMark->subject->getMarksForExam($mark->exam_id);
                    
                    $overallPassOnly = ($rules1['overall_pass_only'] ?? $mark->subject->overall_pass_only ?? false) || 
                                    ($rules2['overall_pass_only'] ?? $partnerMark->subject->overall_pass_only ?? false);

                    $opm1 = $rules1['overall_pass_mark'] ?? $mark->subject->overall_pass_mark ?? 33;
                    $opm2 = $rules2['overall_pass_mark'] ?? $partnerMark->subject->overall_pass_mark ?? 33;
                    $combinedOverallPassMark = $opm1 + $opm2;

                    $pass1 = $rules1['written_pass_mark'] ?? $mark->subject->written_pass_mark ?? 33;
                    $pass2 = $rules2['written_pass_mark'] ?? $partnerMark->subject->written_pass_mark ?? 33;
                    $combinedRequiredPass = $pass1 + $pass2;

                    $mcq1Pass = $rules1['mcq_pass_mark'] ?? $mark->subject->mcq_pass_mark ?? 0;
                    $mcq2Pass = $rules2['mcq_pass_mark'] ?? $partnerMark->subject->mcq_pass_mark ?? 0;
                    $combinedMcqObt = ($mark->mcq_mark ?? 0) + ($partnerMark->mcq_mark ?? 0);
                    $combinedMcqPass = $mcq1Pass + $mcq2Pass;

                    $combinedObt = $mark->marks_obtained + $partnerMark->marks_obtained;

                    if ($overallPassOnly) {
                        $isComponentFailed = ($combinedObt < $combinedOverallPassMark);
                    } else {
                        $mcqFail = ($combinedMcqPass > 0) && ($combinedMcqObt < $combinedMcqPass);
                        $isComponentFailed = ($combinedObt < $combinedRequiredPass) || $mcqFail;
                    }

                    if ($isOptional) {
                        if ($combinedObt > 40.0) {
                            $optionalBonusMarks = $combinedObt - 40.0;
                        }
                    } else {
                        $coreTotalObtained += $combinedObt;

                        if ($isComponentFailed) {
                            if (str_contains($subName, 'bangla')) {
                                $failedSubjectNames['bangla'] = "Bang (F)";
                            } elseif (str_contains($subName, 'english')) {
                                $failedSubjectNames['english'] = "Engl (F)";
                            } else {
                                $cleanName = trim(preg_replace('/\(.*\)/u', '', $mark->subject->name));
                                $words = explode(' ', $cleanName);
                                $prefix = !empty($words[0]) ? ucfirst(strtolower($words[0])) : 'Sub';
                                $failedSubjectNames[$mark->subject_id] = substr($prefix, 0, 4) . " (F)";
                            }
                        }
                    }

                    $processedIds[] = $mark->id;
                    $processedIds[] = $partnerMark->id;
                } else {
                    $obtVal = (float) $mark->marks_obtained;

                    if ($isOptional) {
                        if ($obtVal > 40.0) {
                            $optionalBonusMarks = $obtVal - 40.0;
                        }
                    } else {
                        $coreTotalObtained += $obtVal;

                        if (trim($mark->grade) === 'F') {
                            $cleanName = trim(preg_replace('/\(.*\)/u', '', $mark->subject->name));
                            $words = explode(' ', $cleanName);
                            $prefix = !empty($words[0]) ? ucfirst(strtolower($words[0])) : 'Sub';
                            $failedSubjectNames[$mark->subject_id] = substr($prefix, 0, 4) . " (F)";
                        }
                    }
                    $processedIds[] = $mark->id;
                }
            }

            // Grand Total = Core Marks + (Optional Marks above 40)
            $grandTotalObtained = $coreTotalObtained + $optionalBonusMarks;

            if (!empty($failedSubjectNames)) {
                $groupedFailCount = count($failedSubjectNames);
                $failedSubjectsList = implode(', ', $failedSubjectNames);

                $calculatedFailRecords[] = [
                    'student_id'               => $enrollment->user->student_id ?? 'N/A',
                    'student_name'             => $enrollment->user->name ?? 'N/A',
                    'roll_number'              => (int)($enrollment->roll_number ?? 0),
                    'section_name'             => $enrollment->section->name ?? 'N/A',
                    'group_name'               => $enrollment->study_group ?? 'General',
                    'total_marks'              => (float)$grandTotalObtained, // 🌟 Updated Grand Total
                    'fail_count'               => $groupedFailCount, 
                    'failed_subjects_summary'  => $failedSubjectsList,
                ];
            }
        }

        // 3-PRIORITY SORTING ALGORITHM
        if (!empty($calculatedFailRecords)) {
            usort($calculatedFailRecords, function ($a, $b) {
                if ($a['fail_count'] !== $b['fail_count']) {
                    return $a['fail_count'] <=> $b['fail_count'];
                }
                if ($b['total_marks'] !== $a['total_marks']) {
                    return $b['total_marks'] <=> $a['total_marks'];
                }
                return $a['roll_number'] <=> $b['roll_number'];
            });

            $this->failRecords = $calculatedFailRecords;
        } else {
            $this->failRecords = [];

            \Filament\Notifications\Notification::make()
                ->title('No Failed Students Found')
                ->body('All students passed or no mark records were found for this selection.')
                ->success()
                ->send();
        }
    }

    // Add this right before the final closing brace of the FailList class
    public function printFailList()
    {
        if (empty($this->failRecords)) {
            \Filament\Notifications\Notification::make()
                ->title('Generate List First')
                ->warning()
                ->send();
            return;
        }

        $inputs = $this->data;

        $url = route('print.fail.list', [
            'year'    => $inputs['academic_year_id'],
            'class'   => $inputs['school_class_id'],
            'scope'   => $inputs['merit_scope'] ?? 'class',
            'section' => $inputs['section_id'] ?? null,
            'group'   => $inputs['study_group'] ?? null,
        ]);

        $this->js("window.open('{$url}', '_blank');");
    }
}