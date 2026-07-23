<div class="space-y-6 text-left text-gray-900 dark:text-gray-100">
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Personal Details -->
        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider mb-4 text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 pb-2 flex items-center gap-2">
                <x-heroicon-o-user class="w-4 h-4 text-amber-500"/> Personal Details
            </h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between items-center py-1">
                    <span class="text-gray-500 dark:text-gray-400">Student ID</span>
                    <span class="font-mono font-bold px-2.5 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        {{ $student->student_id ?? 'N/A' }}
                    </span>
                </div>
                <div class="flex justify-between items-center py-1 border-t border-gray-100 dark:border-gray-800/50">
                    <span class="text-gray-500 dark:text-gray-400">Full Name</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $student->name }}</span>
                </div>
                <div class="flex justify-between items-center py-1 border-t border-gray-100 dark:border-gray-800/50">
                    <span class="text-gray-500 dark:text-gray-400">Email Address</span>
                    <span class="text-gray-700 dark:text-gray-300 font-medium font-mono">{{ $student->email ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        @php
            $fourthSubject = $enrollment->optional_subject_id 
                ? \App\Models\Subject::find($enrollment->optional_subject_id) 
                : null;
        @endphp

        <!-- Academic Information -->
        <div class="p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider mb-4 text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 pb-2 flex items-center gap-2">
                <x-heroicon-o-academic-cap class="w-4 h-4 text-amber-500"/> Academic Information
            </h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between items-center py-1">
                    <span class="text-gray-500 dark:text-gray-400">Enrolled Class</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $enrollment->schoolClass->name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between items-center py-1 border-t border-gray-100 dark:border-gray-800/50">
                    <span class="text-gray-500 dark:text-gray-400">Study Group</span>
                    <span class="px-2.5 py-0.5 rounded text-xs font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        {{ $enrollment->study_group ?? 'General' }}
                    </span>
                </div>
                <div class="flex justify-between items-center py-1 border-t border-gray-100 dark:border-gray-800/50">
                    <span class="text-gray-500 dark:text-gray-400">Optional / 4th Subject</span>
                    @if($fourthSubject)
                        <span class="px-2.5 py-0.5 rounded text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            {{ $fourthSubject->name }} ({{ $fourthSubject->code }})
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 rounded text-xs font-medium bg-rose-500/10 text-rose-600 dark:text-rose-400 italic">
                            Not Assigned
                        </span>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-4 pt-1 border-t border-gray-100 dark:border-gray-800/50">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 dark:text-gray-400 text-xs">Section</span>
                        <span class="text-gray-700 dark:text-gray-300 font-medium">{{ $enrollment->section->name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between items-center border-l pl-4 border-gray-100 dark:border-gray-800/50">
                        <span class="text-gray-500 dark:text-gray-400 text-xs">Roll No</span>
                        <span class="font-bold text-gray-900 dark:text-white font-mono">{{ $enrollment->roll_number ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Course Subjects -->
    <div class="p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
        <h3 class="text-xs font-bold uppercase tracking-wider mb-4 text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 pb-2 flex items-center gap-2">
            <x-heroicon-o-book-open class="w-4 h-4 text-amber-500"/> Assigned Course Subjects
        </h3>
        
        @php
            $currentClassId = $enrollment->school_class_id;
            $groupName = $enrollment->study_group; 

            $resolvedGroup = \App\Models\StudyGroup::where('name', $groupName)->first();
            $resolvedGroupId = $resolvedGroup ? $resolvedGroup->id : null;

            $allAssignedSubjects = \App\Models\Subject::whereHas('schoolClasses', function ($q) use ($currentClassId) {
                    $q->where('school_classes.id', $currentClassId);
                })
                ->where(function ($query) use ($resolvedGroupId, $enrollment) {
                    $query->whereNull('study_group_id')
                          ->whereIn('subject_type', ['Core', 'Core / Compulsory']);
                          
                    if ($enrollment->optional_subject_id) {
                        $query->orWhere(function ($q) use ($enrollment) {
                            $q->whereNull('study_group_id')
                              ->where('subject_type', 'Optional')
                              ->where('id', $enrollment->optional_subject_id);
                        });
                    }
                          
                    if ($resolvedGroupId) {
                        $query->orWhere(function ($q) use ($resolvedGroupId, $enrollment) {
                            $q->where('study_group_id', $resolvedGroupId)
                              ->where(function ($subQ) use ($enrollment) {
                                  $subQ->whereIn('subject_type', ['Group', 'Core']);
                                  if ($enrollment->optional_subject_id) {
                                      $subQ->orWhere('id', $enrollment->optional_subject_id);
                                  }
                              });
                        });
                    }
                })
                ->orderBy('code', 'asc')
                ->get();
        @endphp
        
        @if($allAssignedSubjects->isEmpty())
            <div class="text-center py-8">
                <x-heroicon-o-exclamation-triangle class="w-8 h-8 text-gray-400 mx-auto mb-2"/>
                <p class="text-sm text-gray-500 dark:text-gray-400 italic">No mapped subjects configured for this student yet.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-96 overflow-y-auto pr-1">
                @foreach($allAssignedSubjects as $subject)
                    @php
                        $subjectNameLower = strtolower($subject->name);
                        $studentReligion = strtolower(trim($student->religion ?? ''));

                        if (str_contains($subjectNameLower, 'islam') && $studentReligion !== 'islam') continue;
                        if (str_contains($subjectNameLower, 'hindu') && !str_contains($studentReligion, 'hindu')) continue;
                        if (str_contains($subjectNameLower, 'christian') && !str_contains($studentReligion, 'christian')) continue;
                        if (str_contains($subjectNameLower, 'buddhi') && !str_contains($studentReligion, 'buddhi')) continue;

                        $is4th = $fourthSubject && ($subject->id === $fourthSubject->id);
                        $typeLabel = 'Compulsory';
                        
                        if ($is4th) {
                            $typeLabel = '4th Subject Choice';
                        } elseif ($subject->subject_type === 'Optional') {
                            $typeLabel = 'Optional';
                        } elseif ($subject->subject_type === 'Group') {
                            $typeLabel = 'Group Core';
                        }
                    @endphp
                    
                    <!-- 🌟 FORCED DARK STYLING INLINE TO OVERRIDE TAILWIND OVERWRITE 🌟 -->
                    <div 
                        class="flex items-center justify-between p-3.5 rounded-xl border transition-all duration-200"
                        style="
                            background-color: {{ $is4th ? 'rgba(16, 185, 129, 0.15)' : 'rgba(31, 41, 55, 0.8)' }} !important; 
                            border-color: {{ $is4th ? 'rgba(16, 185, 129, 0.4)' : 'rgba(55, 65, 81, 0.8)' }} !important;
                        "
                    >
                        <div class="flex flex-col text-left">
                            <span class="font-bold text-sm text-white flex items-center gap-2" style="color: #ffffff !important;">
                                {{ $subject->name }}
                                @if($is4th)
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-emerald-500/30 text-emerald-300 uppercase tracking-wider">
                                        4th
                                    </span>
                                @endif
                            </span>
                            <span class="text-[10px] uppercase font-bold tracking-wider mt-0.5" style="color: #9ca3af !important;">
                                {{ $typeLabel }}
                            </span>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold border shadow-sm" style="background-color: #111827 !important; color: #f3f4f6 !important; border-color: #374151 !important;">
                                {{ $subject->code ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>